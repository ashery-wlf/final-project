<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session completely
session_destroy();

// Delete session cookie (kung ito ang session storage)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: login.php");
exit;
?>