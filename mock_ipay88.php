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

$user_id = isset($_GET["user_id"]) ? intval($_GET["user_id"]) : 0;
$amount = isset($_GET["amount"]) ? floatval($_GET["amount"]) : 0;
$is_upgrade = isset($_GET["upgrade"]) ? intval($_GET["upgrade"]) : 0;

if ($user_id !== $_SESSION["user_id"]) {
    die("Invalid user access.");
}

if ($amount <= 0) {
    die("Invalid payment amount.");
}

$user_name = $_SESSION["user_name"];

// Get user's current plan details for payment breakdown
$plan_query = $conn->prepare("
    SELECT p.name as plan_name, p.duration_days, p.price
    FROM subscriptions s
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.end_date DESC LIMIT 1
");
$plan_query->bind_param("i", $user_id);
$plan_query->execute();
$plan_result = $plan_query->get_result();

if ($plan_result->num_rows > 0) {
    $plan_details = $plan_result->fetch_assoc();
    $plan_name = $plan_details["plan_name"];
    $duration_days = $plan_details["duration_days"];
    $plan_price = $plan_details["price"];
} else {
    // Fallback if no subscription exists (shouldn't happen but for safety)
    $plan_name = "Membership Renewal";
    $duration_days = 30;
    $plan_price = $amount; // Use the passed amount as fallback
}

$plan_query->close();

// Calculate duration text
if ($duration_days == 30) {
    $duration_text = "1 Month";
} elseif ($duration_days == 365) {
    $duration_text = "1 Year";
} else {
    $duration_text = $duration_days . " days";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment Gateway - FitLife Gym</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }

        .payment-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 40px;
            text-align: center;
        }

        .payment-header {
            margin-bottom: 30px;
        }

        .payment-header i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .payment-header h2 {
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .payment-details {
            background-color: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark-color);
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .amount-display {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 20px 0;
        }

        .payment-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-success {
            background-color: var(--secondary-color);
            color: white;
            flex: 1;
        }

        .btn-success:hover {
            background-color: var(--secondary-dark);
        }

        .btn-danger {
            background-color: var(--accent-color);
            color: white;
            flex: 1;
        }

        .btn-danger:hover {
            background-color: var(--accent-dark);
        }

        .btn-warning {
            background-color: #ff9800;
            color: white;
            flex: 1;
        }

        .btn-warning:hover {
            background-color: #e68900;
        }

        .security-notice {
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .security-notice i {
            color: var(--secondary-color);
        }

        .fpx-processing-message {
            background-color: #f0f8ff;
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            text-align: center;
        }

        .fpx-processing-message i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .fpx-processing-message h4 {
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .fpx-processing-message p {
            color: #666;
            margin-bottom: 8px;
        }

        .fpx-processing-message small {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .payment-method-summary {
            background-color: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid var(--primary-color);
        }

        .payment-method-summary h4 {
            margin-bottom: 8px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-method-summary h4 i {
            color: var(--primary-color);
        }

        .payment-method-summary p {
            color: #666;
            font-size: 0.95rem;
        }

        .payment-section-title {
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-section-title i {
            color: var(--primary-color);
        }

        .payment-breakdown {
            background-color: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .breakdown-title {
            margin-bottom: 15px;
            color: var(--dark-color);
            font-weight: 600;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .breakdown-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .breakdown-total {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid var(--dark-color);
        }

        .payment-method-section {
            margin: 25px 0;
            text-align: left;
        }

        .payment-method-title {
            margin-bottom: 15px;
            color: var(--dark-color);
            font-weight: 600;
        }

        .payment-method-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .payment-method-option {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: white;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-method-option:hover {
            border-color: var(--primary-color);
        }

        .payment-method-option.selected {
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }

        .payment-method-option input {
            margin-right: 15px;
        }

        .payment-method-icon {
            font-size: 1.5rem;
            margin-right: 15px;
            color: var(--primary-color);
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-info h4 {
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .payment-method-info p {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .bank-selection {
            padding: 15px;
            background-color: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .bank-selection-title {
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .bank-select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            background-color: white;
            transition: var(--transition);
        }

        .bank-select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .bank-note {
            margin-top: 10px;
            color: var(--gray-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bank-note i {
            color: var(--primary-color);
        }

        .payment-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }

        .btn-back {
            background-color: var(--light-gray);
            color: var(--dark-color);
            flex: 1;
        }

        .btn-back:hover {
            background-color: #e3e6e8;
        }

        .final-payment-options .btn-back {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            display: block;
        }

        .btn-continue {
            background-color: var(--primary-color);
            color: white;
            flex: 1;
        }

        .btn-continue:hover {
            background-color: var(--primary-dark);
        }

        .final-payment-options {
            display: none;
            margin-top: 30px;
        }

        .final-payment-options.show {
            display: block;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fpxOption = document.getElementById('fpx-method');
            const continueBtn = document.getElementById('continue-btn');
            const finalOptions = document.getElementById('final-payment-options');
            const paymentDetails = document.getElementById('payment-details-section');

            // Select FPX by default
            fpxOption.checked = true;
            fpxOption.parentElement.classList.add('selected');

            // Show bank selection since FPX is selected by default
            const bankSelection = document.getElementById('bank-selection');
            bankSelection.style.display = 'block';

            // Set default bank selection
            document.getElementById('bank-select').value = 'maybank';

            // Handle payment method selection
            document.querySelectorAll('.payment-method-option input').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.payment-method-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    this.parentElement.classList.add('selected');
                });
            });

            // Handle Continue button click
            continueBtn.addEventListener('click', function() {
                const selectedBank = document.getElementById('bank-select').value;
                if (!selectedBank) {
                    alert('Please select your bank to continue with FPX payment.');
                    return;
                }

                paymentDetails.style.display = 'none';
                finalOptions.classList.add('show');
                updatePaymentMethodSummary(selectedBank);
                window.scrollTo({ top: finalOptions.offsetTop - 50, behavior: 'smooth' });
            });

            // Handle Back button
            document.getElementById('back-btn').addEventListener('click', function() {
                window.location.href = 'member_dashboard.php';
            });

            // Handle Back to Details button
            document.getElementById('back-to-details-btn').addEventListener('click', function() {
                finalOptions.classList.remove('show');
                paymentDetails.style.display = 'block';
                window.scrollTo({ top: paymentDetails.offsetTop - 50, behavior: 'smooth' });
            });

            // Handle payment method selection to show/hide bank selection
            fpxOption.addEventListener('change', function() {
                const bankSelection = document.getElementById('bank-selection');
                if (this.checked) {
                    bankSelection.style.display = 'block';
                } else {
                    bankSelection.style.display = 'none';
                }
            });

            // Function to update payment method summary with selected bank
            function updatePaymentMethodSummary(selectedBank) {
                const bankNames = {
                    'maybank': 'Maybank',
                    'cimb': 'CIMB Bank',
                    'public': 'Public Bank',
                    'rhb': 'RHB Bank',
                    'hongleong': 'Hong Leong Bank',
                    'ambank': 'AmBank',
                    'uob': 'UOB Bank',
                    'ocbc': 'OCBC Bank',
                    'hsbc': 'HSBC Bank',
                    'standard': 'Standard Chartered',
                    'bankislam': 'Bank Islam',
                    'affin': 'Affin Bank'
                };

                const bankName = bankNames[selectedBank] || selectedBank;
                document.getElementById('payment-method-summary-text').innerHTML =
                    `<h4><i class="fas fa-credit-card"></i> Payment Method: FPX - Online Banking (${bankName})</h4>
                     <p>You have selected ${bankName} for this FPX transaction.</p>`;
            }
        });
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-dumbbell"></i> FitLife Gym</h1>
            </div>
            <ul class="nav-links">
                <li><a href="member_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
            </ul>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(
                    $user_name,
                ); ?></span>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="payment-container">
            <div class="payment-card">
                <div class="payment-header">
                    <i class="fas fa-lock"></i>
                    <h2>Secure Payment Gateway</h2>
                    <p>Complete your payment securely</p>
                </div>

                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Merchant:</span>
                        <span class="detail-value">Gym Club</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Customer:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(
                            $user_name,
                        ); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-value">TXN<?php echo time(); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo date(
                            "F j, Y, g:i a",
                        ); ?></span>
                    </div>
                </div>

                <div id="payment-details-section">
                    <h3 class="payment-section-title"><i class="fas fa-file-invoice-dollar"></i> Payment Details</h3>
                    <div class="payment-breakdown">
                        <div class="breakdown-title">Payment Breakdown</div>
                        <div class="breakdown-item">
                            <span><?php echo htmlspecialchars(
                                $plan_name,
                            ); ?> Renewal (<?php echo $duration_text; ?>)</span>
                            <span>RM <?php echo number_format(
                                $amount,
                                2,
                            ); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span>Service Tax (0%)</span>
                            <span>RM 0.00</span>
                        </div>
                        <div class="breakdown-item">
                            <span>Processing Fee</span>
                            <span>RM 0.00</span>
                        </div>
                        <div class="breakdown-total breakdown-item">
                            <span>Total Amount</span>
                            <span>RM <?php echo number_format(
                                $amount,
                                2,
                            ); ?></span>
                        </div>
                    </div>

                    <div class="payment-method-section">
                        <div class="payment-method-title">Select Payment Method</div>
                        <div class="payment-method-options">
                            <label class="payment-method-option">
                                <input type="radio" name="payment_method" value="fpx" id="fpx-method">
                                <div class="payment-method-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="payment-method-info">
                                    <h4>FPX - Online Banking</h4>
                                    <p>Pay directly through your bank's online banking portal</p>
                                </div>
                            </label>

                            <div id="bank-selection" class="bank-selection" style="display: none; margin-top: 15px;">
                                <div class="bank-selection-title">Select Your Bank</div>
                                <select id="bank-select" class="bank-select">
                                    <option value="">-- Please select your bank --</option>
                                    <option value="maybank">Maybank</option>
                                    <option value="cimb">CIMB Bank</option>
                                    <option value="public">Public Bank</option>
                                    <option value="rhb">RHB Bank</option>
                                    <option value="hongleong">Hong Leong Bank</option>
                                    <option value="ambank">AmBank</option>
                                    <option value="uob">UOB Bank</option>
                                    <option value="ocbc">OCBC Bank</option>
                                    <option value="hsbc">HSBC Bank</option>
                                    <option value="standard">Standard Chartered</option>
                                    <option value="bankislam">Bank Islam</option>
                                    <option value="affin">Affin Bank</option>
                                </select>
                                <div class="bank-note">
                                    <small><i class="fas fa-info-circle"></i> You will be redirected to your selected bank's secure login page</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="payment-navigation">
                        <button id="back-btn" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </button>
                        <button id="continue-btn" class="btn btn-continue">
                            Continue to Payment <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="final-payment-options" class="final-payment-options">
                    <div class="amount-display">
                        RM <?php echo number_format($amount, 2); ?>
                    </div>

                    <div class="payment-method-summary" id="payment-method-summary">
                        <div id="payment-method-summary-text">
                            <h4><i class="fas fa-credit-card"></i> Payment Method: FPX - Online Banking</h4>
                            <p>You have selected FPX (Financial Process Exchange) for this transaction.</p>
                        </div>
                    </div>

                    <div class="fpx-processing-message">
                        <i class="fas fa-university"></i>
                        <h4>Processing FPX Payment</h4>
                        <p>You will be redirected to your bank's secure online banking portal to authorize this payment.</p>
                        <p><small>Amount: RM <?php echo number_format(
                            $amount,
                            2,
                        ); ?></small></p>
                    </div>
                    <p>This is a mock payment gateway for demonstration purposes.</p>

                    <div class="payment-buttons">
                        <a href="payment_callback.php?status=1&user_id=<?php echo $user_id; ?>&amount=<?php echo $amount; ?>&upgrade=<?php echo $is_upgrade; ?>"
                           class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Success
                        </a>
                        <a href="payment_callback.php?status=2&user_id=<?php echo $user_id; ?>&amount=<?php echo $amount; ?>&upgrade=<?php echo $is_upgrade; ?>"
                           class="btn btn-warning">
                            <i class="fas fa-exclamation-circle"></i> Failed
                        </a>
                        <a href="payment_callback.php?status=0&user_id=<?php echo $user_id; ?>&upgrade=<?php echo $is_upgrade; ?>"
                           class="btn btn-danger">
                            <i class="fas fa-times-circle"></i> Cancel
                        </a>
                    </div>

                    <div class="payment-navigation" style="margin-top: 30px;">
                        <button id="back-to-details-btn" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Payment Details
                        </button>
                    </div>
                </div>

                <div class="security-notice">
                    <i class="fas fa-shield-alt"></i>
                    <span>This is a secure payment gateway for demonstration only</span>
                </div>
            </div>

            <div class="payment-info">
                <h3><i class="fas fa-info-circle"></i> About This Payment</h3>
                <p>This payment will renew your gym membership subscription. Upon successful payment:</p>
                <ul>
                    <li><i class="fas fa-check"></i> Your subscription will be activated</li>
                    <li><i class="fas fa-check"></i> Payment will be recorded in your history</li>
                    <li><i class="fas fa-check"></i> Membership QR code will be available</li>
                </ul>
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
                    <h3>Payment Security</h3>
                    <p><i class="fas fa-lock"></i> 256-bit SSL Encryption</p>
                    <p><i class="fas fa-shield-alt"></i> PCI DSS Compliant</p>
                    <p><i class="fas fa-user-shield"></i> Fraud Protection</p>
                </div>
                <div class="footer-section">
                    <h3>Need Help?</h3>
                    <p><i class="fas fa-phone"></i> Payment Support: +60 12-345 6789</p>
                    <p><i class="fas fa-envelope"></i> billing@fitlifegym.com</p>
                    <p><i class="fas fa-clock"></i> 24/7 Support Available</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date(
                    "Y",
                ); ?> FitLife Gym Membership System. Mock Payment Gateway v1.0</p>
            </div>
        </div>
    </footer>
</body>
</html>
