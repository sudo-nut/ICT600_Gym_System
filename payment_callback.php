<?php
require_once "includes/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_role"])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET["status"]) ? intval($_GET["status"]) : 0;
$user_id = isset($_GET["user_id"]) ? intval($_GET["user_id"]) : 0;
$amount = isset($_GET["amount"]) ? floatval($_GET["amount"]) : 0;
$is_upgrade = isset($_GET["upgrade"]) ? intval($_GET["upgrade"]) : 0;

if ($_SESSION["user_role"] !== "admin" && $user_id !== $_SESSION["user_id"]) {
    header("Location: login.php");
    exit();
}

if ($status == 1) {
    if ($user_id <= 0 || $amount <= 0) {
        header("Location: member_dashboard.php?msg=payment_invalid");
        exit();
    }

    $conn->begin_transaction();

    try {
        // For upgrades, we need to handle the pending upgrade subscription
        if ($is_upgrade == 1) {
            // First, expire any existing active subscription
            $expire_query = $conn->prepare("
                UPDATE subscriptions
                SET status = 'expired', end_date = CURDATE()
                WHERE user_id = ? AND status = 'active'
            ");
            $expire_query->bind_param("i", $user_id);
            $expire_query->execute();
        }

        $plan_query = $conn->prepare("
            SELECT s.plan_id, p.duration_days, p.name as plan_name
            FROM subscriptions s
            JOIN membership_plans p ON s.plan_id = p.id
            WHERE s.user_id = ?
            ORDER BY s.end_date DESC LIMIT 1
        ");
        $plan_query->bind_param("i", $user_id);
        $plan_query->execute();
        $plan_result = $plan_query->get_result();

        if ($plan_result->num_rows > 0) {
            $plan = $plan_result->fetch_assoc();
            $plan_id = $plan["plan_id"];
            $duration_days = $plan["duration_days"];

            $payment_query = $conn->prepare("
                INSERT INTO payments (user_id, amount, payment_method, payment_date)
                VALUES (?, ?, 'Online Banking', NOW())
            ");
            $payment_query->bind_param("id", $user_id, $amount);
            $payment_query->execute();

            $sub_check = $conn->prepare("
                SELECT id FROM subscriptions
                WHERE user_id = ?
                ORDER BY end_date DESC LIMIT 1
            ");
            $sub_check->bind_param("i", $user_id);
            $sub_check->execute();
            $sub_check_result = $sub_check->get_result();

            if ($sub_check_result->num_rows > 0) {
                $update_query = $conn->prepare("
                    UPDATE subscriptions
                    SET status = 'active',
                        start_date = CURDATE(),
                        end_date = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                        plan_id = ?
                    WHERE user_id = ?
                    ORDER BY end_date DESC LIMIT 1
                ");
                $update_query->bind_param(
                    "iii",
                    $duration_days,
                    $plan_id,
                    $user_id,
                );
                $update_query->execute();
            } else {
                $insert_query = $conn->prepare("
                    INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'active')
                ");
                $insert_query->bind_param(
                    "iii",
                    $user_id,
                    $plan_id,
                    $duration_days,
                );
                $insert_query->execute();
            }

            $conn->commit();
            if ($is_upgrade == 1) {
                header("Location: member_dashboard.php?msg=upgrade_success");
            } else {
                header("Location: member_dashboard.php?msg=payment_success");
            }
            exit();
        } else {
            $conn->rollback();
            header("Location: register.php?msg=no_plan");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: member_dashboard.php?msg=payment_error");
        exit();
    }
} elseif ($status == 2) {
    // Failed payment - don't save any info, just redirect with failed message
    header("Location: member_dashboard.php?msg=payment_failed");
    exit();
} else {
    // Cancelled payment (status 0) - don't save any info, redirect with cancelled message
    header("Location: member_dashboard.php?msg=payment_cancelled");
    exit();
}
?>
