<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];

// Get user's events where they attended
$userAttendance = $conn->query("
    SELECT DISTINCT e.*, a.time as attendance_time, COUNT(a.id) as total_attendees
    FROM events e
    LEFT JOIN attendance a ON e.id = a.event_id AND a.user_id = $user_id
    LEFT JOIN attendance all_a ON e.id = all_a.event_id
    WHERE (a.user_id = $user_id OR e.created_by = $user_id)
    GROUP BY e.id
    ORDER BY e.date DESC
");

// Get statistics
$totalEventsAttended = 0;
$upcomingEvents = 0;
$pastEvents = 0;

$events = [];
if ($userAttendance && $userAttendance->num_rows > 0) {
    while ($event = $userAttendance->fetch_assoc()) {
        $events[] = $event;
        $status = eventLifecycleStatus($event);
        
        if ($status === 'upcoming') {
            $upcomingEvents++;
        } elseif ($status === 'ended') {
            $pastEvents++;
        }
        
        if (!empty($event['attendance_time'])) {
            $totalEventsAttended++;
        }
    }
}

// Calculate personal statistics
$personalStats = $conn->query("
    SELECT 
        COUNT(DISTINCT event_id) as events_attended,
        COUNT(*) as total_scans
    FROM attendance
    WHERE user_id = $user_id
");

$personalStatsRow = $personalStats ? $personalStats->fetch_assoc() : ['events_attended' => 0, 'total_scans' => 0];

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

.events-list {
    display: grid;
    gap: 15px;
}

.event-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #667eea;
    transition: transform 0.2s ease;
}

.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.event-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.event-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-upcoming {
    background: #e3f2fd;
    color: #1565c0;
}

.status-live {
    background: #fff3e0;
    color: #e65100;
}

.status-ended {
    background: #e8f5e9;
    color: #2e7d32;
}

.event-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 10px;
    font-size: 14px;
}

.event-detail {
    color: #666;
}

.event-detail strong {
    color: #333;
}

.attendance-badge {
    display: inline-block;
    padding: 8px 16px;
    background: #e8f5e9;
    color: #2e7d32;
    border-radius: 8px;
    font-weight: 600;
    margin-top: 10px;
}

.no-attendance {
    display: inline-block;
    padding: 8px 16px;
    background: #ffebee;
    color: #c62828;
    border-radius: 8px;
    font-weight: 600;
    margin-top: 10px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 12px;
    color: #999;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 10px;
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

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .event-details {
        grid-template-columns: 1fr;
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "My Attendance",
    "active" => "my-attendance",
    "page_title" => "My Attendance",
    "page_subtitle" => "View your personal attendance progress and event history",
    "extra_head" => $pageCss,
]);
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Events Attended</h3>
        <div class="number"><?php echo $personalStatsRow['events_attended']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Check-ins</h3>
        <div class="number"><?php echo $personalStatsRow['total_scans']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Upcoming Events</h3>
        <div class="number"><?php echo $upcomingEvents; ?></div>
    </div>
    <div class="stat-card">
        <h3>Past Events</h3>
        <div class="number"><?php echo $pastEvents; ?></div>
    </div>
</div>

<?php if (count($events) > 0): ?>

<div class="events-list">
    <?php foreach ($events as $event): ?>
        <?php 
        $status = eventLifecycleStatus($event);
        $statusClass = 'status-' . $status;
        $statusLabel = ucfirst($status);
        $isAttended = !empty($event['attendance_time']);
        ?>
        <div class="event-card">
            <div class="event-header">
                <h2 class="event-title"><?php echo h($event['name']); ?></h2>
                <span class="event-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
            </div>
            
            <div class="event-details">
                <div class="event-detail">
                    <strong>📅 Date:</strong> <?php echo formatEventDate($event['date']); ?>
                </div>
                <div class="event-detail">
                    <strong>⏰ Time:</strong> <?php echo formatEventTime($event['time']); ?>
                </div>
                <div class="event-detail">
                    <strong>📍 Location:</strong> <?php echo h($event['venue_name'] ?? 'TBD'); ?>
                </div>
                <div class="event-detail">
                    <strong>👥 Attendees:</strong> <?php echo $event['total_attendees']; ?>
                </div>
            </div>
            
            <?php if ($status === 'ended'): ?>
                <?php if ($isAttended): ?>
                    <span class="attendance-badge">✓ Attended on <?php echo date('M j, Y H:i', strtotime($event['attendance_time'])); ?></span>
                <?php else: ?>
                    <span class="no-attendance">✗ Did not attend</span>
                <?php endif; ?>
            <?php elseif ($status === 'live'): ?>
                <a href="scan.php?id=<?php echo (int)$event['id']; ?>" style="display: inline-block; padding: 8px 16px; background: #667eea; color: white; border-radius: 8px; font-weight: 600; margin-top: 10px; text-decoration: none;">
                    Scan QR Code
                </a>
            <?php elseif ($status === 'upcoming'): ?>
                <span class="event-detail" style="margin-top: 10px; color: #999;">Event starts <?php echo formatEventDate($event['date']); ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>

<div class="empty-state">
    <div class="empty-state-icon">📋</div>
    <p>No events yet. Start by creating or registering for an event!</p>
</div>

<?php endif; ?>

<?php renderAppShellEnd("my-attendance"); ?>
