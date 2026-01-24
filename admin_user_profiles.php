<?php
require_once "includes/db.php";

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_role"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["user_role"] !== "admin") {
    header("Location: member_dashboard.php");
    exit();
}

$admin_name = $_SESSION["user_name"];
$admin_email = $_SESSION["user_email"];

// Get all users
$users_query =
    "SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

$total_users = $conn
    ->query("SELECT COUNT(*) as count FROM users")
    ->fetch_assoc()["count"];
$total_members = $conn
    ->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")
    ->fetch_assoc()["count"];
$total_admins = $conn
    ->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")
    ->fetch_assoc()["count"];

$today = date("Y-m-d");
$today_users_query = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = '$today'";
$today_users_result = $conn->query($today_users_query);
$today_users = $today_users_result->fetch_assoc();
$today_joined = $today_users["count"];

// Function to get subscription status class for a user
function get_subscription_status_class($user_id, $conn)
{
    $sub_query = $conn->prepare("
        SELECT end_date, status
        FROM subscriptions
        WHERE user_id = ?
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $sub_query->bind_param("i", $user_id);
    $sub_query->execute();
    $sub_result = $sub_query->get_result();

    if ($sub_result->num_rows === 0) {
        return "row-no-subscription";
    }

    $subscription = $sub_result->fetch_assoc();
    $end_date = $subscription["end_date"];
    $status = $subscription["status"];

    if ($status === "expired") {
        return "row-expired";
    }

    if ($status === "pending") {
        return "row-pending";
    }

    $today = new DateTime();
    $expiry_date = new DateTime($end_date);
    $days_diff = $today->diff($expiry_date)->days;

    if ($today > $expiry_date) {
        return "row-expired";
    } elseif ($days_diff <= 7) {
        return "row-expiring-soon";
    } else {
        return "row-active";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profiles - FitLife Gym Admin</title>
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
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="admin_user_profiles.php" class="active"><i class="fas fa-users"></i> User Profiles</a></li>
                <li><a href="admin_payments.php"><i class="fas fa-credit-card"></i> Payment Records</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(
                    $admin_name,
                ); ?></span>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h1><i class="fas fa-users"></i> User Profiles Management</h1>
            <p class="welcome-message">Manage all user accounts and subscription statuses.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Users</h3>
                    <span class="stat-number"><?php echo $total_users; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Members</h3>
                    <span class="stat-number"><?php echo $total_members; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <h3>Admins</h3>
                    <span class="stat-number"><?php echo $total_admins; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-content">
                    <h3>Joined Today</h3>
                    <span class="stat-number"><?php echo $today_joined; ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> All Users</h3>
                <span class="badge"><?php echo $total_users; ?> total</span>
                <div class="status-legend">
                    <small>
                        <span class="status-indicator-small status-active"></span>Active
                        <span class="status-indicator-small status-expiring-soon"></span>Expiring Soon (≤7 days)
                        <span class="status-indicator-small status-expired"></span>Expired
                        <span class="status-indicator-small status-pending"></span>Pending
                        <span class="status-indicator-small status-none"></span>No Subscription
                    </small>
                </div>
            </div>
            <div class="card-body">
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Subscription Status</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users_result->fetch_assoc()):
                                    $status_class = get_subscription_status_class(
                                        $user["id"],
                                        $conn,
                                    );
                                    $status_text = "";
                                    $status_indicator = "";
                                    switch ($status_class) {
                                        case "row-active":
                                            $status_text = "Active";
                                            $status_indicator = "status-active";
                                            break;
                                        case "row-expiring-soon":
                                            $status_text = "Expiring Soon";
                                            $status_indicator = "status-expiring-soon";
                                            break;
                                        case "row-expired":
                                            $status_text = "Expired";
                                            $status_indicator = "status-expired";
                                            break;
                                        case "row-pending":
                                            $status_text = "Pending";
                                            $status_indicator = "status-pending";
                                            break;
                                        default:
                                            $status_text = "No Subscription";
                                            $status_indicator = "status-none";
                                            break;
                                    }
                                    ?>
                                    <tr class="<?php echo $status_class; ?>">
                                        <td><?php echo $user["id"]; ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $user["name"],
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $user["email"],
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $user["phone"] ?: "N/A",
                                        ); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo strtolower(
                                                $user["role"],
                                            ); ?>">
                                                <?php echo ucfirst(
                                                    $user["role"],
                                                ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-indicator-small <?php echo $status_indicator; ?>"></span>
                                            <?php echo $status_text; ?>
                                        </td>
                                        <td><?php echo date(
                                            "M j, Y",
                                            strtotime($user["created_at"]),
                                        ); ?></td>
                                        <td>
                                            <?php if ($user["role"] === "member"): ?>
                                                <a href="member_dashboard.php?user_id=<?php echo $user[
                                                    "id"
                                                ]; ?>" class="btn-action" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_profile.php?user_id=<?php echo $user[
                                                    "id"
                                                ]; ?>" class="btn-action" title="Edit Profile">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="action-disabled" title="Cannot manage admin accounts">
                                                    <i class="fas fa-eye-slash"></i>
                                                </span>
                                                <span class="action-disabled" title="Cannot edit admin accounts">
                                                    <i class="fas fa-edit"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php
                                endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="text-align: center; margin-top: 15px; color: #666;">
                        <i class="fas fa-info-circle"></i> Showing all <?php echo $total_users; ?> users
                    </p>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-users-slash"></i>
                        <h4>No Users Found</h4>
                        <p>There are no users in the system yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-actions" style="margin-top: 30px; padding: 20px; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow);">
            <h3><i class="fas fa-cogs"></i> Admin Tools</h3>
            <div class="action-buttons" style="display: flex; gap: 15px; margin-top: 15px;">
                <a href="admin_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
                <a href="admin_payments.php" class="btn btn-secondary">
                    <i class="fas fa-credit-card"></i> View Payment Records
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="contact-support" style="margin-top: 40px;">
            <h3><i class="fas fa-headset"></i> Admin Support</h3>
            <p>For technical issues or assistance with user management, contact our system administrator.</p>
            <div class="contact-options">
                <div class="contact-option">
                    <i class="fas fa-phone"></i>
                    <h4>System Admin</h4>
                    <p>+60 12-345 6789</p>
                </div>
                <div class="contact-option">
                    <i class="fas fa-envelope"></i>
                    <h4>Admin Email</h4>
                    <p>admin@fitlifegym.com</p>
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

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-dumbbell"></i> FitLife Gym</h3>
                    <p>Your journey to a healthier lifestyle starts here.</p>
                </div>
                <div class="footer-section">
                    <h3>Admin Services</h3>
                    <ul>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <li><a href="admin_user_profiles.php">User Profiles</a></li>
                        <li><a href="admin_payments.php">Payment Records</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Admin Support</h3>
                    <p><i class="fas fa-headset"></i> Admin Support: +60 12-345 6789</p>
                    <p><i class="fas fa-envelope"></i> admin@fitlifegym.com</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM, Sat: 9AM-1PM</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date(
                    "Y",
                ); ?> FitLife Gym Membership System. Admin Panel | Logged in as <?php echo htmlspecialchars(
     $admin_name,
 ); ?></p>
            </div>
        </div>
    </footer>

    <style>
        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .stat-content h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--dark-color);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-badge.role-admin {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .role-badge.role-member {
            background-color: #d4edda;
            color: #155724;
        }

        .action-disabled {
            display: inline-block;
            padding: 5px 10px;
            color: #ccc;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            padding: 40px;
        }

        .no-data i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 15px;
        }

        .no-data h4 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .no-data p {
            color: #666;
        }
    </style>
</body>
</html>
