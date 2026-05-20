<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$search = trim($_GET['search'] ?? "");
$safeSearch = $conn->real_escape_string($search);

// Handle delete action
if (isset($_POST['delete_event']) && is_numeric($_POST['delete_event'])) {
    if (!appVerifyCsrf()) {
        die("Security check failed.");
    }

    $eventId = (int) $_POST['delete_event'];
    $eventCheck = $conn->query("SELECT created_by FROM events WHERE id = $eventId LIMIT 1");
    if ($eventCheck && $eventCheck->num_rows > 0) {
        $event = $eventCheck->fetch_assoc();
        if ((int) $event['created_by'] === $user_id) {
            $conn->query("UPDATE events SET deleted = TRUE, deleted_at = NOW() WHERE id = $eventId");
            header("Location: events.php");
            exit();
        }
    }
}

$where = "1=1";
if ($search !== "") {
    $where .= " AND (name LIKE '%$safeSearch%' OR venue_name LIKE '%$safeSearch%' OR venue_location LIKE '%$safeSearch%')";
}

$events = $conn->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM participants p WHERE p.event_id = e.id) AS registered_count,
           (SELECT COUNT(*) FROM attendance a WHERE a.event_id = e.id) AS attended_count
    FROM events e
    WHERE $where AND e.deleted = FALSE
    ORDER BY e.date DESC, e.time DESC
");
?>
<?php
$pageCss = <<<'CSS'
<style>
.top{
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-end;
    margin-bottom:18px;
}

.top h1{
    margin:0;
    font-size:38px;
}

.top p{
    margin:8px 0 0;
    color:#64748b;
}

.cta{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:14px 18px;
    border-radius:14px;
    background:linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    color:#fff;
    font-weight:800;
    box-shadow:0 16px 30px rgba(37, 99, 235, 0.25);
}

.toolbar{
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:18px;
    padding:14px;
    display:flex;
    gap:12px;
    align-items:center;
    margin-bottom:18px;
    box-shadow:0 16px 34px rgba(15, 23, 42, 0.06);
}

.toolbar input{
    flex:1;
    border:1px solid #dce5f1;
    border-radius:12px;
    padding:13px 14px;
    font-size:14px;
    background:#f8fbff;
}

.toolbar button, .toolbar a{
    border:none;
    border-radius:12px;
    padding:13px 16px;
    font-weight:700;
}

.toolbar button{
    background:#0f172a;
    color:#fff;
}

.toolbar a{
    background:#e2e8f0;
    color:#334155;
    text-decoration:none;
}

.list{
    display:grid;
    gap:16px;
}

.event-card{
    display:grid;
    grid-template-columns:100px 1fr;
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(15, 23, 42, 0.04);
    min-height:60px;
}

.poster{
    width:100%;
    height:100%;
    min-height:60px;
    object-fit:cover;
    background:#dbeafe;
}

.body{
    padding:8px;
}

.body h2{
    margin:0 0 4px;
    font-size:14px;
    line-height:1.1;
}

.simple-meta{
    display:flex;
    flex-direction:column;
    gap:2px;
    margin-bottom:6px;
}

.simple-info{
    display:flex;
    flex-direction:column;
    gap:1px;
}

.simple-label{
    font-size:9px;
    color:#64748b;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.01em;
}

.simple-value{
    font-size:11px;
    font-weight:600;
    color:#0f172a;
}

.footer{
    margin-top:4px;
    display:flex;
    justify-content:flex-end;
}

.view{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 8px;
    border-radius:6px;
    background:#0f172a;
    color:#fff;
    font-weight:600;
    font-size:10px;
}

.delete-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 8px;
    border-radius:6px;
    background:#dc2626;
    color:#fff;
    font-weight:600;
    font-size:10px;
    text-decoration:none;
    transition:background 0.2s;
}

.delete-btn:hover{
    background:#b91c1c;
}

.deleted-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 8px;
    border-radius:6px;
    background:#6b7280;
    color:#fff;
    font-weight:600;
    font-size:9px;
}

.empty{
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:22px;
    padding:34px 24px;
    text-align:center;
    color:#64748b;
}

@media (max-width: 820px){
    .top{
        flex-direction:column;
        align-items:flex-start;
    }

    .toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .event-card{
        grid-template-columns:80px minmax(0, 1fr);
        border-radius:10px;
        padding:6px;
        gap:6px;
        align-items:start;
        min-height:50px;
    }

    .poster{
        width:100%;
        min-height:50px;
        height:50px;
        border-radius:8px;
        margin:0;
    }

    .body{
        padding:0;
        min-width:0;
    }

    .body h2{
        font-size:12px;
        line-height:1.1;
        margin-bottom:2px;
    }

    .simple-meta{
        gap:1px;
        margin-bottom:3px;
    }

    .simple-label{
        font-size:8px;
    }

    .simple-value{
        font-size:9px;
    }

    .footer{
        align-items:flex-start;
        margin-top:2px;
    }

    .view{
        width:auto;
        min-width:60px;
        font-size:8px;
        padding:2px 4px;
        border-radius:4px;
    }
}

@media (max-width: 440px){
    .event-card{
        grid-template-columns:70px minmax(0, 1fr);
        padding:4px;
        gap:4px;
        min-height:40px;
    }

    .poster{
        width:100%;
        min-height:40px;
        height:40px;
        border-radius:6px;
        margin:0;
    }

    .body{
        padding:0;
    }

    .body h2{
        font-size:10px;
        line-height:1.1;
        margin-bottom:1px;
    }

    .simple-meta{
        gap:1px;
        margin-bottom:2px;
    }

    .simple-label{
        font-size:7px;
    }

    .simple-value{
        font-size:8px;
    }

    .footer{
        margin-top:1px;
    }

    .view{
        font-size:7px;
        padding:1px 3px;
        border-radius:3px;
        min-width:50px;
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Events",
    "active" => "events",
    "page_title" => "Online Events",
    "page_subtitle" => "Everyone can see all events online. Access to join depends on the event settings chosen by the admin.",
    "search_placeholder" => "Search events...",
    "page_actions" => '<a href="create-event.php" class="cta">+ Create Event</a>',
    "extra_head" => $pageCss,
]);
?>
    <form class="toolbar" method="GET">
        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Search by event name, venue, or location">
        <button type="submit">Search</button>
        <a href="dashboard.php">Dashboard</a>
    </form>

    <div class="list">
        <?php if ($events && $events->num_rows > 0): ?>
            <?php while ($row = $events->fetch_assoc()): ?>
                <?php
                $isOwner = (int) $row['created_by'] === $user_id;
                $mode = eventRegistrationMode($row);
                $status = eventLifecycleStatus($row);
                $statusText = eventLifecycleLabel($row);
                ?>
                <article class="event-card">
                    <img src="<?php echo h(eventImagePath($row['image'])); ?>" alt="<?php echo h($row['name']); ?>" class="poster">

                    <div class="body">
                        <h2><?php echo h($row['name']); ?></h2>
                        
                        <div class="simple-meta">
                            <div class="simple-info">
                                <div class="simple-label">📍 Location</div>
                                <div class="simple-value"><?php echo h($row['venue_location'] ?: 'Location not shared yet'); ?></div>
                            </div>
                            
                            <div class="simple-info">
                                <div class="simple-label">🕐 Start Time</div>
                                <div class="simple-value"><?php echo h(formatEventDate($row['date'])); ?> • <?php echo h(formatEventTime($row['time'])); ?></div>
                            </div>
                        </div>

                        <div class="footer">
                            <a href="event.php?id=<?php echo (int) $row['id']; ?>" class="view">Open Event</a>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">No events found. Create a new event or try a different search.</div>
        <?php endif; ?>
    </div>
<?php renderAppShellEnd("events"); ?>
