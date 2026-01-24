<?php
require_once "includes/db.php";

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_role"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["user_role"] === "admin") {
    // If admin is trying to view their own dashboard (no user_id parameter), redirect to admin dashboard
    if (!isset($_GET["user_id"])) {
        header("Location: admin_dashboard.php");
        exit();
    }
    // Otherwise, admin is viewing a member profile - allow it
}

// Check if admin is viewing a specific member profile
$viewing_member_id = null;
if ($_SESSION["user_role"] === "admin" && isset($_GET["user_id"])) {
    $viewing_member_id = intval($_GET["user_id"]);

    // Verify the user exists and is a member (not another admin)
    $member_check = $conn->prepare(
        "SELECT id, name, email, role FROM users WHERE id = ?",
    );
    $member_check->bind_param("i", $viewing_member_id);
    $member_check->execute();
    $member_result = $member_check->get_result();

    if ($member_result->num_rows === 0) {
        // User doesn't exist
        header("Location: admin_dashboard.php");
        exit();
    }

    $member_data = $member_result->fetch_assoc();
    if ($member_data["role"] === "admin") {
        // Can't view another admin's profile
        header("Location: admin_dashboard.php");
        exit();
    }

    // Use member's data
    $user_id = $viewing_member_id;
    $user_name = $member_data["name"];
    $user_email = $member_data["email"];
} else {
    // Regular member viewing their own profile
    $user_id = $_SESSION["user_id"];
    $user_name = $_SESSION["user_name"];
    $user_email = $_SESSION["user_email"];
}

// Get user's subscription and plan information
$subscription_query = $conn->prepare("
    SELECT s.*, p.name as plan_name, p.price, p.duration_days
    FROM subscriptions s
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.end_date DESC LIMIT 1
");
$subscription_query->bind_param("i", $user_id);
$subscription_query->execute();
$subscription_result = $subscription_query->get_result();
$subscription = $subscription_result->fetch_assoc();

$subscription_status = "none";
$is_active = false;
$is_pending = false;
$is_expired = false;
$expiry_date = null;
$plan_name = "No Active Plan";
$plan_price = 0;

if ($subscription) {
    $subscription_status = $subscription["status"];
    $expiry_date = $subscription["end_date"];
    $plan_name = $subscription["plan_name"];
    $plan_price = $subscription["price"];

    if ($subscription_status === "active") {
        $today = date("Y-m-d");
        if ($expiry_date >= $today) {
            $is_active = true;
        } else {
            $is_expired = true;
            $subscription_status = "expired";
        }
    } elseif ($subscription_status === "pending") {
        $is_pending = true;
    } else {
        $is_expired = true;
    }
} else {
    $is_expired = true;
}

$message = "";
$message_type = "";

if (isset($_GET["msg"])) {
    $msg = $_GET["msg"];
    switch ($msg) {
        case "payment_success":
            $message =
                "Payment successful! Your membership has been activated.";
            $message_type = "success";
            break;
        case "upgrade_success":
            $message = "Upgrade successful! Your membership has been upgraded.";
            $message_type = "success";
            break;
        case "payment_failed":
            $message = "Payment failed. Please try again.";
            $message_type = "error";
            break;
        case "payment_error":
            $message = "An error occurred during payment processing.";
            $message_type = "error";
            break;
        case "payment_invalid":
            $message = "Invalid payment request.";
            $message_type = "error";
            break;
        case "payment_cancelled":
            $message =
                "Payment was cancelled. No changes were made to your membership.";
            $message_type = "warning";
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - FitLife Gym</title>
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
                <?php if (
                    $_SESSION["user_role"] === "admin" &&
                    isset($_GET["user_id"])
                ): ?>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="member_dashboard.php?user_id=<?php echo $viewing_member_id; ?>" class="active"><i class="fas fa-user-circle"></i> Member Profile</a></li>
                    <li><a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Admin</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="member_dashboard.php" class="active"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php endif; ?>
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
                    <a href="member_dashboard.php" class="active">
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

                <h1><i class="fas fa-user-circle"></i> Member Profile</h1>
                <p class="welcome-message">Welcome back, <?php echo htmlspecialchars(
                    $user_name,
                ); ?>! Here's an overview of your membership.</p>

                <div class="profile-overview">
                    <div class="profile-section">
                        <h2><i class="fas fa-user"></i> Personal Information</h2>
                        <div class="profile-details">
                            <div class="detail-item">
                                <span class="detail-label">Full Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(
                                    $user_name,
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(
                                    $user_email,
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Account Type:</span>
                                <span class="detail-value">Member</span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h2><i class="fas fa-id-card"></i> Membership Details</h2>
                        <div class="profile-details">
                            <div class="detail-item">
                                <span class="detail-label">Current Plan:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(
                                    $plan_name,
                                ); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Subscription Status:</span>
                                <span class="detail-value">
                                    <?php if ($is_active): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php elseif ($is_pending): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php else: ?>
                                        <span class="status-badge status-expired">Expired</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($expiry_date): ?>
                            <div class="detail-item">
                                <span class="detail-label">Expiry Date:</span>
                                <span class="detail-value"><?php echo date(
                                    "F j, Y",
                                    strtotime($expiry_date),
                                ); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="membership-actions">
                    <?php if ($is_active): ?>
                        <div class="action-card active-membership">
                            <div class="action-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="action-content">
                                <h3>Membership Active</h3>
                                <p>Your membership is currently active and valid for gym access.</p>
                                <?php if ($expiry_date): ?>
                                    <p class="expiry-notice">Your membership will expire on <?php echo date(
                                        "F j, Y",
                                        strtotime($expiry_date),
                                    ); ?>.</p>
                                <?php endif; ?>
                            </div>
                            <div class="qr-section">
                                <h4><i class="fas fa-qrcode"></i> Member QR Code</h4>
                                <p>Show this QR code at the gym entrance for access.</p>
                                <div class="qr-code">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=MemberValid_<?php echo $user_id; ?>" alt="Member QR Code">
                                </div>
                                <p class="qr-note">QR Code valid for gym access</p>
                            </div>
                            <div class="upgrade-action" style="margin-top: 20px; text-align: center;">
                                <a href="upgrade_subscription.php" class="btn btn-upgrade">
                                    <i class="fas fa-arrow-up"></i> Upgrade Membership
                                </a>
                                <p class="form-hint" style="margin-top: 10px; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i> Upgrade to a higher-tier plan for more benefits
                                </p>
                            </div>
                        </div>
                    <?php elseif ($is_pending): ?>
                        <div class="action-card pending-membership">
                            <div class="action-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="action-content">
                                <h3>Payment Pending</h3>
                                <p>Your membership plan has been selected, but payment is required to activate it.</p>
                                <?php if ($expiry_date): ?>
                                    <p class="expiry-notice">Your membership will be valid until <?php echo date(
                                        "F j, Y",
                                        strtotime($expiry_date),
                                    ); ?> after payment.</p>
                                <?php endif; ?>

                                <?php if ($plan_price > 0): ?>
                                    <div class="renewal-info">
                                        <h4><i class="fas fa-credit-card"></i> Complete Payment</h4>
                                        <p class="amount-due"><strong>Amount due: RM <?php echo number_format(
                                            $plan_price,
                                            2,
                                        ); ?></strong></p>
                                        <p>Complete your payment to activate your membership immediately.</p>
                                        <a href="mock_ipay88.php?user_id=<?php echo $user_id; ?>&amount=<?php echo $plan_price; ?>" class="btn btn-pay btn-large">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </a>
                                        <p class="secure-note"><i class="fas fa-shield-alt"></i> Secure payment via our trusted payment gateway</p>
                                    </div>
                                <?php else: ?>
                                    <div class="renewal-info">
                                        <p>Please contact our support team to complete your payment.</p>
                                        <a href="select_plan.php" class="btn btn-primary">
                                            <i class="fas fa-sync-alt"></i> Select a New Plan
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="action-card expired-membership">
                            <div class="action-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="action-content">
                                <h3>Membership Expired</h3>
                                <p>Your membership has expired and gym access is no longer available.</p>
                                <p>Renew your membership to continue enjoying our facilities.</p>

                                <?php if ($plan_price > 0): ?>
                                    <div class="renewal-info">
                                        <h4><i class="fas fa-credit-card"></i> Renew Your Membership</h4>
                                        <p class="amount-due"><strong>Amount due: RM <?php echo number_format(
                                            $plan_price,
                                            2,
                                        ); ?></strong></p>
                                        <p>Securely renew your membership online to restore access immediately.</p>
                                        <a href="mock_ipay88.php?user_id=<?php echo $user_id; ?>&amount=<?php echo $plan_price; ?>" class="btn btn-pay btn-large">
                                            <i class="fas fa-credit-card"></i> Pay Renewal Now
                                        </a>
                                        <p class="secure-note"><i class="fas fa-shield-alt"></i> Secure payment via our trusted payment gateway</p>
                                    </div>
                                <?php else: ?>
                                    <div class="renewal-info">
                                        <p>Please contact our support team to renew your membership or select a new plan.</p>
                                        <a href="select_plan.php" class="btn btn-primary">
                                            <i class="fas fa-sync-alt"></i> Select a New Plan
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="contact-support">
                    <h3><i class="fas fa-headset"></i> Need Assistance?</h3>
                    <p>Our support team is here to help you with any questions about your membership.</p>
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
</body>
</html>
