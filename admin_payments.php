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

// Get filter parameters
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$start_date = isset($_GET["start_date"]) ? $_GET["start_date"] : "";
$end_date = isset($_GET["end_date"]) ? $_GET["end_date"] : "";
$payment_method = isset($_GET["payment_method"]) ? $_GET["payment_method"] : "";

// Build query with filters
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR p.id = ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $params[] = $search;
    $types .= "sss";
}

if (!empty($start_date)) {
    $where_conditions[] = "DATE(p.payment_date) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $where_conditions[] = "DATE(p.payment_date) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($payment_method)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

// Get unique payment methods for filter dropdown
$methods_query = "SELECT DISTINCT payment_method FROM payments WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method";
$methods_result = $conn->query($methods_query);

// Build main query
$payments_query = "
    SELECT p.id, p.amount, p.payment_date, p.payment_method,
           u.id as user_id, u.name as user_name, u.email as user_email, u.phone as user_phone
    FROM payments p
    JOIN users u ON p.user_id = u.id
";

if (!empty($where_conditions)) {
    $payments_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$payments_query .= " ORDER BY p.payment_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($payments_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments_result = $stmt->get_result();

// Calculate statistics
$stats_query = "
    SELECT
        COUNT(*) as total_payments,
        SUM(amount) as total_revenue,
        AVG(amount) as average_payment,
        MIN(payment_date) as first_payment,
        MAX(payment_date) as last_payment
    FROM payments
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$today = date("Y-m-d");
$today_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE DATE(payment_date) = '$today'";
$today_result = $conn->query($today_query);
$today_stats = $today_result->fetch_assoc();

$month_start = date("Y-m-01");
$month_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE DATE(payment_date) >= '$month_start'";
$month_result = $conn->query($month_query);
$month_stats = $month_result->fetch_assoc();

// Handle payment receipt download
if (isset($_GET["download_receipt"]) && isset($_GET["payment_id"])) {
    $payment_id = intval($_GET["payment_id"]);

    $receipt_query = $conn->prepare("
        SELECT p.*, u.name, u.email
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $receipt_query->bind_param("i", $payment_id);
    $receipt_query->execute();
    $receipt_result = $receipt_query->get_result();

    if ($receipt_result->num_rows > 0) {
        $payment_data = $receipt_result->fetch_assoc();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Receipt - FitLife Gym</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .receipt-header {
                    text-align: center;
                    border-bottom: 2px solid #4CAF50;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .receipt-header h1 {
                    color: #4CAF50;
                    margin: 0;
                }
                .company-info {
                    text-align: center;
                    margin-bottom: 30px;
                    color: #666;
                }
                .receipt-details {
                    background: #f9f9f9;
                    padding: 25px;
                    border-radius: 8px;
                    margin-bottom: 30px;
                }
                .detail-row {
                    display: flex;
                    margin-bottom: 15px;
                }
                .detail-label {
                    font-weight: bold;
                    width: 200px;
                    color: #555;
                }
                .detail-value {
                    flex: 1;
                }
                .amount-box {
                    text-align: center;
                    background: #4CAF50;
                    color: white;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 30px 0;
                }
                .amount-box h2 {
                    margin: 0 0 10px 0;
                    font-size: 24px;
                }
                .amount-box .amount {
                    font-size: 36px;
                    font-weight: bold;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px dashed #ccc;
                    color: #666;
                    font-size: 14px;
                }
                .print-btn {
                    text-align: center;
                    margin: 30px 0;
                }
                .btn-print {
                    display: inline-block;
                    background: #4CAF50;
                    color: white;
                    padding: 12px 25px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                    text-decoration: none;
                }
                .btn-print:hover {
                    background: #45a049;
                }
                .admin-note {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #856404;
                }
                @media print {
                    .print-btn {
                        display: none;
                    }
                    .btn-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="receipt-header">
                <h1><i class="fas fa-dumbbell"></i> FitLife Gym - Admin Receipt</h1>
            </div>

            <div class="company-info">
                <h2>FitLife Gym Membership System</h2>
                <p>123 Fitness Street, Kuala Lumpur</p>
                <p>Email: billing@fitlifegym.com | Phone: +60 12-345 6789</p>
            </div>

            <div class="admin-note">
                <p><i class="fas fa-info-circle"></i> <strong>ADMIN RECEIPT</strong> - This receipt was generated by <?php echo htmlspecialchars($admin_name); ?> on <?php echo date("F j, Y, h:i A"); ?></p>
            </div>

            <div class="receipt-details">
                <h2 style="color: #4CAF50; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                    RECEIPT #<?php echo str_pad($payment_data["id"], 6, "0", STR_PAD_LEFT); ?>
                </h2>

                <div class="detail-row">
                    <div class="detail-label">Receipt Date:</div>
                    <div class="detail-value"><?php echo date("F j, Y, h:i A", strtotime($payment_data["payment_date"])); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Member Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($payment_data["name"]); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Member Email:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($payment_data["email"]); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Payment Method:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($payment_data["payment_method"]); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Payment ID:</div>
                    <div class="detail-value"><?php echo $payment_data["id"]; ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Generated By:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($admin_name); ?> (Admin)</div>
                </div>
            </div>

            <div class="amount-box">
                <h2>Amount Paid</h2>
                <div class="amount">RM <?php echo number_format($payment_data["amount"], 2); ?></div>
            </div>

            <div class="print-btn">
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>

            <div class="footer">
                <p>This is an official receipt from FitLife Gym.</p>
                <p>For inquiries, contact: support@fitlifegym.com</p>
                <p><strong>Admin Reference:</strong> Receipt generated on <?php echo date("Y-m-d H:i:s"); ?></p>
            </div>

            <script>
                // Auto print option
                // window.onload = function() {
                //     window.print();
                // };
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Records - FitLife Gym Admin</title>
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
                <li><a href="admin_user_profiles.php"><i class="fas fa-users"></i> User Profiles</a></li>
                <li><a href="admin_payments.php" class="active"><i class="fas fa-credit-card"></i> Payment Records</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-header">
            <h1><i class="fas fa-credit-card"></i> Payment Records Management</h1>
            <p class="welcome-message">View, filter, and manage all payment transactions in the system.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Payments</h3>
                    <span class="stat-number"><?php echo $stats["total_payments"] ?? 0; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Revenue</h3>
                    <span class="stat-number">RM <?php echo number_format($stats["total_revenue"] ?? 0, 2); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-content">
                    <h3>Avg Payment</h3>
                    <span class="stat-number">RM <?php echo number_format($stats["average_payment"] ?? 0, 2); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3>Today's Payments</h3>
                    <span class="stat-number"><?php echo $today_stats["count"] ?? 0; ?> (RM <?php echo number_format($today_stats["total"] ?? 0, 2); ?>)</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-month"></i>
                </div>
                <div class="stat-content">
                    <h3>This Month</h3>
                    <span class="stat-number"><?php echo $month_stats["count"] ?? 0; ?> (RM <?php echo number_format($month_stats["total"] ?? 0, 2); ?>)</span>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Payments</h3>
                <span class="badge"><?php echo $payments_result->num_rows; ?> records found</span>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="search"><i class="fas fa-search"></i> Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, or payment ID">
                        </div>

                        <div class="form-group">
                            <label for="start_date"><i class="fas fa-calendar"></i> Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_date"><i class="fas fa-calendar"></i> End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>

                        <div class="form-group">
                            <label for="payment_method"><i class="fas fa-credit-card"></i> Payment Method</label>
                            <select id="payment_method" name="payment_method">
                                <option value="">All Methods</option>
                                <?php while ($method = $methods_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($method["payment_method"]); ?>" <?php echo $payment_method == $method["payment_method"] ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($method["payment_method"]); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions" style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_payments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Payments</h3>
                <span class="badge"><?php echo $payments_result->num_rows; ?> records</span>
            </div>
            <div class="card-body">
                <?php if ($payments_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Member</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($payment["id"], 6, "0", STR_PAD_LEFT); ?></td>
                                        <td>
                                            <a href="member_dashboard.php?user_id=<?php echo $payment["user_id"]; ?>" class="user-link">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($payment["user_name"]); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment["user_email"]); ?></td>
                                        <td class="text-success">RM <?php echo number_format($payment["amount"], 2); ?></td>
                                        <td>
                                            <span class="payment-method">
                                                <i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($payment["payment_method"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("M j, Y h:i A", strtotime($payment["payment_date"])); ?></td>
                                        <td>
                                            <a href="admin_payments.php?download_receipt=1&payment_id=<?php echo $payment["id"]; ?>" class="btn-action" title="Download Receipt">
                                                <i class="fas fa-file-download"></i>
                                            </a>
                                            <a href="#" class="btn-action" onclick="alert('View payment details for ID: <?php echo $payment["id"]; ?>')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="export-section" style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                        <h4><i class="fas fa-download"></i> Export Data</h4>
                        <p style="margin: 10px 0;">Export payment records for reporting and analysis.</p>
                        <div style="display: flex; gap: 15px; margin-top: 15px;">
                            <a href="#" class="btn btn-secondary" onclick="alert('Export as CSV coming soon!')">
                                <i class="fas fa-file-csv"></i> Export as CSV
                            </a>
                            <a href="#" class="btn btn-secondary" onclick="alert('Export as Excel coming soon!')">
                                <i class="fas fa-file-excel"></i> Export as Excel
                            </a>
                            <a href="#" class="btn btn-secondary" onclick="alert('Print report coming soon!')">
                                <i class="fas fa-print"></i> Print Report
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-credit-card"></i>
                        <h4>No Payments Found</h4>
                        <p>No payment records match your search criteria.</p>
                        <div style="margin-top: 20px;">
                            <a href="admin_payments.php" class="btn btn-primary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
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
                <a href="admin_user_profiles.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i> View User Profiles
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="contact-support" style="margin-top: 40px;">
            <h3><i class="fas fa-headset"></i> Admin Support</h3>
            <p>For assistance with payment records or technical issues, contact our system administrator.</p>
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
                <p>&copy; <?php echo date("Y"); ?> FitLife Gym Membership System. Admin Panel | Logged in as <?php echo htmlspecialchars($admin_name); ?></p>
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
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark-color);
        }

        .filter-form {
            background-color: white;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .user-link {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-link:hover {
            text-decoration: underline;
        }

        .payment-method {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background-color: #e9ecef;
            border-radius: 15px;
            font-size: 0.85rem;
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

        .text-success {
            color: #28a745;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
