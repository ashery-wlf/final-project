<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$event_id = (int) ($_POST['event_id'] ?? 0);
$qr_payload = trim($_POST['qr_payload'] ?? '');
$scan_lat = trim($_POST['scan_lat'] ?? '');
$scan_lng = trim($_POST['scan_lng'] ?? '');
$scan_address = trim($_POST['scan_address'] ?? '');
$device_info = trim($_POST['device_info'] ?? '');

if ($event_id <= 0) {
    echo "Event not found.";
    exit;
}

$event = $conn->query("SELECT * FROM events WHERE id=$event_id LIMIT 1")->fetch_assoc();
if (!$event) {
    echo "Event not found.";
    exit;
}

$windowState = attendanceWindowState($event);
if ($windowState !== 'open') {
    echo "Attendance window is not open.";
    exit;
}

$isRegistered = registrationExists($conn, $user_id, $event_id);
$isAdmin = (int) $event['created_by'] === $user_id;

if (!$isRegistered && !$isAdmin) {
    echo "You are not registered for this event.";
    exit;
}

$existing = $conn->query("SELECT id FROM attendance WHERE user_id=$user_id AND event_id=$event_id LIMIT 1");
if ($existing && $existing->num_rows > 0) {
    echo "Attendance already recorded.";
    exit;
}

if (!validateQrPayload($qr_payload)) {
    echo "Invalid QR code.";
    exit;
}

$userResult = $conn->query("SELECT name, email, phone FROM users WHERE id=$user_id LIMIT 1");
$user = $userResult && $userResult->num_rows > 0 ? $userResult->fetch_assoc() : ['name' => '', 'email' => '', 'phone' => ''];

$stmt = $conn->prepare("
    INSERT INTO attendance(event_id, user_id, user_name, user_email, user_phone, device_info, time, scan_address) 
    VALUES(?, ?, ?, ?, ?, ?, NOW(), ?)
");
$stmt->bind_param("iissssss", 
    $event_id, 
    $user_id, 
    $user['name'], 
    $user['email'], 
    $user['phone'], 
    $device_info, 
    $scan_address
);

if ($stmt->execute()) {
    echo "Attendance recorded successfully!";
} else {
    echo "Could not record attendance.";
}
?>
