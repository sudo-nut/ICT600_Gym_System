<?php
require_once "db.php";

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_role"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["user_role"] === "admin") {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];
$user_email = $_SESSION["user_email"];

// Get available membership plans
$plans = [];
$plans_query = "SELECT * FROM membership_plans ORDER BY price";
$plans_result = $conn->query($plans_query);
if ($plans_result && $plans_result->num_rows > 0) {
    while ($row = $plans_result->fetch_assoc()) {
        $plans[] = $row;
    }
} else {
    // Fallback if no plans in database
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

$error = "";
$success = "";

// Check if user already has a subscription
$existing_subscription_query = $conn->prepare("
    SELECT s.*, p.name as plan_name, p.price
    FROM subscriptions s
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.end_date DESC LIMIT 1
");
$existing_subscription_query->bind_param("i", $user_id);
$existing_subscription_query->execute();
$existing_subscription_result = $existing_subscription_query->get_result();
$existing_subscription = $existing_subscription_result->fetch_assoc();

$has_subscription = $existing_subscription !== null;
$is_active_subscription =
    $existing_subscription && $existing_subscription["status"] === "active";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plan_id"])) {
    $plan_id = intval($_POST["plan_id"] ?? 0);

    // Check if user already has an active subscription
    if ($is_active_subscription) {
        $error =
            "You already have an active subscription. You can upgrade your current plan or cancel it before selecting a new plan.";
    } elseif ($plan_id <= 0) {
        $error = "Please select a valid membership plan.";
    } else {
        // Get plan details
        $selected_plan = null;
        foreach ($plans as $plan) {
            if ($plan["id"] == $plan_id) {
                $selected_plan = $plan;
                break;
            }
        }

        if (!$selected_plan) {
            $error = "Selected plan not found.";
        } else {
            $conn->begin_transaction();

            try {
                if ($has_subscription) {
                    // Update existing subscription with new plan (keep status as is)
                    $update_query = $conn->prepare("
                        UPDATE subscriptions
                        SET plan_id = ?, start_date = CURDATE(), end_date = DATE_ADD(CURDATE(), INTERVAL ? DAY), status = 'pending'
                        WHERE user_id = ?
                        ORDER BY end_date DESC LIMIT 1
                    ");
                    $update_query->bind_param(
                        "iii",
                        $plan_id,
                        $selected_plan["duration_days"],
                        $user_id,
                    );
                    $update_query->execute();
                } else {
                    // Create new subscription with 'expired' status (payment required to activate)
                    $insert_query = $conn->prepare("
                        INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status)
                        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'pending')
                    ");
                    $insert_query->bind_param(
                        "iii",
                        $user_id,
                        $plan_id,
                        $selected_plan["duration_days"],
                    );
                    $insert_query->execute();
                }

                $conn->commit();

                // Redirect to payment page with plan price
                header(
                    "Location: mock_ipay88.php?user_id=" .
                        $user_id .
                        "&amount=" .
                        $selected_plan["price"],
                );
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to select plan: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Membership Plan - FitLife Gym</title>
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
                <li><a href="member_dashboard.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li><a href="select_plan.php" class="active"><i class="fas fa-calendar-check"></i> Select Plan</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(
                    $user_name,
                ); ?></span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-layout">
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-user-circle"></i> My Account</h3>
                </div>
                <nav class="sidebar-nav">
                    <a href="member_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="edit_profile.php">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                    <a href="select_plan.php" class="active">
                        <i class="fas fa-calendar-check"></i> Select Plan
                    </a>
                    <a href="upgrade_subscription.php">
                        <i class="fas fa-arrow-up"></i> Upgrade Subscription
                    </a>
                    <a href="payment_history.php">
                        <i class="fas fa-history"></i> Payment History
                    </a>
                    <a href="cancel_subscription.php">
                        <i class="fas fa-ban"></i> Cancel Subscription
                    </a>
                    <a href="delete_account.php">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </aside>

            <main class="main-content">
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
                    </div>
                <?php endif; ?>

                <h1><i class="fas fa-calendar-check"></i> Select Membership Plan</h1>

                <?php if ($has_subscription && $is_active_subscription): ?>
                    <div class="current-plan-notice" style="margin-bottom: 30px; padding: 20px; background-color: #d4edda; border-radius: var(--border-radius); border-left: 4px solid #28a745;">
                        <h3><i class="fas fa-info-circle"></i> Current Active Plan</h3>
                        <p>You currently have an active <strong><?php echo htmlspecialchars(
                            $existing_subscription["plan_name"],
                        ); ?></strong> subscription.</p>
                        <p>You need to cancel your current subscription before selecting a new plan.</p>
                    </div>
                <?php elseif ($has_subscription && !$is_active_subscription): ?>
                    <div class="expired-plan-notice" style="margin-bottom: 30px; padding: 20px; background-color: #fff3cd; border-radius: var(--border-radius); border-left: 4px solid #ffc107;">
                        <h3><i class="fas fa-exclamation-triangle"></i> Expired Subscription</h3>
                        <p>Your previous <strong><?php echo htmlspecialchars(
                            $existing_subscription["plan_name"],
                        ); ?></strong> subscription has expired.</p>
                        <p>Select a new plan and make payment to renew your gym membership.</p>
                    </div>
                <?php else: ?>
                    <div class="welcome-message" style="margin-bottom: 30px;">
                        <p>Welcome, <?php echo htmlspecialchars(
                            $user_name,
                        ); ?>! Choose your preferred membership plan below.</p>
                        <p>After selecting a plan, you'll be redirected to the secure payment page to activate your membership.</p>
                    </div>
                <?php endif; ?>

                <div class="plans-grid">
                    <?php foreach ($plans as $plan): ?>
                        <div class="plan-card <?php echo $plan["name"] ===
                        "Premium Plan"
                            ? "highlighted"
                            : ""; ?>">
                            <?php if ($plan["name"] === "Premium Plan"): ?>
                                <div class="badge">Most Popular</div>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars(
                                $plan["name"],
                            ); ?></h3>
                            <div class="plan-price">
                                RM <?php echo number_format(
                                    $plan["price"],
                                    2,
                                ); ?>
                                <span>
                                    <?php if ($plan["duration_days"] == 365): ?>
                                        /year
                                    <?php else: ?>
                                        /<?php echo $plan[
                                            "duration_days"
                                        ]; ?> days
                                    <?php endif; ?>
                                </span>
                            </div>

                            <ul class="plan-features">
                                <?php if ($plan["name"] === "Basic Plan"): ?>
                                    <li><i class="fas fa-check"></i> Basic gym access</li>
                                    <li><i class="fas fa-check"></i> Cardio area</li>
                                    <li><i class="fas fa-check"></i> Locker room access</li>
                                    <li><i class="fas fa-check"></i> Limited operating hours</li>
                                <?php elseif (
                                    $plan["name"] === "Premium Plan"
                                ): ?>
                                    <li><i class="fas fa-check"></i> Everything in Basic Plan</li>
                                    <li><i class="fas fa-check"></i> Weight training area</li>
                                    <li><i class="fas fa-check"></i> Extended hours access</li>
                                    <li><i class="fas fa-check"></i> Free towel service</li>
                                <?php elseif ($plan["name"] === "VIP Plan"): ?>
                                    <li><i class="fas fa-check"></i> Everything in Premium Plan</li>
                                    <li><i class="fas fa-check"></i> 24/7 gym access</li>
                                    <li><i class="fas fa-check"></i> Personal locker reservation</li>
                                    <li><i class="fas fa-check"></i> Priority customer support</li>
                                    <li><i class="fas fa-check"></i> Save RM 50 annually</li>
                                <?php endif; ?>
                            </ul>

                            <form method="POST" action="" style="margin-top: 20px;">
                                <input type="hidden" name="plan_id" value="<?php echo $plan[
                                    "id"
                                ]; ?>">
                                <button type="submit" class="btn btn-primary" <?php echo $is_active_subscription
                                    ? "disabled"
                                    : ""; ?>>
                                    <i class="fas fa-check"></i> <?php echo $is_active_subscription
                                        ? "Active Subscription Exists"
                                        : "Select This Plan"; ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="plan-selection-info" style="margin-top: 40px; padding: 25px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                    <h3><i class="fas fa-info-circle"></i> Plan Selection Process</h3>
                    <ol style="margin: 15px 0; padding-left: 20px;">
                        <li><strong>Select your plan</strong> - Choose from Basic, Premium, or VIP membership</li>
                        <li><strong>Secure payment</strong> - You'll be redirected to our secure payment gateway</li>
                        <li><strong>Instant activation</strong> - Your membership will be activated immediately after payment</li>
                        <li><strong>Access gym</strong> - Use your member QR code for gym access</li>
                    </ol>
                    <p style="margin-top: 15px; color: #666;">
                        <i class="fas fa-shield-alt"></i> All payments are secured with 256-bit SSL encryption.
                    </p>
                </div>

                <div class="contact-support" style="margin-top: 40px;">
                    <h3><i class="fas fa-headset"></i> Need Help Choosing?</h3>
                    <p>Our support team can help you select the best plan for your fitness goals.</p>
                    <div class="contact-options">
                        <div class="contact-option">
                            <i class="fas fa-phone"></i>
                            <h4>Call Us</h4>
                            <p>+60 12-345 6789</p>
                        </div>
                        <div class="contact-option">
                            <i class="fas fa-envelope"></i>
                            <h4>Email Us</h4>
                            <p>support@fitlifegym.com</p>
                        </div>
                        <div class="contact-option">
                            <i class="fas fa-clock"></i>
                            <h4>Support Hours</h4>
                            <p>Mon-Fri: 9AM-6PM</p>
                            <p>Sat: 9AM-1PM</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-dumbbell"></i> FitLife Gym</h3>
                    <p>Your journey to a healthier lifestyle starts here.</p>
                </div>
                <div class="footer-section">
                    <h3>Member Services</h3>
                    <ul>
                        <li><a href="member_dashboard.php">Profile Dashboard</a></li>
                        <li><a href="select_plan.php">Select Plan</a></li>
                        <li><a href="edit_profile.php">Edit Profile</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Support</h3>
                    <p><i class="fas fa-headset"></i> Member Support: +60 12-345 6789</p>
                    <p><i class="fas fa-envelope"></i> support@fitlifegym.com</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM, Sat: 9AM-1PM</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date(
                    "Y",
                ); ?> FitLife Gym Membership System. Member ID: M<?php echo str_pad(
     $user_id,
     5,
     "0",
     STR_PAD_LEFT,
 ); ?></p>
            </div>
        </div>
    </footer>
</body>
</html>
