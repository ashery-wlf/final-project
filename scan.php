<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1")->fetch_assoc();

if (!$event) {
    die("Event not found.");
}

$isAdmin = (int) $event['created_by'] === $user_id;
$isRegistered = registrationExists($conn, $user_id, $event_id) || $isAdmin;
$windowState = attendanceWindowState($event);
?>
<?php
$pageCss = <<<'CSS'
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
.shell{
    background:#fff;
    border:1px solid #dce5f1;
    border-radius:24px;
    padding:24px;
    box-shadow:0 20px 40px rgba(15, 23, 42, 0.08);
}

h1{
    margin:0 0 8px;
    font-size:34px;
}

p{
    margin:0;
    color:#64748b;
}

.notice{
    margin-top:18px;
    padding:14px 16px;
    border-radius:14px;
    font-weight:700;
    background:#eff6ff;
    color:#1d4ed8;
}

.error{
    background:#fef2f2;
    color:#b91c1c;
}

#reader{
    width:min(100%, 420px);
    margin:24px auto 0;
}

.manual-box{
    margin-top:22px;
    border-top:1px solid #e5eaf3;
    padding-top:18px;
}

.manual-box h2{
    margin:0 0 8px;
    font-size:20px;
}

.manual-box p{
    margin:0 0 12px;
}

.manual-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.manual-row input{
    flex:1;
    min-width:220px;
    height:44px;
    border:1px solid #dce5f1;
    border-radius:12px;
    padding:0 14px;
    font-size:14px;
    background:#fff;
}

.manual-row button{
    height:44px;
    border:none;
    border-radius:12px;
    padding:0 18px;
    font-weight:800;
    background:#1d4ed8;
    color:#fff;
    cursor:pointer;
}

.upload-row{
    margin-top:12px;
}

.upload-row input{
    width:100%;
    border:1px solid #dce5f1;
    border-radius:12px;
    padding:10px 12px;
    background:#fff;
}

#result{
    margin-top:18px;
    padding:14px 16px;
    border-radius:14px;
    background:#f8fafc;
    min-height:52px;
}

a{
    display:inline-block;
    margin-top:20px;
    color:#1d4ed8;
    text-decoration:none;
    font-weight:700;
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Scan Attendance",
    "active" => "attendance",
    "page_title" => "Scan Attendance",
    "page_subtitle" => "Open the camera and scan the admin live QR code during the attendance window.",
    "search_placeholder" => "Search events...",
    "extra_head" => $pageCss,
]);
?>
<div class="shell">
    <h1>Scan Attendance</h1>
    <p><?php echo h($event['name']); ?> • <?php echo h(formatEventDate($event['date'])); ?> • <?php echo h(formatEventTime($event['time'])); ?></p>

    <?php if (!$isRegistered): ?>
        <div class="notice error">You must join this event before scanning attendance.</div>
    <?php elseif ($windowState === 'before'): ?>
        <div class="notice">Attendance has not opened yet. Scan starts at <?php echo h(formatEventTime($event['attendance_start'] ?: $event['time'])); ?>.</div>
    <?php elseif ($windowState === 'closed'): ?>
        <div class="notice error">Attendance window is already closed for this event.</div>
    <?php else: ?>
        <div class="notice">Use camera scan when available. If your phone or PC browser blocks camera on local network, upload a QR screenshot/photo.</div>
        <div id="reader"></div>
        <div class="manual-box">
            <h2>Fallback options</h2>
            <p>If camera access fails, upload a QR image from another device.</p>
            <div class="upload-row">
                <input type="file" id="qrImageInput" accept="image/*">
            </div>
        </div>
        <div id="result">Waiting for QR scan...</div>
    <?php endif; ?>

    <a href="event.php?id=<?php echo $event_id; ?>">Back to event</a>
</div>

<?php if ($isRegistered && $windowState === 'open'): ?>
<script>
let scannerBusy = false;
let scannerInstance = null;
let scannerStopped = false;
let scanGeo = { lat: "", lng: "", address: "" };

function setResult(message, isError) {
    const box = document.getElementById("result");
    box.textContent = message;
    box.className = isError ? "notice error" : "notice";
}

function stopScanner() {
    if (!scannerInstance || scannerStopped) {
        return Promise.resolve();
    }

    scannerStopped = true;
    return scannerInstance.clear().catch(() => {});
}

function getDeviceInfo() {
    const userAgent = navigator.userAgent;
    let browser = "Unknown";
    let deviceType = "Desktop";
    
    // Detect browser
    if (userAgent.indexOf("Chrome") > -1) browser = "Chrome";
    else if (userAgent.indexOf("Safari") > -1) browser = "Safari";
    else if (userAgent.indexOf("Firefox") > -1) browser = "Firefox";
    else if (userAgent.indexOf("Edge") > -1) browser = "Edge";
    else if (userAgent.indexOf("Opera") > -1) browser = "Opera";
    
    // Detect device type
    if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent)) {
        deviceType = "Mobile";
        if (/iPad/i.test(userAgent)) deviceType = "Tablet";
    }
    
    return {
        browser: browser,
        device_type: deviceType,
        user_agent: userAgent,
        screen_resolution: screen.width + "x" + screen.height,
        platform: navigator.platform,
        language: navigator.language,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    };
}

function submitAttendance(token) {
    if (scannerBusy) {
        return;
    }
    scannerBusy = true;
    
    const deviceInfo = getDeviceInfo();

    fetch("attendance-submit.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "event_id=<?php echo $event_id; ?>&qr_payload=" + encodeURIComponent(token)
            + "&scan_lat=" + encodeURIComponent(scanGeo.lat)
            + "&scan_lng=" + encodeURIComponent(scanGeo.lng)
            + "&scan_address=" + encodeURIComponent(scanGeo.address)
            + "&device_info=" + encodeURIComponent(JSON.stringify(deviceInfo))
    })
    .then(res => res.text())
    .then(data => {
        const isError = data.toLowerCase().includes("not") || data.toLowerCase().includes("wrong") || data.toLowerCase().includes("closed") || data.toLowerCase().includes("already");
        setResult(data, isError);

        if (!isError || data.toLowerCase().includes("already")) {
            stopScanner();
            return;
        }

        scannerBusy = false;
    })
    .catch(() => {
        setResult("Could not submit attendance right now.", true);
        scannerBusy = false;
    });
}

function onScanSuccess(decodedText) {
    submitAttendance(decodedText);
}

function captureScanLocation() {
    if (!navigator.geolocation) {
        return;
    }

    navigator.geolocation.getCurrentPosition(function(position) {
        scanGeo.lat = Number(position.coords.latitude.toFixed(7));
        scanGeo.lng = Number(position.coords.longitude.toFixed(7));
        scanGeo.address = scanGeo.lat + ", " + scanGeo.lng;
    }, function() {}, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000
    });
}

captureScanLocation();

scannerInstance = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 220 });
scannerInstance.render(onScanSuccess, () => {});

document.getElementById("qrImageInput").addEventListener("change", async (event) => {
    const file = event.target.files && event.target.files[0];
    if (!file) {
        return;
    }

    try {
        const tempId = "file-reader-temp";
        const tempNode = document.createElement("div");
        tempNode.id = tempId;
        tempNode.style.display = "none";
        document.body.appendChild(tempNode);
        const imageScanner = new Html5Qrcode(tempId);
        const decodedText = await imageScanner.scanFile(file, true);
        await imageScanner.clear().catch(() => {});
        tempNode.remove();
        submitAttendance(decodedText);
    } catch (error) {
        setResult("Could not read that QR image. Try another screenshot.", true);
        scannerBusy = false;
    }
});
</script>
<?php endif; ?>
<?php renderAppShellEnd("attendance"); ?>
