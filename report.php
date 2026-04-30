<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];

// Get user's events (events created by this user)
$userEvents = $conn->query("SELECT * FROM events WHERE created_by=$user_id ORDER BY date DESC");

// Get attendance statistics for user's events
$attendanceStats = [];
$totalAttendance = 0;
$totalEvents = 0;

if ($userEvents && $userEvents->num_rows > 0) {
    while ($event = $userEvents->fetch_assoc()) {
        $eventId = (int)$event['id'];
        $attendanceResult = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE event_id=$eventId");
        $attendanceRow = $attendanceResult->fetch_assoc();
        
        $attendanceStats[] = [
            'event_id' => $eventId,
            'event_name' => $event['name'],
            'event_date' => $event['date'],
            'attendance_count' => (int)$attendanceRow['count'],
            'status' => eventLifecycleStatus($event)
        ];
        
        $totalAttendance += (int)$attendanceRow['count'];
        $totalEvents++;
    }
}

// Get device information from recent attendance
$deviceStats = [];
$deviceResult = $conn->query("
    SELECT device_info, COUNT(*) as count 
    FROM attendance 
    WHERE event_id IN (SELECT id FROM events WHERE created_by=$user_id)
    AND device_info IS NOT NULL
    GROUP BY device_info
");

if ($deviceResult) {
    while ($row = $deviceResult->fetch_assoc()) {
        if (!empty($row['device_info'])) {
            $info = json_decode($row['device_info'], true);
            if ($info) {
                $deviceStats[] = [
                    'browser' => $info['browser'] ?? 'Unknown',
                    'os' => $info['os'] ?? 'Unknown',
                    'device_type' => $info['device_type'] ?? 'Unknown',
                    'count' => (int)$row['count']
                ];
            }
        }
    }
}

// Get browser statistics
$browserStats = [];
foreach ($deviceStats as $stat) {
    $browser = $stat['browser'];
    if (!isset($browserStats[$browser])) {
        $browserStats[$browser] = 0;
    }
    $browserStats[$browser] += $stat['count'];
}

// Get OS statistics
$osStats = [];
foreach ($deviceStats as $stat) {
    $os = $stat['os'];
    if (!isset($osStats[$os])) {
        $osStats[$os] = 0;
    }
    $osStats[$os] += $stat['count'];
}

// Get device type statistics
$deviceTypeStats = [];
foreach ($deviceStats as $stat) {
    $type = $stat['device_type'];
    if (!isset($deviceTypeStats[$type])) {
        $deviceTypeStats[$type] = 0;
    }
    $deviceTypeStats[$type] += $stat['count'];
}

// Get detailed attendance records
$detailedRecords = $conn->query("
    SELECT a.*, e.name as event_name
    FROM attendance a
    JOIN events e ON e.id = a.event_id
    WHERE e.created_by=$user_id
    ORDER BY a.time DESC
    LIMIT 100
");

$pageCss = <<<'CSS'
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #667eea;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #667eea;
    font-size: 14px;
    text-transform: uppercase;
}

.stat-card .number {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: relative;
    height: 400px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}

.table-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

th {
    background: #f5f5f5;
    font-weight: 600;
    color: #333;
}

tr:hover {
    background: #f9f9f9;
}

.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #e8f5e9;
    color: #2e7d32;
}

.badge-info {
    background: #e3f2fd;
    color: #1565c0;
}

.badge-warning {
    background: #fff3e0;
    color: #e65100;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.chart-btn {
    background: #f0f0f0;
    border: none;
    padding: 8px 16px;
    margin: 0 5px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.chart-btn:hover {
    background: #e0e0e0;
    transform: translateY(-2px);
}

.chart-btn.active {
    background: #667eea;
    color: white;
}

.chart-btn.active:hover {
    background: #5a6fd8;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-container {
        height: 300px;
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Attendance Report",
    "active" => "report",
    "page_title" => "Attendance Report",
    "page_subtitle" => "View your events attendance statistics and analytics",
    "extra_head" => $pageCss,
]);
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Events Created</h3>
        <div class="number"><?php echo $totalEvents; ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Attendance</h3>
        <div class="number"><?php echo $totalAttendance; ?></div>
    </div>
    <div class="stat-card">
        <h3>Average Attendance</h3>
        <div class="number"><?php echo $totalEvents > 0 ? round($totalAttendance / $totalEvents) : 0; ?></div>
    </div>
</div>

<?php if (count($attendanceStats) > 0): ?>

<!-- Graph Type Toggle -->
<div style="margin-bottom: 20px; text-align: center;">
    <button onclick="switchAttendanceChart('doughnut')" class="chart-btn active" data-chart="doughnut">🍩 Doughnut</button>
    <button onclick="switchAttendanceChart('bar')" class="chart-btn" data-chart="bar">📊 Bar</button>
    <button onclick="switchAttendanceChart('line')" class="chart-btn" data-chart="line">📈 Line</button>
</div>

<div class="chart-container">
    <div class="chart-title">📊 Event Attendance Overview</div>
    <canvas id="attendanceChart"></canvas>
</div>

<?php endif; ?>

<?php if (count($browserStats) > 0): ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">

<div class="chart-container">
    <div class="chart-title">🌐 Browsers Used</div>
    <canvas id="browserChart"></canvas>
</div>

<div class="chart-container">
    <div class="chart-title">💻 Operating Systems</div>
    <canvas id="osChart"></canvas>
</div>

</div>

<div class="chart-container">
    <div class="chart-title">📱 Device Types</div>
    <canvas id="deviceTypeChart"></canvas>
</div>

<?php endif; ?>

<?php if ($detailedRecords && $detailedRecords->num_rows > 0): ?>

<div class="table-container">
    <div class="chart-title">📋 Recent Attendance Records</div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Event</th>
                <th>Browser</th>
                <th>Device</th>
                <th>Location</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($record = $detailedRecords->fetch_assoc()): ?>
                <?php 
                $deviceInfo = [];
                if (!empty($record['device_info'])) {
                    $deviceInfo = json_decode($record['device_info'], true);
                }
                ?>
                <tr>
                    <td><?php echo h($record['user_name']); ?></td>
                    <td><?php echo h($record['user_email']); ?></td>
                    <td><?php echo h($record['user_phone']); ?></td>
                    <td><?php echo h($record['event_name']); ?></td>
                    <td>
                        <span class="badge badge-info">
                            <?php echo h($deviceInfo['browser'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-success">
                            <?php echo h($deviceInfo['device_type'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td><?php echo h($record['scan_address'] ?: 'Not captured'); ?></td>
                    <td><?php echo h(date('M j, Y H:i', strtotime($record['time']))); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php else: ?>

<div class="table-container">
    <div class="empty-state">
        <div class="empty-state-icon">📊</div>
        <p>No attendance records yet. Create an event and start scanning QR codes!</p>
    </div>
</div>

<?php endif; ?>

<script>
// Attendance Chart
<?php if (count($attendanceStats) > 0): ?>
let attendanceChart;
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
const attendanceData = {
    labels: <?php echo json_encode(array_map(function($s) { return $s['event_name']; }, $attendanceStats)); ?>,
    datasets: [{
        label: 'Attendance Count',
        data: <?php echo json_encode(array_map(function($s) { return $s['attendance_count']; }, $attendanceStats)); ?>,
        backgroundColor: [
            '#667eea',
            '#764ba2',
            '#f093fb',
            '#4facfe',
            '#00f2fe',
            '#43e97b',
            '#fa709a',
        ],
        borderColor: [
            '#667eea',
            '#764ba2',
            '#f093fb',
            '#4facfe',
            '#00f2fe',
            '#43e97b',
            '#fa709a',
        ],
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
    }]
};

// Initialize with doughnut chart
attendanceChart = new Chart(attendanceCtx, {
    type: 'doughnut',
    data: attendanceData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Chart switching function
function switchAttendanceChart(type) {
    // Update button states
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-chart="${type}"]`).classList.add('active');
    
    // Destroy existing chart
    if (attendanceChart) {
        attendanceChart.destroy();
    }
    
    // Create new chart with selected type
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: type === 'doughnut' ? false : true,
                position: type === 'doughnut' ? 'bottom' : 'top'
            }
        }
    };
    
    // Add scales for bar and line charts
    if (type === 'bar' || type === 'line') {
        chartOptions.scales = {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        };
    }
    
    // Add line-specific options
    if (type === 'line') {
        attendanceData.datasets[0].fill = true;
        attendanceData.datasets[0].tension = 0.4;
        attendanceData.datasets[0].backgroundColor = 'rgba(102, 126, 234, 0.2)';
        attendanceData.datasets[0].borderColor = '#667eea';
        attendanceData.datasets[0].pointBackgroundColor = '#667eea';
        attendanceData.datasets[0].pointBorderColor = '#fff';
        attendanceData.datasets[0].pointBorderWidth = 2;
        attendanceData.datasets[0].pointRadius = 6;
    }
    
    attendanceChart = new Chart(attendanceCtx, {
        type: type,
        data: attendanceData,
        options: chartOptions
    });
}
<?php endif; ?>

// Browser Chart
<?php if (count($browserStats) > 0): ?>
const browserCtx = document.getElementById('browserChart').getContext('2d');
const browserChart = new Chart(browserCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($browserStats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($browserStats)); ?>,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#4facfe',
                '#00f2fe',
                '#43e97b',
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// OS Chart
<?php if (count($osStats) > 0): ?>
const osCtx = document.getElementById('osChart').getContext('2d');
const osChart = new Chart(osCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($osStats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($osStats)); ?>,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#4facfe',
                '#00f2fe',
                '#43e97b',
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Device Type Chart
<?php if (count($deviceTypeStats) > 0): ?>
const deviceTypeCtx = document.getElementById('deviceTypeChart').getContext('2d');
const deviceTypeChart = new Chart(deviceTypeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($deviceTypeStats)); ?>,
        datasets: [{
            label: 'Device Count',
            data: <?php echo json_encode(array_values($deviceTypeStats)); ?>,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
            ],
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php renderAppShellEnd("report"); ?>
