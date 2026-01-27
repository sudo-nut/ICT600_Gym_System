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

// Get user's payment history
$payments_query = $conn->prepare("
    SELECT p.id, p.amount, p.payment_method, p.payment_date
    FROM payments p
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
");
$payments_query->bind_param("i", $user_id);
$payments_query->execute();
$payments_result = $payments_query->get_result();

$total_payments = 0;
$total_amount = 0;
$payments = [];

while ($payment = $payments_result->fetch_assoc()) {
    $payments[] = $payment;
    $total_payments++;
    $total_amount += $payment["amount"];
}

// Handle receipt download
if (isset($_GET["download"]) && isset($_GET["payment_id"])) {
    $payment_id = intval($_GET["payment_id"]);

    // Verify payment belongs to user
    $verify_query = $conn->prepare("
        SELECT p.*, u.name, u.email
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $verify_query->bind_param("ii", $payment_id, $user_id);
    $verify_query->execute();
    $verify_result = $verify_query->get_result();

    if ($verify_result->num_rows > 0) {
        $payment_data = $verify_result->fetch_assoc();
        // Generate HTML receipt
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Receipt - FitLife Gym</title>
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
                <h1>FitLife Gym - Payment Receipt</h1>
            </div>

            <div class="company-info">
                <h2>FitLife Gym Membership System</h2>
                <p>123 Fitness Street, Kuala Lumpur</p>
                <p>Email: billing@fitlifegym.com</p>
            </div>

            <div class="receipt-details">
                <h2 style="color: #4CAF50; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                    RECEIPT #<?php echo str_pad(
                        $payment_data["id"],
                        6,
                        "0",
                        STR_PAD_LEFT,
                    ); ?>
                </h2>

                <div class="detail-row">
                    <div class="detail-label">Receipt Date:</div>
                    <div class="detail-value"><?php echo date(
                        "F j, Y, h:i A",
                        strtotime($payment_data["payment_date"]),
                    ); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Member Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars(
                        $payment_data["name"],
                    ); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Member Email:</div>
                    <div class="detail-value"><?php echo htmlspecialchars(
                        $payment_data["email"],
                    ); ?></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Payment Method:</div>
                    <div class="detail-value"><?php echo htmlspecialchars(
                        $payment_data["payment_method"],
                    ); ?></div>
                </div>
            </div>

            <div class="amount-box">
                <h2>Amount Paid</h2>
                <div class="amount">RM <?php echo number_format(
                    $payment_data["amount"],
                    2,
                ); ?></div>
            </div>

            <div class="print-btn">
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>

            <div class="footer">
                <p>Thank you for your payment!</p>
                <p>This is an official receipt from FitLife Gym.</p>
                <p>For inquiries, contact: support@fitlifegym.com</p>
            </div>

            <script>
                // Auto print option (optional)
                // window.onload = function() {
                //     window.print();
                // };
            </script>
        </body>
        </html>
        <?php exit();
    } else {
        header("Location: payment_history.php?error=invalid_payment");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - FitLife Gym</title>
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
                <li><a href="payment_history.php" class="active"><i class="fas fa-history"></i> Payment History</a></li>
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
                    <a href="payment_history.php" class="active">
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
                <h1><i class="fas fa-history"></i> Payment History</h1>
                <p class="welcome-message">View your payment transactions and billing history.</p>

                <?php if (count($payments) > 0): ?>
                    <div class="payment-summary">
                        <div class="summary-card">
                            <i class="fas fa-receipt"></i>
                            <h3>Total Payments</h3>
                            <p class="amount"><?php echo $total_payments; ?></p>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <h3>Total Amount</h3>
                            <p class="amount">RM <?php echo number_format(
                                $total_amount,
                                2,
                            ); ?></p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>#<?php echo str_pad(
                                            $payment["id"],
                                            6,
                                            "0",
                                            STR_PAD_LEFT,
                                        ); ?></td>
                                        <td class="text-success">RM <?php echo number_format(
                                            $payment["amount"],
                                            2,
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $payment["payment_method"],
                                        ); ?></td>
                                        <td><?php echo date(
                                            "M j, Y h:i A",
                                            strtotime($payment["payment_date"]),
                                        ); ?></td>
                                        <td>
                                            <a href="payment_history.php?download=1&payment_id=<?php echo $payment[
                                                "id"
                                            ]; ?>" class="btn-action" title="Download Receipt">
                                                <i class="fas fa-file-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="export-options" style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                        <h4><i class="fas fa-download"></i> Export Options</h4>
                        <p style="margin: 10px 0;">Download your payment history in different formats.</p>
                        <div style="display: flex; gap: 15px; margin-top: 15px;">
                            <a href="#" class="btn btn-secondary" onclick="alert('PDF export coming soon!')">
                                <i class="fas fa-file-pdf"></i> Export as PDF
                            </a>
                            <a href="#" class="btn btn-secondary" onclick="alert('CSV export coming soon!')">
                                <i class="fas fa-file-csv"></i> Export as CSV
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-payments">
                        <div class="no-payments-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3>No Payment History Found</h3>
                        <p>You haven't made any payments yet. Once you complete a payment, it will appear here.</p>
                        <a href="select_plan.php" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Make Your First Payment
                        </a>
                    </div>
                <?php endif; ?>

                <div class="contact-support" style="margin-top: 40px;">
                    <h3><i class="fas fa-headset"></i> Need Assistance?</h3>
                    <p>If you need information about a specific payment, contact our support team.</p>
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
                        <li><a href="payment_history.php">Payment History</a></li>
                        <li><a href="cancel_subscription.php">Cancel Subscription</a></li>
                        <li><a href="delete_account.php">Delete Account</a></li>
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
        .payment-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--primary-color);
        }

        .summary-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .summary-card h3 {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-card .amount {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-color);
            margin: 0;
        }

        .no-payments {
            text-align: center;
            padding: 50px 30px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .no-payments-icon {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }

        .no-payments h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .no-payments p {
            color: #666;
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</body>
</html>
