<?php 
session_start();
include("includes/db.php");

$message = "";
$message_type = "";

if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            // Save session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];

            // Redirect
            header("Location: dashboard.php");
            exit();

        } else {
            $message = "Wrong password!";
            $message_type = "error";
        }

    } else {
        $message = "User not found!";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/style.css">
</head>

<body>

<div class="container">
    <div class="logo">
        <img src="logo.png" alt="Logo">
    </div>
    <h2>Welcome!</h2>
    <p class="subtitle">Please login to continue</p>

    <div class="msg msg-<?php echo $message_type; ?>"><?php echo $message; ?></div>

    <form method="POST">
        <input type="email" name="email" placeholder="📧 Email" required>
        <input type="password" name="password" placeholder="🔒 Password" required>
        <button type="submit" name="login" class="btn-login">Login →</button>
    </form>

    <div class="divider"><span>OR</span></div>

    <div class="auth-link">Don't have an account? <a href="registrer.php">Register</a></div>
</div>

</body>
</html>
