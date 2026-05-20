<?php 
session_start();

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

include("includes/db.php");

$message = "";
$message_type = "";

if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        $storedPassword = $user['password'] ?? ($user['password_hash'] ?? '');

        if (password_verify($password, $storedPassword)) {

            session_regenerate_id(true);

            // Save session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
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
<html lang="en">
<head>
    <title>Login - QR Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="white-background"></div>

    <div class="simple-auth-container">
        <div class="auth-card">
            <!-- Logo Section -->
            <div class="auth-header">
                <div class="logo-container">
                    <img src="logo.png" alt="QR Attendance" class="logo">
                </div>
                <h1 class="auth-title">QR Attendance</h1>
                <p class="auth-subtitle">Sign in to track attendance</p>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <!-- Footer -->
            <div class="auth-footer">
                <p>Don't have an account? <a href="registrer.php" class="auth-link">Register here</a></p>
                <p><a href="index.php" class="auth-link">← Back to Home</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        // Test password functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Password toggle functionality loaded');
            
            // Test if elements exist
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput && passwordIcon) {
                console.log('Password field elements found');
                
                // Test toggle functionality
                passwordIcon.addEventListener('click', function() {
                    console.log('Password toggle clicked');
                    console.log('Input type:', passwordInput.type);
                });
            } else {
                console.error('Password field elements not found');
            }
        });
    </script>
</body>
</html>
