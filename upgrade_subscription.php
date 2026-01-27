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

// Get current active subscription
$current_subscription_query = $conn->prepare("
    SELECT s.*, p.name as plan_name, p.price as plan_price, p.duration_days
    FROM subscriptions s
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.end_date DESC LIMIT 1
");
$current_subscription_query->bind_param("i", $user_id);
$current_subscription_query->execute();
$current_subscription_result = $current_subscription_query->get_result();
$current_subscription = $current_subscription_result->fetch_assoc();

// Check if user has active subscription
$has_active_subscription =
    $current_subscription && $current_subscription["status"] === "active";
$current_plan_id = $current_subscription["plan_id"] ?? 0;
$current_plan_name = $current_subscription["plan_name"] ?? "No Active Plan";
$current_plan_price = $current_subscription["plan_price"] ?? 0;
$current_end_date = $current_subscription["end_date"] ?? null;
$current_start_date = $current_subscription["start_date"] ?? null;

// Get all membership plans
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

// Filter upgrade options (only plans with higher price than current)
$upgrade_options = [];
if ($has_active_subscription && $current_end_date) {
    foreach ($plans as $plan) {
        // Only show plans that are more expensive than current plan (upgrade only)
        if ($plan["price"] > $current_plan_price) {
            // Calculate remaining days in current subscription
            $today = new DateTime();
            $end_date = new DateTime($current_end_date);
            if ($today > $end_date) {
                $remaining_days = 0;
            } else {
                $interval = $today->diff($end_date);
                $remaining_days = $interval->days;
            }

            // Calculate total days in current subscription
            $start_date = new DateTime($current_start_date);
            $total_days_interval = $start_date->diff($end_date);
            $total_days = $total_days_interval->days;

            if ($total_days <= 0) {
                $total_days = 1; // Prevent division by zero
            }

            // Calculate unused value of current subscription (prorated)
            $daily_rate_current = $current_plan_price / $total_days;
            $unused_value = $daily_rate_current * $remaining_days;

            // Calculate upgrade cost: full new plan price minus unused value from current plan
            $upgrade_cost = $plan["price"] - $unused_value;

            // Ensure upgrade cost is at least 0
            if ($upgrade_cost < 0) {
                $upgrade_cost = 0;
            }

            // Add to upgrade options
            $plan["upgrade_cost"] = round($upgrade_cost, 2);
            $plan["remaining_days"] = $remaining_days;
            $plan["unused_value"] = round($unused_value, 2);
            $upgrade_options[] = $plan;
        }
    }
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["upgrade_plan_id"])) {
    $upgrade_plan_id = intval($_POST["upgrade_plan_id"] ?? 0);

    if (!$has_active_subscription) {
        $error =
            "You don't have an active subscription to upgrade. Please select a new plan instead.";
    } elseif ($upgrade_plan_id <= 0) {
        $error = "Please select a valid upgrade plan.";
    } else {
        // Find the selected upgrade plan
        $selected_upgrade_plan = null;
        foreach ($upgrade_options as $plan) {
            if ($plan["id"] == $upgrade_plan_id) {
                $selected_upgrade_plan = $plan;
                break;
            }
        }

        if (!$selected_upgrade_plan) {
            $error =
                "Selected upgrade plan not found or is not a valid upgrade option.";
        } else {
            $conn->begin_transaction();

            try {
                // Create new pending subscription for upgrade
                $insert_query = $conn->prepare("
                INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status)
                VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'pending')
            ");
                $insert_query->bind_param(
                    "iii",
                    $user_id,
                    $upgrade_plan_id,
                    $selected_upgrade_plan["duration_days"],
                );

                if ($insert_query->execute()) {
                    $conn->commit();

                    // Redirect to payment page with upgrade cost
                    header(
                        "Location: mock_ipay88.php?user_id=" .
                            $user_id .
                            "&amount=" .
                            $selected_upgrade_plan["upgrade_cost"] .
                            "&upgrade=1",
                    );
                    exit();
                } else {
                    throw new Exception(
                        "Failed to process upgrade. Please try again.",
                    );
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Upgrade failed: " . $e->getMessage();
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
    <title>Upgrade Subscription - FitLife Gym</title>
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
                <li><a href="upgrade_subscription.php" class="active"><i class="fas fa-arrow-up"></i> Upgrade</a></li>
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
                    <a href="select_plan.php">
                        <i class="fas fa-calendar-check"></i> Select Plan
                    </a>
                    <a href="upgrade_subscription.php" class="active">
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
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <h1><i class="fas fa-arrow-up"></i> Upgrade Subscription</h1>

                <?php if (!$has_active_subscription): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        You don't have an active subscription to upgrade.
                        <a href="select_plan.php" class="alert-link">Select a new plan instead</a>.
                    </div>

                    <div class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Get Started with a New Membership</h3>
                            <p>Choose from our range of membership plans to start your fitness journey.</p>
                            <a href="select_plan.php" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Select a New Plan
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="welcome-message">Upgrade your current membership to enjoy more benefits and features.</p>

                    <div class="profile-section">
                        <h2><i class="fas fa-id-card"></i> Current Membership</h2>
                        <div class="profile-details">
                            <div class="detail-item">
                                <span class="detail-label">Current Plan:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(
                                    $current_plan_name,
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Current Plan Price:</span>
                                <span class="detail-value">RM <?php echo number_format(
                                    $current_plan_price,
                                    2,
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Subscription End Date:</span>
                                <span class="detail-value"><?php echo date(
                                    "F j, Y",
                                    strtotime($current_end_date),
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Remaining Days:</span>
                                <span class="detail-value">
                                    <?php if ($current_end_date) {
                                        $today = new DateTime();
                                        $end_date = new DateTime(
                                            $current_end_date,
                                        );
                                        if ($today > $end_date) {
                                            echo "0 days (expired)";
                                        } else {
                                            $interval = $today->diff($end_date);
                                            echo $interval->days . " days";
                                        }
                                    } else {
                                        echo "N/A";
                                    } ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h2><i class="fas fa-arrow-up"></i> Available Upgrades</h2>
                        <p class="form-hint">Upgrade your current plan to enjoy more benefits. Prorated pricing applies based on your remaining subscription days.</p>

                        <?php if (empty($upgrade_options)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                You're already on our highest-tier plan! No upgrade options available.
                            </div>
                        <?php else: ?>
                            <div class="plans-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                                <?php foreach ($upgrade_options as $plan): ?>
                                    <div class="plan-card <?php echo strtolower(
                                        str_replace(" ", "-", $plan["name"]),
                                    ); ?>">
                                        <div class="plan-header">
                                            <h3><?php echo htmlspecialchars(
                                                $plan["name"],
                                            ); ?></h3>
                                            <div class="plan-price">
                                                <span class="price">RM <?php echo number_format(
                                                    $plan["price"],
                                                    2,
                                                ); ?></span>
                                                <span class="duration">/ <?php echo $plan[
                                                    "duration_days"
                                                ]; ?> days</span>
                                            </div>
                                        </div>

                                        <div class="plan-features">
                                            <ul>
                                                <?php if (
                                                    $plan["name"] ==
                                                    "Premium Plan"
                                                ): ?>
                                                    <li><i class="fas fa-check"></i> Extended access hours</li>
                                                    <li><i class="fas fa-check"></i> Group class access</li>
                                                    <li><i class="fas fa-check"></i> Personal trainer discounts</li>
                                                    <li><i class="fas fa-check"></i> Locker room access</li>
                                                    <li><i class="fas fa-check"></i> Nutritional guidance</li>
                                                <?php elseif (
                                                    $plan["name"] == "VIP Plan"
                                                ): ?>
                                                    <li><i class="fas fa-check"></i> 24/7 gym access</li>
                                                    <li><i class="fas fa-check"></i> Unlimited group classes</li>
                                                    <li><i class="fas fa-check"></i> Free personal trainer sessions</li>
                                                    <li><i class="fas fa-check"></i> Premium locker room</li>
                                                    <li><i class="fas fa-check"></i> Nutritional consultations</li>
                                                    <li><i class="fas fa-check"></i> Guest passes monthly</li>
                                                    <li><i class="fas fa-check"></i> Priority booking</li>
                                                    <li><i class="fas fa-check"></i> Annual commitment savings</li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>

                                        <div class="upgrade-details">
                                            <div class="upgrade-cost-breakdown">
                                                <h4><i class="fas fa-calculator"></i> Upgrade Cost Calculation</h4>
                                                <div class="breakdown-item">
                                                    <span>Remaining days:</span>
                                                    <span><?php echo $plan[
                                                        "remaining_days"
                                                    ]; ?> days</span>
                                                </div>
                                                <div class="breakdown-item">
                                                    <span>Unused value:</span>
                                                    <span>RM <?php echo number_format(
                                                        $plan["unused_value"],
                                                        2,
                                                    ); ?></span>
                                                </div>
                                                <div class="breakdown-item">
                                                    <span>New plan price:</span>
                                                    <span>RM <?php echo number_format(
                                                        $plan["price"],
                                                        2,
                                                    ); ?></span>
                                                </div>
                                                <div class="breakdown-item total">
                                                    <span><strong>Amount to pay:</strong></span>
                                                    <span><strong>RM <?php echo number_format(
                                                        $plan["upgrade_cost"],
                                                        2,
                                                    ); ?></strong></span>
                                                </div>
                                            </div>
                                        </div>

                                        <form method="POST" action="" class="upgrade-form">
                                            <input type="hidden" name="upgrade_plan_id" value="<?php echo $plan[
                                                "id"
                                            ]; ?>">
                                            <button type="submit" class="btn btn-upgrade btn-block">
                                                <i class="fas fa-arrow-up"></i> Upgrade Now
                                            </button>
                                            <p class="form-hint" style="margin-top: 10px; font-size: 0.85rem;">
                                                <i class="fas fa-info-circle"></i>
                                                After payment, your subscription will be upgraded and extended to <?php echo $plan[
                                                    "duration_days"
                                                ]; ?> days from today. Upgrade cost is calculated as the new plan price minus the prorated unused value of your current subscription.
                                            </p>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-section">
                        <h2><i class="fas fa-question-circle"></i> How Upgrading Works</h2>
                        <div class="how-it-works">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>Select Upgrade Plan</h4>
                                    <p>Choose a higher-tier plan that suits your fitness goals.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>Prorated Pricing</h4>
                                    <p>Pay only for the upgrade difference based on your remaining subscription days.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>Immediate Activation</h4>
                                    <p>Your upgraded benefits become available immediately after payment.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h4>Extended Duration</h4>
                                    <p>Your subscription is renewed for the full duration of the new plan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="contact-support" style="margin-top: 40px;">
                    <h3><i class="fas fa-headset"></i> Need Assistance with Upgrading?</h3>
                    <p>Our support team can help you choose the right upgrade option for your needs.</p>
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
                        <li><a href="upgrade_subscription.php">Upgrade Subscription</a></li>
                        <li><a href="index.php">Home</a></li>
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

    <style>
        .plan-card {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--light-gray);
            overflow: hidden;
            transition: var(--transition);
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .plan-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .plan-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }

        .plan-price {
            margin-top: 10px;
        }

        .plan-price .price {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .plan-price .duration {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .plan-features {
            padding: 20px;
        }

        .plan-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .plan-features li:last-child {
            border-bottom: none;
        }

        .plan-features li i {
            color: var(--primary-color);
            margin-right: 10px;
        }

        .upgrade-details {
            padding: 0 20px;
        }

        .upgrade-cost-breakdown {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--light-gray);
        }

        .upgrade-cost-breakdown h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        .breakdown-item.total {
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--primary-color);
        }

        .upgrade-form {
            padding: 20px;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
        }

        .btn-upgrade:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .how-it-works {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .step-content h4 {
            margin: 0 0 5px 0;
            color: var(--dark-color);
        }

        .step-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .alert-link {
            color: inherit;
            text-decoration: underline;
            font-weight: bold;
        }

        .alert-link:hover {
            text-decoration: none;
        }
    </style>
</body>
</html>
