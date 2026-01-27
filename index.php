<?php
require_once "db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitLife Gym - Membership System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-dumbbell"></i> FitLife Gym</h1>
            </div>
            <ul class="nav-links">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
            </ul>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Transform Your Body, Transform Your Life</h1>
                <p>Join our premier gym with state-of-the-art equipment and flexible membership plans designed for your fitness journey. Get started with easy online registration.</p>
                <a href="register.php" class="btn btn-primary">
                    <i class="fas fa-running"></i> Join Now
                </a>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Member Login
                </a>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Gym workout">
            </div>
        </div>
    </header>

    <section class="features">
        <div class="container">
            <h2 class="section-title">Why Choose FitLife Gym?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Flexible Memberships</h3>
                    <p>Choose from three tiers (Basic, Premium, VIP) with durations from 30 days to 1 year. Easy online registration and secure payment processing.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Modern Equipment</h3>
                    <p>Access to the latest cardio machines, strength training equipment, and functional training areas to support your fitness goals.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure Management</h3>
                    <p>Your membership data is protected with advanced security. Manage your subscription and payments safely through our portal.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Easy Management</h3>
                    <p>Manage your membership, check subscription status, and make payments through our user-friendly online portal anytime.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="plans">
        <div class="container">
            <h2 class="section-title">Membership Plans</h2>
            <div class="plans-grid">
                <div class="plan-card">
                    <h3>Basic Plan</h3>
                    <div class="plan-price">RM 30<span>/30 days</span></div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> Basic gym access</li>
                        <li><i class="fas fa-check"></i> Cardio area</li>
                        <li><i class="fas fa-check"></i> Locker room access</li>
                        <li><i class="fas fa-check"></i> Limited operating hours</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary">Select Plan</a>
                </div>
                <div class="plan-card">
                    <div class="badge">Most Popular</div>
                    <h3>Premium Plan</h3>
                    <div class="plan-price">RM 50<span>/30 days</span></div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> Everything in Basic Plan</li>
                        <li><i class="fas fa-check"></i> Weight training area</li>
                        <li><i class="fas fa-check"></i> Extended hours access</li>
                        <li><i class="fas fa-check"></i> Free towel service</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary">Select Plan</a>
                </div>
                <div class="plan-card">
                    <h3>VIP Plan</h3>
                    <div class="plan-price">RM 550<span>/year</span></div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> Everything in Premium Plan</li>
                        <li><i class="fas fa-check"></i> 24/7 gym access</li>
                        <li><i class="fas fa-check"></i> Personal locker reservation</li>
                        <li><i class="fas fa-check"></i> Priority customer support</li>
                        <li><i class="fas fa-check"></i> Save RM 50 annually</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary">Select Plan</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-dumbbell"></i> FitLife Gym</h3>
                    <p>Your journey to a healthier lifestyle starts here.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Fitness Street, Kuala Lumpur</p>
                    <p><i class="fas fa-phone"></i> +60 12-345 6789</p>
                    <p><i class="fas fa-envelope"></i> info@fitlifegym.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date(
                    "Y",
                ); ?> FitLife Gym Membership System. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
