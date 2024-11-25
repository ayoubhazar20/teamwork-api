<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vue d'ensemble du projet - Suivi du temps</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Arial', sans-serif;
        }

        .container {
            max-width: 1200px;
        }

        .title {
            color: #4a4a4a;
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .info-box {
            background: linear-gradient(135deg, #333, #4e8cff);
            color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            transition: box-shadow 0.3s;
        }

        .info-box:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
        }

        .info-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-top: 5px;
        }

        .chart-container {
            width: 100%;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .task-list {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            padding: 20px;
        }

        .task-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }

        .task-ul {
            list-style-type: none;
            padding: 0;
        }

        .task-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            transition: background-color 0.3s;
        }

        .task-item:hover {
            background-color: #f0f0f0;
        }

        .task-icon {
            margin-right: 15px;
            color: #4e8cff;
            font-size: 1.2rem;
        }

        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 8px solid #eaeaea;
            border-top: 8px solid #4e8cff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">Vue d'ensemble du projet : Suivi du temps</h1>

        <form action="" method="GET" id="date-filter-form" class="row g-3 mb-5">
            <input type="hidden" name="apiKey" value="{{ request()->query('apiKey') }}">
            <input type="hidden" name="companyName" value="{{ request()->query('companyName') }}">
            <input type="hidden" name="teamwork_project_id" value="{{ request()->query('teamwork_project_id') }}">

            <div class="col-md-4">
                <label for="start_date" class="form-label">Date de début :</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $data['startDate'] }}">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">Date de fin :</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $data['endDate'] }}">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </form>

        <!-- Info Tiles -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="info-box">
                    <h4 class="info-title"><i class="fas fa-clock"></i> Total des heures facturables</h4>
                    <p class="info-value">{{ array_sum($data['billableHours']) }} heures</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <h4 class="info-title"><i class="fas fa-stopwatch"></i> Total des heures non facturables</h4>
                    <p class="info-value">{{ array_sum($data['nonBillableHours']) }} heures</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <h4 class="info-title"><i class="fas fa-tasks"></i> Tâches restantes</h4>
                    <p class="info-value">{{ count($data['tasksRemaining']) + count($data['tasksStarted']) }} tâches</p>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-container mb-5">
            <h3 class="text-center mb-4"><i class="fas fa-chart-bar"></i> Heures par utilisateur</h3>
            <canvas id="userHoursChart"></canvas>
        </div>

        <!-- Task List Section -->
        <div class="task-list">
            <h3 class="task-title"><i class="fas fa-tasks"></i> Tâches restantes :</h3>
            <ul class="task-ul">
                @foreach($data['tasksRemaining'] as $task)
                    <li class="task-item">
                        <span class="task-icon"><i class="fas fa-calendar-alt"></i></span>
                        <strong>{{ $task['taskName'] }}</strong> 
                        <span class="task-responsible"> - Responsable : {{ $task['responsible'] }}</span>
                    </li>
                @endforeach
            </ul>

            <h3 class="task-title mt-5"><i class="fas fa-hourglass-half"></i> Tâches en cours :</h3>
            <ul class="task-ul">
                @foreach($data['tasksStarted'] as $task)
                    <li class="task-item">
                        <span class="task-icon"><i class="fas fa-hourglass-start"></i></span>
                        <strong>{{ $task['taskName'] }}</strong> 
                        <span class="task-responsible"> - Responsable : {{ $task['responsible'] }} (Commencée : {{ $task['startDate'] }})</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner" style="display: none;">
        <div class="spinner"></div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>

    <!-- Chart.js -->
    <script>
        // Show loading spinner while waiting for data
        document.getElementById('loadingSpinner').style.display = 'flex';

        // Data for User Hours Chart
        var userHoursData = {
            labels: {!! json_encode($data['users']) !!},  
            datasets: [
                {
                    label: 'Heures totales',
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    data: {!! json_encode($data['totalHours']) !!},
                },
                {
                    label: 'Heures facturables',
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    data: {!! json_encode($data['billableHours']) !!},
                },
                {
                    label: 'Heures non facturables',
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    data: {!! json_encode($data['nonBillableHours']) !!},
                }
            ]
        };

        var ctx1 = document.getElementById('userHoursChart').getContext('2d');
        var userHoursChart = new Chart(ctx1, {
            type: 'bar',
            data: userHoursData,
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Hide spinner after data is loaded
        window.onload = function() {
            document.getElementById('loadingSpinner').style.display = 'none';
        };
    </script>
</body>
</html>
