<?php
require_once "includes/db.php";

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

// Get current subscription information
$subscription_query = $conn->prepare("
    SELECT s.*, p.name as plan_name, p.price, p.duration_days
    FROM subscriptions s
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.end_date DESC LIMIT 1
");
$subscription_query->bind_param("i", $user_id);
$subscription_query->execute();
$subscription_result = $subscription_query->get_result();
$subscription = $subscription_result->fetch_assoc();

$has_active_subscription =
    $subscription && $subscription["status"] === "active";
$message = "";
$message_type = "";
$cancellation_successful = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["confirm_cancel"])) {
    $confirmation = $_POST["confirmation"] ?? "";

    if (empty($confirmation) || strtolower($confirmation) !== "cancel") {
        $message = "Please type 'cancel' in the confirmation box to proceed.";
        $message_type = "error";
    } elseif (!$has_active_subscription) {
        $message = "You don't have an active subscription to cancel.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();

        try {
            // Update subscription status to 'expired'
            $update_query = $conn->prepare("
                UPDATE subscriptions
                SET status = 'expired', end_date = CURDATE()
                WHERE user_id = ? AND status = 'active'
            ");
            $update_query->bind_param("i", $user_id);

            if ($update_query->execute()) {
                $conn->commit();
                $cancellation_successful = true;
                $has_active_subscription = false;
                $message =
                    "Your subscription has been successfully cancelled. You will no longer have gym access.";
                $message_type = "success";
            } else {
                throw new Exception(
                    "Failed to cancel subscription. Please try again.",
                );
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Subscription - FitLife Gym</title>
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
                <li><a href="cancel_subscription.php" class="active"><i class="fas fa-ban"></i> Cancel Subscription</a></li>
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
                    <a href="upgrade_subscription.php">
                        <i class="fas fa-arrow-up"></i> Upgrade Subscription
                    </a>
                    <a href="payment_history.php">
                        <i class="fas fa-history"></i> Payment History
                    </a>
                    <a href="cancel_subscription.php" class="active">
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
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == "success"
                            ? "check-circle"
                            : "exclamation-circle"; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <h1><i class="fas fa-ban"></i> Cancel Subscription</h1>

                <?php if ($cancellation_successful): ?>
                    <div class="cancellation-success">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2>Subscription Cancelled</h2>
                        <p>Your subscription has been successfully cancelled. You will no longer have access to the gym facilities.</p>
                        <p>If you change your mind, you can always renew your membership from your dashboard.</p>
                        <div style="margin-top: 30px;">
                            <a href="member_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Return to Dashboard
                            </a>
                        </div>
                    </div>
                <?php elseif (!$has_active_subscription): ?>
                    <div class="no-subscription">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2>No Active Subscription</h2>
                        <p>You don't have an active subscription to cancel.</p>
                        <p>If you'd like to join FitLife Gym, you can select a membership plan from our registration page.</p>
                        <div style="margin-top: 30px;">
                            <a href="register.php" class="btn btn-primary">
                                <i class="fas fa-running"></i> Join FitLife Gym
                            </a>
                            <a href="member_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-tachometer-alt"></i> Return to Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="cancellation-warning">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2>Cancel Your Subscription</h2>
                        <p>You are about to cancel your active subscription. Please read the following information carefully:</p>

                        <div class="subscription-details" style="margin: 25px 0; padding: 20px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                            <h3><i class="fas fa-id-card"></i> Current Subscription Details</h3>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <span style="font-weight: 600;">Plan:</span>
                                <span><?php echo htmlspecialchars(
                                    $subscription["plan_name"],
                                ); ?></span>
                            </div>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <span style="font-weight: 600;">Expiry Date:</span>
                                <span><?php echo date(
                                    "F j, Y",
                                    strtotime($subscription["end_date"]),
                                ); ?></span>
                            </div>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0;">
                                <span style="font-weight: 600;">Status:</span>
                                <span class="status-badge status-active">Active</span>
                            </div>
                        </div>

                        <div class="cancellation-consequences" style="margin: 25px 0; padding: 20px; background-color: #fff3cd; border-radius: var(--border-radius); border-left: 4px solid #ffc107;">
                            <h3><i class="fas fa-exclamation-circle"></i> What happens when you cancel:</h3>
                            <ul style="margin: 15px 0; padding-left: 20px;">
                                <li>Your gym access will be immediately revoked</li>
                                <li>No refunds will be issued for unused time</li>
                                <li>You will need to purchase a new subscription to regain access</li>
                                <li>Your membership QR code will become invalid</li>
                            </ul>
                        </div>

                        <form method="POST" action="" style="margin-top: 30px;">
                            <div class="form-group">
                                <label for="confirmation">
                                    <i class="fas fa-check-circle"></i> Type "cancel" to confirm
                                </label>
                                <input type="text" id="confirmation" name="confirmation" required
                                       placeholder="Type 'cancel' to confirm cancellation"
                                       style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: var(--border-radius);">
                                <p class="form-hint" style="margin-top: 8px; color: #666;">
                                    This is required to prevent accidental cancellations.
                                </p>
                            </div>

                            <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="confirm_cancel" class="btn btn-danger">
                                    <i class="fas fa-ban"></i> Cancel Subscription
                                </button>
                                <a href="member_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Keep My Subscription
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="contact-support" style="margin-top: 40px;">
                    <h3><i class="fas fa-headset"></i> Need Help?</h3>
                    <p>If you have questions about cancellation or need assistance, contact our support team.</p>
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
                        <li><a href="edit_profile.php">Edit Profile</a></li>
                        <li><a href="cancel_subscription.php">Cancel Subscription</a></li>
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
        .cancellation-success, .no-subscription, .cancellation-warning {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .success-icon, .warning-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .success-icon {
            color: #28a745;
        }

        .warning-icon {
            color: #ffc107;
        }

        .cancellation-success h2, .no-subscription h2, .cancellation-warning h2 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c82333;
            color: white;
        }
    </style>
</body>
</html>
