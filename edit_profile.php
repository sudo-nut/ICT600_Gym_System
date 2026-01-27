<?php
require_once "db.php";

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_role"])) {
    header("Location: login.php");
    exit();
}

// Check if admin is editing a specific member profile
$viewing_member_id = null;
if ($_SESSION["user_role"] === "admin" && isset($_GET["user_id"])) {
    $viewing_member_id = intval($_GET["user_id"]);

    // Verify the user exists and is a member (not another admin)
    $member_check = $conn->prepare(
        "SELECT id, name, email, phone, role FROM users WHERE id = ?",
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
        // Can't edit another admin's profile
        header("Location: admin_dashboard.php");
        exit();
    }

    // Use member's data for editing
    $user_id = $viewing_member_id;
    $user_role = "member"; // Treat as member for editing purposes
    $current_name = $member_data["name"];
    $current_email = $member_data["email"];
    $current_phone = $member_data["phone"];
} else {
    // Regular user editing their own profile
    $user_id = $_SESSION["user_id"];
    $user_role = $_SESSION["user_role"];

    // Get current user information
    $user_query = $conn->prepare(
        "SELECT name, email, phone FROM users WHERE id = ?",
    );
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user_data = $user_result->fetch_assoc();

    $current_name = $user_data["name"];
    $current_email = $user_data["email"];
    $current_phone = $user_data["phone"];
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if (empty($name) || empty($email)) {
        $message = "Name and email are required fields.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();

        try {
            // Check if email is already taken by another user
            if ($email !== $current_email) {
                $check_email = $conn->prepare(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                );
                $check_email->bind_param("si", $email, $user_id);
                $check_email->execute();
                $check_email->store_result();

                if ($check_email->num_rows > 0) {
                    throw new Exception(
                        "Email already exists. Please use a different email.",
                    );
                }
                $check_email->close();
            }

            // If password change is requested
            if (!empty($new_password)) {
                // Admin editing member profile doesn't need current password
                $is_admin_editing_member =
                    $_SESSION["user_role"] === "admin" &&
                    isset($_GET["user_id"]);

                if (!$is_admin_editing_member) {
                    // For regular users or admin editing own profile, require current password
                    if (empty($current_password)) {
                        throw new Exception(
                            "Current password is required to set a new password.",
                        );
                    }

                    // Verify current password
                    $verify_query = $conn->prepare(
                        "SELECT password FROM users WHERE id = ?",
                    );
                    $verify_query->bind_param("i", $user_id);
                    $verify_query->execute();
                    $verify_result = $verify_query->get_result();
                    $verify_data = $verify_result->fetch_assoc();

                    if (
                        !password_verify(
                            $current_password,
                            $verify_data["password"],
                        )
                    ) {
                        throw new Exception("Current password is incorrect.");
                    }
                }

                if (strlen($new_password) < 6) {
                    throw new Exception(
                        "New password must be at least 6 characters long.",
                    );
                }

                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT,
                );

                // Update with password
                $update_query = $conn->prepare(
                    "UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?",
                );
                $update_query->bind_param(
                    "ssssi",
                    $name,
                    $email,
                    $phone,
                    $hashed_password,
                    $user_id,
                );
            } else {
                // Update without password
                $update_query = $conn->prepare(
                    "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?",
                );
                $update_query->bind_param(
                    "sssi",
                    $name,
                    $email,
                    $phone,
                    $user_id,
                );
            }

            if ($update_query->execute()) {
                $conn->commit();

                // Update session variables
                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                $message = "Profile updated successfully!";
                $message_type = "success";

                // Refresh user data
                $current_name = $name;
                $current_email = $email;
                $current_phone = $phone;
            } else {
                throw new Exception(
                    "Failed to update profile. Please try again.",
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
    <title>Edit Profile - FitLife Gym</title>
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
                    <li><a href="member_dashboard.php?user_id=<?php echo $viewing_member_id; ?>"><i class="fas fa-user-circle"></i> Member Profile</a></li>
                    <li><a href="edit_profile.php?user_id=<?php echo $viewing_member_id; ?>" class="active"><i class="fas fa-user-edit"></i> Edit Member</a></li>
                    <li><a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Admin</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="member_dashboard.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="edit_profile.php" class="active"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(
                    $_SESSION["user_name"] ?? "",
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
                    <a href="edit_profile.php" class="active">
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

                <h1><i class="fas fa-user-edit"></i> <?php echo $_SESSION[
                    "user_role"
                ] === "admin" && isset($_GET["user_id"])
                    ? "Edit Member Profile"
                    : "Edit Profile"; ?></h1>
                <p class="welcome-message"><?php echo $_SESSION["user_role"] ===
                    "admin" && isset($_GET["user_id"])
                    ? "Update member's personal information."
                    : "Update your personal information and password."; ?></p>

                <div class="profile-section">
                    <h2><i class="fas fa-user"></i> Personal Information</h2>
                    <form method="POST" action="" class="auth-form">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars(
                                $current_name,
                            ); ?>" required placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(
                                $current_email,
                            ); ?>" required placeholder="Enter your email">
                        </div>

                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars(
                                $current_phone,
                            ); ?>" placeholder="Enter your phone number">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Change Password (Optional)</label>
                            <p class="form-hint">Leave blank if you don't want to change your password.</p>

                            <?php if (
                                !(
                                    $_SESSION["user_role"] === "admin" &&
                                    isset($_GET["user_id"])
                                )
                            ): ?>
                            <div style="margin-bottom: 15px;">
                                <label for="current_password" style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Current Password</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                            </div>
                            <?php endif; ?>

                            <div style="margin-bottom: 15px;">
                                <label for="new_password" style="display: block; margin-bottom: 5px; font-size: 0.9rem;">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="At least 6 characters">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label for="confirm_password" style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>

                        <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <?php if (
                                $_SESSION["user_role"] === "admin" &&
                                isset($_GET["user_id"])
                            ): ?>
                                <a href="member_dashboard.php?user_id=<?php echo $viewing_member_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <a href="admin_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Admin
                                </a>
                            <?php else: ?>
                                <a href="member_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="contact-support" style="margin-top: 30px;">
                    <h3><i class="fas fa-headset"></i> Need Assistance?</h3>
                    <p>If you encounter any issues updating your profile, contact our support team.</p>
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
                ); ?> FitLife Gym Membership System. <?php echo $_SESSION[
     "user_role"
 ] === "admin" && isset($_GET["user_id"])
     ? "Editing Member ID: M" . str_pad($user_id, 5, "0", STR_PAD_LEFT)
     : "Member ID: M" . str_pad($user_id, 5, "0", STR_PAD_LEFT); ?></p>
            </div>
        </div>
    </footer>
</body>
</html>
