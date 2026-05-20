<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$search = trim($_GET['search'] ?? "");
$filter = $_GET['filter'] ?? "all";
$safeSearch = $conn->real_escape_string($search);

$joinedResult = $conn->query("SELECT COUNT(*) AS total FROM participants WHERE user_id=$user_id");
$joinedCount = $joinedResult ? (int) $joinedResult->fetch_assoc()['total'] : 0;

$attendedResult = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE user_id=$user_id");
$attendedCount = $attendedResult ? (int) $attendedResult->fetch_assoc()['total'] : 0;

$pendingResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM participants p
    LEFT JOIN attendance a ON a.user_id = p.user_id AND a.event_id = p.event_id
    JOIN events e ON e.id = p.event_id
    WHERE p.user_id = $user_id
    AND a.id IS NULL
    AND e.deleted = FALSE
    AND CONCAT(e.date, ' ', COALESCE(e.end_time, e.time), ':00') >= NOW()
");
$pendingCount = $pendingResult ? (int) $pendingResult->fetch_assoc()['total'] : 0;
$upcomingCountResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE deleted = FALSE
    AND CONCAT(date, ' ', time, ':00') >= NOW()
");
$upcomingCount = $upcomingCountResult ? (int) $upcomingCountResult->fetch_assoc()['total'] : 0;
$endedCountResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE deleted = FALSE
    AND CONCAT(date, ' ', COALESCE(end_time, time), ':00') < NOW()
");
$endedCount = $endedCountResult ? (int) $endedCountResult->fetch_assoc()['total'] : 0;
$liveCountResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE deleted = FALSE
    AND CONCAT(date, ' ', time, ':00') <= NOW()
    AND CONCAT(date, ' ', COALESCE(end_time, time), ':00') >= NOW()
");
$liveCount = $liveCountResult ? (int) $liveCountResult->fetch_assoc()['total'] : 0;
$progressTotal = max(1, $attendedCount + $pendingCount + $endedCount);
$attendedAngle = round(($attendedCount / $progressTotal) * 360, 2);
$pendingAngle = round(($pendingCount / $progressTotal) * 360, 2);
$endedAngle = min(360, round(($endedCount / $progressTotal) * 360, 2));
$progressChart = 'conic-gradient(#2563ff 0deg ' . $attendedAngle . 'deg, #22c55e ' . $attendedAngle . 'deg ' . ($attendedAngle + $pendingAngle) . 'deg, #f59e0b ' . ($attendedAngle + $pendingAngle) . 'deg ' . ($attendedAngle + $pendingAngle + $endedAngle) . 'deg, #e2e8f0 ' . ($attendedAngle + $pendingAngle + $endedAngle) . 'deg 360deg)';
$activityMax = max(1, $joinedCount, $attendedCount, $pendingCount, $upcomingCount);

$where = [];
$where[] = "1=1";

if ($search !== "") {
    $where[] = "(e.name LIKE '%$safeSearch%' OR e.access_code LIKE '%$safeSearch%' OR e.venue_name LIKE '%$safeSearch%' OR e.venue_location LIKE '%$safeSearch%')";
}

if ($filter === "joined") {
    $where[] = "p.id IS NOT NULL";
} elseif ($filter === "not_joined") {
    $where[] = "p.id IS NULL";
} elseif ($filter === "upcoming") {
    $where[] = "CONCAT(e.date, ' ', e.time, ':00') >= NOW()";
}

$where[] = "e.deleted = FALSE";

$events = $conn->query("
    SELECT e.*,
           p.id AS participant_id,
           a.id AS attendance_id,
           u.name AS owner_name
    FROM events e
    LEFT JOIN participants p ON p.event_id = e.id AND p.user_id = $user_id
    LEFT JOIN attendance a ON a.event_id = e.id AND a.user_id = $user_id
    LEFT JOIN users u ON u.id = e.created_by
    WHERE " . implode(" AND ", $where) . "
    ORDER BY e.date DESC, e.time DESC
");

function dashboardStatusBadge($row)
{
    if (eventLifecycleStatus($row) === 'ended') {
        return ['Ended', 'ended'];
    }

    if (!empty($row['attendance_id'])) {
        return ['Joined', 'joined'];
    }

    if (!empty($row['participant_id'])) {
        return ['Pending', 'pending'];
    }

    return ['Available', 'available'];
}

$firstCodeResult = $conn->query("
    SELECT access_code
    FROM events
    WHERE created_by = $user_id
    AND access_code IS NOT NULL
    AND access_code <> ''
    ORDER BY id DESC
    LIMIT 1
");
$latestAccessCode = $firstCodeResult && $firstCodeResult->num_rows > 0 ? $firstCodeResult->fetch_assoc()['access_code'] : '';

$pageCss = <<<'CSS'
<style>
.stats-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
    margin-bottom:20px;
}

.mobile-hello{
    display:none;
    margin-bottom:16px;
}

.mobile-hello h1{
    margin:0;
    font-size:28px;
    line-height:1.05;
    color:#0f172a;
}

.mobile-hello p{
    margin:6px 0 0;
    color:#64748b;
    font-size:14px;
}

.stat-card{
    background:rgba(255,255,255,0.92);
    border:1px solid #e7ecf5;
    border-radius:18px;
    padding:18px 20px;
    display:flex;
    align-items:flex-start;
    gap:14px;
    box-shadow:0 10px 24px rgba(42, 15, 15, 0.05);
}

.stat-icon{
    width:44px;
    height:44px;
    border-radius:14px;
    display:grid;
    place-items:center;
    flex-shrink:0;
}

.stat-icon svg{
    width:20px;
    height:20px;
    stroke:currentColor;
    fill:none;
    stroke-width:1.9;
    stroke-linecap:round;
    stroke-linejoin:round;
}

.stat-card h3{
    margin:0;
    font-size:14px;
    color:#334155;
}

.stat-card strong{
    display:block;
    margin-top:4px;
    font-size:38px;
    line-height:1;
    color:#0f172a;
}

.stat-card span{
    display:block;
    margin-top:6px;
    font-size:13px;
    font-weight:700;
}

.lavender{background:#ecebff;color:#5448f6;}
.mint{background:#dcfce7;color:#16a34a;}
.amber{background:#fff3d6;color:#f59e0b;}
.up-note{color:#16a34a;}
.flat-note{color:#64748b;}

.board{
    background:rgba(255,255,255,0.92);
    border:1px solid #e7ecf5;
    border-radius:22px;
    padding:18px;
    box-shadow:0 12px 30px rgba(42, 15, 15, 0.06);
}

.visuals-grid{
    display:grid;
    grid-template-columns:1.05fr 1.45fr;
    gap:14px;
    margin-bottom:20px;
}

.viz-card{
    background:rgba(255,255,255,0.92);
    border:1px solid #e7ecf5;
    border-radius:22px;
    padding:18px;
    box-shadow:0 12px 30px rgba(42, 15, 15, 0.06);
}

.viz-card h3{
    margin:0;
    font-size:18px;
    color:#0f172a;
}

.viz-card p{
    margin:6px 0 0;
    color:#64748b;
    font-size:13px;
}

.progress-wrap{
    display:flex;
    align-items:center;
    gap:18px;
    margin-top:18px;
}

.progress-donut{
    width:164px;
    height:164px;
    border-radius:50%;
    background:var(--progress-chart);
    position:relative;
    flex-shrink:0;
}

.progress-donut::after{
    content:"";
    position:absolute;
    inset:20px;
    border-radius:50%;
    background:#fff;
    box-shadow:inset 0 0 0 1px #edf2f7;
}

.progress-center{
    position:absolute;
    inset:0;
    display:grid;
    place-items:center;
    text-align:center;
    z-index:1;
}

.progress-center strong{
    display:block;
    font-size:30px;
    line-height:1;
    color:#0f172a;
}

.progress-center span{
    display:block;
    margin-top:6px;
    font-size:12px;
    font-weight:700;
    color:#64748b;
}

.legend{
    display:grid;
    gap:10px;
    flex:1;
}

.legend-item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    font-size:13px;
    font-weight:700;
    color:#334155;
}

.legend-label{
    display:flex;
    align-items:center;
    gap:8px;
}

.legend-dot{
    width:10px;
    height:10px;
    border-radius:50%;
    flex-shrink:0;
}

.dot-blue{background:#2563ff;}
.dot-green{background:#22c55e;}
.dot-amber{background:#f59e0b;}

.bars{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    align-items:end;
    min-height:220px;
}

.bar-card{
    height:100%;
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
    gap:10px;
}

.bar-track{
    height:180px;
    border-radius:18px;
    background:linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
    border:1px solid #e5edf7;
    padding:10px;
    display:flex;
    align-items:flex-end;
}

.bar-fill{
    width:100%;
    border-radius:14px 14px 10px 10px;
    min-height:20px;
    box-shadow:0 12px 20px rgba(235, 37, 37, 0.16);
}

.bar-label{
    font-size:12px;
    font-weight:800;
    color:#64748b;
    text-align:center;
}

.bar-value{
    font-size:18px;
    font-weight:800;
    color:#0f172a;
    text-align:center;
}

.board-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    margin-bottom:16px;
}

.board-head h2{
    margin:0;
    font-size:32px;
    line-height:1.05;
}

.board-head p{
    margin:8px 0 0;
    color:#64748b;
    font-size:14px;
}

.board-tools{
    display:flex;
    align-items:center;
    gap:10px;
}

.pill-select,
.tool-button{
    height:40px;
    border-radius:12px;
    border:1px solid #dbe4f0;
    background:#fff;
    color:#334155;
    padding:0 14px;
    font-weight:700;
}

.search-form{
    margin-bottom:14px;
}

.search-box{
    position:relative;
}

.search-box svg{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    width:18px;
    height:18px;
    stroke:#94a3b8;
    fill:none;
    stroke-width:1.9;
    stroke-linecap:round;
    stroke-linejoin:round;
}

.search-box input{
    width:100%;
    height:46px;
    border:1px solid #dde6f1;
    border-radius:14px;
    padding:0 16px 0 44px;
    font-size:14px;
    background:#fff;
    color:#0f172a;
}

.filters{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:18px;
}

.filter-chip{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    height:34px;
    padding:0 14px;
    border-radius:11px;
    border:1px solid #e2e8f0;
    background:#f8fafc;
    color:#475569;
    font-size:13px;
    font-weight:700;
}

.filter-chip.active{
    background:#5b4df7;
    border-color:#5b4df7;
    color:#fff;
}

.cards{
    display:grid;
    gap:14px;
}

.event-card{
    display:grid;
    grid-template-columns:96px 1fr;
    gap:14px;
    background:#fff;
    border:1px solid #e7ecf5;
    border-radius:18px;
    padding:12px;
    align-items:start;
}

.event-card img{
    width:96px;
    height:96px;
    object-fit:cover;
    border-radius:12px;
    background:#dbeafe;
}

.event-main{
    min-width:0;
}

.event-top{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
}

.event-title{
    margin:0;
    font-size:22px;
    line-height:1.12;
    color:#0f172a;
}

.event-desc{
    margin:8px 0 0;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

.tag{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}

.tag.joined{background:#dcfce7;color:#16a34a;}
.tag.pending{background:#fff3d6;color:#f59e0b;}
.tag.available{background:#e0e7ff;color:#5b4df7;}
.tag.ended{background:#f1f5f9;color:#475569;}

.event-meta{
    margin-top:14px;
    display:flex;
    flex-wrap:wrap;
    gap:18px;
    color:#475569;
    font-size:13px;
    font-weight:700;
}

.event-meta span{
    display:inline-flex;
    align-items:center;
    gap:7px;
}

.event-meta svg{
    width:16px;
    height:16px;
    stroke:currentColor;
    fill:none;
    stroke-width:1.9;
    stroke-linecap:round;
    stroke-linejoin:round;
}

.event-footer{
    margin-top:16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
}

.event-owner{
    color:#64748b;
    font-size:13px;
}

.event-owner strong{
    color:#334155;
}

.action-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:140px;
    height:42px;
    border-radius:12px;
    padding:0 18px;
    background:linear-gradient(180deg, #3b0808 0%, #301b1b 100%);
    color:#fff;
    font-weight:800;
}

.action-link.secondary{
    background:#fff;
    border:1px solid #dbe4f0;
    color:#5b4df7;
}

.empty{
    padding:44px 18px;
    text-align:center;
    color:#64748b;
    background:#fff;
    border:1px solid #e7ecf5;
    border-radius:18px;
}

@media (max-width: 980px){
    .stats-grid{
        grid-template-columns:1fr;
    }

    .visuals-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 760px){
    .mobile-hello{
        display:block;
    }

    .stats-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:10px;
        margin-bottom:14px;
    }

    .stat-card{
        padding:14px;
        gap:10px;
        border-radius:16px;
    }

    .stat-icon{
        width:38px;
        height:38px;
        border-radius:12px;
    }

    .stat-card h3{
        font-size:12px;
    }

    .stat-card strong{
        font-size:30px;
    }

    .stat-card span{
        font-size:11px;
    }

    .board{
        padding:14px;
        border-radius:18px;
    }

    .visuals-grid{
        display:none;
    }

    .board-head,
    .event-top,
    .event-footer{
        flex-direction:column;
        align-items:flex-start;
    }

    .board-head{
        margin-bottom:12px;
    }

    .board-head h2{
        font-size:24px;
    }

    .board-head p{
        font-size:13px;
    }

    .filters{
        gap:8px;
        overflow:auto;
        flex-wrap:nowrap;
        padding-bottom:2px;
    }

    .filter-chip{
        flex:0 0 auto;
    }

    .event-card{
        grid-template-columns:88px minmax(0, 1fr);
        gap:12px;
        padding:10px;
        border-radius:16px;
        align-items:start;
    }

    .event-card img{
        width:88px;
        height:88px;
        border-radius:12px;
    }

    .event-main{
        display:grid;
        gap:10px;
    }

    .event-top{
        display:block;
    }

    .event-title{
        font-size:18px;
        line-height:1.2;
        word-break:break-word;
    }

    .event-desc{
        margin-top:6px;
        font-size:13px;
        line-height:1.45;
        word-break:break-word;
    }

    .tag{
        display:inline-flex;
        margin-top:8px;
        max-width:100%;
    }

    .event-meta{
        margin-top:0;
        display:grid;
        grid-template-columns:1fr;
        gap:8px;
        font-size:12px;
    }

    .event-meta span{
        align-items:flex-start;
        line-height:1.35;
    }

    .event-footer{
        margin-top:0;
        gap:10px;
    }

    .event-owner{
        font-size:12px;
        line-height:1.4;
    }

    .action-link,
    .action-link.secondary{
        width:auto;
        min-width:0;
        height:40px;
        padding:0 14px;
        font-size:13px;
    }

    .mobile-code{
        display:block;
        background:rgba(255,255,255,0.92);
        border:1px solid #e7ecf5;
        border-radius:18px;
        padding:14px 16px;
        margin-bottom:14px;
        box-shadow:0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .mobile-code small{
        display:block;
        color:#64748b;
        font-weight:700;
        margin-bottom:8px;
    }

    .mobile-code strong{
        font-size:26px;
        letter-spacing:0.03em;
        color:#2563ff;
    }
}

@media (max-width: 440px){
    .event-card{
        grid-template-columns:76px minmax(0, 1fr);
        gap:10px;
        padding:9px;
    }

    .event-card img{
        width:76px;
        height:76px;
        border-radius:10px;
    }

    .event-title{
        font-size:16px;
    }

    .event-desc,
    .event-owner,
    .event-meta{
        font-size:11px;
    }

    .tag{
        padding:5px 10px;
        font-size:11px;
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Dashboard",
    "active" => "dashboard",
    "page_title" => "Dashboard",
    "page_subtitle" => "Track your joined events, attendance, and available events in one place.",
    "search_placeholder" => "Search events...",
    "show_page_head" => false,
    "extra_head" => $pageCss,
]);
?>

<section class="mobile-hello">
    <h1>Hello, <?php echo h($user_name); ?> 👋</h1>
    <p>Here&apos;s what&apos;s happening.</p>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <div class="stat-icon lavender"><?php echo appIcon("events"); ?></div>
        <div>
            <h3>Joined Events</h3>
            <strong><?php echo $joinedCount; ?></strong>
            <span class="up-note">↑ Your registrations</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-icon mint"><?php echo appIcon("attendance"); ?></div>
        <div>
            <h3>Attended</h3>
            <strong><?php echo $attendedCount; ?></strong>
            <span class="up-note">↑ Checked in</span>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-icon amber"><?php echo appIcon("codes"); ?></div>
        <div>
            <h3>Pending</h3>
            <strong><?php echo $pendingCount; ?></strong>
            <span class="flat-note">No attendance yet</span>
        </div>
    </article>
</section>


<section class="board">
    <div class="board-head">
        <div>
            <h2>Available events</h2>
            <p>Discover and join events using a secure code or self registration.</p>
        </div>

        <div class="board-tools">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="search" value="<?php echo h($search); ?>">
                <select name="filter" class="pill-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                    <option value="joined" <?php echo $filter === 'joined' ? 'selected' : ''; ?>>Joined</option>
                    <option value="not_joined" <?php echo $filter === 'not_joined' ? 'selected' : ''; ?>>Not Joined</option>
                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                </select>
            </form>
        </div>
    </div>

    <form class="search-form" method="GET">
        <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
        <div class="search-box">
            <?php echo appIcon("search"); ?>
            <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Search by title or code...">
        </div>
    </form>

    <div class="filters">
        <a href="?filter=all&search=<?php echo urlencode($search); ?>" class="filter-chip <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?filter=joined&search=<?php echo urlencode($search); ?>" class="filter-chip <?php echo $filter === 'joined' ? 'active' : ''; ?>">Joined</a>
        <a href="?filter=not_joined&search=<?php echo urlencode($search); ?>" class="filter-chip <?php echo $filter === 'not_joined' ? 'active' : ''; ?>">Not Joined</a>
        <a href="?filter=upcoming&search=<?php echo urlencode($search); ?>" class="filter-chip <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
    </div>

    <div class="cards">
        <?php if ($events && $events->num_rows > 0): ?>
            <?php while ($row = $events->fetch_assoc()): ?>
                <?php
                [$badgeText, $badgeClass] = dashboardStatusBadge($row);
                $actionClass = !empty($row['participant_id']) ? "action-link secondary" : "action-link";
                $actionText = !empty($row['participant_id']) ? "Details" : "View Event";
                ?>
                <article class="event-card">
                    <img src="<?php echo h(eventImagePath($row['image'])); ?>" alt="<?php echo h($row['name']); ?>">

                    <div class="event-main">
                        <div class="event-top">
                            <div>
                                <h3 class="event-title"><?php echo h($row['name']); ?></h3>
                                <p class="event-desc"><?php echo h($row['description'] ?: 'Product demos, keynotes, and workshop tracks.'); ?></p>
                            </div>
                            <span class="tag <?php echo $badgeClass; ?>"><?php echo h($badgeText); ?></span>
                        </div>

                        <div class="event-meta">
                            <span><?php echo appIcon("events"); ?> <?php echo h(formatEventDate($row['date'])); ?> <?php echo h(formatEventTime($row['time'])); ?></span>
                            <span><?php echo appIcon("attendance"); ?> <?php echo h($row['owner_name'] ?: 'Event Owner'); ?></span>
                        </div>

                        <div class="event-footer">
                            <div class="event-owner">
                                Organizer:
                                <strong><?php echo h($row['owner_name'] ?: 'Event Owner'); ?></strong>
                            </div>
                            <a href="event.php?id=<?php echo (int) $row['id']; ?>" class="<?php echo $actionClass; ?>"><?php echo $actionText; ?></a>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">No events found. Try another filter or search.</div>
        <?php endif; ?>
    </div>
</section>

<?php renderAppShellEnd("dashboard"); ?>
