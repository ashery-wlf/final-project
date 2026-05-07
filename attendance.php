<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$view = $_GET['view'] ?? 'attended'; // 'attended' or 'created'
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Get events user attended
$attendedEvents = $conn->query("
    SELECT e.*, a.time as check_in_time, a.attendance_status, a.scan_address, a.scan_ip, a.browser_info, a.distance_from_venue
    FROM events e
    JOIN attendance a ON e.id = a.event_id
    WHERE a.user_id = $user_id
    ORDER BY a.time DESC
");

// Get events user created
$createdEvents = $conn->query("
    SELECT e.*, COUNT(a.id) as attendance_count
    FROM events e
    LEFT JOIN attendance a ON e.id = a.event_id
    WHERE e.created_by = $user_id
    GROUP BY e.id
    ORDER BY e.date DESC, e.time DESC
");

// Get specific event details if event_id is provided
$event = null;
$attendanceData = null;
if ($event_id > 0) {
    $event = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1")->fetch_assoc();
    
    if ($event) {
        // Check if user attended or created this event
        $isCreator = (int) $event['created_by'] === $user_id;
        $isAttendee = false;
        
        if (!$isCreator) {
            $attendanceCheck = $conn->query("SELECT * FROM attendance WHERE event_id=$event_id AND user_id=$user_id LIMIT 1")->fetch_assoc();
            $isAttendee = $attendanceCheck !== null;
        }
        
        if ($isCreator || $isAttendee) {
            if ($isCreator) {
                // Get all attendance for created event
                $attendanceData = $conn->query("
                    SELECT id, user_name, user_email, user_phone, device_info, time, attendance_status, scan_address, scan_ip, browser_info, distance_from_venue, phone_matched, verification_method, check_in_time, notes
                    FROM attendance
                    WHERE event_id = $event_id
                    ORDER BY time DESC
                ");
            } else {
                // Get user's attendance for attended event
                $attendanceData = $conn->query("
                    SELECT id, user_name, user_email, user_phone, device_info, time, attendance_status, scan_address, scan_ip, browser_info, distance_from_venue, phone_matched, verification_method, check_in_time, notes
                    FROM attendance
                    WHERE event_id = $event_id AND user_id = $user_id
                    ORDER BY time DESC
                ");
            }
        } else {
            die("Access denied: You don't have permission to view this event's attendance.");
        }
    } else {
        die("Event not found.");
    }
}

$pageCss = <<<'CSS'
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { box-sizing: border-box; }

/* Mobile-first responsive design */
.container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }

/* Category Section */
.category-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 20px;
    text-align: center;
}

/* View Toggle Buttons */
.view-toggle {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    background: #f8fafc;
    padding: 8px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.view-btn {
    flex: 1;
    padding: 20px;
    border: none;
    background: transparent;
    color: #64748b;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 15px;
    text-align: left;
}

.view-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.view-btn:hover:not(.active) {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.view-btn i {
    font-size: 24px;
    min-width: 24px;
}

.btn-text {
    flex: 1;
}

.btn-text strong {
    display: block;
    font-size: 16px;
    margin-bottom: 4px;
}

.btn-text span {
    font-size: 13px;
    opacity: 0.8;
    font-weight: 400;
}

.count-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 700;
    min-width: 30px;
    text-align: center;
}

.view-btn:not(.active) .count-badge {
    background: #e2e8f0;
    color: #64748b;
}

/* Event Cards */
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.event-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    cursor: pointer;
}

.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.event-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.event-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 5px 0;
}

.event-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #6b7280;
    font-size: 14px;
}

.event-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-attended {
    background: #dcfce7;
    color: #166534;
}

.status-created {
    background: #dbeafe;
    color: #1d4ed8;
}

.event-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6b7280;
}

.detail-item i {
    width: 16px;
    color: #667eea;
}

/* Device Information Row */
.device-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.device-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.device-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.device-summary i:first-child {
    font-size: 24px;
}

.device-info {
    flex: 1;
}

.device-info strong {
    display: block;
    font-size: 16px;
    font-weight: 600;
}

.device-info span {
    font-size: 14px;
    opacity: 0.8;
}

.device-summary i:last-child {
    font-size: 16px;
    opacity: 0.7;
}

/* Device Info Cell */
.device-info-cell {
    color: #6b7280;
    font-size: 13px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.device-info-cell:hover {
    background-color: #f3f4f6;
    color: #374151;
}

/* Device Tooltip */
.device-tooltip {
    position: absolute;
    background: #1f2937;
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
    z-index: 1000;
    max-width: 250px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    pointer-events: none;
}

.device-tooltip-item {
    margin-bottom: 6px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.device-tooltip-item:last-child {
    margin-bottom: 0;
}

.device-tooltip-label {
    font-weight: 600;
    color: #9ca3af;
}

.device-tooltip-value {
    color: #f3f4f6;
    word-break: break-all;
}

/* Attendance Table */
.table-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.table-title {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.table-stats {
    display: flex;
    gap: 15px;
    align-items: center;
}

.stat-badge {
    background: #f3f4f6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

/* Responsive Table */
.attendance-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.attendance-table th,
.attendance-table td {
    padding: 12px 8px;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
}

.attendance-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    background: #f9fafb;
    font-weight: 600;
}

.attendance-table tr:hover {
    background: #f9fafb;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-present { background: #dcfce7; color: #166534; }
.badge-late { background: #fef3c7; color: #92400e; }
.badge-absent { background: #fee2e2; color: #991b1b; }
.badge-browser { background: #dbeafe; color: #1d4ed8; }
.badge-device { background: #dcfce7; color: #166534; }

/* Mobile Responsive */
@media (max-width: 768px) {
    .container { padding: 0 10px; }
    
    .section-title {
        font-size: 20px;
    }
    
    .view-toggle {
        flex-direction: column;
        gap: 10px;
    }
    
    .view-btn {
        padding: 15px;
        gap: 12px;
    }
    
    .view-btn i {
        font-size: 20px;
    }
    
    .btn-text strong {
        font-size: 14px;
    }
    
    .btn-text span {
        font-size: 12px;
    }
    
    .events-grid {
        grid-template-columns: 1fr;
    }
    
    .device-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .attendance-table {
        font-size: 14px;
    }
    
    .attendance-table th,
    .attendance-table td {
        padding: 8px 4px;
    }
    
    .event-details {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .event-card {
        padding: 15px;
    }
    
    .device-section {
        padding: 20px;
    }
    
    .table-card {
        padding: 15px;
    }
}

/* Chart Container */
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

.back-link {
    display: inline-flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 12px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s;
}

.back-link:hover {
    background: #cbd5e1;
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

// Calculate statistics
$attendedCount = $attendedEvents ? $attendedEvents->num_rows : 0;
$createdCount = $createdEvents ? $createdEvents->num_rows : 0;
$totalAttendees = $attendanceData ? $attendanceData->num_rows : 0;

// Get device statistics for current event
$deviceStats = [];
$browserStats = [];
$locationStats = [];

if ($attendanceData && $attendanceData->num_rows > 0) {
    while ($row = $attendanceData->fetch_assoc()) {
        if (!empty($row['device_info'])) {
            $info = json_decode($row['device_info'], true);
            if ($info) {
                $browser = $info['browser'] ?? 'Unknown';
                $deviceType = $info['device_type'] ?? 'Unknown';
                
                if (!isset($browserStats[$browser])) {
                    $browserStats[$browser] = 0;
                }
                $browserStats[$browser]++;
                
                if (!isset($deviceStats[$deviceType])) {
                    $deviceStats[$deviceType] = 0;
                }
                $deviceStats[$deviceType]++;
            }
        }
        
        if (!empty($row['scan_address'])) {
            $location = $row['scan_address'];
            if (!isset($locationStats[$location])) {
                $locationStats[$location] = 0;
            }
            $locationStats[$location]++;
        }
    }
    // Reset pointer for later use
    $attendanceData->data_seek(0);
}

renderAppShellStart($conn, [
    "title" => "Attendance Hub",
    "active" => "attendance",
    "page_title" => "Attendance Hub",
    "page_subtitle" => "View events you attended and events you created",
    "search_placeholder" => "Search attendance...",
    "extra_head" => $pageCss,
]);
?>

<div class="container">
    <?php if ($event_id > 0): ?>
        <!-- Single Event View -->
        <div style="margin-bottom: 20px;">
            <a href="attendance.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to All Events
            </a>
        </div>

        
        <!-- Attendance Table -->
        <div class="table-card">
            <div class="table-header">
                <h2 class="table-title"><?php echo h($event['name']); ?></h2>
                <div class="table-stats">
                    <span class="stat-badge">
                        <i class="fas fa-users"></i> <?php echo $totalAttendees; ?> Attendees
                    </span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Device Info</th>
                            <th>Check-in Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendanceData && $attendanceData->num_rows > 0): ?>
                            <?php while ($row = $attendanceData->fetch_assoc()): ?>
                                <?php 
                                $deviceInfo = [];
                                if (!empty($row['device_info'])) {
                                    $deviceInfo = json_decode($row['device_info'], true);
                                }
                                $status = $row['attendance_status'] ?? 'present';
                                
                                $deviceText = ($deviceInfo['device_type'] ?? 'Unknown') . ' • ' . ($deviceInfo['browser'] ?? 'Unknown');
                                $deviceDetails = [
                                    'IP' => $row['scan_ip'] ?: 'Not captured',
                                    'Device' => $deviceInfo['device_type'] ?? 'Unknown',
                                    'Browser' => $deviceInfo['browser'] ?? 'Unknown',
                                    'Location' => $row['scan_address'] ?: 'Not captured'
                                ];
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo h($row['user_name']); ?></div>
                                    </td>
                                    <td><?php echo h($row['user_email']); ?></td>
                                    <td><?php echo h($row['user_phone']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $status; ?>">
                                            <?php echo ucfirst($status); ?>
                                            <?php if ($row['phone_matched']): ?> ✅<?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="device-info-cell" 
                                             onmouseover="showDeviceTooltip(event, <?php echo json_encode($deviceDetails); ?>)"
                                             onmouseout="hideDeviceTooltip()"
                                             ontouchstart="showDeviceTooltip(event, <?php echo json_encode($deviceDetails); ?>)"
                                             ontouchend="hideDeviceTooltip()">
                                            <?php echo h($deviceText); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo h(date('M j, Y H:i', strtotime($row['time']))); ?></div>
                                        <?php if (!empty($row['check_in_time'])): ?>
                                            <small style="color: #6b7280;"><?php echo h($row['check_in_time']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($row['notes'])): ?>
                                <tr>
                                    <td colspan="6" style="background: #f9fafb; padding: 8px;">
                                        <small><strong>Notes:</strong> <?php echo h($row['notes']); ?></small>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-users" style="font-size: 48px; color: #d1d5db; margin-bottom: 15px; display: block;"></i>
                                    <div style="color: #6b7280;">No attendance records yet.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Device Tooltip -->
                <div id="deviceTooltip" class="device-tooltip" style="display: none;"></div>
            </div>
        </div>

        <!-- Charts Section -->
        <?php if (count($browserStats) > 0): ?>
        <div class="chart-container">
            <div class="chart-title">🌐 Browser Distribution</div>
            <canvas id="browserChart"></canvas>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Events Overview View -->
        <div class="category-section">
            <h2 class="section-title">Select Attendance View</h2>
            <div class="view-toggle">
                <button class="view-btn <?php echo $view === 'attended' ? 'active' : ''; ?>" onclick="window.location.href='?view=attended'">
                    <i class="fas fa-user-check"></i>
                    <div class="btn-text">
                        <strong>My Attendance Only</strong>
                        <span>Events I attended and my check-ins</span>
                    </div>
                    <span class="count-badge"><?php echo $attendedCount; ?></span>
                </button>
                <button class="view-btn <?php echo $view === 'created' ? 'active' : ''; ?>" onclick="window.location.href='?view=created'">
                    <i class="fas fa-calendar-plus"></i>
                    <div class="btn-text">
                        <strong>Events I Created</strong>
                        <span>My events and all attendees</span>
                    </div>
                    <span class="count-badge"><?php echo $createdCount; ?></span>
                </button>
            </div>
        </div>

        <?php if ($view === 'attended'): ?>
            <!-- Events Attended -->
            <div class="events-grid">
                <?php if ($attendedEvents && $attendedEvents->num_rows > 0): ?>
                    <?php while ($event = $attendedEvents->fetch_assoc()): ?>
                        <div class="event-card" onclick="window.location.href='?id=<?php echo $event['id']; ?>'">
                            <div class="event-card-header">
                                <div>
                                    <h3 class="event-title"><?php echo h($event['name']); ?></h3>
                                    <div class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo h(date('M j, Y', strtotime($event['date']))); ?>
                                        <i class="fas fa-clock"></i>
                                        <?php echo h(date('H:i', strtotime($event['time']))); ?>
                                    </div>
                                </div>
                                <span class="event-status status-attended">Attended</span>
                            </div>
                            <div class="event-details">
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo h($event['venue_name'] ?: 'No venue'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo ucfirst(h($event['attendance_status'] ?? 'present')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo h(date('H:i', strtotime($event['check_in_time']))); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map"></i>
                                    <span><?php echo h(substr($event['scan_address'] ?: 'Not captured', 0, 30)); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; grid-column: 1 / -1;">
                        <i class="fas fa-user-check" style="font-size: 64px; color: #d1d5db; margin-bottom: 20px; display: block;"></i>
                        <h3 style="color: #374151; margin-bottom: 10px;">No Events Attended</h3>
                        <p style="color: #6b7280;">You haven't attended any events yet. Start scanning QR codes to check in!</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Events Created -->
            <div class="events-grid">
                <?php if ($createdEvents && $createdEvents->num_rows > 0): ?>
                    <?php while ($event = $createdEvents->fetch_assoc()): ?>
                        <div class="event-card" onclick="window.location.href='?id=<?php echo $event['id']; ?>'">
                            <div class="event-card-header">
                                <div>
                                    <h3 class="event-title"><?php echo h($event['name']); ?></h3>
                                    <div class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo h(date('M j, Y', strtotime($event['date']))); ?>
                                        <i class="fas fa-clock"></i>
                                        <?php echo h(date('H:i', strtotime($event['time']))); ?>
                                    </div>
                                </div>
                                <span class="event-status status-created">Created</span>
                            </div>
                            <div class="event-details">
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $event['attendance_count']; ?> Attendees</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo h($event['venue_name'] ?: 'No venue'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-cog"></i>
                                    <span><?php echo ucfirst(h($event['registration_mode'] ?? 'self')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo $event['deleted'] ? 'Deleted' : 'Active'; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; grid-column: 1 / -1;">
                        <i class="fas fa-calendar-plus" style="font-size: 64px; color: #d1d5db; margin-bottom: 20px; display: block;"></i>
                        <h3 style="color: #374151; margin-bottom: 10px;">No Events Created</h3>
                        <p style="color: #6b7280;">You haven't created any events yet. Start organizing your first event!</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (count($browserStats) > 0): ?>
<script>
// Initialize browser chart
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
            '#fa709a',
            '#fee140'
        ],
        borderColor: [
            '#667eea',
            '#764ba2',
            '#f093fb', 
            '#4facfe',
            '#00f2fe',
            '#43e97b',
            '#fa709a',
            '#fee140'
        ],
        borderWidth: 2,
        borderRadius: 8
    }]
};

// Initialize with line chart
browserChart = new Chart(browserCtx, {
    type: 'line',
    data: chartData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed.y || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});

// Add real-time device information updates
function updateDeviceInformation() {
    // This function can be enhanced to show real-time updates
    // For now, it displays the static information beautifully
    const deviceItems = document.querySelectorAll('.device-item');
    deviceItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Initialize animations when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateDeviceInformation();
    
    // Animate event cards
    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Animate stats
    const statNumbers = document.querySelectorAll('.stat-badge');
    statNumbers.forEach(stat => {
        const finalNumber = stat.textContent.match(/\d+/)[0];
        let currentNumber = 0;
        const increment = Math.ceil(finalNumber / 20);
        const timer = setInterval(() => {
            currentNumber += increment;
            if (currentNumber >= finalNumber) {
                currentNumber = finalNumber;
                clearInterval(timer);
            }
            stat.innerHTML = stat.innerHTML.replace(/\d+/, currentNumber);
        }, 50);
    });
});

// Device Tooltip Functions
function showDeviceTooltip(event, deviceDetails) {
    const tooltip = document.getElementById('deviceTooltip');
    if (!tooltip) return;
    
    let tooltipContent = '';
    for (const [key, value] of Object.entries(deviceDetails)) {
        tooltipContent += `
            <div class="device-tooltip-item">
                <span class="device-tooltip-label">${key}:</span>
                <span class="device-tooltip-value">${value}</span>
            </div>
        `;
    }
    
    tooltip.innerHTML = tooltipContent;
    tooltip.style.display = 'block';
    
    // Position tooltip
    const rect = event.target.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    let left = rect.left + window.scrollX;
    let top = rect.bottom + window.scrollY + 5;
    
    // Adjust if tooltip goes off screen
    if (left + tooltipRect.width > window.innerWidth) {
        left = window.innerWidth - tooltipRect.width - 10;
    }
    
    if (top + tooltipRect.height > window.innerHeight + window.scrollY) {
        top = rect.top + window.scrollY - tooltipRect.height - 5;
    }
    
    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
}

function hideDeviceTooltip() {
    const tooltip = document.getElementById('deviceTooltip');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

// Mobile touch interactions
if ('ontouchstart' in window) {
    document.querySelectorAll('.event-card').forEach(card => {
        card.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        card.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Touch interactions for device info cells
    document.querySelectorAll('.device-info-cell').forEach(cell => {
        cell.addEventListener('touchstart', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '#f3f4f6';
        });
        cell.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.style.backgroundColor = '';
            // Hide tooltip after a short delay on mobile
            setTimeout(() => hideDeviceTooltip(), 2000);
        });
    });
}
</script>
<?php endif; ?>

<?php renderAppShellEnd("attendance"); ?>
