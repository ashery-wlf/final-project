<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = $conn->query("SELECT * FROM events WHERE id=$event_id AND deleted = FALSE LIMIT 1")->fetch_assoc();

if (!$event) {
    die("Event not found.");
}

if ((int) $event['created_by'] !== $user_id) {
    die("Only the event admin can display this QR code.");
}

if (isset($_GET['token'])) {
    $window = attendanceWindowState($event);
    header("Content-Type: application/json");
    echo json_encode([
        "payload" => $window === "open" ? buildQrPayload($event_id) : "",
        "window" => $window,
        "generated_at" => date("H:i:s"),
    ]);
    exit();
}
?>
<?php
$pageCss = <<<'CSS'
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
/* Scope all styles to content area only */
.app-page .shell{
    max-width:560px;
    margin:0 auto;
    background:linear-gradient(180deg, #1e3a8a 0%, #0f172a 100%);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:20px;
    padding:18px;
    text-align:center;
    box-shadow:0 24px 60px rgba(0,0,0,0.32);
    color:#fff;
}

.app-page .shell h1{
    margin:0 0 8px;
    font-size:32px;
}

.app-page .shell p{
    margin:0;
    color:rgba(255,255,255,0.78);
}

.app-page .shell #qrcode{
    width:240px;
    height:240px;
    margin:16px auto 10px;
    display:grid;
    place-items:center;
    background:#fff;
    padding:12px;
    border-radius:20px;
}

.app-page .shell #qrcode.hidden{
    display:none;
}

.app-page .shell .qr-closed{
    width:240px;
    min-height:240px;
    margin:16px auto 10px;
    display:none;
    place-items:center;
    background:rgba(255,255,255,0.08);
    border:1px dashed rgba(255,255,255,0.24);
    border-radius:20px;
    padding:18px;
    color:rgba(255,255,255,0.82);
    font-weight:700;
    line-height:1.6;
}

.app-page .shell .qr-closed.show{
    display:grid;
}

.app-page .shell .status{
    margin-top:12px;
    display:inline-flex;
    padding:8px 12px;
    border-radius:999px;
    background:rgba(255,255,255,0.12);
    font-weight:700;
}

.app-page .shell .meta{
    margin-top:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    flex-wrap:wrap;
    color:rgba(255,255,255,0.84);
    font-size:14px;
}

.app-page .shell a{
    display:inline-block;
    margin-top:14px;
    color:#bfdbfe;
    text-decoration:none;
    font-weight:700;
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Live Attendance QR",
    "active" => "qr",
    "page_title" => "Live Attendance QR",
    "page_subtitle" => "Display this QR on the admin screen. It refreshes automatically for safer attendance scanning.",
    "search_placeholder" => "Search events...",
    "extra_head" => $pageCss,
]);
?>
<div class="shell">
    <h1><?php echo h($event['name']); ?></h1>
    <p>Display this live QR on the admin screen. It refreshes automatically for safer attendance scanning.</p>

    <div id="qrcode"></div>
    <div class="qr-closed" id="qrClosed">Attendance time is over. Live QR is no longer available.</div>
    <div class="status" id="status">Preparing live QR...</div>
    <div class="meta">
        <span>Attendance window: <?php echo h(formatEventTime($event['attendance_start'] ?: $event['time'])); ?> - <?php echo h(formatEventTime($event['attendance_end'] ?: ($event['end_time'] ?: $event['time']))); ?></span>
        <span id="generatedAt"></span>
    </div>

    <a href="event.php?id=<?php echo $event_id; ?>">Back to event</a>
</div>

<script>
let qr;

function renderQr(payload) {
    const box = document.getElementById("qrcode");
    const closedBox = document.getElementById("qrClosed");
    box.innerHTML = "";
    box.classList.remove("hidden");
    closedBox.classList.remove("show");
    qr = new QRCode(box, {
        text: payload,
        width: 208,
        height: 208
    });
}

function hideQr(message) {
    const box = document.getElementById("qrcode");
    const closedBox = document.getElementById("qrClosed");
    box.innerHTML = "";
    box.classList.add("hidden");
    closedBox.classList.add("show");
    closedBox.textContent = message;
}

function refreshQr() {
    fetch("generate-qr.php?id=<?php echo $event_id; ?>&token=1")
        .then(response => response.json())
        .then(data => {
            if (data.window === "open" && data.payload) {
                renderQr(data.payload);
                document.getElementById("status").textContent = "Attendance is open. QR is live.";
            } else if (data.window === "before") {
                hideQr("Attendance has not started yet. QR will appear automatically when scan time begins.");
                document.getElementById("status").textContent = "Attendance not open yet.";
            } else {
                hideQr("Attendance time is over. Live QR is no longer available.");
                document.getElementById("status").textContent = "Attendance window has closed.";
            }
            document.getElementById("generatedAt").textContent = "Updated at " + data.generated_at;
        });
}

refreshQr();
setInterval(refreshQr, 15000);
</script>
<?php renderAppShellEnd("qr"); ?>
