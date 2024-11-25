<div>
    <h1>Vue d'ensemble du projet : Suivi du temps</h1>

    <!-- Date Filter Inputs -->
    <div>
        <label for="start_date">Date de début :</label>
        <input type="date" id="start_date" wire:model="startDate">

        <label for="end_date">Date de fin :</label>
        <input type="date" id="end_date" wire:model="endDate">
    </div>

    <h2>Total des heures facturables : {{ array_sum(array_column($userHoursData, 'billableHours')) }}</h2>
    <h2>Total des heures non facturables : {{ array_sum(array_column($userHoursData, 'nonBillableHours')) }}</h2>

    <div class="chart-container">
        <canvas id="userHoursChart"></canvas>
    </div>

    <h3>Tâches restantes et utilisateurs assignés :</h3>
    <ul>
        @foreach($tasksData as $task)
            <li>{{ $task['content'] }} - Responsable : {{ $task['responsible-party-names'] ?? 'Non assigné' }}</li>
        @endforeach
    </ul>

    <script>
        // Chart.js logic here
        document.addEventListener('livewire:load', function () {
            var userHoursData = @json($userHoursData);

            var labels = Object.keys(userHoursData);
            var totalHours = labels.map(function(label) {
                return userHoursData[label].totalHours;
            });
            var billableHours = labels.map(function(label) {
                return userHoursData[label].billableHours;
            });
            var nonBillableHours = labels.map(function(label) {
                return userHoursData[label].nonBillableHours;
            });

            var ctx = document.getElementById('userHoursChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Heures totales',
                            data: totalHours,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Heures facturables',
                            data: billableHours,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Heures non facturables',
                            data: nonBillableHours,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</div>
