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
$browser_info = trim($_POST['browser_info'] ?? '');

if ($event_id <= 0) {
    echo "Event not found.";
    exit;
}

$event = $conn->query("SELECT * FROM events WHERE id=$event_id AND deleted = FALSE LIMIT 1")->fetch_assoc();
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

$payloadEventId = validateQrPayload($qr_payload);
if ($payloadEventId === false || (int) $payloadEventId !== $event_id) {
    echo "Wrong or expired QR code.";
    exit;
}

// Get comprehensive user information
$userResult = $conn->query("SELECT name, email, phone FROM users WHERE id=$user_id LIMIT 1");
$user = $userResult && $userResult->num_rows > 0 ? $userResult->fetch_assoc() : ['name' => '', 'email' => '', 'phone' => ''];

// Get scan IP address
$scan_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// Calculate distance from venue if coordinates are available
$distance_from_venue = null;
$venue_lat = $event['location_lat'] ?? ($event['venue_lat'] ?? null);
$venue_lng = $event['location_lng'] ?? ($event['venue_lng'] ?? null);
if (!empty($scan_lat) && !empty($scan_lng) && !empty($venue_lat) && !empty($venue_lng)) {
    $earthRadius = 6371; // Earth's radius in kilometers
    $latFrom = deg2rad(floatval($venue_lat));
    $lonFrom = deg2rad(floatval($venue_lng));
    $latTo = deg2rad(floatval($scan_lat));
    $lonTo = deg2rad(floatval($scan_lng));
    
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    
    $a = sin($latDelta/2) * sin($latDelta/2) + cos($latFrom) * cos($latTo) * sin($lonDelta/2) * sin($lonDelta/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance_from_venue = $earthRadius * $c;
}

// Determine attendance status based on distance and time
$attendance_status = 'present';
$notes = '';

// Check if user is too far from venue
$max_distance_km = isset($event['max_distance_km']) ? (float) $event['max_distance_km'] : 0.0;
if ($distance_from_venue !== null && $max_distance_km > 0 && $distance_from_venue > $max_distance_km) {
    $attendance_status = 'absent';
    $notes = "User was " . number_format($distance_from_venue, 2) . "km from venue (max allowed: " . $max_distance_km . "km)";
}

// Check if user is late
$event_start_time = new DateTime($event['date'] . ' ' . $event['time']);
$current_time = new DateTime();
if ($current_time > $event_start_time) {
    $time_diff = $current_time->getTimestamp() - $event_start_time->getTimestamp();
    $minutes_late = floor($time_diff / 60);
    if ($minutes_late > 15) { // Consider late if more than 15 minutes
        $attendance_status = 'late';
        $notes .= ($notes ? ' | ' : '') . "Arrived " . $minutes_late . " minutes late";
    }
}

// Phone matching verification
$phone_matched = false;
if (!empty($user['phone'])) {
    // Check if phone matches registered phone for this event
    $participant_phone_check = $conn->prepare("SELECT participant_phone FROM participants WHERE user_id = ? AND event_id = ?");
    $participant_phone_check->bind_param("ii", $user_id, $event_id);
    $participant_phone_check->execute();
    $participant_result = $participant_phone_check->get_result();
    
    if ($participant_result && $participant_result->num_rows > 0) {
        $participant = $participant_result->fetch_assoc();
        if (!empty($participant['participant_phone']) && $participant['participant_phone'] === $user['phone']) {
            $phone_matched = true;
        }
    }
}

$verification_method = 'qr_scan';
$check_in_time = $current_time->format('H:i:s');

// Insert comprehensive attendance record
$stmt = $conn->prepare("
    INSERT INTO attendance(
        event_id, user_id, user_name, user_email, user_phone, device_info, 
        time, scan_lat, scan_lng, scan_address, scan_ip, browser_info, 
        distance_from_venue, attendance_status, phone_matched, verification_method, 
        check_in_time, notes
    ) VALUES(?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$scan_lat_value = !empty($scan_lat) ? floatval($scan_lat) : null;
$scan_lng_value = !empty($scan_lng) ? floatval($scan_lng) : null;

$phone_matched_value = $phone_matched ? 1 : 0;

$stmt->bind_param("iissssddsssdissss", 
    $event_id, 
    $user_id, 
    $user['name'], 
    $user['email'], 
    $user['phone'], 
    $device_info,
    $scan_lat_value,
    $scan_lng_value,
    $scan_address,
    $scan_ip,
    $browser_info,
    $distance_from_venue,
    $attendance_status,
    $phone_matched_value,
    $verification_method,
    $check_in_time,
    $notes
);

if ($stmt->execute()) {
    // Create attendance log entry
    $log_stmt = $conn->prepare("
        INSERT INTO attendance_logs(attendance_id, user_id, event_id, action, details) 
        VALUES(?, ?, ?, 'check_in', ?)
    ");
    $attendance_id = $conn->insert_id;
    $log_details = "Status: $attendance_status | Distance: " . ($distance_from_venue ? number_format($distance_from_venue, 2) . 'km' : 'N/A') . 
                  " | Phone matched: " . ($phone_matched ? 'Yes' : 'No') . 
                  " | IP: $scan_ip";
    $log_stmt->bind_param("iiis", $attendance_id, $user_id, $event_id, $log_details);
    $log_stmt->execute();
    
    echo "Attendance recorded successfully! Status: $attendance_status" . 
         ($distance_from_venue ? " (Distance: " . number_format($distance_from_venue, 2) . "km)" : "");
} else {
    echo "Could not record attendance.";
}
?>
