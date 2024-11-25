<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChartDateRangeFilter  extends Component
{
    public $startDate;
    public $endDate;
    public $projectData = [];
    public $tasksWithResponsibility = [];
    public $totalHours = [];
    public $billableHours = [];
    public $nonBillableHours = [];
    public $tasksRemaining;
    public $creator;

    public $apiKey;
    public $companyName;
    public $teamwork_project_id;

    public function mount($apiKey, $companyName, $teamwork_project_id)
    {
        $this->apiKey = $apiKey;
        $this->companyName = $companyName;
        $this->teamwork_project_id = $teamwork_project_id;

        // Set default date range (start date is the first of the year and end date is today)
        $this->startDate = Carbon::parse('2024-10-11')->toDateString();
        $this->endDate = Carbon::today()->toDateString();

        // Fetch initial data
        $this->fetchProjectData();
    }

    public function fetchProjectData()
    {
        if (!$this->apiKey || !$this->companyName || !$this->teamwork_project_id) {
            session()->flash('error', 'Missing required parameters');
            return;
        }

        // Fetch tasks within the selected date range
        $tasksUrl = "https://{$this->companyName}.teamwork.com/projects/{$this->teamwork_project_id}/tasks.json";
        $taskResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:")
        ])->get($tasksUrl);

        if ($taskResponse->failed()) {
            session()->flash('error', 'Failed to fetch tasks data from Teamwork');
            return;
        }

        $tasksData = $taskResponse->json()['todo-items'] ?? [];
        $this->tasksRemaining = count($tasksData);

        // Prepare task-related information
        $this->tasksWithResponsibility = [];
        foreach ($tasksData as $task) {
            $this->tasksWithResponsibility[] = [
                'taskName' => $task['content'] ?? 'Nom de tâche non défini',
                'responsible' => $task['responsible-party-names'] ?? 'Non assigné'
            ];
        }

        // Fetch time entries filtered by date range
        $timeEntriesUrl = "https://{$this->companyName}.teamwork.com/projects/{$this->teamwork_project_id}/time_entries.json?fromDate={$this->startDate}&toDate={$this->endDate}";
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:")
        ])->get($timeEntriesUrl);

        if ($response->failed()) {
            session()->flash('error', 'Failed to fetch time entries');
            return;
        }

        $timeEntries = $response->json()['time-entries'] ?? [];
        $this->processTimeEntries($timeEntries);
    }

    public function processTimeEntries($timeEntries)
    {
        $userHoursMap = [];

        foreach ($timeEntries as $entry) {
            $userName = $entry['person-first-name'] . " " . $entry['person-last-name'];
            $hoursDecimal = floatval($entry['hoursDecimal']);
            $isBillable = $entry['isbillable'] === "1";

            if (!isset($userHoursMap[$userName])) {
                $userHoursMap[$userName] = [
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
        }

        // Split the data into separate arrays for chart
        $this->totalHours = array_column($userHoursMap, 'totalHours');
        $this->billableHours = array_column($userHoursMap, 'billableHours');
        $this->nonBillableHours = array_column($userHoursMap, 'nonBillableHours');
    }

    public function render()
    {
        return view('livewire.chart-date-range-filter');
    }
}