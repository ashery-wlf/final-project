<?php 
include("includes/db.php");

$message = "";
$message_type = "";

if (isset($_POST['register'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($_POST['password'])) {
        $message = "All fields are required!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
        $message_type = "error";
    } else {
        // Check if email exists
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");

        if ($check->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            $sql = "INSERT INTO users (name, email, phone, password)
                    VALUES ('$name', '$email', '$phone', '$password')";

            if ($conn->query($sql)) {
                $message = "Registered successfully! Redirecting to login...";
                $message_type = "success";
                header("refresh:2; url=login.php");
            } else {
                $message = "Error occurred: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/style.css">
</head>

<body>

<div class="container">
    <div class="logo">
        <img src="logo.png" alt="Logo">
    </div>
    <h2>Welcome!</h2>
    <p class="subtitle">Please register to get started</p>

    <div class="msg msg-<?php echo $message_type; ?>"><?php echo $message; ?></div>

    <form method="POST">
        <input type="text" name="name" placeholder="👤 Full Name" required>
        <input type="email" name="email" placeholder="📧 Email" required>
        <input type="tel" name="phone" placeholder="📱 Phone Number" required>
        <input type="password" name="password" placeholder="🔒 Password" required>
        <button type="submit" name="register" class="btn-register">Register →</button>
    </form>

    <div class="divider"><span>OR</span></div>

    <div class="auth-link">Already have account? <a href="login.php">Login</a></div>
</div>

</body>
</html>