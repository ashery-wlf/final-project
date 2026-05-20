<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$qr_payload = $_POST['qr_payload'] ?? "";
$scan_lat = trim($_POST['scan_lat'] ?? "");
$scan_lng = trim($_POST['scan_lng'] ?? "");
$scan_address = trim($_POST['scan_address'] ?? "");

if ($event_id <= 0) {
    echo "Event not found.";
    exit();
}

$event = $conn->query("SELECT * FROM events WHERE id=$event_id AND deleted = FALSE LIMIT 1")->fetch_assoc();

if (!$event) {
    echo "Event not found.";
    exit();
}

$isAdmin = (int) $event['created_by'] === $user_id;
$isRegistered = registrationExists($conn, $user_id, $event_id) || $isAdmin;

if (!$isRegistered) {
    echo "Not allowed. Register for this event first.";
    exit();
}

if (attendanceWindowState($event) !== 'open') {
    echo "Attendance window is closed.";
    exit();
}

$payloadEventId = validateQrPayload($qr_payload);

if ($payloadEventId === false || (int) $payloadEventId !== $event_id) {
    echo "Wrong or expired QR code.";
    exit();
}

$check = $conn->query("SELECT id FROM attendance WHERE user_id=$user_id AND event_id=$event_id LIMIT 1");

if ($check && $check->num_rows > 0) {
    echo "Already marked!";
    exit();
}

// Capture device information
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$deviceInfo = json_encode([
    'user_agent' => $userAgent,
    'browser' => getBrowserName($userAgent),
    'os' => getOSName($userAgent),
    'device_type' => getDeviceType($userAgent),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    'timestamp' => date('Y-m-d H:i:s')
]);

// Get user information from session
$user_name = $_SESSION['user_name'] ?? 'Unknown';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = $_SESSION['user_phone'] ?? '';
$participantInfo = $conn->query("SELECT participant_name, participant_email, participant_phone FROM participants WHERE event_id=$event_id AND (user_id=$user_id OR participant_email='" . $conn->real_escape_string($user_email) . "') ORDER BY id DESC LIMIT 1");
if ($participantInfo && $participantInfo->num_rows > 0) {
    $participant = $participantInfo->fetch_assoc();
    $user_name = $participant['participant_name'] ?: $user_name;
    $user_email = $participant['participant_email'] ?: $user_email;
    $user_phone = $participant['participant_phone'] ?: $user_phone;
}
$scanLatValue = is_numeric($scan_lat) ? (float) $scan_lat : null;
$scanLngValue = is_numeric($scan_lng) ? (float) $scan_lng : null;
$safeScanAddress = $scan_address;

$stmt = $conn->prepare("INSERT INTO attendance(user_id, event_id, device_info, user_name, user_email, user_phone, time, scan_lat, scan_lng, scan_address) VALUES(?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
$stmt->bind_param("iissssdds", $user_id, $event_id, $deviceInfo, $user_name, $user_email, $user_phone, $scanLatValue, $scanLngValue, $safeScanAddress);

if ($stmt->execute()) {
    echo "Attendance recorded successfully.";
} else {
    echo "Could not save attendance right now.";
}

// Helper functions for device detection
function getBrowserName($userAgent) {
    if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
        return 'Chrome';
    } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
        return 'Safari';
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (strpos($userAgent, 'Edg') !== false) {
        return 'Edge';
    } elseif (strpos($userAgent, 'Trident') !== false) {
        return 'Internet Explorer';
    } elseif (strpos($userAgent, 'Opera') !== false) {
        return 'Opera';
    }
    return 'Unknown';
}

function getOSName($userAgent) {
    if (strpos($userAgent, 'Windows') !== false) {
        return 'Windows';
    } elseif (strpos($userAgent, 'Mac') !== false) {
        return 'macOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        return 'Linux';
    } elseif (strpos($userAgent, 'Android') !== false) {
        return 'Android';
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        return 'iOS';
    }
    return 'Unknown';
}

function getDeviceType($userAgent) {
    if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
        return 'Mobile';
    } elseif (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
        return 'Tablet';
    }
    return 'Desktop';
}
