<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TeamworkController extends Controller
{
    private function getBasicAuthHeader($apiKey)
    {
        return 'Basic ' . base64_encode($apiKey . ':');
    }

    private function checkPagesForData($teamwork_project_id, $page = 1, $pagesWithData = [], $maxPages = 10)
    {
        $url = "https://innovsa.teamwork.com/projects/{$teamwork_project_id}/time_entries.json?page={$page}";

        $response = Http::withHeaders([
            'Authorization' => $this->getBasicAuthHeader('twp_TeP8QX3pbw8pUk4DiWOGbFCAtfY6') // Replace with your API key
        ])->get($url);

        if ($response->failed()) {
            return ['error' => 'Failed to fetch data from Teamwork'];
        }

        $data = $response->json();

        if (!empty($data['time-entries']) && count($data['time-entries']) > 0) {
            $pagesWithData[$page] = $data['time-entries'];
        }

        if ($page < $maxPages && count($data['time-entries']) > 0) {
            return $this->checkPagesForData($teamwork_project_id, $page + 1, $pagesWithData, $maxPages);
        }

        return $pagesWithData;
    }


    public function fetchProjectData(Request $request)
    {
        $teamwork_project_id = $request->query('teamwork_project_id');
      
        $pagesWithData = $this->checkPagesForData($teamwork_project_id, 1, [], 10);

        // Handle error in fetching data
        if (isset($pagesWithData['error'])) {
            return response()->json($pagesWithData, 500);
        }

        // Initialize user hours map
        $userHoursMap = [];

        // Process the time entries to calculate total, billable, and non-billable hours
        foreach ($pagesWithData as $pageEntries) {
            foreach ($pageEntries as $entry) {
                $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
                $hoursDecimal = floatval($entry['hoursDecimal']);
                $isBillable = $entry['isbillable'] === "1";

                // Initialize user data if not already set
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
            }
        }

        // Prepare formatted users summary for HubSpot display
        $formattedUsers = [];
        foreach ($userHoursMap as $user) {
            $formattedUsers[] = "{$user['user']}: Heures totales: {$user['totalHours']}, Heures facturables: {$user['billableHours']}, Heures non facturables: {$user['nonBillableHours']}";
        }

        // Prepare the overall summary
        $projectD = [
            'project_id' => 1,
            'projectName' => 'Teamwork Project', // Ensure consistent naming
            'overallTotalHours' => array_sum(array_column($userHoursMap, 'totalHours')),
            'overallBillableHours' => array_sum(array_column($userHoursMap, 'billableHours')),
            'overallNonBillableHours' => array_sum(array_column($userHoursMap, 'nonBillableHours')),
            'usersSummary' => implode("\n", $formattedUsers) // Combined summary
        ];
        $results = [];
        $results[] = [
            "objectId" => 2,
            "title" => $projectD['projectName'],
            "created" => now()->toDateTimeString(),
            "properties" => [
              
                [
                    "label" => "Statut",
                    "dataType" => "STATUS",  // Status field type
                    "value" => "Diagnostic",  // Descriptive value
                    "optionType" => "SUCCESS"  // Red/urgent indicator
                ],
                [
                    "label" => "Heures totales",
                    "dataType" => "NUMERIC",
                    "value" => $projectD['overallTotalHours']
                ],
                [
                    "label" => "Heures facturables",
                    "dataType" => "NUMERIC",
                    "value" => $projectD['overallBillableHours']
                ],
                [
                    "label" => "Heures non facturables",
                    "dataType" => "NUMERIC",
                    "value" => $projectD['overallNonBillableHours']
                ],

            ],
            "actions" => [
                [
                    "type" => "ACTION_HOOK",
                    "httpMethod" => "POST",
                    "uri" => "https://your-api-endpoint.com/teamwork/extractByDate", // Endpoint for date range filtering
                    "label" => "Extract Time Entries by Date",
                   
                ]
            ]
        ];
        foreach ($userHoursMap as $index => $user) {
            $results[] = [
                "objectId" =>   1,
                "title" => "Employee:" . $user['user'],
                "created" => now()->toDateTimeString(),
                "properties" => [
                    [
                        "label" => "Employé",
                        "dataType" => "STATUS",  // Status field type
                        "value" =>  "" . $user['user'],  // Descriptive value
                        "optionType" => "INFO"  // Red/urgent indicator
                    ],
                    [
                        "label" => "Heures totales",
                        "dataType" => "NUMERIC",
                        "value" => $user['totalHours']
                    ],
                    [
                        "label" => "Heures facturables",
                        "dataType" => "NUMERIC",
                        "value" => $user['billableHours']
                    ],
                    [
                        "label" => "Heures non facturables",
                        "dataType" => "NUMERIC",
                        "value" => $user['nonBillableHours']
                    ],
                ],
                "actions" => [
                    [
                        "type" => "ACTION_HOOK",
                        "httpMethod" => "POST",
                        "uri" => "https://your-api-endpoint.com/resolve",
                        "label" => "Resolve Issue"
                    ]
                ]
            ];
        }

        // Return the response with all the cards
        return response()->json([
            "results" => $results,
            "status" => 200
        ]);

        // $data = [
        //     "results" => [
        //         [
        //             "objectId" => 1,
        //             "title" => $projectD['projectName'],
        //             "created" => now()->toDateTimeString(),
        //             "properties" => [
        //                 [
        //                     "label" => "Heures totales",
        //                     "dataType" => "NUMERIC",  // STRING data type for teamwork_hours
        //                     "value" => $projectD['overallTotalHours']
        //                 ],
        //                 [
        //                     "label" => "Heures facturables",
        //                     "dataType" => "NUMERIC",  // STRING data type for teamwork_hours
        //                     "value" => $projectD['overallBillableHours']
        //                 ],
        //                 [
        //                     "label" => "Heures non facturables",
        //                     "dataType" => "NUMERIC",  // STRING data type for teamwork_hours
        //                     "value" => $projectD['overallNonBillableHours']
        //                 ],
        //                 [
        //                     "label" => "Project Name",
        //                     "dataType" => "STRING",  // STRING data type for project_name
        //                     "value" => $projectD['usersSummary']
        //                 ],

        //             ],
        //             "actions" => [
        //                 [
        //                     "type" => "ACTION_HOOK",
        //                     "httpMethod" => "POST",
        //                     "uri" => "https://your-api-endpoint.com/resolve",
        //                     "label" => "Resolve Issue"
        //                 ]
        //             ]
        //         ]
        //     ]
        // ];

        // Return the response as JSON
        // return response()->json($data);
    }
    // public function fetchProjectData(Request $request)
    // {
    //     $teamwork_project_id = $request->query('teamwork_project_id');

    //     // Fetch time entries from Teamwork
    //     $pagesWithData = $this->checkPagesForData($teamwork_project_id, 1, [], 10);

    //     // Handle error in fetching data
    //     if (isset($pagesWithData['error'])) {
    //         return response()->json($pagesWithData, 500);
    //     }

    //     // Initialize user hours map
    //     $userHoursMap = [];

    //     // Process the time entries to calculate total, billable, and non-billable hours
    //     foreach ($pagesWithData as $pageEntries) {
    //         foreach ($pageEntries as $entry) {
    //             $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
    //             $hoursDecimal = floatval($entry['hoursDecimal']);
    //             $isBillable = $entry['isbillable'] === "1";

    //             // Initialize user data if not already set
    //             if (!isset($userHoursMap[$userName])) {
    //                 $userHoursMap[$userName] = [
    //                     'user' => $userName,
    //                     'totalHours' => 0,
    //                     'billableHours' => 0,
    //                     'nonBillableHours' => 0
    //                 ];
    //             }

    //             // Accumulate hours based on billability
    //             $userHoursMap[$userName]['totalHours'] += $hoursDecimal;
    //             if ($isBillable) {
    //                 $userHoursMap[$userName]['billableHours'] += $hoursDecimal;
    //             } else {
    //                 $userHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
    //             }
    //         }
    //     }

    //     // Prepare formatted users summary for HubSpot display
    //     $formattedUsers = [];
    //     foreach ($userHoursMap as $user) {
    //         $formattedUsers[] = "{$user['user']}: Heures totales: {$user['totalHours']}, Heures facturables: {$user['billableHours']}, Heures non facturables: {$user['nonBillableHours']}";
    //     }

    //     // Prepare the overall summary
    //     $projectD = [
    //         'project_id' => $teamwork_project_id,
    //         'projectName' => 'Teamwork Project',
    //         'overallTotalHours' => array_sum(array_column($userHoursMap, 'totalHours')),
    //         'overallBillableHours' => array_sum(array_column($userHoursMap, 'billableHours')),
    //         'overallNonBillableHours' => array_sum(array_column($userHoursMap, 'nonBillableHours')),
    //         'usersSummary' => implode("\n", $formattedUsers)
    //     ];

    //     // Create the result array with project data and users
    //     $results = [
    //         [
    //             "objectId" => 1,
    //             "title" => $projectD['projectName'],
    //             "created" => now()->toDateTimeString(),
    //             "properties" => [
    //                 [
    //                     "label" => "Heures totales",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $projectD['overallTotalHours']
    //                 ],
    //                 [
    //                     "label" => "Heures facturables",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $projectD['overallBillableHours']
    //                 ],
    //                 [
    //                     "label" => "Heures non facturables",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $projectD['overallNonBillableHours']
    //                 ],
    //             ],
    //             "actions" => [
    //                 [
    //                     "type" => "ACTION_HOOK",
    //                     "httpMethod" => "POST",
    //                     "uri" => "https://your-api-endpoint.com/teamwork/extractByDate", // Endpoint for date range filtering
    //                     "label" => "Extract Time Entries by Date",
    //                     "inputs" => [ // Inputs for the date range
    //                         [
    //                             "type" => "DATE",
    //                             "name" => "start_date",
    //                             "label" => "Start Date"
    //                         ],
    //                         [
    //                             "type" => "DATE",
    //                             "name" => "end_date",
    //                             "label" => "End Date"
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ];

    //     foreach ($userHoursMap as $index => $user) {
    //         $results[] = [
    //             "objectId" => $index + 2,
    //             "title" => "Employé: " . $user['user'],
    //             "created" => now()->toDateTimeString(),
    //             "properties" => [
    //                 [
    //                     "label" => "Employé",
    //                     "dataType" => "STATUS",
    //                     "value" => $user['user'],
    //                     "optionType" => "INFO"
    //                 ],
    //                 [
    //                     "label" => "Heures totales",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $user['totalHours']
    //                 ],
    //                 [
    //                     "label" => "Heures facturables",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $user['billableHours']
    //                 ],
    //                 [
    //                     "label" => "Heures non facturables",
    //                     "dataType" => "NUMERIC",
    //                     "value" => $user['nonBillableHours']
    //                 ]
    //             ],
    //             "actions" => [
    //                 [
    //                     "type" => "ACTION_HOOK",
    //                     "httpMethod" => "POST",
    //                     "uri" => "https://your-api-endpoint.com/teamwork/extractByDate", // Endpoint for date range
    //                     "label" => "Extract Time Entries by Date",
    //                     "inputs" => [
    //                         [
    //                             "type" => "DATE",
    //                             "name" => "start_date",
    //                             "label" => "Start Date"
    //                         ],
    //                         [
    //                             "type" => "DATE",
    //                             "name" => "end_date",
    //                             "label" => "End Date"
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ];
    //     }

    //     // Return the response with all the cards
    //     return response()->json([
    //         "results" => $results,
    //         "status" => 200
    //     ]);
    // }
    public function extractByDate(Request $request)
    {
        $teamwork_project_id = $request->input('teamwork_project_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Add your logic to filter time entries between the specified dates
        $filteredEntries = $this->getTimeEntriesByDateRange($teamwork_project_id, $startDate, $endDate);

        return response()->json($filteredEntries);
    }

    // Return the structured response without redundant fields
    // public function fetchProjectData($projectId)
    // {
    //     $pagesWithData = $this->checkPagesForData($projectId, 1, [], 10);

    //     if (isset($pagesWithData['error'])) {
    //         return response()->json($pagesWithData, 500);
    //     }

    //     $userHoursMap = [];

    //     foreach ($pagesWithData as $pageEntries) {
    //         foreach ($pageEntries as $entry) {
    //             $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
    //             $hoursDecimal = floatval($entry['hoursDecimal']);
    //             $isBillable = $entry['isbillable'] === "1";

    //             if (!isset($userHoursMap[$userName])) {
    //                 $userHoursMap[$userName] = [
    //                     'user' => $userName,
    //                     'totalHours' => 0,
    //                     'billableHours' => 0,
    //                     'nonBillableHours' => 0
    //                 ];
    //             }

    //             $userHoursMap[$userName]['totalHours'] += $hoursDecimal;
    //             if ($isBillable) {
    //                 $userHoursMap[$userName]['billableHours'] += $hoursDecimal;
    //             } else {
    //                 $userHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
    //             }
    //         }
    //     }

    //     $responseData = [
    //         'project_id' => $projectId,
    //         'project_name' => 'Teamwork Project', // Placeholder name, you may update this with actual data
    //         'users' => $userHoursMap,
    //         'overall_total_hours' => array_sum(array_column($userHoursMap, 'totalHours')),
    //         'overall_billable_hours' => array_sum(array_column($userHoursMap, 'billableHours')),
    //         'overall_non_billable_hours' => array_sum(array_column($userHoursMap, 'nonBillableHours'))
    //     ];

    //     return response()->json([
    //         'project_summary' => $responseData,
    //         'message' => 'Data successfully retrieved',
    //         'status' => 200
    //     ]);
    // }
    // public function fetchProjectData($projectId)
    // {
    //     // Fetch all pages with data
    //     $pagesWithData = $this->checkPagesForData($projectId, 1, [], 10);

    //     // Handle error in fetching data
    //     if (isset($pagesWithData['error'])) {
    //         return response()->json($pagesWithData, 500);
    //     }

    //     // Initialize user hours map
    //     $userHoursMap = [];

    //     // Process the time entries to calculate total, billable, and non-billable hours
    //     foreach ($pagesWithData as $pageEntries) {
    //         foreach ($pageEntries as $entry) {
    //             $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
    //             $hoursDecimal = floatval($entry['hoursDecimal']);
    //             $isBillable = $entry['isbillable'] === "1";

    //             if (!isset($userHoursMap[$userName])) {
    //                 $userHoursMap[$userName] = [
    //                     'user' => $userName,
    //                     'totalHours' => 0,
    //                     'billableHours' => 0,
    //                     'nonBillableHours' => 0
    //                 ];
    //             }

    //             // Add hours to the correct category
    //             $userHoursMap[$userName]['totalHours'] += $hoursDecimal;
    //             if ($isBillable) {
    //                 $userHoursMap[$userName]['billableHours'] += $hoursDecimal;
    //             } else {
    //                 $userHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
    //             }
    //         }
    //     }

    //     // Format user data into a single string
    //     $formattedUsers = '';
    //     foreach ($userHoursMap as $user) {
    //         $formattedUsers .= "{$user['user']}: Total Hours: {$user['totalHours']}, Billable Hours: {$user['billableHours']}, Non-Billable Hours: {$user['nonBillableHours']} \n";
    //     }

    //     // Prepare the overall summary
    //     $responseData = [
    //         'project_id' => $projectId,
    //         'projectName' => 'Teamwork Project', // Placeholder name
    //         // 'users' => $userHoursMap,
    //         'overallTotalHours' => array_sum(array_column($userHoursMap, 'totalHours')),
    //         'overallBillableHours' => array_sum(array_column($userHoursMap, 'billableHours')),
    //         'overallNonBillableHours' => array_sum(array_column($userHoursMap, 'nonBillableHours')),
    //         'usersSummary' => $formattedUsers // Add formatted user summary
    //     ];

    //     // Return the response with project summary and users summary
    //     return response()->json([
    //         'project_summary' => $responseData,
    //         'usersSummary' => "bla bla bla",
    //         'status' => 200
    //     ]);
    // }

    // public function fetchProjectData($projectId)
    // {
    //     // Return a simple static response for testing
    //     $responseData = [
    //         'projectName' => 'This is a test message to check card rendering.',
    //         'static_total_hours' => 100  // Static value for total hours
    //     ];

    //     return response()->json([
    //         'project_summary' => $responseData,
    //         'message' => 'Data successfully retrieved',
    //         'status' => 200
    //     ]);
    // }

    // public function fetchProjectData(Request $request)
    // {
    //     // Example project data for multiple cards
    //     $projects = [
    //         [
    //             'teamwork_hours' => '120 hours',
    //             'project_name' => "Website Development",
    //             'total_cost' => 3500,
    //             'titleColor' => 'red'  // Red title for the first card
    //         ],
    //         [
    //             'teamwork_hours' => '80 hours',
    //             'project_name' => "Mobile App Development",
    //             'total_cost' => 5000,
    //             'titleColor' => 'green'  // Green title for the second card
    //         ],
    //         [
    //             'teamwork_hours' => '150 hours',
    //             'project_name' => "API Integration",
    //             'total_cost' => 7500,
    //             'titleColor' => 'green'  // Green title for the third card
    //         ]
    //     ];

    //     // Prepare an array to hold all cards
    //     $results = [];

    //     foreach ($projects as $index => $project) {
    //         $results[] = [
    //             "objectId" => $index + 1,
    //             "title" => $project['project_name'],
    //             "titleColor" => $project['titleColor'],  // Adding the title color dynamically
    //             "created" => now()->toDateTimeString(),
    //             "properties" => [
    //                 [
    //                     "label" => "Teamwork Billable Hours",
    //                     "dataType" => "STRING",
    //                     "value" => $project['teamwork_hours']
    //                 ],
    //                 [
    //                     "label" => "Project Name",
    //                     "dataType" => "STRING",
    //                     "value" => $project['project_name']
    //                 ],
    //                 [
    //                     "name" => "total_cost",
    //                     "label" => "Total Cost",
    //                     "dataType" => "CURRENCY",
    //                     "value" => $project['total_cost'],
    //                     "currencyCode" => "USD"
    //                 ]
    //             ],
    //             "actions" => [
    //                 [
    //                     "type" => "ACTION_HOOK",
    //                     "httpMethod" => "POST",
    //                     "uri" => "https://your-api-endpoint.com/resolve",
    //                     "label" => "Resolve Issue"
    //                 ]
    //             ]
    //         ];
    //     }

    //     // Return the response with all the cards
    //     return response()->json([
    //         "results" => $results,
    //         "status" => 200
    //     ]);
    // }

}