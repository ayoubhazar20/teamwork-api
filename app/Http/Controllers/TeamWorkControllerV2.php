<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamWorkControllerV2 extends Controller
{
    private function getBasicAuthHeader($apiKey)
    {
        return 'Basic ' . base64_encode($apiKey . ':');
    }

    private function checkPagesForData($companyName,$api_key,$teamwork_project_id, $page = 1, $pagesWithData = [], $maxPages = 10)
    {
        $url = "https://{$companyName}.teamwork.com/projects/{$teamwork_project_id}/time_entries.json?page={$page}";

        $response = Http::withHeaders([
            'Authorization' => $this->getBasicAuthHeader($api_key) // Replace with your API key
        ])->get($url);

        if ($response->failed()) {
            return ['error' => 'Failed to fetch data from Teamwork'];
        }

        $data = $response->json();

        if (!empty($data['time-entries']) && count($data['time-entries']) > 0) {
            $pagesWithData[$page] = $data['time-entries'];
        }

        if ($page < $maxPages && count($data['time-entries']) > 0) {
            return $this->checkPagesForData($companyName,$api_key,$teamwork_project_id, $page + 1, $pagesWithData, $maxPages);
        }

        return $pagesWithData;
    }


    public function fetchProjectData(Request $request)
    {

        $userId = $request->query('userId'); // Updated to match your request format
        $portalId = $request->query('portalId');

        if (!$this->validateHubSpotSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $authController = new AuthController();

        $userResponse = $authController->fetchUsers(new Request(['user_id' => $userId, 'portal_id' => $portalId]));

        if ($userResponse->status() !== 200) {
            return response()->json(['error' => 'User not found or access token not available'], $userResponse->status());
        }

        $company = Company::where('company_id', $portalId)->first();

        // Collect all project IDs from the request
        $teamwork_project_ids = [];

        // Dynamically collect project IDs from the request (up to 4 IDs in this case)
        for ($i = 1; $i <= 4; $i++) {
            $project_id_key = $i === 1 ? 'teamwork_project_id' : "teamwork_project_id_{$i}";
            if ($request->has($project_id_key)) {
                $teamwork_project_ids[] = $request->query($project_id_key);
            }
        }
        if (empty($teamwork_project_ids)) {
            return response()->json(['error' => 'No valid project IDs provided'], 400);
        }

        // Initialize arrays to store data
        $allProjectData = [];
        $overallUserHoursMap = [];
        $failedProjects = []; // To store project IDs that failed to fetch

        foreach ($teamwork_project_ids as $teamwork_project_id) {
            // API call to get project details (name, etc.)
            $projectUrl = "https://{$company->name}.teamwork.com/projects/{$teamwork_project_id}.json";
            $projectResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$company->api_key}:") // Use the API key from the company record
            ])->get($projectUrl);

            // Check if the project exists or the API call failed
            if ($projectResponse->failed()) {
                $failedProjects[] = $teamwork_project_id; // Log the failed project ID
                continue; // Skip this project and move to the next one
            }

            // Get the project name from the API response
            $projectData = $projectResponse->json();
            $projectName = $projectData['project']['name'] ?? "Unknown Project"; // Use the project name or fallback to "Unknown Project"

            // Fetch time entries for this project
            $pagesWithData = $this->checkPagesForData($company->name,$company->api_key,$teamwork_project_id, 1, [], 10);

            // Handle error in fetching data for this specific project
            if (isset($pagesWithData['error'])) {
                $failedProjects[] = $teamwork_project_id; // Log the failed project ID
                continue; // Skip this project and move to the next one
            }

            // Initialize user hours map for this project
            $userHoursMap = [];

            // Process the time entries to calculate total, billable, and non-billable hours for each project
            foreach ($pagesWithData as $pageEntries) {
                foreach ($pageEntries as $entry) {
                    $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
                    $hoursDecimal = floatval($entry['hoursDecimal']);
                    $isBillable = $entry['isbillable'] === "1";

                    // Initialize user data if not already set for this project
                    if (!isset($userHoursMap[$userName])) {
                        $userHoursMap[$userName] = [
                            'user' => $userName,
                            'totalHours' => 0,
                            'billableHours' => 0,
                            'nonBillableHours' => 0
                        ];
                    }

                    // Accumulate hours based on billability
                    $userHoursMap[$userName]['totalHours'] += $hoursDecimal;
                    if ($isBillable) {
                        $userHoursMap[$userName]['billableHours'] += $hoursDecimal;
                    } else {
                        $userHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
                    }

                    // Aggregate data for overall summary across multiple projects
                    if (!isset($overallUserHoursMap[$userName])) {
                        $overallUserHoursMap[$userName] = $userHoursMap[$userName];
                    } else {
                        $overallUserHoursMap[$userName]['totalHours'] += $hoursDecimal;
                        if ($isBillable) {
                            $overallUserHoursMap[$userName]['billableHours'] += $hoursDecimal;
                        } else {
                            $overallUserHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
                        }
                    }
                }
            }

            // Add project-specific data to the result
            $allProjectData[] = [
                'project_id' => $teamwork_project_id,
                'projectName' => $projectName, // Use the project name fetched from the API
                'totalHours' => array_sum(array_column($userHoursMap, 'totalHours')),
                'billableHours' => array_sum(array_column($userHoursMap, 'billableHours')),
                'nonBillableHours' => array_sum(array_column($userHoursMap, 'nonBillableHours')),
                'usersSummary' => $userHoursMap
            ];
        }

        // If no projects fetched successfully, return an error response
        if (empty($allProjectData)) {
            return response()->json([
                'error' => 'Failed to fetch data for all projects',
                'failedProjects' => $failedProjects
            ], 500);
        }

        // Format response as per your requirements
        $results = [];
        foreach ($allProjectData as $projectData) {
            $results[] = [
                "objectId" => $projectData['project_id'],
                "title" => $projectData['projectName'],
                "link" => "https://{$company->name}.teamwork.com/projects/{$projectData['project_id']}", // Fixed link generation

                "created" => now()->toDateTimeString(),
                "properties" => [
                    [
                        "label" => "Heures totales",
                        "dataType" => "NUMERIC",
                        "value" => $projectData['totalHours']
                    ],
                    [
                        "label" => "Heures facturables",
                        "dataType" => "NUMERIC",
                        "value" => $projectData['billableHours']
                    ],
                    [
                        "label" => "Heures non facturables",
                        "dataType" => "NUMERIC",
                        "value" => $projectData['nonBillableHours']
                    ],


                ],
                "actions" => [
                    [
                        "type" => "IFRAME",
                        "width" =>  890,
                        "height" =>  748,
                        "httpMethod" => "GET",
                        "uri" => "https://658a-196-75-50-148.ngrok-free.app/chart?companyName={$company->name}&apiKey={$company->api_key}&teamwork_project_id={$projectData['project_id']}", // Correctly interpolated variables
                        "label" => "Show Settings"
                    ]
                ]


            ];
        }

        // Add information about failed projects if any
        if (!empty($failedProjects)) {
            foreach ($failedProjects as $failedProjectId) {
                $results[] = [
                    "objectId" => null,
                    "title" => "Projet échoué : $failedProjectId",
                    "created" => now()->toDateTimeString(),
                    "properties" => [
                        [
                            "label" => "Échec de récupération des données pour le projet suivant",
                            "dataType" => "STRING",
                            "value" => $failedProjectId
                        ],
                        [
                            "label" => "Statut du projet",
                            "dataType" => "STATUS",  // Status field type
                            "value" => "Erreur de récupération",  // Descriptive value in French
                            "optionType" => "DANGER"  // Red/urgent indicator
                        ]
                    ]
                ];
            }
        }

        // Return the response with all project data and any failed project information
        return response()->json([
            "results" => $results,
            "status" => 200
        ]);
    }

    public function validateHubSpotSignature(Request $request)
    {
        $hubspotSignature = $request->header('x-hubspot-signature'); // Signature from HubSpot
        $clientSecret = env('HUBSPOT_CLIENT_SECRET'); // Your client secret
        $httpMethod = $request->getMethod(); // GET, POST, etc.

        // Define the order of parameters you want
        $orderedParams = [
            'userId' => $request->query('userId'),
            'userEmail' => $request->query('userEmail'),
            'associatedObjectId' => $request->query('associatedObjectId'),
            'associatedObjectType' => $request->query('associatedObjectType'),
            'portalId' => $request->query('portalId'),
            'teamwork_project_id' => $request->query('teamwork_project_id'),
            'teamwork_project_id_2' => $request->query('teamwork_project_id_2'),
            'teamwork_project_id_3' => $request->query('teamwork_project_id_3'),
            'teamwork_project_id_4' => $request->query('teamwork_project_id_4')
        ];

        // Build the ordered URL
        $baseUri = secure_url('/api/teamworkV2'); // This ensures HTTPS
        $orderedQuery = http_build_query($orderedParams);
        $orderedFullUrl = urldecode("{$baseUri}?{$orderedQuery}");

        // Create the data string for signature
        $data = trim($clientSecret) . trim($httpMethod) . trim($orderedFullUrl);

        // Compute the HMAC SHA-256 hash
        $generatedSignature = hash('sha256', $data);

        // Log the signature for debugging
        Log::info("Generated Signature: " . $generatedSignature);
        Log::info("HubSpot Signature: " . $hubspotSignature);

        // Compare the generated signature with the one from HubSpot
        if (!hash_equals($generatedSignature, $hubspotSignature)) {
            Log::info('Invalid signature');
            return false;
        }

        Log::info('Valid signature');
        return true;
    }


}