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
$user_email = $_SESSION["user_email"];

// Get user information
$user_query = $conn->prepare(
    "SELECT name, email, phone, created_at FROM users WHERE id = ?",
);
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Get subscription status
$subscription_query = $conn->prepare("
    SELECT s.*, p.name as plan_name
    FROM subscriptions s
    LEFT JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.end_date DESC LIMIT 1
");
$subscription_query->bind_param("i", $user_id);
$subscription_query->execute();
$subscription_result = $subscription_query->get_result();
$subscription = $subscription_result->fetch_assoc();

$has_subscription = $subscription !== null;
$is_active_subscription = $subscription && $subscription["status"] === "active";

$message = "";
$message_type = "";
$deletion_successful = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["confirm_delete"])) {
    $confirmation = $_POST["confirmation"] ?? "";
    $password = $_POST["password"] ?? "";

    // Validate inputs
    if (empty($confirmation) || strtolower($confirmation) !== "delete") {
        $message = "Please type 'delete' in the confirmation box to proceed.";
        $message_type = "error";
    } elseif (empty($password)) {
        $message = "Please enter your password to confirm account deletion.";
        $message_type = "error";
    } else {
        // Verify password
        $verify_query = $conn->prepare(
            "SELECT password FROM users WHERE id = ?",
        );
        $verify_query->bind_param("i", $user_id);
        $verify_query->execute();
        $verify_result = $verify_query->get_result();
        $verify_data = $verify_result->fetch_assoc();

        if (!password_verify($password, $verify_data["password"])) {
            $message =
                "Incorrect password. Please enter your current password.";
            $message_type = "error";
        } else {
            $conn->begin_transaction();

            try {
                // Delete related records first (due to foreign key constraints)
                // Delete payments
                $delete_payments = $conn->prepare(
                    "DELETE FROM payments WHERE user_id = ?",
                );
                $delete_payments->bind_param("i", $user_id);
                $delete_payments->execute();

                // Delete subscriptions
                $delete_subscriptions = $conn->prepare(
                    "DELETE FROM subscriptions WHERE user_id = ?",
                );
                $delete_subscriptions->bind_param("i", $user_id);
                $delete_subscriptions->execute();

                // Finally delete the user
                $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_user->bind_param("i", $user_id);

                if ($delete_user->execute()) {
                    $conn->commit();
                    $deletion_successful = true;

                    // Destroy session
                    session_destroy();

                    // Message will be shown on the page before redirect
                    $message =
                        "Your account has been successfully deleted. You will be redirected to the home page.";
                    $message_type = "success";
                } else {
                    throw new Exception(
                        "Failed to delete account. Please try again.",
                    );
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $message_type = "error";
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
    <title>Delete Account - FitLife Gym</title>
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
                <li><a href="delete_account.php" class="active"><i class="fas fa-trash-alt"></i> Delete Account</a></li>
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
                    <a href="cancel_subscription.php">
                        <i class="fas fa-ban"></i> Cancel Subscription
                    </a>
                    <a href="delete_account.php" class="active">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </aside>

            <main class="main-content">
                <?php if ($message && !$deletion_successful): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == "success"
                            ? "check-circle"
                            : "exclamation-circle"; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <h1><i class="fas fa-trash-alt"></i> Delete Account</h1>

                <?php if ($deletion_successful): ?>
                    <div class="deletion-success">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2>Account Deleted Successfully</h2>
                        <p>Your account and all associated data have been permanently deleted.</p>
                        <p>Thank you for being a member of FitLife Gym. We hope to see you again in the future!</p>
                        <div style="margin-top: 30px;">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Return to Home
                            </a>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 5000);
                        </script>
                    </div>
                <?php else: ?>
                    <div class="deletion-warning">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2>Delete Your Account</h2>
                        <p>You are about to permanently delete your account. This action cannot be undone.</p>

                        <div class="account-details" style="margin: 25px 0; padding: 20px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                            <h3><i class="fas fa-user"></i> Account Information</h3>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <span style="font-weight: 600;">Name:</span>
                                <span><?php echo htmlspecialchars(
                                    $user_data["name"],
                                ); ?></span>
                            </div>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <span style="font-weight: 600;">Email:</span>
                                <span><?php echo htmlspecialchars(
                                    $user_data["email"],
                                ); ?></span>
                            </div>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <span style="font-weight: 600;">Member Since:</span>
                                <span><?php echo date(
                                    "F j, Y",
                                    strtotime($user_data["created_at"]),
                                ); ?></span>
                            </div>
                            <div class="detail-item" style="display: flex; justify-content: space-between; margin: 10px 0;">
                                <span style="font-weight: 600;">Subscription Status:</span>
                                <span>
                                    <?php if ($is_active_subscription): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php elseif ($has_subscription): ?>
                                        <span class="status-badge status-expired">Expired</span>
                                    <?php else: ?>
                                        <span>No Subscription</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="deletion-consequences" style="margin: 25px 0; padding: 20px; background-color: #f8d7da; border-radius: var(--border-radius); border-left: 4px solid #dc3545;">
                            <h3><i class="fas fa-exclamation-circle"></i> Permanent Consequences:</h3>
                            <ul style="margin: 15px 0; padding-left: 20px;">
                                <li>All your personal information will be permanently deleted</li>
                                <li>Your subscription will be cancelled immediately</li>
                                <li>All payment history will be removed</li>
                                <li>You will lose access to the gym immediately</li>
                                <li>This action cannot be undone</li>
                                <li>You will need to create a new account to use our services again</li>
                            </ul>
                            <p style="font-weight: 600; color: #721c24; margin-top: 15px;">
                                <i class="fas fa-exclamation-circle"></i> Important: If you have an active subscription, consider cancelling it first from the subscription page.
                            </p>
                        </div>

                        <form method="POST" action="" style="margin-top: 30px;">
                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i> Enter Your Password
                                </label>
                                <input type="password" id="password" name="password" required
                                       placeholder="Enter your current password"
                                       style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: var(--border-radius); margin-bottom: 20px;">
                                <p class="form-hint" style="margin-top: 8px; color: #666;">
                                    This is required to verify your identity.
                                </p>
                            </div>

                            <div class="form-group">
                                <label for="confirmation">
                                    <i class="fas fa-check-circle"></i> Type "delete" to confirm
                                </label>
                                <input type="text" id="confirmation" name="confirmation" required
                                       placeholder="Type 'delete' to confirm account deletion"
                                       style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: var(--border-radius);">
                                <p class="form-hint" style="margin-top: 8px; color: #666;">
                                    This is required to prevent accidental deletions.
                                </p>
                            </div>

                            <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="confirm_delete" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> Permanently Delete Account
                                </button>
                                <a href="member_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Keep My Account
                                </a>
                            </div>
                        </form>

                        <div class="alternative-options" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
                            <h3><i class="fas fa-info-circle"></i> Alternative Options</h3>
                            <p>If you're not sure about deleting your account permanently, consider these alternatives:</p>
                            <ul style="margin: 15px 0; padding-left: 20px;">
                                <li><a href="edit_profile.php">Update your profile information</a> instead of deleting</li>
                                <li><a href="cancel_subscription.php">Cancel your subscription</a> if you just want to stop payments</li>
                                <li>Contact support if you have issues with your account</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="contact-support" style="margin-top: 40px;">
                    <h3><i class="fas fa-headset"></i> Need Assistance?</h3>
                    <p>If you have questions about account deletion or need help, contact our support team.</p>
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
                        <li><a href="delete_account.php">Delete Account</a></li>
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
        .deletion-success, .deletion-warning {
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

        .deletion-success h2, .deletion-warning h2 {
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

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</body>
</html>
