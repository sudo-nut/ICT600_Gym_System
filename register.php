<?php
require_once "db.php";

$error = "";
$success = "";
$name = $email = $phone = "";

$plans = [];
$plans_query = "SELECT * FROM membership_plans ORDER BY price";
$plans_result = $conn->query($plans_query);
if ($plans_result && $plans_result->num_rows > 0) {
    while ($row = $plans_result->fetch_assoc()) {
        $plans[] = $row;
    }
} else {
    $plans = [
        [
            "id" => 1,
            "name" => "Basic Plan",
            "price" => 30.0,
            "duration_days" => 30,
        ],
        [
            "id" => 2,
            "name" => "Premium Plan",
            "price" => 50.0,
            "duration_days" => 30,
        ],
        [
            "id" => 3,
            "name" => "VIP Plan",
            "price" => 550.0,
            "duration_days" => 365,
        ],
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $phone = trim($_POST["phone"] ?? "");

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            $error =
                "Email already registered. Please use a different email or login.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();

            try {
                $insert_user = $conn->prepare(
                    "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'member')",
                );
                $insert_user->bind_param(
                    "ssss",
                    $name,
                    $email,
                    $hashed_password,
                    $phone,
                );
                $insert_user->execute();
                $user_id = $conn->insert_id;

                $conn->commit();

                $success =
                    "Registration successful! Your account has been created. Please login to select a membership plan and make payment.";
                $name = $email = $phone = "";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
        $check_email->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FitLife Gym</title>
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
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="register.php" class="active"><i class="fas fa-user-plus"></i> Register</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <div class="auth-container">
            <div class="auth-card">
                <h2 class="auth-title"><i class="fas fa-user-plus"></i> Create Your Account</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars(
                            $error,
                        ); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(
                            $success,
                        ); ?>
                        <p>Redirecting to login page in 5 seconds... <a href="login.php">Login now</a></p>
                        <script>
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 5000);
                        </script>
                    </div>
                <?php endif; ?>

                <?php if (empty($success)): ?>
                <form method="POST" action="" class="auth-form">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars(
                            $name,
                        ); ?>" required placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(
                            $email,
                        ); ?>" required placeholder="Enter your email">
                    </div>

                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars(
                            $phone,
                        ); ?>" placeholder="Enter your phone number">
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" id="password" name="password" required placeholder="At least 6 characters">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                    </div>

                    <div class="form-group">
                        <div class="plan-description">
                            <p><i class="fas fa-info-circle"></i> After registration, you can login to select a membership plan and make payment to activate your gym access.</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>

                    <div class="auth-footer">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <div class="auth-info">
                <h3><i class="fas fa-shield-alt"></i> Secure Registration</h3>
                <p>Your information is protected with industry-standard security measures.</p>

                <h3><i class="fas fa-check-circle"></i> Benefits of Registration</h3>
                <ul class="benefits-list">
                    <li><i class="fas fa-check"></i> Create your member account first</li>
                    <li><i class="fas fa-check"></i> Choose your plan after login</li>
                    <li><i class="fas fa-check"></i> Easy online payment activation</li>
                    <li><i class="fas fa-check"></i> Access to member dashboard</li>
                </ul>

                <div class="plan-cards-mini">
                    <div class="plan-card-mini">
                        <h4>Register First</h4>
                        <div class="price">Free</div>
                        <p>Create your account</p>
                    </div>
                    <div class="plan-card-mini">
                        <h4>Choose Plan</h4>
                        <div class="price">After Login</div>
                        <p>Select from 3 tiers</p>
                    </div>
                    <div class="plan-card-mini">
                        <h4>Make Payment</h4>
                        <div class="price">Secure</div>
                        <p>Activate membership</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
