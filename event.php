<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = "";
$error = "";

if ($event_id <= 0) {
    die("Event not found.");
}

$eventQuery = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1");
$event = $eventQuery ? $eventQuery->fetch_assoc() : null;

if (!$event) {
    die("Event not found.");
}

$isAdmin = (int) $event['created_by'] === $user_id;

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    
    // Use current event ID if delete parameter matches current event
    if ($deleteId == $event_id) {
        $eventId = $event_id;
        
        // Check if user is the owner
        if ($isAdmin) {
            // Store event data in deleted_events table
            $eventData = json_encode($event);
            
            $stmt = $conn->prepare("
                INSERT INTO deleted_events (original_event_id, event_name, deleted_by, reason, attendance_data_preserved, event_data) 
                VALUES (?, ?, ?, 'Event deleted by organizer', TRUE, ?)
            ");
            $stmt->bind_param("isis", $eventId, $event['name'], $user_id, $eventData);
            
            if ($stmt->execute()) {
                // Now soft delete the original event
                $result = $conn->query("UPDATE events SET deleted = TRUE, deleted_at = NOW() WHERE id = $eventId");
                if ($result) {
                    header("Location: events.php");
                    exit();
                } else {
                    die("Error deleting event: " . $conn->error);
                }
            } else {
                die("Error storing deleted event: " . $conn->error);
            }
        } else {
            die("You don't have permission to delete this event.");
        }
    } else {
        die("Invalid delete request.");
    }
}

if ($isAdmin) {
    registerUserToEvent($conn, $user_id, $event_id);
}

if (isset($_POST['save_settings']) && $isAdmin) {
    $name = trim($_POST['name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $date = $_POST['date'] ?? "";
    $time = $_POST['time'] ?? "";
    $end_time = $_POST['end_time'] ?? "";
    $attendance_start = $_POST['attendance_start'] ?? "";
    $attendance_end = $_POST['attendance_end'] ?? "";
    $registration_mode = $_POST['registration_mode'] ?? "self";
    $venue_name = trim($_POST['venue_name'] ?? "");
    $venue_location = trim($_POST['venue_location'] ?? "");
    $location_lat = trim($_POST['location_lat'] ?? "");
    $location_lng = trim($_POST['location_lng'] ?? "");
    $target_audience = trim($_POST['target_audience'] ?? "");
    $access_code = trim($_POST['access_code'] ?? "");
    $registrant_list = trim($_POST['registrant_list'] ?? "");

    if ($registration_mode === "code" && $access_code === "") {
        $access_code = generateAccessCode();
    }

    $locationLatValue = is_numeric($location_lat) ? (float) $location_lat : null;
    $locationLngValue = is_numeric($location_lng) ? (float) $location_lng : null;
    $registrants = parseRegistrantLines($registrant_list);
    $invitedEmailsText = implode("\n", array_map(function ($item) {
        return $item['email'];
    }, $registrants));

    $stmt = $conn->prepare("
        UPDATE events
        SET name=?, description=?, date=?, time=?, end_time=?, attendance_start=?, attendance_end=?,
            venue_name=?, venue_location=?, location_lat=?, location_lng=?, target_audience=?, registration_mode=?, access_code=?, invited_emails=?
        WHERE id=?
    ");
    $stmt->bind_param(
        "ssssssssssddssssi",
        $name,
        $description,
        $date,
        $time,
        $end_time,
        $attendance_start,
        $attendance_end,
        $venue_name,
        $venue_location,
        $locationLatValue,
        $locationLngValue,
        $target_audience,
        $registration_mode,
        $access_code,
        $invitedEmailsText,
        $event_id
    );

    if ($stmt->execute()) {
        if ($registration_mode === "code" && !empty($registrants)) {
            foreach ($registrants as $registrant) {
                upsertPrivateRegistrant($conn, $event_id, $registrant);
                sendEventAccessCodeEmail($registrant['name'], $registrant['email'], $event, $access_code);
            }
        }
        $message = "Event settings updated.";
        $eventQuery = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1");
        $event = $eventQuery ? $eventQuery->fetch_assoc() : $event;
    } else {
        $error = "Settings could not be saved.";
    }
}

if (isset($_POST['join_self'])) {
    if (registrationExists($conn, $user_id, $event_id)) {
        $message = "You are already registered for this event.";
    } elseif (eventRegistrationMode($event) !== 'self') {
        $error = "This event needs an access code.";
    } else {
        registerUserToEvent($conn, $user_id, $event_id);
        
        // Get user details for confirmation message
        $userResult = $conn->query("SELECT name, email, phone FROM users WHERE id=$user_id LIMIT 1");
        $user = $userResult && $userResult->num_rows > 0 ? $userResult->fetch_assoc() : ['name' => '', 'email' => '', 'phone' => ''];
        
        $message = "Registration completed! Welcome to the event.";
        $registrationDetails = "
            <div class='registration-confirm' style='background:#f0f9ff; border:1px solid #0284c7; border-radius:12px; padding:16px; margin:16px 0;'>
                <h4 style='margin:0 0 12px 0; color:#0284c7;'>🎉 Registration Confirmed</h4>
                <div style='display:grid; gap:8px; font-size:14px;'>
                    <div><strong>Name:</strong> " . h($user['name']) . "</div>
                    <div><strong>Email:</strong> " . h($user['email']) . "</div>
                    <div><strong>Phone:</strong> " . h($user['phone']) . "</div>
                    <div><strong>Event:</strong> " . h($event['name']) . "</div>
                    <div><strong>Date:</strong> " . h(formatEventDate($event['date'])) . "</div>
                    <div><strong>Time:</strong> " . h(formatEventTime($event['time'])) . "</div>
                </div>
                <p style='margin:12px 0 0 0; color:#64748b; font-size:13px;'>You'll be able to scan for attendance when the window opens.</p>
            </div>
        ";
    }
}

if (isset($_POST['join_code'])) {
    if (registrationExists($conn, $user_id, $event_id)) {
        $message = "You are already registered for this event.";
    } elseif (trim($_POST['access_code'] ?? '') !== (string) ($event['access_code'] ?? '')) {
        $error = "Wrong access code.";
    } else {
        registerUserToEvent($conn, $user_id, $event_id);
        $message = "Access granted and registration completed.";
    }
}

$isRegistered = registrationExists($conn, $user_id, $event_id);
$registrationMode = eventRegistrationMode($event);
$lifecycle = eventLifecycleStatus($event);
$lifecycleLabel = eventLifecycleLabel($event);
$windowState = attendanceWindowState($event);
$registeredCountResult = $conn->query("SELECT COUNT(*) AS total FROM participants WHERE event_id=$event_id");
$registeredCount = $registeredCountResult ? (int) $registeredCountResult->fetch_assoc()['total'] : 0;
$attendedCountResult = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE event_id=$event_id");
$attendedCount = $attendedCountResult ? (int) $attendedCountResult->fetch_assoc()['total'] : 0;
$mapEmbedUrl = eventMapEmbedUrl($event);
$directionsUrl = eventDirectionsUrl($event);
$registrantRowsResult = $conn->query("SELECT participant_name, participant_email, participant_phone, invite_status FROM participants WHERE event_id=$event_id ORDER BY invited_at DESC, id DESC");
$registrantRows = [];
if ($registrantRowsResult) {
    while ($registrant = $registrantRowsResult->fetch_assoc()) {
        $registrantRows[] = $registrant;
    }
}
$registrantListValue = implode("\n", array_map(function ($item) {
    return trim(($item['participant_name'] ?? '') . ', ' . ($item['participant_email'] ?? '') . ', ' . ($item['participant_phone'] ?? ''));
}, $registrantRows));

$attendanceRows = $conn->query("
    SELECT u.name, u.email, a.time
    FROM attendance a
    JOIN users u ON u.id = a.user_id
    WHERE a.event_id = $event_id
    ORDER BY a.time DESC
");
?>
<?php
$pageCss = <<<'CSS'
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>
.nav{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
    margin-bottom:16px;
}

.nav a{
    text-decoration:none;
    font-weight:800;
}

.back{
    color:#1d4ed8;
}

.hero{
    display:grid;
    grid-template-columns:280px 1fr;
    gap:22px;
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 20px 40px rgba(15, 23, 42, 0.08);
}

.hero img{
    width:100%;
    height:100%;
    min-height:260px;
    object-fit:cover;
}

.hero-body{
    padding:24px;
}

.badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.badge{
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}

.admin{background:#dbeafe;color:#1d4ed8;}
.reg-open{background:#ecfdf5;color:#15803d;}
.reg-code{background:#fff7ed;color:#c2410c;}
.live{background:#fee2e2;color:#b91c1c;}
.upcoming{background:#eff6ff;color:#2563eb;}
.ended{background:#f1f5f9;color:#475569;}

.hero-body h1{
    margin:14px 0 8px;
    font-size:38px;
    line-height:1.05;
}

.hero-body p{
    margin:0;
    color:#64748b;
    line-height:1.7;
}

.grid{
    margin-top:22px;
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
}

.stat{
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:18px;
    padding:18px;
}

.stat small{
    color:#64748b;
    font-weight:700;
    display:block;
    margin-bottom:8px;
}

.stat strong{
    font-size:22px;
}

.section{
    margin-top:22px;
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:22px;
    padding:22px;
    box-shadow:0 16px 34px rgba(15, 23, 42, 0.06);
}

.section h2{
    margin:0 0 8px;
    font-size:24px;
}

.section p{
    margin:0;
    color:#64748b;
}

.message, .error{
    margin-top:16px;
    padding:14px 16px;
    border-radius:14px;
    font-weight:700;
}

.message{background:#ecfdf5;color:#166534;}
.error{background:#fef2f2;color:#b91c1c;}

.form-grid{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.field.full{
    grid-column:1 / -1;
}

label{
    font-size:14px;
    font-weight:700;
    color:#334155;
}

input, textarea, select{
    width:100%;
    border:1px solid #dce5f1;
    border-radius:14px;
    padding:13px 14px;
    font-size:14px;
    background:#f8fbff;
}

textarea{
    min-height:110px;
    resize:vertical;
}

.actions{
    margin-top:18px;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

button, .button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:none;
    border-radius:14px;
    padding:13px 18px;
    font-weight:800;
    cursor:pointer;
    text-decoration:none;
}

.primary{
    background:linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    color:#fff;
}

.deleted{
    background:#6b7280;
    color:#fff;
}

.section-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
}

.header-buttons{
    display:flex;
    gap:8px;
    align-items:center;
}

.edit-button{
    background:#2563ff;
    color:#fff;
    border:none;
    border-radius:8px;
    padding:8px 16px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:background 0.2s;
}

.edit-button:hover{
    background:#1d4ed8;
}

.delete-header{
    background:#dc2626;
    color:#fff;
    text-decoration:none;
    border-radius:8px;
    padding:8px 16px;
    font-size:14px;
    font-weight:600;
    transition:background 0.2s;
}

.delete-header:hover{
    background:#b91c1c;
}

.dark{
    background:#0f172a;
    color:#fff;
}

.danger{
    background:#dc2626;
    color:#fff;
}

.danger:hover{
    background:#b91c1c;
}

.soft{
    background:#e2e8f0;
    color:#334155;
}

.mini-grid{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
}

.mini{
    background:#f8fbff;
    border:1px solid #e5edf7;
    border-radius:16px;
    padding:16px;
}

.mini strong{
    display:block;
    margin-top:6px;
}

.map-panel{
    margin-top:20px;
    border:1px solid #dce5f1;
    border-radius:20px;
    overflow:hidden;
    background:#fff;
}

.map-panel iframe{
    width:100%;
    height:320px;
    border:0;
    display:block;
}

.map-panel-foot{
    padding:14px 16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    border-top:1px solid #e7edf7;
}

.map-panel-foot span{
    color:#64748b;
    font-size:13px;
    font-weight:700;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:16px;
}

th, td{
    padding:14px 10px;
    text-align:left;
    border-bottom:1px solid #eef2f7;
}

th{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.04em;
    color:#64748b;
}

.location-tools,
.invite-tools{
    grid-column:1 / -1;
    border:1px solid #dce5f1;
    border-radius:18px;
    padding:16px;
    background:#f8fbff;
}

.location-toolbar{
    margin-top:12px;
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    gap:10px;
    align-items:center;
}

.ghost{
    background:#e0e7ff;
    color:#3730a3;
}

.inline-map{
    margin-top:14px;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #dbe3ef;
    min-height:118px;
    background:#fff;
}

.inline-map #adminEventMap{
    width:100%;
    height:118px;
}

.leaflet-container{
    font:inherit;
}

.leaflet-control-zoom{
    transform:scale(0.92);
    transform-origin:top left;
}

.registrant-list{
    margin-top:18px;
    display:grid;
    gap:10px;
}

.registrant-item{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    border:1px solid #e5edf7;
    border-radius:16px;
    padding:14px;
    background:#fff;
}

.registrant-item strong{
    display:block;
}

.registrant-item span{
    display:block;
    margin-top:4px;
    color:#64748b;
    font-size:13px;
}

@media (max-width: 920px){
    .hero{
        grid-template-columns:1fr;
    }

    .grid, .mini-grid, .form-grid{
        grid-template-columns:1fr 1fr;
    }
}

@media (max-width: 640px){
    .grid, .mini-grid, .form-grid{
        grid-template-columns:1fr;
    }

    .hero-body h1{
        font-size:30px;
    }

    .location-toolbar{
        grid-template-columns:1fr;
    }

    .ghost{
        width:100%;
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => $event['name'],
    "active" => "events",
    "page_title" => $event['name'],
    "page_subtitle" => "Open the event, manage access, and monitor attendance from one place.",
    "search_placeholder" => "Search events...",
    "show_page_head" => false,
    "extra_head" => $pageCss,
]);
?>
    <div class="nav">
        <a href="events.php" class="back">← Back to events</a>
        <a href="dashboard.php" class="back">Dashboard</a>
    </div>

    <section class="hero">
        <img src="<?php echo h(eventImagePath($event['image'])); ?>" alt="<?php echo h($event['name']); ?>">

        <div class="hero-body">
            <div class="badges">
                <?php if ($isAdmin): ?><span class="badge admin">Admin of this event</span><?php endif; ?>
                <span class="badge <?php echo $registrationMode === 'code' ? 'reg-code' : 'reg-open'; ?>">
                    <?php echo $registrationMode === 'code' ? 'Access code required' : 'Self registration enabled'; ?>
                </span>
                <span class="badge <?php echo h($lifecycle); ?>"><?php echo h($lifecycleLabel); ?></span>
            </div>

            <h1><?php echo h($event['name']); ?></h1>
            <p><?php echo h($event['description'] ?: 'No description added yet.'); ?></p>

            <div class="mini-grid">
                <div class="mini">
                    <small>Event Starts</small>
                    <strong><?php echo h(formatEventDate($event['date'])); ?> • <?php echo h(formatEventTime($event['time'])); ?></strong>
                </div>
                <div class="mini">
                    <small>Attendance Window</small>
                    <strong><?php echo h(formatEventTime($event['attendance_start'] ?: $event['time'])); ?> - <?php echo h(formatEventTime($event['attendance_end'] ?: ($event['end_time'] ?: $event['time']))); ?></strong>
                </div>
                <div class="mini">
                    <small>Shared Location</small>
                    <strong><?php echo h($event['venue_location'] ?: 'Not shared yet'); ?></strong>
                </div>
            </div>

            <?php if ($mapEmbedUrl !== ''): ?>
                <div class="map-panel">
                    <iframe src="<?php echo h($mapEmbedUrl); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <div class="map-panel-foot">
                        <span>Zoom in and inspect the exact event location.</span>
                        <a href="<?php echo h($directionsUrl); ?>" target="_blank" rel="noopener noreferrer" class="dark button">Open Directions</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message !== ""): ?><div class="message"><?php echo h($message); ?></div><?php endif; ?>
            <?php if ($error !== ""): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
            <?php if (isset($registrationDetails)): ?><?php echo $registrationDetails; ?><?php endif; ?>
            <?php if ($lifecycle === 'ended' && $isAdmin): ?>
                <div class="message">This event is currently marked as Ended. If that happened by mistake, update the event time or attendance window below and save again.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid">
        <div class="stat"><small>Registered</small><strong><?php echo $registeredCount; ?></strong></div>
        <div class="stat"><small>Attended</small><strong><?php echo $attendedCount; ?></strong></div>
        <div class="stat"><small>Venue</small><strong><?php echo h($event['venue_name'] ?: 'Not set'); ?></strong></div>
        <div class="stat"><small>Audience</small><strong><?php echo h($event['target_audience'] ?: 'Open to all'); ?></strong></div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="section">
            <div class="section-header">
                <h2>Event Settings</h2>
                <div class="header-buttons">
                    <button type="button" id="toggleSettings" class="edit-button">
                        <span id="toggleText">Edit Settings</span>
                    </button>
                    <?php if (!$event['deleted']): ?>
                        <a href="?id=<?php echo $event_id; ?>&delete=<?php echo $event_id; ?>" class="danger delete-header" onclick="return confirm('Are you sure you want to delete this event? Attendance data will be preserved but the event will be hidden from public.')">Delete Event</a>
                    <?php else: ?>
                        <span class="badge deleted">Event Deleted</span>
                    <?php endif; ?>
                </div>
            </div>
            <p id="settingsDescription">Adjust event time, attendance window, who should attend, and how people access this event.</p>

            <div id="adminSettingsForm" style="display: none;">
                <form method="POST" class="form-grid">
                <div class="field full">
                    <label for="name">Event Name</label>
                    <input id="name" type="text" name="name" required value="<?php echo h($event['name']); ?>">
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo h($event['description']); ?></textarea>
                </div>

                <div class="field">
                    <label for="date">Event Date</label>
                    <input id="date" type="date" name="date" value="<?php echo h($event['date']); ?>">
                </div>

                <div class="field">
                    <label for="time">Start Time</label>
                    <input id="time" type="time" name="time" value="<?php echo h(substr((string) $event['time'], 0, 5)); ?>">
                </div>

                <div class="field">
                    <label for="end_time">End Time</label>
                    <input id="end_time" type="time" name="end_time" value="<?php echo h(substr((string) $event['end_time'], 0, 5)); ?>">
                </div>

                <div class="field">
                    <label for="target_audience">Target Attendees</label>
                    <input id="target_audience" type="text" name="target_audience" value="<?php echo h($event['target_audience']); ?>">
                </div>

                <div class="field">
                    <label for="attendance_start">Attendance Opens</label>
                    <input id="attendance_start" type="time" name="attendance_start" value="<?php echo h(substr((string) $event['attendance_start'], 0, 5)); ?>">
                </div>

                <div class="field">
                    <label for="attendance_end">Attendance Closes</label>
                    <input id="attendance_end" type="time" name="attendance_end" value="<?php echo h(substr((string) $event['attendance_end'], 0, 5)); ?>">
                </div>

                <div class="field">
                    <label for="venue_name">Venue Name</label>
                    <input id="venue_name" type="text" name="venue_name" value="<?php echo h($event['venue_name']); ?>">
                </div>

                <div class="field">
                    <label for="venue_location">Shared Location</label>
                    <input id="venue_location" type="text" name="venue_location" value="<?php echo h($event['venue_location']); ?>">
                </div>

                <div class="location-tools">
                    <label>Live Location Share</label>
                    <p style="margin-top:8px;">Search the exact event place so attendees can zoom in and follow directions without getting lost.</p>
                    <input id="location_lat" type="hidden" name="location_lat" value="<?php echo h($event['location_lat'] ?? ''); ?>">
                    <input id="location_lng" type="hidden" name="location_lng" value="<?php echo h($event['location_lng'] ?? ''); ?>">
                    <div class="location-toolbar">
                        <input id="locationSearch" type="text" placeholder="Search event venue or place name">
                        <div class="actions" style="margin-top:0;">
                            <button type="button" id="findLocation" class="ghost">Find on Map</button>
                        </div>
                    </div>
                    <div id="locationStatus" class="message" style="display:block;margin-top:14px;background:#eff6ff;color:#1d4ed8;">
                        <?php echo (!empty($event['location_lat']) && !empty($event['location_lng'])) ? 'Event location is already set.' : 'No event location selected yet.'; ?>
                    </div>
                    <div class="inline-map">
                        <div id="adminEventMap"></div>
                    </div>
                </div>

                <div class="field">
                    <label for="registration_mode">Access Mode</label>
                    <select id="registration_mode" name="registration_mode">
                        <option value="self" <?php echo $registrationMode === 'self' ? 'selected' : ''; ?>>Self registration</option>
                        <option value="code" <?php echo $registrationMode === 'code' ? 'selected' : ''; ?>>Access code</option>
                    </select>
                </div>

                <div class="field">
                    <label for="access_code">Access Code</label>
                    <input id="access_code" type="text" name="access_code" value="<?php echo h($event['access_code']); ?>">
                </div>

                <div class="invite-tools">
                    <label for="registrant_list">Private Event Registrants</label>
                    <textarea id="registrant_list" name="registrant_list" placeholder="Amina Yusuf, amina@example.com, 0712345678&#10;John Peter, john@example.com, 0755123456"><?php echo h($registrantListValue); ?></textarea>
                    <p style="margin-top:10px;">One person per line: Full Name, Email, Phone Number. The system uses this list to pre-register people and send the access code by email.</p>
                </div>

                <div class="actions">
                    <button type="submit" name="save_settings" class="primary">Save Settings</button>
                    <a href="attendance.php?id=<?php echo $event_id; ?>" class="dark button">View Attendance</a>
                    <?php if ($windowState === 'open'): ?>
                        <a href="generate-qr.php?id=<?php echo $event_id; ?>" class="dark button">Display Live QR</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="section">
        <h2><?php echo $isAdmin ? 'Event Control' : 'Event Access'; ?></h2>
        <p>
            <?php if ($isAdmin): ?>
                You are the admin for this event. You can manage settings, display the live QR, and review attendance progress here.
            <?php elseif ($isRegistered): ?>
                You have already joined this event. When the attendance window opens, the scan button appears here automatically.
            <?php else: ?>
                Register or enter the access code to join this event. Once attendance opens, the scan button becomes available.
            <?php endif; ?>
        </p>

        <div class="actions">
            <?php if (!$isAdmin && !$isRegistered && $registrationMode === 'self'): ?>
                <form method="POST">
                    <button type="submit" name="join_self" class="primary">Register for Event</button>
                </form>
            <?php elseif (!$isAdmin && !$isRegistered && $registrationMode === 'code'): ?>
                <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;margin-top:16px;">
                    <input type="text" name="access_code" placeholder="Enter access code" style="flex:1;min-width:220px;">
                    <button type="submit" name="join_code" class="primary">Access Event</button>
                </form>
            <?php elseif ($isRegistered || $isAdmin): ?>
                <?php if ($windowState === 'before'): ?>
                    <div class="message">You have already joined this event. The scan button will appear here at <?php echo h(formatEventTime($event['attendance_start'] ?: $event['time'])); ?>.</div>
                <?php elseif ($windowState === 'open'): ?>
                    <a href="scan.php?id=<?php echo $event_id; ?>" class="primary button">Scan Attendance</a>
                    <?php if ($isAdmin): ?><a href="generate-qr.php?id=<?php echo $event_id; ?>" class="dark button">Open Live QR</a><?php endif; ?>
                <?php else: ?>
                    <div class="message">Attendance window has closed for this event.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isAdmin || $lifecycle === 'ended'): ?>
        <section class="section">
            <h2>Attendance Progress</h2>
            <p>Once the event is over, this becomes your quick review table for the people who checked in.</p>

            <table>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Time</th>
                </tr>
                <?php if ($attendanceRows && $attendanceRows->num_rows > 0): ?>
                    <?php while ($row = $attendanceRows->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['name']); ?></td>
                            <td><?php echo h($row['email']); ?></td>
                            <td><?php echo h($row['time']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No attendance records yet.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="section">
            <h2>Registered / Invited People</h2>
            <p>These are the people already known for this event before attendance scanning starts.</p>
            <div class="registrant-list">
                <?php if (!empty($registrantRows)): ?>
                    <?php foreach ($registrantRows as $registrant): ?>
                        <div class="registrant-item">
                            <div>
                                <strong><?php echo h($registrant['participant_name'] ?: 'No name'); ?></strong>
                                <span><?php echo h($registrant['participant_email'] ?: 'No email'); ?></span>
                                <span><?php echo h($registrant['participant_phone'] ?: 'No phone'); ?></span>
                            </div>
                            <span class="badge <?php echo ($registrant['invite_status'] ?? '') === 'invited' ? 'upcoming' : 'admin'; ?>">
                                <?php echo h(ucfirst($registrant['invite_status'] ?: 'registered')); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mini">No invited or registered list yet.</div>
                <?php endif; ?>
            </div>
            </div>
        </section>
    <?php endif; ?>
<script>
(function() {
    const button = document.getElementById("findLocation");
    const latInput = document.getElementById("location_lat");
    const lngInput = document.getElementById("location_lng");
    const status = document.getElementById("locationStatus");
    const locationSearch = document.getElementById("locationSearch");
    const accessCodeInput = document.getElementById("access_code");
    const modeSelect = document.getElementById("registration_mode");
    const initialLat = parseFloat(latInput.value || "-6.7924");
    const initialLng = parseFloat(lngInput.value || "37.6601");
    const map = document.getElementById("adminEventMap") ? L.map("adminEventMap").setView([initialLat, initialLng], latInput.value && lngInput.value ? 15 : 6) : null;
    let marker = null;

    if (map) {
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);
    }

    function generateCode() {
        return String(Math.floor(100000 + Math.random() * 900000));
    }

    function setMarker(lat, lng, zoom) {
        if (!map) {
            return;
        }
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            marker.on("dragend", function(event) {
                const pos = event.target.getLatLng();
                latInput.value = Number(pos.lat.toFixed(7));
                lngInput.value = Number(pos.lng.toFixed(7));
                status.textContent = "Event location updated from the map.";
            });
        }
        latInput.value = Number(lat.toFixed(7));
        lngInput.value = Number(lng.toFixed(7));
        if (zoom) {
            map.setView([lat, lng], zoom);
        }
        setTimeout(function() {
            map.invalidateSize();
        }, 50);
    }

    if (modeSelect) {
        modeSelect.addEventListener("change", function() {
            if (modeSelect.value === "code" && accessCodeInput && !accessCodeInput.value.trim()) {
                accessCodeInput.value = generateCode();
            }
        });
    }

    if (map) {
        if (latInput.value && lngInput.value) {
            setMarker(initialLat, initialLng, 15);
        }

        map.on("click", function(event) {
            setMarker(event.latlng.lat, event.latlng.lng);
            status.textContent = "Event location selected directly on the map.";
        });
    }

    if (button) {
        button.addEventListener("click", function() {
            const query = locationSearch ? locationSearch.value.trim() : "";
            if (!query) {
                status.textContent = "Type the event place name first.";
                return;
            }

            status.textContent = "Searching location...";
            fetch("https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=" + encodeURIComponent(query))
            .then(function(response) {
                return response.json();
            })
            .then(function(results) {
                if (!results || !results.length) {
                    status.textContent = "Location not found. Try a more specific venue name.";
                    return;
                }

                const result = results[0];
                const lat = Number(parseFloat(result.lat).toFixed(7));
                const lng = Number(parseFloat(result.lon).toFixed(7));
                setMarker(lat, lng, 15);
                status.textContent = "Event location updated. Save settings to keep it on this event.";
            })
            .catch(function() {
                status.textContent = "Could not search that place right now.";
            });
        });
    }
// Toggle admin settings visibility
    const toggleButton = document.getElementById("toggleSettings");
    const toggleText = document.getElementById("toggleText");
    const settingsForm = document.getElementById("adminSettingsForm");
    const settingsDescription = document.getElementById("settingsDescription");
    
    if (toggleButton && settingsForm) {
        toggleButton.addEventListener("click", function() {
            if (settingsForm.style.display === "none") {
                settingsForm.style.display = "block";
                toggleText.textContent = "Hide Settings";
                settingsDescription.style.display = "none";
            } else {
                settingsForm.style.display = "none";
                toggleText.textContent = "Edit Settings";
                settingsDescription.style.display = "block";
            }
        });
    }
})();
</script>
<?php renderAppShellEnd("events"); ?>
