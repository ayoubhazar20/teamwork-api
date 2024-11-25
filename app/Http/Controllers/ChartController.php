<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChartController extends Controller
{
    public function showChart(Request $request)
    {
        $companyName = $request->query('companyName', 'innovsa'); // Default to 'innovsa' if not provided
        $teamwork_project_id = $request->query('teamwork_project_id'); // Example project ID

        // Set the API key based on the company name
        $apiKey = match ($companyName) {
            'innovsa' => 'twp_TeP8QX3pbw8pUk4DiWOGbFCAtfY6',
            'sismikculture', 'sismikimpact' => 'twp_6f2IYWXU8e7kA8npYrn4rQQ8XZwA',
            'innovsa2sandbox', 'innovsa2sandbox' => 'twp_wOVWaKGjL1SsXS2SLb2DF9FcT04F',

            default => null
        };


        $projectUrl = "https://{$companyName}.teamwork.com/projects/{$teamwork_project_id}.json";
        $projectResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$apiKey}:")
        ])->get($projectUrl);

        if ($projectResponse->failed()) {
            return response()->json(['error' => 'Failed to fetch project data from Teamwork'], 500);
        }

        $projectData = $projectResponse->json()['project'] ?? [];

        // Use the created-on date as the default start date if available
        $defaultStartDate = isset($projectData['created-on'])
            ? Carbon::parse($projectData['created-on'])->format('Y-m-d')
            : '2024-01-01';

        $startDate = $request->query('start_date', $defaultStartDate);
        $endDate = $request->query('end_date', Carbon::today()->toDateString()); // Default end date

        if (!$apiKey || !$companyName || !$teamwork_project_id) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Log the parameters for debugging purposes
        Log::info("API Key: {$apiKey}");
        Log::info("Company Name: {$companyName}");
        Log::info("Teamwork Project ID: {$teamwork_project_id}");
        Log::info("Start Date: {$startDate}");
        Log::info("End Date: {$endDate}");

        // Fetch data for the project from Teamwork using the provided API key and project ID
        $projectUrl = "https://{$companyName}.teamwork.com/projects/{$teamwork_project_id}.json";
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$apiKey}:")
        ])->get($projectUrl);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch project data from Teamwork'], 500);
        }

        $projectData = $response->json()['project'] ?? [];

        // Fetch remaining tasks
        $tasksUrl = "https://{$companyName}.teamwork.com/projects/{$teamwork_project_id}/tasks.json";
        $taskResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$apiKey}:")
        ])->get($tasksUrl);

        if ($taskResponse->failed()) {
            return response()->json(['error' => 'Failed to fetch tasks data from Teamwork'], 500);
        }

        $tasksData = $taskResponse->json()['todo-items'] ?? [];

        // Prepare task-related information
        $tasksRemaining = count($tasksData);
        $tasksWithResponsibility = [];

        foreach ($tasksData as $task) {
            $tasksWithResponsibility[] = [
                'taskName' => $task['content'] ?? 'Nom de tâche non défini',
                'responsible' => $task['responsible-party-names'] ?? 'Non assigné'
            ];
        }

        // Fetch time entries filtered by date range
        $pagesWithData = $this->checkPagesForDataByDate($companyName, $apiKey, $teamwork_project_id, $startDate, $endDate);

        // Initialize user hours map
        $userHoursMap = [];
        $overallUserHoursMap = [];

        foreach ($pagesWithData as $pageEntries) {
            foreach ($pageEntries as $entry) {
                $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
                $hoursDecimal = floatval($entry['hoursDecimal']);
                $isBillable = $entry['isbillable'] === "1";

                if (!isset($userHoursMap[$userName])) {
                    $userHoursMap[$userName] = [
                        'user' => $userName,
                        'totalHours' => 0,
                        'billableHours' => 0,
                        'nonBillableHours' => 0
                    ];
                }

                $userHoursMap[$userName]['totalHours'] += $hoursDecimal;
                if ($isBillable) {
                    $userHoursMap[$userName]['billableHours'] += $hoursDecimal;
                } else {
                    $userHoursMap[$userName]['nonBillableHours'] += $hoursDecimal;
                }

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

        $tasksData = $taskResponse->json()['todo-items'] ?? [];
        $tasksRemaining = [];
        $tasksStarted = [];
        $tasksCompleted = [];

        foreach ($tasksData as $task) {
            // Check if the task is completed
            if (isset($task['completed']) && $task['completed']) {
                $tasksCompleted[] = [
                    'taskName' => $task['content'] ?? 'Nom de tâche non défini',
                    'responsible' => $task['responsible-party-names'] ?? 'Non assigné',
                    'completedOn' => $task['completed-on'] ?? 'Non spécifié'
                ];
            }
            // Check if the task has started but not completed
            elseif (isset($task['start-date']) && !empty($task['start-date'])) {
                $tasksStarted[] = [
                    'taskName' => $task['content'] ?? 'Nom de tâche non défini',
                    'responsible' => $task['responsible-party-names'] ?? 'Non assigné',
                    'startDate' => $task['start-date']
                ];
            }
            // Task remaining
            else {
                $tasksRemaining[] = [
                    'taskName' => $task['content'] ?? 'Nom de tâche non défini',
                    'responsible' => $task['responsible-party-names'] ?? 'Non assigné'
                ];
            }
        }

        // Total tasks calculation
        $totalTasks = count($tasksData);

        // Log task categories for debugging
        Log::info('tasksRemaining: ' . count($tasksRemaining));
        Log::info('tasksStarted: ' . count($tasksStarted));
        Log::info('tasksCompleted: ' . count($tasksCompleted));

        $totalTasks = count($tasksData); // Total number of tasks

        Log::info('totalTasks: ' . $totalTasks);
        Log::info('tasksCompleted: ' . count($tasksCompleted));

        $data = [
            'users' => array_keys($userHoursMap),
            'totalHours' => array_column($userHoursMap, 'totalHours'),
            'billableHours' => array_column($userHoursMap, 'billableHours'),
            'nonBillableHours' => array_column($userHoursMap, 'nonBillableHours'),
            'tasksRemaining' => $tasksRemaining,
            'tasksWithResponsibility' => $tasksWithResponsibility,
            'creator' => $projectData['created-by-user-name'] ?? 'Inconnu',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'tasksRemaining' => $tasksRemaining,
            'tasksStarted' => $tasksStarted,
            'tasksCompleted' => $tasksCompleted,
        ];

        return view('chart', compact('data'));
    }

    private function checkPagesForDataByDate($companyName, $api_key, $teamwork_project_id, $startDate, $endDate, $page = 1, $pagesWithData = [], $maxPages = 10)
    {
        $url = "https://{$companyName}.teamwork.com/projects/{$teamwork_project_id}/time_entries.json?page={$page}&fromDate={$startDate}&toDate={$endDate}";

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
            return $this->checkPagesForDataByDate($companyName, $api_key, $teamwork_project_id, $startDate, $endDate, $page + 1, $pagesWithData, $maxPages);
        }

        return $pagesWithData;
    }

    private function getBasicAuthHeader($apiKey)
    {
        return 'Basic ' . base64_encode($apiKey . ':');
    }
}
