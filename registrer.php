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
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $message = "Passwords do not match!";
        $message_type = "error";
    } else {
        // Check if email exists
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check = $check_stmt->get_result();

        if ($check->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            $sql = "INSERT INTO users (name, email, phone, password)
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $phone, $password);
            
            if ($stmt->execute()) {
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
<html lang="en">
<head>
    <title>Register - QR Attendance</title>
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
                <p class="auth-subtitle">Create your account</p>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="name" class="form-label">
                        <i class="fas fa-user"></i>
                        Full Name
                    </label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone"></i>
                        Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-input" placeholder="Enter your phone number" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" class="form-input" placeholder="Create a password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Confirm Password
                    </label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" onclick="toggleConfirmPassword()">
                            <i class="fas fa-eye" id="confirm-password-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-primary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <!-- Footer -->
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php" class="auth-link">Sign in here</a></p>
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

        function toggleConfirmPassword() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const confirmPasswordIcon = document.getElementById('confirm-password-icon');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                confirmPasswordIcon.classList.remove('fa-eye');
                confirmPasswordIcon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                confirmPasswordIcon.classList.remove('fa-eye-slash');
                confirmPasswordIcon.classList.add('fa-eye');
            }
        }

        // Test password functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Password toggle functionality loaded');
            
            // Test if elements exist
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const confirmPasswordIcon = document.getElementById('confirm-password-icon');
            
            if (passwordInput && passwordIcon) {
                console.log('Password field elements found');
            }
            
            if (confirmPasswordInput && confirmPasswordIcon) {
                console.log('Confirm password field elements found');
            }
        });
    </script>
</body>
</html>