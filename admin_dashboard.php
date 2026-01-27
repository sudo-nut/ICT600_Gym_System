<?php
require_once "db.php";

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

$users_query =
    "SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

$payments_query = "
    SELECT p.id, p.amount, p.payment_date, p.payment_method, u.name as user_name, u.email as user_email
    FROM payments p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
";
$payments_result = $conn->query($payments_query);

$total_users = $conn
    ->query("SELECT COUNT(*) as count FROM users")
    ->fetch_assoc()["count"];
$total_members = $conn
    ->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")
    ->fetch_assoc()["count"];
$total_admins = $conn
    ->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")
    ->fetch_assoc()["count"];

$total_payments = $conn
    ->query("SELECT COUNT(*) as count FROM payments")
    ->fetch_assoc()["count"];
$total_revenue = $conn
    ->query("SELECT SUM(amount) as total FROM payments")
    ->fetch_assoc()["total"];
$total_revenue = $total_revenue ? number_format($total_revenue, 2) : "0.00";

$today = date("Y-m-d");
$today_payments_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE DATE(payment_date) = '$today'";
$today_payments_result = $conn->query($today_payments_query);
$today_payments = $today_payments_result->fetch_assoc();
$today_count = $today_payments["count"];
$today_total = $today_payments["total"]
    ? number_format($today_payments["total"], 2)
    : "0.00";

$today_users_query = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = '$today'";
$today_users_result = $conn->query($today_users_query);
$today_users = $today_users_result->fetch_assoc();
$today_joined = $today_users["count"];

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
    <title>Admin Dashboard - FitLife Gym</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-dumbbell"></i> FitLife Gym Admin</h1>
            </div>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="admin_user_profiles.php"><i class="fas fa-users"></i> User Profiles</a></li>
                <li><a href="admin_payments.php"><i class="fas fa-credit-card"></i> Payment Records</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(
                    $admin_name,
                ); ?> (Admin)</span>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Welcome, Administrator <?php echo htmlspecialchars(
                $admin_name,
            ); ?>!</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <span class="stat-number"><?php echo $total_users; ?></span>
                    <span class="stat-detail"><?php echo $total_members; ?> members, <?php echo $total_admins; ?> admins</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3>Joined Today</h3>
                    <span class="stat-number"><?php echo $today_joined; ?></span>
                    <span class="stat-detail">new users registered today</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <span class="stat-number">RM <?php echo $total_revenue; ?></span>
                    <span class="stat-detail">from <?php echo $total_payments; ?> payments</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3>Today's Revenue</h3>
                    <span class="stat-number">RM <?php echo $today_total; ?></span>
                    <span class="stat-detail">from <?php echo $today_count; ?> payments today</span>
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
                                <?php while (
                                    $user = $users_result->fetch_assoc()
                                ):

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
                                            $status_indicator =
                                                "status-expiring-soon";
                                            break;
                                        case "row-expired":
                                            $status_text = "Expired";
                                            $status_indicator =
                                                "status-expired";
                                            break;
                                        case "row-pending":
                                            $status_text = "Pending";
                                            $status_indicator =
                                                "status-pending";
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
                                        </td>
                                    </tr>
                                <?php
                                endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-data"><i class="fas fa-info-circle"></i> No users found in the system.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-credit-card"></i> Payment History</h3>
                <span class="badge"><?php echo $total_payments; ?> payments</span>
            </div>
            <div class="card-body">
                <?php if (
                    $payments_result &&
                    $payments_result->num_rows > 0
                ): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while (
                                    $payment = $payments_result->fetch_assoc()
                                ): ?>
                                    <tr>
                                        <td>#<?php echo str_pad(
                                            $payment["id"],
                                            5,
                                            "0",
                                            STR_PAD_LEFT,
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $payment["user_name"],
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $payment["user_email"],
                                        ); ?></td>
                                        <td class="text-success">RM <?php echo number_format(
                                            $payment["amount"],
                                            2,
                                        ); ?></td>
                                        <td>
                                            <span class="payment-method">
                                                <?php echo htmlspecialchars(
                                                    $payment["payment_method"],
                                                ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date(
                                            "M j, Y h:i A",
                                            strtotime($payment["payment_date"]),
                                        ); ?></td>
                                        <td>
                                            <a href="#" class="btn-action" onclick="alert('View payment details for ID: <?php echo $payment[
                                                "id"
                                            ]; ?>')" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="#" class="btn-action" onclick="alert('Generate invoice for payment ID: <?php echo $payment[
                                                "id"
                                            ]; ?>')" title="Invoice">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-data"><i class="fas fa-info-circle"></i> No payment records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-dumbbell"></i> FitLife Gym Admin</h3>
                    <p>Administrative dashboard for managing gym memberships.</p>
                </div>
                <div class="footer-section">
                    <h3>Admin Tools</h3>
                    <ul>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <li><a href="#" onclick="alert('Coming soon!')">User Management</a></li>
                        <li><a href="#" onclick="alert('Coming soon!')">Financial Reports</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Admin Support</h3>
                    <p><i class="fas fa-user-tie"></i> Logged in as: <?php echo htmlspecialchars(
                        $admin_email,
                    ); ?></p>
                    <p><i class="fas fa-clock"></i> Last login: <?php echo date(
                        "F j, Y, g:i a",
                    ); ?></p>
                    <p><i class="fas fa-shield-alt"></i> Admin privileges active</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date(
                    "Y",
                ); ?> FitLife Gym Membership System - Admin Panel v1.0</p>
            </div>
        </div>
    </footer>
</body>
</html>
