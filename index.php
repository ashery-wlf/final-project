<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Attendance System - Modern Event Attendance Tracking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Modern QR code-based attendance tracking system for events and organizations">
    
    <!-- Disable caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <link rel="stylesheet" href="includes/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Landing Page Styles */
        :root {
            --primary: #ffffff98;
            --primary-dark: #2b0f0f98;
            --secondary: #251a1ad8;
            --dark: #371f1f;
            --light: #F9FAFB;
            --white: #FFFFFF;
            --gradient: linear-gradient(135deg, #301616 0%, #3f3e3e 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #1a1a1a;
            min-height: 100vh;
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Navigation */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.49);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--secondary);
        }

        .nav-logo img {
            width: 70px;
            height: 60px;
            border-radius: 8px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: color 0.3s ease;
            letter-spacing: -0.01em;
        }

        .nav-links a:hover {
            color: var(--secondary);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--secondary);
            color: rgba(255, 255, 255, 0.97);
        }

        .btn-primary:hover {
            background: rgba(36, 10, 10, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(229, 70, 70, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--secondary);
            color: white;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: relative;
            align-items: center;
            padding: 6rem 2rem 2rem;
            position: relative;
            background: linear-gradient(135deg, #482d2d 0%, #2c1a1a 100%);
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero-content p {
            font-size: 1.25rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.95);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-image {
            width: 100%;
            max-width: 500px;
            height: auto;
            border-radius: 1rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        /* Features Section */
        .features {
            padding: 5rem 2rem;
            background: white;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--dark);
            letter-spacing: -0.02em;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--light);
            padding: 2rem;
            border-radius: 1rem;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .feature-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark);
            letter-spacing: -0.01em;
        }

        .feature-card p {
            color: #6B7280;
            line-height: 1.6;
            font-size: 1rem;
            margin: 0;
        }

        /* CTA Section */
        .cta {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, #0f0c0c 0%, #200000 100%);
            text-align: center;
            color: white;
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.02em;
        }

        .cta p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                color: var(--dark);
                font-size: 1.5rem;
                cursor: pointer;
            }

            .hero-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2.5rem;
                letter-spacing: -0.02em;
            }

            .hero-buttons {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav>
        <div class="nav-container">
            <div class="nav-logo">
                <img src="logo.png" alt="QR Attendance">
                <span>QR Attendance</span>
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#" class="btn ">FAQ</a>
                <a href="#" class="btn ">Help</a>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Modern QR Attendance System</h1>
                <p>Transform your event management with smart QR code-based attendance
                     tracking. Fast, reliable, and contactless.</p>
                <div class="hero-buttons">
                    <a href="registrer.php" class="btn btn-primary">
                        <i class="fas fa-rocket"></i>
                        Get Started
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <img src="pchs.jpg" alt="QR Attendance System" class="hero-image">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="features-container">
            <h2>Powerful Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>QR Code Scanning</h3>
                    <p>Fast and secure check-in process using QR codes. No more manual attendance tracking.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Real-time Analytics</h3>
                    <p>Track attendance patterns and insights with comprehensive reporting dashboards.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Works seamlessly on all devices - smartphones, tablets, and desktop computers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-container">
            <h2>Ready to Transform Your Events?</h2>
            <p>Join thousands of organizations using QR Attendance System for efficient event management</p>
            <div class="hero-buttons" style="justify-content: center;">
                <a href="registrer.php" class="btn btn-primary">
                    <i class="fas fa-rocket"></i>
                    Start Now
                </a>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            alert('Mobile menu would open here');
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 50) {
                nav.style.background = 'rgba(255, 255, 255, 0.98)';
                nav.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
            } else {
                nav.style.background = 'rgba(255, 255, 255, 0.95)';
                nav.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
