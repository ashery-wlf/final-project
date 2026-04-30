<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1")->fetch_assoc();

if (!$event) {
    die("Event not found.");
}

$data = $conn->query("
    SELECT id, user_name, user_email, user_phone, device_info, time, attendance_status, scan_address
    FROM attendance
    WHERE event_id = $event_id
    ORDER BY time DESC
");

$pageCss = <<<'CSS'
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.table-card{background:#fff;border:1px solid #dce5f1;border-radius:22px;padding:22px;box-shadow:0 16px 34px rgba(15, 23, 42, 0.06);}
table{width:100%;border-collapse:collapse;}
th,td{padding:14px 10px;text-align:left;border-bottom:1px solid #eef2f7;}
th{font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;background:#f5f5f5;font-weight:600;}
.top-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:space-between;}
.back-link{display:inline-flex;align-items:center;padding:12px 16px;border-radius:12px;background:#e2e8f0;color:#334155;font-weight:700;text-decoration:none;transition:0.2s;}
.back-link:hover{background:#cbd5e1;}
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-browser {
    background-color: #e3f2fd;
    color: #1565c0;
}
.badge-device {
    background-color: #e8f5e9;
    color: #2e7d32;
}
.badge-present {
    background-color: #e8f5e9;
    color: #2e7d32;
}
.badge-late {
    background-color: #fff3e0;
    color: #e65100;
}
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: relative;
    height: 300px;
}
.chart-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}
.stat-card h4 {
    margin: 0 0 10px 0;
    color: #667eea;
    font-size: 12px;
    text-transform: uppercase;
}
.stat-card .number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}
tr:hover {
    background-color: #f9f9f9;
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
</style>
CSS;

// Get statistics
$totalAttendees = $data ? $data->num_rows : 0;
$deviceStats = [];
$browserStats = [];

// Reset the result for statistics
$statsData = $conn->query("
    SELECT device_info, attendance_status, COUNT(*) as count
    FROM attendance
    WHERE event_id = $event_id
    GROUP BY device_info, attendance_status
");

if ($statsData) {
    while ($row = $statsData->fetch_assoc()) {
        if (!empty($row['device_info'])) {
            $info = json_decode($row['device_info'], true);
            if ($info) {
                $browser = $info['browser'] ?? 'Unknown';
                if (!isset($browserStats[$browser])) {
                    $browserStats[$browser] = 0;
                }
                $browserStats[$browser] += $row['count'];
            }
        }
    }
}

renderAppShellStart($conn, [
    "title" => "Attendance",
    "active" => "attendance",
    "page_title" => "Attendance List",
    "page_subtitle" => "Review everyone who has checked in for this event.",
    "search_placeholder" => "Search events...",
    "page_actions" => '<a href="event.php?id=' . $event_id . '" class="back-link">← Back to event</a>',
    "extra_head" => $pageCss,
]);
?>

<div class="stats-grid">
    <div class="stat-card">
        <h4>Total Attendees</h4>
        <div class="number"><?php echo $totalAttendees; ?></div>
    </div>
</div>

<!-- Graph Type Toggle -->
<div style="margin-bottom: 20px; text-align: center;">
    <button onclick="switchChart('doughnut')" class="chart-btn active" data-chart="doughnut">🍩 Doughnut</button>
    <button onclick="switchChart('bar')" class="chart-btn" data-chart="bar">📊 Bar</button>
    <button onclick="switchChart('line')" class="chart-btn" data-chart="line">📈 Line</button>
</div>

<?php if (count($browserStats) > 0): ?>
<div class="chart-container">
    <div class="chart-title">🌐 Browsers Used by Attendees</div>
    <canvas id="browserChart"></canvas>
</div>
<?php endif; ?>

<div class="table-card">
    <div class="top-actions">
        <strong><?php echo h($event['name']); ?></strong>
        <span style="font-size: 14px; color: #666;">Total: <?php echo $totalAttendees; ?> attendees</span>
    </div>

    <table>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Browser</th>
            <th>Device</th>
            <th>Status</th>
            <th>Location</th>
            <th>Check-in Time</th>
        </tr>
        <?php if ($data && $data->num_rows > 0): ?>
            <?php while ($row = $data->fetch_assoc()): ?>
                <?php 
                $deviceInfo = [];
                if (!empty($row['device_info'])) {
                    $deviceInfo = json_decode($row['device_info'], true);
                }
                $status = $row['attendance_status'] ?? 'present';
                $statusClass = 'badge-' . $status;
                ?>
                <tr>
                    <td><?php echo h($row['user_name']); ?></td>
                    <td><?php echo h($row['user_email']); ?></td>
                    <td><?php echo h($row['user_phone']); ?></td>
                    <td>
                        <span class="badge badge-browser">
                            <?php echo h($deviceInfo['browser'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-device">
                            <?php echo h($deviceInfo['device_type'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td><?php echo h($row['scan_address'] ?: 'Not captured'); ?></td>
                    <td><?php echo h(date('M j, Y H:i', strtotime($row['time']))); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">No attendance records yet.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php if (count($browserStats) > 0): ?>
<script>
let browserChart;
const browserCtx = document.getElementById('browserChart').getContext('2d');
const chartData = {
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
        ],
        borderColor: [
            '#667eea',
            '#764ba2',
            '#f093fb',
            '#4facfe',
            '#00f2fe',
            '#43e97b',
        ],
        borderWidth: 2
    }]
};

// Initialize with doughnut chart
browserChart = new Chart(browserCtx, {
    type: 'doughnut',
    data: chartData,
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

// Chart switching function
function switchChart(type) {
    // Update button states
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-chart="${type}"]`).classList.add('active');
    
    // Destroy existing chart
    if (browserChart) {
        browserChart.destroy();
    }
    
    // Create new chart with selected type
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: type === 'line' ? 'top' : 'bottom'
            }
        }
    };
    
    // Add specific options for line chart
    if (type === 'line') {
        options.scales = {
            y: {
                beginAtZero: true
            }
        };
        chartData.datasets[0].fill = true;
        chartData.datasets[0].tension = 0.4;
        chartData.datasets[0].backgroundColor = 'rgba(102, 126, 234, 0.2)';
        chartData.datasets[0].borderColor = '#667eea';
    } else if (type === 'bar') {
        options.scales = {
            y: {
                beginAtZero: true
            }
        };
    }
    
    browserChart = new Chart(browserCtx, {
        type: type,
        data: chartData,
        options: options
    });
}
</script>
<?php endif; ?>

<?php renderAppShellEnd("attendance"); ?>
