<?php

date_default_timezone_set('Africa/Dar_es_Salaam');

const QR_ATTENDANCE_SECRET = 'qr-attendance-phase-one-secret';

function ensureEventSchema($conn)
{
    static $done = false;

    if ($done) {
        return;
    }

    ensureUserSchema($conn);

    $conn->query("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM events");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $additions = [
        "name" => "ALTER TABLE events ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT 'Untitled Event'",
        "date" => "ALTER TABLE events ADD COLUMN date DATE NULL",
        "time" => "ALTER TABLE events ADD COLUMN time TIME NULL",
        "created_by" => "ALTER TABLE events ADD COLUMN created_by INT NOT NULL DEFAULT 0",
        "description" => "ALTER TABLE events ADD COLUMN description TEXT NULL",
        "image" => "ALTER TABLE events ADD COLUMN image VARCHAR(255) NULL DEFAULT 'logo.png'",
        "venue_name" => "ALTER TABLE events ADD COLUMN venue_name VARCHAR(255) NULL",
        "venue_location" => "ALTER TABLE events ADD COLUMN venue_location VARCHAR(255) NULL",
        "location_lat" => "ALTER TABLE events ADD COLUMN location_lat DECIMAL(10,7) NULL",
        "location_lng" => "ALTER TABLE events ADD COLUMN location_lng DECIMAL(10,7) NULL",
        "max_distance_km" => "ALTER TABLE events ADD COLUMN max_distance_km DECIMAL(10,2) NULL",
        "target_audience" => "ALTER TABLE events ADD COLUMN target_audience VARCHAR(255) NULL",
        "type" => "ALTER TABLE events ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'online'",
        "registration_mode" => "ALTER TABLE events ADD COLUMN registration_mode VARCHAR(20) NOT NULL DEFAULT 'self'",
        "access_code" => "ALTER TABLE events ADD COLUMN access_code VARCHAR(20) NULL",
        "invited_emails" => "ALTER TABLE events ADD COLUMN invited_emails TEXT NULL",
        "end_time" => "ALTER TABLE events ADD COLUMN end_time TIME NULL",
        "attendance_start" => "ALTER TABLE events ADD COLUMN attendance_start TIME NULL",
        "attendance_end" => "ALTER TABLE events ADD COLUMN attendance_end TIME NULL",
        "deleted" => "ALTER TABLE events ADD COLUMN deleted BOOLEAN NOT NULL DEFAULT FALSE",
        "deleted_at" => "ALTER TABLE events ADD COLUMN deleted_at TIMESTAMP NULL",
    ];

    foreach ($additions as $name => $sql) {
        if (!isset($columns[$name])) {
            $conn->query($sql);
            $columns[$name] = true;
        }
    }

    if (isset($columns['title'])) {
        $conn->query("ALTER TABLE events MODIFY COLUMN title VARCHAR(200) NULL");
        $conn->query("UPDATE events SET name = title WHERE (name IS NULL OR name = '' OR name = 'Untitled Event') AND title IS NOT NULL AND title <> ''");
    }

    if (isset($columns['start_datetime'])) {
        $conn->query("ALTER TABLE events MODIFY COLUMN start_datetime DATETIME NULL");
        $conn->query("UPDATE events SET date = DATE(start_datetime) WHERE date IS NULL AND start_datetime IS NOT NULL");
        $conn->query("UPDATE events SET time = TIME(start_datetime) WHERE time IS NULL AND start_datetime IS NOT NULL");
    }

    if (isset($columns['end_datetime'])) {
        $conn->query("ALTER TABLE events MODIFY COLUMN end_datetime DATETIME NULL");
    }

    $conn->query("UPDATE events SET image = 'logo.png' WHERE image IS NULL OR image = ''");

    $conn->query("
        UPDATE events
        SET registration_mode = CASE
            WHEN registration_mode IS NULL OR registration_mode = '' THEN
                CASE WHEN type = 'private' THEN 'code' ELSE 'self' END
            ELSE registration_mode
        END
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            event_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $participantColumns = [];
    $participantResult = $conn->query("SHOW COLUMNS FROM participants");
    if ($participantResult) {
        while ($row = $participantResult->fetch_assoc()) {
            $participantColumns[$row['Field']] = true;
        }
    }

    $participantAdditions = [
        "participant_name" => "ALTER TABLE participants ADD COLUMN participant_name VARCHAR(255) NULL",
        "participant_email" => "ALTER TABLE participants ADD COLUMN participant_email VARCHAR(255) NULL",
        "participant_phone" => "ALTER TABLE participants ADD COLUMN participant_phone VARCHAR(50) NULL",
        "invite_status" => "ALTER TABLE participants ADD COLUMN invite_status VARCHAR(30) NOT NULL DEFAULT 'registered'",
        "invited_at" => "ALTER TABLE participants ADD COLUMN invited_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        "access_code" => "ALTER TABLE participants ADD COLUMN access_code VARCHAR(20) NULL",
    ];

    foreach ($participantAdditions as $name => $sql) {
        if (!isset($participantColumns[$name])) {
            $conn->query($sql);
            $participantColumns[$name] = true;
        }
    }
    
    // Create deleted_events table
    $conn->query("
        CREATE TABLE IF NOT EXISTS deleted_events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_event_id INT NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            deleted_by INT NOT NULL,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reason TEXT,
            attendance_data_preserved BOOLEAN DEFAULT TRUE,
            event_data JSON,
            INDEX idx_original_event_id (original_event_id),
            INDEX idx_deleted_by (deleted_by),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $attendanceColumns = [];
    $attendanceResult = $conn->query("SHOW COLUMNS FROM attendance");
    if ($attendanceResult) {
        while ($row = $attendanceResult->fetch_assoc()) {
            $attendanceColumns[$row['Field']] = true;
        }
    }

    $attendanceAdditions = [
        "user_name" => "ALTER TABLE attendance ADD COLUMN user_name VARCHAR(255) NULL",
        "user_email" => "ALTER TABLE attendance ADD COLUMN user_email VARCHAR(255) NULL",
        "user_phone" => "ALTER TABLE attendance ADD COLUMN user_phone VARCHAR(50) NULL",
        "device_info" => "ALTER TABLE attendance ADD COLUMN device_info LONGTEXT NULL",
        "attendance_status" => "ALTER TABLE attendance ADD COLUMN attendance_status VARCHAR(30) NOT NULL DEFAULT 'present'",
        "time" => "ALTER TABLE attendance ADD COLUMN time TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        "scan_lat" => "ALTER TABLE attendance ADD COLUMN scan_lat DECIMAL(10,7) NULL",
        "scan_lng" => "ALTER TABLE attendance ADD COLUMN scan_lng DECIMAL(10,7) NULL",
        "scan_address" => "ALTER TABLE attendance ADD COLUMN scan_address VARCHAR(255) NULL",
        "scan_ip" => "ALTER TABLE attendance ADD COLUMN scan_ip VARCHAR(80) NULL",
        "browser_info" => "ALTER TABLE attendance ADD COLUMN browser_info LONGTEXT NULL",
        "distance_from_venue" => "ALTER TABLE attendance ADD COLUMN distance_from_venue DECIMAL(10,2) NULL",
        "phone_matched" => "ALTER TABLE attendance ADD COLUMN phone_matched BOOLEAN NOT NULL DEFAULT FALSE",
        "verification_method" => "ALTER TABLE attendance ADD COLUMN verification_method VARCHAR(30) NULL",
        "check_in_time" => "ALTER TABLE attendance ADD COLUMN check_in_time TIME NULL",
        "notes" => "ALTER TABLE attendance ADD COLUMN notes TEXT NULL",
    ];

    foreach ($attendanceAdditions as $name => $sql) {
        if (!isset($attendanceColumns[$name])) {
            $conn->query($sql);
            $attendanceColumns[$name] = true;
        }
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS attendance_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            attendance_id INT NOT NULL,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_attendance_id (attendance_id),
            INDEX idx_user_id (user_id),
            INDEX idx_event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

function ensureUserSchema($conn)
{
    static $done = false;

    if ($done) {
        return;
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM users");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $additions = [
        "name" => "ALTER TABLE users ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT ''",
        "password" => "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT ''",
        "profile_image" => "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL",
    ];

    foreach ($additions as $name => $sql) {
        if (!isset($columns[$name])) {
            $conn->query($sql);
            $columns[$name] = true;
        }
    }

    if (isset($columns['first_name']) || isset($columns['last_name'])) {
        if (isset($columns['first_name'])) {
            $conn->query("ALTER TABLE users MODIFY COLUMN first_name VARCHAR(50) NULL DEFAULT ''");
        }
        if (isset($columns['last_name'])) {
            $conn->query("ALTER TABLE users MODIFY COLUMN last_name VARCHAR(50) NULL DEFAULT ''");
        }
        $firstNameExpression = isset($columns['first_name']) ? "first_name" : "''";
        $lastNameExpression = isset($columns['last_name']) ? "last_name" : "''";
        $conn->query("
            UPDATE users
            SET name = TRIM(CONCAT(COALESCE($firstNameExpression, ''), ' ', COALESCE($lastNameExpression, '')))
            WHERE name = ''
        ");
    }

    if (isset($columns['password_hash'])) {
        $conn->query("ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT ''");
        $conn->query("UPDATE users SET password = password_hash WHERE password = '' AND password_hash IS NOT NULL AND password_hash <> ''");
    }

    $done = true;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appInitials($value)
{
    $initials = '';
    foreach (preg_split('/\s+/', trim((string) $value)) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($initials ?: 'U', 0, 2);
}

function appUserImagePath($image)
{
    if (!empty($image) && file_exists($image)) {
        return $image;
    }

    return '';
}

function appSetFlash($type, $text)
{
    $_SESSION['app_flash'] = [
        'type' => $type,
        'text' => $text,
    ];
}

function appConsumeFlash()
{
    $flash = $_SESSION['app_flash'] ?? null;
    unset($_SESSION['app_flash']);
    return $flash;
}

function appCurrentUrl()
{
    return $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
}

function appCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function appCsrfInput()
{
    return '<input type="hidden" name="csrf_token" value="' . h(appCsrfToken()) . '">';
}

function appVerifyCsrf()
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function appUploadedImagePath($file, $prefix, &$error = '')
{
    if (empty($file['name'])) {
        return '';
    }

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Could not upload that image.';
        return false;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Image must be jpg, png, webp, or gif.';
        return false;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $info = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $info ? finfo_file($info, $file['tmp_name']) : '';
    if ($info) {
        finfo_close($info);
    }

    if (!isset($allowedMime[$mime])) {
        $error = 'Uploaded file is not a valid image.';
        return false;
    }

    $uploadDir = 'uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix);
    $target = $uploadDir . '/' . $safePrefix . '_' . bin2hex(random_bytes(8)) . '.' . ($allowedMime[$mime] === 'jpg' ? 'jpg' : $allowedMime[$mime]);
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error = 'Could not save that image.';
        return false;
    }

    return $target;
}

function generateAccessCode($length = 6)
{
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= (string) random_int(0, 9);
    }
    return $code;
}

function parseRegistrantLines($value)
{
    $rows = preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: [];
    $registrants = [];

    foreach ($rows as $row) {
        $row = trim($row);
        if ($row === '') {
            continue;
        }

        $parts = array_map('trim', preg_split('/\s*,\s*/', $row));
        if (count($parts) < 3) {
            continue;
        }

        [$name, $email, $phone] = [$parts[0], $parts[1], $parts[2]];
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
            continue;
        }

        $registrants[strtolower($email)] = [
            'name' => $name,
            'email' => strtolower($email),
            'phone' => $phone,
        ];
    }

    return array_values($registrants);
}

function normalizeInviteEmails($value)
{
    $emails = preg_split('/[\s,;]+/', strtolower(trim((string) $value))) ?: [];
    $unique = [];

    foreach ($emails as $email) {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $unique[$email] = true;
    }

    return array_keys($unique);
}

function syncPrivateEventParticipants($conn, $eventId, $emails)
{
    $eventId = (int) $eventId;
    foreach ($emails as $email) {
        $safeEmail = $conn->real_escape_string($email);
        $userResult = $conn->query("SELECT id FROM users WHERE email='$safeEmail' LIMIT 1");
        if ($userResult && $userResult->num_rows > 0) {
            $userId = (int) $userResult->fetch_assoc()['id'];
            registerUserToEvent($conn, $userId, $eventId);
        }
    }
}

function upsertPrivateRegistrant($conn, $eventId, $registrant)
{
    $eventId = (int) $eventId;
    $name = $conn->real_escape_string($registrant['name']);
    $email = $conn->real_escape_string($registrant['email']);
    $phone = $conn->real_escape_string($registrant['phone']);

    $userResult = $conn->query("SELECT id, name, email, phone FROM users WHERE email='$email' LIMIT 1");
    if ($userResult && $userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $userId = (int) $user['id'];
        $exists = $conn->query("SELECT id FROM participants WHERE event_id=$eventId AND user_id=$userId LIMIT 1");
        if ($exists && $exists->num_rows > 0) {
            $participantId = (int) $exists->fetch_assoc()['id'];
            $conn->query("UPDATE participants SET participant_name='" . $conn->real_escape_string($user['name']) . "', participant_email='" . $conn->real_escape_string($user['email']) . "', participant_phone='" . $conn->real_escape_string($user['phone']) . "', invite_status='registered' WHERE id=$participantId");
        } else {
            $conn->query("INSERT INTO participants(user_id, event_id, participant_name, participant_email, participant_phone, invite_status) VALUES($userId, $eventId, '" . $conn->real_escape_string($user['name']) . "', '" . $conn->real_escape_string($user['email']) . "', '" . $conn->real_escape_string($user['phone']) . "', 'registered')");
        }
        return true;
    }

    $exists = $conn->query("SELECT id FROM participants WHERE event_id=$eventId AND participant_email='$email' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        $participantId = (int) $exists->fetch_assoc()['id'];
        $conn->query("UPDATE participants SET participant_name='$name', participant_phone='$phone', invite_status='invited' WHERE id=$participantId");
    } else {
        $conn->query("INSERT INTO participants(user_id, event_id, participant_name, participant_email, participant_phone, invite_status) VALUES(NULL, $eventId, '$name', '$email', '$phone', 'invited')");
    }

    return false;
}

function sendEventAccessCodeEmail($recipientName, $recipientEmail, $event, $accessCode)
{
    $subject = 'Private Event Access Code - ' . ($event['name'] ?? 'QR Attendance Event');
    $message = "Hello " . $recipientName . ",\n\n"
        . "You have been registered for the private event: " . ($event['name'] ?? 'Event') . ".\n"
        . "Access code: " . $accessCode . "\n"
        . "Date: " . ($event['date'] ?? '') . " " . ($event['time'] ?? '') . "\n"
        . "Venue: " . ($event['venue_name'] ?? 'Not set') . "\n\n"
        . "Use this code after logging into the system to access the event.\n";
    $headers = "From: no-reply@qrattendance.local\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($recipientEmail, $subject, $message, $headers);
}

function eventMapEmbedUrl($event)
{
    $lat = isset($event['location_lat']) ? (float) $event['location_lat'] : 0.0;
    $lng = isset($event['location_lng']) ? (float) $event['location_lng'] : 0.0;

    if ($lat == 0.0 && $lng == 0.0) {
        return '';
    }

    $bbox = ($lng - 0.01) . '%2C' . ($lat - 0.01) . '%2C' . ($lng + 0.01) . '%2C' . ($lat + 0.01);
    return 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $lat . '%2C' . $lng;
}

function eventDirectionsUrl($event)
{
    $lat = isset($event['location_lat']) ? (float) $event['location_lat'] : 0.0;
    $lng = isset($event['location_lng']) ? (float) $event['location_lng'] : 0.0;

    if ($lat == 0.0 && $lng == 0.0) {
        return '';
    }

    return 'https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lng;
}

function appHandleProfileActions($conn, $userId)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['app_action'])) {
        return;
    }

    $action = $_POST['app_action'];
    $redirect = appCurrentUrl();

    if (!appVerifyCsrf()) {
        appSetFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $redirect);
        exit();
    }

    if ($action === 'update_profile') {
        $name = trim($_POST['profile_name'] ?? '');
        $email = trim($_POST['profile_email'] ?? '');
        $phone = trim($_POST['profile_phone'] ?? '');

        if ($name === '' || $email === '' || $phone === '') {
            appSetFlash('error', 'Name, email, and phone are required.');
            header('Location: ' . $redirect);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            appSetFlash('error', 'Please enter a valid email address.');
            header('Location: ' . $redirect);
            exit();
        }

        $safeEmail = $conn->real_escape_string($email);
        $check = $conn->query("SELECT id FROM users WHERE email='$safeEmail' AND id<>" . (int) $userId . " LIMIT 1");
        if ($check && $check->num_rows > 0) {
            appSetFlash('error', 'That email is already being used.');
            header('Location: ' . $redirect);
            exit();
        }

        $currentResult = $conn->query("SELECT profile_image FROM users WHERE id=" . (int) $userId . " LIMIT 1");
        $current = $currentResult ? $currentResult->fetch_assoc() : ['profile_image' => ''];
        $profileImage = $current['profile_image'] ?? '';

        if (!empty($_FILES['profile_image']['name'])) {
            $uploadError = '';
            $uploadedPath = appUploadedImagePath($_FILES['profile_image'], 'profile_' . $userId, $uploadError);
            if ($uploadedPath === false) {
                appSetFlash('error', $uploadError);
                header('Location: ' . $redirect);
                exit();
            }

            $profileImage = $uploadedPath;
        }

        $safeName = $conn->real_escape_string($name);
        $safePhone = $conn->real_escape_string($phone);
        $safeImage = $conn->real_escape_string($profileImage);
        $conn->query("UPDATE users SET name='$safeName', email='$safeEmail', phone='$safePhone', profile_image='$safeImage' WHERE id=" . (int) $userId);

        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;

        appSetFlash('success', 'Profile updated successfully.');
        header('Location: ' . $redirect);
        exit();
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            appSetFlash('error', 'Fill in all password fields.');
            header('Location: ' . $redirect);
            exit();
        }

        if (strlen($newPassword) < 6) {
            appSetFlash('error', 'New password must be at least 6 characters.');
            header('Location: ' . $redirect);
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            appSetFlash('error', 'New password and confirm password do not match.');
            header('Location: ' . $redirect);
            exit();
        }

        $userResult = $conn->query("SELECT * FROM users WHERE id=" . (int) $userId . " LIMIT 1");
        $user = $userResult ? $userResult->fetch_assoc() : null;
        $storedPassword = $user['password'] ?? ($user['password_hash'] ?? '');

        if (!$user || !password_verify($currentPassword, $storedPassword)) {
            appSetFlash('error', 'Current password is not correct.');
            header('Location: ' . $redirect);
            exit();
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $safeHash = $conn->real_escape_string($hashed);
        $passwordColumn = array_key_exists('password', $user) ? 'password' : 'password_hash';
        $conn->query("UPDATE users SET $passwordColumn='$safeHash' WHERE id=" . (int) $userId);

        appSetFlash('success', 'Password changed successfully.');
        header('Location: ' . $redirect);
        exit();
    }
}

function eventImagePath($image)
{
    if (!empty($image) && file_exists($image)) {
        return $image;
    }

    return "logo.png";
}

function eventRegistrationMode($event)
{
    if (!empty($event['registration_mode'])) {
        return $event['registration_mode'];
    }

    return ($event['type'] ?? '') === 'private' ? 'code' : 'self';
}

function eventStartDateTime($event)
{
    return strtotime($event['date'] . ' ' . $event['time']);
}

function eventEndDateTime($event)
{
    $endTime = !empty($event['end_time']) ? $event['end_time'] : $event['time'];
    return strtotime($event['date'] . ' ' . $endTime);
}

function attendanceStartDateTime($event)
{
    $start = !empty($event['attendance_start']) ? $event['attendance_start'] : $event['time'];
    $dateTime = $event['date'] . ' ' . $start;
    $timestamp = strtotime($dateTime);
    
    // If the timestamp is false (invalid date/time), use event time as fallback
    if ($timestamp === false) {
        $timestamp = strtotime($event['date'] . ' ' . $event['time']);
    }
    
    return $timestamp;
}

function attendanceEndDateTime($event)
{
    $end = !empty($event['attendance_end']) ? $event['attendance_end'] : (!empty($event['end_time']) ? $event['end_time'] : $event['time']);
    $dateTime = $event['date'] . ' ' . $end;
    $timestamp = strtotime($dateTime);
    
    // If the timestamp is false (invalid date/time), use event end time as fallback
    if ($timestamp === false) {
        $endFallback = !empty($event['end_time']) ? $event['end_time'] : $event['time'];
        $timestamp = strtotime($event['date'] . ' ' . $endFallback);
    }
    
    return $timestamp;
}

function attendanceWindowState($event)
{
    $now = time();
    $start = attendanceStartDateTime($event);
    $end = attendanceEndDateTime($event);

    // Debug logging (can be removed in production)
    error_log("Attendance Window Debug: Now=" . date('Y-m-d H:i:s', $now) . 
              ", Start=" . date('Y-m-d H:i:s', $start) . 
              ", End=" . date('Y-m-d H:i:s', $end));

    // If start or end times are invalid, use event time as fallback
    if ($start === false || $end === false) {
        $eventTime = strtotime($event['date'] . ' ' . $event['time']);
        if ($start === false) $start = $eventTime;
        if ($end === false) $end = $eventTime;
    }

    // Ensure end time is after start time
    if ($end < $start) {
        $end = $start + 3600; // Add 1 hour if end is before start
    }

    if ($now < $start) {
        return 'before';
    }

    if ($now > $end) {
        return 'closed';
    }

    return 'open';
}

function eventLifecycleStatus($event)
{
    $now = time();
    $start = eventStartDateTime($event);
    $end = eventEndDateTime($event);

    if ($now < $start) {
        return 'upcoming';
    }

    if ($now > $end) {
        return 'ended';
    }

    return 'live';
}

function eventLifecycleLabel($event)
{
    $status = eventLifecycleStatus($event);

    if ($status === 'ended') {
        return 'Ended';
    }

    if ($status === 'upcoming') {
        return 'Upcoming';
    }

    return 'Live';
}

function formatEventDate($date)
{
    return date("M j, Y", strtotime($date));
}

function formatEventTime($time)
{
    return date("h:i A", strtotime($time));
}

function registrationExists($conn, $userId, $eventId)
{
    $userId = (int) $userId;
    $eventId = (int) $eventId;
    $result = $conn->query("SELECT id FROM participants WHERE user_id=$userId AND event_id=$eventId LIMIT 1");
    return $result && $result->num_rows > 0;
}

function registerUserToEvent($conn, $userId, $eventId)
{
    $userId = (int) $userId;
    $eventId = (int) $eventId;

    if (!registrationExists($conn, $userId, $eventId)) {
        $userResult = $conn->query("SELECT name, email, phone FROM users WHERE id=$userId LIMIT 1");
        $user = $userResult && $userResult->num_rows > 0 ? $userResult->fetch_assoc() : ['name' => '', 'email' => '', 'phone' => ''];
        $name = $conn->real_escape_string($user['name'] ?? '');
        $email = $conn->real_escape_string($user['email'] ?? '');
        $phone = $conn->real_escape_string($user['phone'] ?? '');
        
        // Get event details
        $eventResult = $conn->query("SELECT name, access_code, registration_mode FROM events WHERE id=$eventId LIMIT 1");
        $event = $eventResult ? $eventResult->fetch_assoc() : null;
        
        // Generate access code for this user if event requires it
        $userAccessCode = '';
        if ($event && $event['registration_mode'] === 'code') {
            $userAccessCode = generateAccessCode();
        }
        
        // Insert participant with access code
        $conn->query("INSERT INTO participants(user_id, event_id, participant_name, participant_email, participant_phone, invite_status, access_code) VALUES($userId, $eventId, '$name', '$email', '$phone', 'registered', '$userAccessCode')");
        
        // Send access code email if event requires code
        if ($event && $event['registration_mode'] === 'code' && !empty($user['email']) && !empty($userAccessCode)) {
            sendEventAccessCodeEmail($user['name'], $user['email'], $event, $userAccessCode);
        }
    }
}

function qrTimeslot($timestamp = null)
{
    $timestamp = $timestamp ?? time();
    return (int) floor($timestamp / 60);
}

function buildQrPayload($eventId, $timeslot = null)
{
    $eventId = (int) $eventId;
    $timeslot = $timeslot ?? qrTimeslot();
    $signature = substr(hash_hmac('sha256', $eventId . '|' . $timeslot, QR_ATTENDANCE_SECRET), 0, 16);
    return $eventId . '|' . $timeslot . '|' . $signature;
}

function validateQrPayload($payload)
{
    $parts = explode('|', trim((string) $payload));
    if (count($parts) !== 3) {
        return false;
    }

    $eventId = (int) $parts[0];
    $timeslot = (int) $parts[1];
    $signature = $parts[2];

    foreach ([-2, -1, 0, 1, 2] as $offset) {
        $candidate = qrTimeslot() + $offset;
        if ($candidate !== $timeslot) {
            continue;
        }

        if (hash_equals(buildQrPayload($eventId, $timeslot), $eventId . '|' . $timeslot . '|' . $signature)) {
            return $eventId;
        }
    }

    return false;
}

function appIcon($name)
{
    $icons = [
        "menu" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M9 17h11" /></svg>',
        "search" => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="M20 20l-4.2-4.2" /></svg>',
        "dashboard" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5L12 4l9 7.5" /><path d="M5 10.5V20h5v-5h4v5h5v-9.5" /></svg>',
        "events" => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3v4M16 3v4M4 10h16" /></svg>',
        "scan" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M20 8V5a1 1 0 0 0-1-1h-3M4 16v3a1 1 0 0 0 1 1h3M20 16v3a1 1 0 0 1-1 1h-3" /><path d="M8 12h8" /></svg>',
        "attendance" => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2" /><path d="M9 2v4M15 2v4M8 10h8M8 14h5" /></svg>',
        "codes" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h4v4H7zM13 7h4M13 11h4M7 13h4M7 17h4M13 15h4v4h-4z" /></svg>',
        "create" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>',
        "profile" => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M5 20c1.8-3.2 4.2-4.8 7-4.8s5.2 1.6 7 4.8" /></svg>',
        "logout" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M11 4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-7" /></svg>',
    ];

    return $icons[$name] ?? "";
}

function appPrimaryEventLink($conn, $userId, $target)
{
    $userId = (int) $userId;
    $result = $conn->query("SELECT id FROM events WHERE created_by=$userId ORDER BY date DESC, time DESC LIMIT 1");
    $eventId = $result && $result->num_rows > 0 ? (int) $result->fetch_assoc()['id'] : 0;

    if ($eventId <= 0) {
        return "events.php";
    }

    return $target . "?id=" . $eventId;
}

function renderAppShellStart($conn, $options = [])
{
    ensureUserSchema($conn);

    $userName = $_SESSION['user_name'] ?? 'Member';
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    appHandleProfileActions($conn, $userId);
    $active = $options['active'] ?? '';
    $title = $options['title'] ?? 'QR Attendance';
    $pageTitle = $options['page_title'] ?? 'QR Attendance';
    $pageSubtitle = $options['page_subtitle'] ?? '';
    $searchPlaceholder = $options['search_placeholder'] ?? 'Search...';
    $topbarContent = $options['topbar_content'] ?? '';
    $pageActions = $options['page_actions'] ?? '';
    $extraHead = $options['extra_head'] ?? '';
    $showPageHead = $options['show_page_head'] ?? true;
    $attendanceLink = 'attendance.php';
    $qrLink = appPrimaryEventLink($conn, $userId, 'generate-qr.php');
    $GLOBALS['app_mobile_attendance_link'] = $attendanceLink;
    $GLOBALS['app_mobile_qr_link'] = $qrLink;
    $profileResult = $conn->query("SELECT name, email, phone, profile_image FROM users WHERE id=$userId LIMIT 1");
    $profile = $profileResult && $profileResult->num_rows > 0 ? $profileResult->fetch_assoc() : [
        'name' => $userName,
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'profile_image' => '',
    ];
    $userName = $profile['name'] ?: $userName;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $profile['email'] ?? ($_SESSION['user_email'] ?? '');
    $_SESSION['user_phone'] = $profile['phone'] ?? ($_SESSION['user_phone'] ?? '');
    $initials = appInitials($userName);
    $profileImagePath = appUserImagePath($profile['profile_image'] ?? '');
    $profileAvatar = $profileImagePath !== ''
        ? '<img src="' . h($profileImagePath) . '" alt="' . h($userName) . '" class="app-avatar-image">'
        : h($initials);
    $flash = appConsumeFlash();
    $flashMarkup = '';
    if ($flash && !empty($flash['text'])) {
        $flashClass = $flash['type'] === 'success' ? 'success' : 'error';
        $flashMarkup = '<div class="app-inline-flash ' . $flashClass . '">' . h($flash['text']) . '</div>';
    }
    $GLOBALS['app_profile_avatar'] = $profileAvatar;
    $GLOBALS['app_profile_name'] = $userName;
    $GLOBALS['app_profile_email'] = $profile['email'] ?? '';
    $GLOBALS['app_profile_phone'] = $profile['phone'] ?? '';
    $GLOBALS['app_profile_image_path'] = $profileImagePath;
    $GLOBALS['app_profile_flash'] = $flashMarkup;

    echo '<!DOCTYPE html>
<html>
<head>
<title>' . h($title) . '</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{
    --line:#e6ebf4;
    --text:#0f172a;
    --muted:#707a8f;
    --blue:#2563ff;
    --shadow:0 20px 45px rgba(42, 15, 15, 0.08);
}
*{box-sizing:border-box;}
body{
    margin:0;
    background:radial-gradient(circle at top left, rgba(255, 37, 37, 0.1), transparent 28%), linear-gradient(180deg, #eef2f9 0%, #f7f9fd 100%);
    color:var(--text);
    font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}
a{color:inherit;text-decoration:none;}
.app-layout{height:100vh;display:flex;overflow:hidden;}
.app-sidebar{
    width:248px;background:linear-gradient(180deg, #291e1e 0%, #1b0f0f 100%);color:#fff;padding:18px 14px;display:flex;flex-direction:column;gap:18px;
    box-shadow:24px 0 40px rgba(37, 9, 9, 0.16);
    overflow:hidden;
    flex-shrink:0;
    transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position:fixed;
    top:0;
    left:0;
    bottom:0;
    z-index:30;
}
.app-sidebar.hidden{
    width:0;
    padding:18px 0;
    box-shadow:none;
}
.app-brand{display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px 12px 14px;border-radius:20px;background:linear-gradient(180deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.05) 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,0.08), 0 4px 12px rgba(0,0,0,0.15);}
.app-brand-logo-wrap{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(255,255,255,0.92) 100%);padding:6px;display:grid;place-items:center;box-shadow:0 12px 24px rgba(0,0,0,0.15), inset 0 2px 4px rgba(255,255,255,0.9);border:2px solid rgba(255,255,255,0.4);}
.app-brand-logo{width:100%;height:100%;object-fit:contain;border-radius:16px;display:block;image-rendering:crisp-edges;image-rendering:-webkit-optimize-contrast;transform:scale(1.1);filter:contrast(1.1) brightness(1.05);}
.app-brand-text{font-weight:800;line-height:1.08;font-size:13px;letter-spacing:0.06em;text-align:center;}
.app-brand-text small{display:block;margin-top:6px;font-size:11px;font-weight:600;letter-spacing:0.03em;color:rgba(255,255,255,0.72);}
.app-menu{display:flex;flex-direction:column;gap:4px;}
.app-menu-link{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;font-size:14px;color:rgba(255,255,255,0.92);}
.app-menu-link:hover{background:rgba(255,255,255,0.08);}
.app-menu-link.active{background:linear-gradient(180deg, #3a0909 0%, #da1515 100%);box-shadow:0 12px 22px rgba(255, 36, 36, 0.35);}
.app-sidebar-footer{margin-top:auto;padding-top:14px;border-top:1px solid rgba(255,255,255,0.12);display:flex;flex-direction:column;gap:8px;}
.app-profile{display:flex;align-items:center;gap:12px;padding:12px 10px;border-radius:14px;background:rgba(255,255,255,0.04);}
.app-profile-main{display:flex;align-items:center;gap:12px;flex:1;min-width:0;color:inherit;text-decoration:none;transition:background 0.2s;border-radius:10px;padding:4px;}
.app-profile-main:hover{background:rgba(255,255,255,0.08);}
.app-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg, #ffcf8a, #b86d39);display:grid;place-items:center;font-size:13px;font-weight:800;color:#fff;}
.app-avatar-image{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;}
.app-profile-name{font-weight:700;font-size:14px;}
.app-profile-text{min-width:0;}
.app-profile-logout{
width:36px;height:36px;
border-radius:10px;
display:grid;
place-items:center;
background:rgba(255,255,255,0.08);
border:1px solid rgba(255,255,255,0.12);
color:#fff;
cursor:pointer;
transition:background 0.2s;
text-decoration:none;
}
.app-profile-logout:hover{
background:rgba(255,255,255,0.16);
}
.app-content{flex:1;
padding:18px 18px 18px 266px;
height:100vh;overflow:hidden;
}
.app-shell{max-width:1180px;
margin:0 auto;
background:rgba(255,255,255,0.66);
border:1px solid rgba(255,255,255,0.75);
border-radius:18px;
overflow:hidden;
box-shadow:var(--shadow);backdrop-filter:blur(12px);
height:calc(100vh - 36px);
display:flex;
flex-direction:column;
}
.app-topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 18px;background:rgba(255,255,255,0.92);border-bottom:1px solid var(--line);flex-shrink:0;}
.app-topbar-brand{display:none;align-items:center;gap:10px;font-weight:800;color:#0f172a;}
.app-topbar-brand img{width:34px;height:34px;object-fit:contain;}
.app-topbar-brand span{font-size:13px;line-height:1.05;letter-spacing:0.04em;}
.app-topbar-content{display:flex;align-items:center;gap:12px;flex:1;justify-content:flex-end;}
.app-topbar-profile{width:42px;height:42px;border-radius:50%;border:1px solid #dbe4f0;background:#fff;display:none;align-items:center;justify-content:center;padding:0;overflow:hidden;color:#334155;box-shadow:0 8px 18px rgba(42, 15, 15, 0.08);}
.app-topbar-profile .app-avatar{width:100%;height:100%;font-size:12px;}
.app-topbar-desktop{display:flex;align-items:center;gap:12px;}
.app-inline-flash{margin:0 0 16px;border-radius:14px;padding:12px 14px;font-size:14px;font-weight:700;}
.app-inline-flash.success{background:#e8f7ee;color:#166534;border:1px solid #ccebd7;}
.app-inline-flash.error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3;}
.app-profile-drawer-backdrop{position:fixed;inset:0;background:rgba(42, 15, 15, 0.45);opacity:0;pointer-events:none;transition:opacity 0.25s ease;z-index:80;}
.app-profile-drawer{position:fixed;top:0;right:0;width:min(420px, 100vw);height:100vh;background:#fff;box-shadow:-24px 0 48px rgba(42, 15, 15, 0.16);transform:translateX(100%);transition:transform 0.25s ease;z-index:81;display:flex;flex-direction:column;}
.app-profile-open .app-profile-drawer-backdrop{opacity:1;pointer-events:auto;}
.app-profile-open .app-profile-drawer{transform:translateX(0);}
.app-drawer-head{display:flex;align-items:center;justify-content:space-between;padding:18px 18px 14px;border-bottom:1px solid #e7edf7;}
.app-drawer-head h3{margin:0;font-size:18px;}
.app-drawer-close{width:38px;height:38px;border:none;border-radius:12px;background:#f8fafc;color:#334155;display:grid;place-items:center;cursor:pointer;}
.app-drawer-close svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;}
.app-drawer-body{padding:18px;overflow:auto;display:grid;gap:18px;}
.app-drawer-card{background:#f8fbff;border:1px solid #e6edf7;border-radius:18px;padding:16px;}
.app-drawer-profile{display:flex;align-items:center;gap:14px;}
.app-drawer-profile .app-avatar{width:60px;height:60px;font-size:18px;}
.app-drawer-profile strong{display:block;font-size:17px;}
.app-drawer-profile span{display:block;margin-top:4px;color:#64748b;font-size:13px;}
.app-drawer-card h4{margin:0 0 12px;font-size:14px;color:#0f172a;}
.app-drawer-form{display:grid;gap:12px;}
.app-drawer-form label{display:grid;gap:6px;font-size:13px;font-weight:700;color:#334155;}
.app-drawer-form input{width:100%;height:42px;border:1px solid #dbe4f0;border-radius:12px;padding:0 12px;font-size:14px;background:#fff;color:#0f172a;}
.app-drawer-form input[type="file"]{height:auto;padding:10px 12px;}
.app-drawer-actions{display:flex;gap:10px;flex-wrap:wrap;}
.app-drawer-save,.app-drawer-link{display:inline-flex;align-items:center;justify-content:center;height:42px;border-radius:12px;padding:0 16px;font-weight:800;border:none;cursor:pointer;}
.app-drawer-save{background:#2563ff;color:#fff;}
.app-drawer-link{background:#0f172a;color:#fff;}
.app-search{flex:1;position:relative;}
.app-search input{width:100%;height:40px;border-radius:10px;border:1px solid #e4eaf4;background:#fff;padding:0 42px 0 38px;font-size:14px;color:var(--text);outline:none;}
.app-search-icon-left,.app-search-icon-right{position:absolute;top:50%;transform:translateY(-50%);color:#9aa4b2;}
.app-search-icon-left{left:12px;}
.app-search-icon-right{right:12px;}
.app-page{padding:22px;flex:1;overflow:auto;}
.app-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px;}
.app-page-head h1{margin:0;font-size:34px;line-height:1.05;}
.app-page-head p{margin:8px 0 0;color:var(--muted);}
.app-page-actions{display:flex;gap:10px;flex-wrap:wrap;}
.app-mobile-nav{display:none;}
.app-sidebar svg,.app-topbar svg,.app-mobile-nav svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}
@media (max-width: 860px){
    .app-sidebar{display:none;}
    body{background:#f5f7fc;}
    .app-content{padding:0;margin-left:0;height:100vh;}
    .app-shell{max-width:none;border-radius:0;border-left:0;border-right:0;border-bottom:0;height:100vh;}
    .app-topbar{
    padding:12px 14px;z-index:40;
    background:rgba(255,255,255,0.96);
    }
    .app-topbar-brand{display:flex;}
    .app-topbar-profile{display:flex;}
    .app-topbar-brand span{display:block;}
    .app-topbar-desktop{display:none;}
    .app-page{padding:14px 14px 96px;}
    .app-page-head{flex-direction:column;}
    .app-mobile-nav{
        display:grid;
        grid-template-columns:repeat(5, 1fr);
        gap:4px;
        position:fixed;
        left:10px;
        right:10px;
        bottom:10px;
        padding:10px 8px calc(10px + env(safe-area-inset-bottom, 0px));
        background:rgba(255,255,255,0.96);
        border:1px solid #dfe6f2;
        border-radius:18px;
        box-shadow:0 16px 34px rgba(42, 15, 15, 0.12);
        backdrop-filter:blur(12px);
        z-index:50;
        transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .app-mobile-link{
        padding:6px 4px;
        display:flex;
        flex-direction:column;
        align-items:center;
        gap:5px;
        font-size:11px;
        font-weight:700;
        color:#64748b;
        border-radius:12px;
    }
    .app-mobile-link.active{color:#2563ff;background:#eef4ff;}
    .app-mobile-link svg{width:20px;height:20px;}
}
</style>' . $extraHead . '
</head>
<body>
<div class="app-layout">
<aside class="app-sidebar">
    <div class="app-brand">
        <div class="app-brand-logo-wrap">
            <img src="logo.png" alt="QR Attendance System" class="app-brand-logo">
        </div>
        <div class="app-brand-text">QR ATTENDANCE<br>EVENT</div>
    </div>
    <nav class="app-menu">
        <a href="dashboard.php" class="app-menu-link ' . ($active === 'dashboard' ? 'active' : '') . '">' . appIcon('dashboard') . '<span>Dashboard</span></a>
        <a href="events.php" class="app-menu-link ' . ($active === 'events' ? 'active' : '') . '">' . appIcon('events') . '<span>Events</span></a>
        <a href="create-event.php" class="app-menu-link ' . ($active === 'create-event' ? 'active' : '') . '">' . appIcon('create') . '<span>Create Event</span></a>
        <a href="' . h($attendanceLink) . '" class="app-menu-link ' . ($active === 'attendance' ? 'active' : '') . '">' . appIcon('attendance') . '<span>Attendance</span></a>
        <a href="' . h($qrLink) . '" class="app-menu-link ' . ($active === 'qr' ? 'active' : '') . '">' . appIcon('codes') . '<span>Live QR</span></a>
    </nav>
    <div class="app-sidebar-footer">
        <div class="app-profile">
            <a href="#" class="app-profile-main" data-open-profile>
                <div class="app-avatar">' . $profileAvatar . '</div>
                <div class="app-profile-text">
                    <div class="app-profile-name">' . h($userName) . '</div>
                </div>
            </a>
            <a href="logout.php" class="app-profile-logout" title="Logout">' . appIcon('logout') . '</a>
        </div>
    </div>
</aside>
<div class="app-content">
<div class="app-shell">
    <div class="app-topbar">
        <div class="app-topbar-brand">
            <img src="logo.png" alt="QR Attendance">
            <span>QR ATTENDANCE<br>EVENT</span>
        </div>
        <div class="app-topbar-content">
            <div class="app-topbar-desktop">' . $topbarContent . '</div>
            <button type="button" class="app-topbar-profile" data-open-profile aria-label="Open profile">
                <span class="app-avatar">' . $profileAvatar . '</span>
            </button>
        </div>
    </div>
    <main class="app-page">' . $flashMarkup;

    if ($showPageHead) {
        echo '<div class="app-page-head">
            <div>
                <h1>' . h($pageTitle) . '</h1>
                <p>' . h($pageSubtitle) . '</p>
            </div>
            <div class="app-page-actions">' . $pageActions . '</div>
        </div>';
    } elseif ($pageActions !== '') {
        echo '<div class="app-page-actions" style="justify-content:flex-end;margin-bottom:18px;">' . $pageActions . '</div>';
    }
}

function renderAppShellEnd($active = '')
{
    $mobileAttendanceLink = $GLOBALS['app_mobile_attendance_link'] ?? 'events.php';
    $mobileQrLink = $GLOBALS['app_mobile_qr_link'] ?? 'events.php';
    $profileAvatar = $GLOBALS['app_profile_avatar'] ?? 'U';
    $profileName = $GLOBALS['app_profile_name'] ?? '';
    $profileEmail = $GLOBALS['app_profile_email'] ?? '';
    $profilePhone = $GLOBALS['app_profile_phone'] ?? '';
    echo '<div class="app-mobile-nav">
        <a href="dashboard.php" class="app-mobile-link ' . ($active === 'dashboard' ? 'active' : '') . '">' . appIcon('dashboard') . '<span>Dashboard</span></a>
        <a href="events.php" class="app-mobile-link ' . ($active === 'events' ? 'active' : '') . '">' . appIcon('events') . '<span>Events</span></a>
        <a href="create-event.php" class="app-mobile-link ' . ($active === 'create-event' ? 'active' : '') . '">' . appIcon('create') . '<span>Create</span></a>
        <a href="' . h($mobileAttendanceLink) . '" class="app-mobile-link ' . ($active === 'attendance' ? 'active' : '') . '">' . appIcon('attendance') . '<span>Attendance</span></a>
        <a href="' . h($mobileQrLink) . '" class="app-mobile-link ' . ($active === 'qr' ? 'active' : '') . '">' . appIcon('codes') . '<span>Live QR</span></a>
    </div>
    </main>
</div>
</div>
 </div>
 <div class="app-profile-drawer-backdrop" data-close-profile></div>
 <aside class="app-profile-drawer" aria-hidden="true">
    <div class="app-drawer-head">
        <h3>My Account</h3>
        <button type="button" class="app-drawer-close" data-close-profile aria-label="Close profile">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
        </button>
    </div>
    <div class="app-drawer-body">
        <div class="app-drawer-card">
            <div class="app-drawer-profile">
                <div class="app-avatar">' . $profileAvatar . '</div>
                <div>
                    <strong>' . h($profileName) . '</strong>
                    <span>' . h($profileEmail) . '</span>
                    <span>' . h($profilePhone) . '</span>
                </div>
            </div>
        </div>
        <div class="app-drawer-card">
            <h4>Profile Details</h4>
            <form method="POST" enctype="multipart/form-data" class="app-drawer-form">
                <input type="hidden" name="app_action" value="update_profile">
                ' . appCsrfInput() . '
                <label>Full Name
                    <input type="text" name="profile_name" value="' . h($profileName) . '" required>
                </label>
                <label>Email
                    <input type="email" name="profile_email" value="' . h($profileEmail) . '" required>
                </label>
                <label>Phone Number
                    <input type="text" name="profile_phone" value="' . h($profilePhone) . '" required>
                </label>
                <label>Profile Picture
                    <input type="file" name="profile_image" accept="image/*">
                </label>
                <div class="app-drawer-actions">
                    <button type="submit" class="app-drawer-save">Save Profile</button>
                </div>
            </form>
        </div>
        <div class="app-drawer-card">
            <h4>Security</h4>
            <form method="POST" class="app-drawer-form">
                <input type="hidden" name="app_action" value="change_password">
                ' . appCsrfInput() . '
                <label>Current Password
                    <input type="password" name="current_password" required>
                </label>
                <label>New Password
                    <input type="password" name="new_password" required>
                </label>
                <label>Confirm New Password
                    <input type="password" name="confirm_password" required>
                </label>
                <div class="app-drawer-actions">
                    <button type="submit" class="app-drawer-save">Change Password</button>
                    <a href="logout.php" class="app-drawer-link">Logout</a>
                </div>
            </form>
        </div>
    </div>
 </aside>';

    echo '<script>
(function() {
    // ========== HISTORY DEFENSE ==========
    // Prevent back button access to protected pages
    window.history.pushState(null, "", window.location.href);
    window.onpopstate = function () {
        window.history.pushState(null, "", window.location.href);
        // // Force redirect to login if trying to go back
        // window.location.href = "login.php";
    };

    // ========== PROFILE DRAWER ==========
    const body = document.body;
    const openButtons = document.querySelectorAll("[data-open-profile]");
    const closeButtons = document.querySelectorAll("[data-close-profile]");
    const drawer = document.querySelector(".app-profile-drawer");

    const openDrawer = function() {
        body.classList.add("app-profile-open");
        if (drawer) {
            drawer.setAttribute("aria-hidden", "false");
        }
    };

    const closeDrawer = function() {
        body.classList.remove("app-profile-open");
        if (drawer) {
            drawer.setAttribute("aria-hidden", "true");
        }
    };

    openButtons.forEach(function(button) {
        button.addEventListener("click", openDrawer);
    });

    closeButtons.forEach(function(button) {
        button.addEventListener("click", closeDrawer);
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            closeDrawer();
        }
    });
})();
</script>';

    echo '</body>
</html>';
}
