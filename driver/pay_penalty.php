<?php
session_start();
include '../config/database.php';

// ✅ FUNCTION TO GET ADMIN SETTINGS
function getAdminSettings($conn) {
    $settings = [];
    $query = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
    while ($row = mysqli_fetch_assoc($query)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// ✅ GET ADMIN SETTINGS
$admin_settings = getAdminSettings($conn);
$admin_phone = $admin_settings['admin_phone'] ?? '+251911234567';
$admin_name = $admin_settings['admin_name'] ?? 'Smart Driver Admin';
$admin_email = $admin_settings['admin_email'] ?? 'admin@smartdriver.com';
$bank_name = $admin_settings['bank_name'] ?? 'Commercial Bank of Ethiopia';
$bank_account = '1000410107566';
$bank_account_name = $admin_settings['bank_account_name'] ?? 'Smart Driver';
$office_address = $admin_settings['office_address'] ?? 'Bole Road, Addis Ababa';
$office_phone = $admin_settings['office_phone'] ?? '+251 911 234 567';

// Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$receipt_data = null;

// ✅ GET USER INFO
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// ✅ GET DRIVER BALANCE
$driver_balance = $user['driver_balance'] ?? 0;

// ============================================ //
// ✅ PROCESS PAYMENT WITH VALIDATION           //
// ============================================ //
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_penalty'])) {
    $violation_id = (int)$_POST['violation_id'];
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number'] ?? '');
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
    $bank_name_input = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $driver_balance = (float)($_POST['driver_balance'] ?? 0);
    $transaction_reference = mysqli_real_escape_string($conn, $_POST['transaction_reference'] ?? '');
    
    $validation_error = '';
    
    // ✅ Get violation details - ONLY UNPAID AND VERIFIED
    $check = mysqli_query($conn, "SELECT * FROM violations WHERE id = $violation_id AND user_id = $user_id AND paid_status = 'unpaid' AND status = 'verified'");
    
    if (mysqli_num_rows($check) > 0) {
        $violation = mysqli_fetch_assoc($check);
        $fine_amount = $violation['fine_amount'];
        
        // ✅ Get rule name for receipt
        $rule_query = mysqli_query($conn, "SELECT rule_name FROM traffic_rules WHERE id = " . $violation['rule_id']);
        $rule_data = mysqli_fetch_assoc($rule_query);
        $rule_name = $rule_data['rule_name'] ?? 'Traffic Violation';
        
        // ✅ Validate based on payment method
        if ($payment_method == 'telebirr') {
            $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
            $clean_admin_phone = preg_replace('/[^0-9]/', '', $admin_phone);
            
            if (empty($phone_number)) {
                $validation_error = "❌ TeleBirr phone number is required!";
            } elseif ($clean_phone !== $clean_admin_phone) {
                $validation_error = "❌ Invalid TeleBirr number! Please use the admin's TeleBirr number: $admin_phone";
            } elseif ($driver_balance < $fine_amount) {
                $validation_error = "❌ Insufficient balance! You need " . number_format($fine_amount, 2) . " ETB. Your balance: " . number_format($driver_balance, 2) . " ETB";
            }
            
        } elseif ($payment_method == 'cbe_birr') {
            if (empty($account_number)) {
                $validation_error = "❌ CBE Birr account number is required!";
            } elseif (!preg_match('/^1000[0-9]{6,}$/', $account_number) && !preg_match('/^10000[0-9]{5,}$/', $account_number)) {
                $validation_error = "❌ Invalid CBE Birr account! Must start with 1000 or 10000";
            } elseif ($driver_balance < $fine_amount) {
                $validation_error = "❌ Insufficient balance! You need " . number_format($fine_amount, 2) . " ETB. Your balance: " . number_format($driver_balance, 2) . " ETB";
            }
            
        } elseif ($payment_method == 'bank_transfer') {
            if (empty($account_number)) {
                $validation_error = "❌ Bank account number is required!";
            } elseif (strlen($account_number) < 8) {
                $validation_error = "❌ Invalid account number! Must be at least 8 digits.";
            } elseif ($driver_balance < $fine_amount) {
                $validation_error = "❌ Insufficient balance! You need " . number_format($fine_amount, 2) . " ETB. Your balance: " . number_format($driver_balance, 2) . " ETB";
            }
            
        } elseif ($payment_method == 'cash') {
            if ($driver_balance < $fine_amount) {
                $validation_error = "❌ Insufficient balance! You need " . number_format($fine_amount, 2) . " ETB. Your balance: " . number_format($driver_balance, 2) . " ETB";
            }
            
        } elseif ($payment_method == 'credit_card') {
            $card_number = $_POST['card_number'] ?? '';
            $card_expiry = $_POST['card_expiry'] ?? '';
            $card_cvv = $_POST['card_cvv'] ?? '';
            
            if (empty($card_number) || strlen($card_number) < 16) {
                $validation_error = "❌ Valid card number is required!";
            } elseif (empty($card_expiry) || !preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
                $validation_error = "❌ Valid expiry date (MM/YY) is required!";
            } elseif (empty($card_cvv) || strlen($card_cvv) < 3) {
                $validation_error = "❌ Valid CVV is required!";
            } elseif ($driver_balance < $fine_amount) {
                $validation_error = "❌ Insufficient balance! You need " . number_format($fine_amount, 2) . " ETB. Your balance: " . number_format($driver_balance, 2) . " ETB";
            }
            
        } else {
            $validation_error = "❌ Please select a valid payment method.";
        }
        
        // ✅ If validation passes, process payment
        if (empty($validation_error)) {
            // Generate unique transaction reference
            $ref = 'TRX-' . strtoupper(uniqid()) . '-' . date('Ymd');
            
            // ✅ UPDATE VIOLATION - MARK AS PAID
            $update = "UPDATE violations SET 
                        paid_status = 'paid',
                        payment_method = '$payment_method',
                        transaction_id = '$ref',
                        payment_date = NOW(),
                        payment_status = 'completed'
                       WHERE id = $violation_id";
            
            if (mysqli_query($conn, $update)) {
                // ✅ Update driver balance (deduct the fine amount)
                $new_balance = $driver_balance - $fine_amount;
                $update_balance = mysqli_query($conn, "UPDATE users SET driver_balance = $new_balance WHERE id = $user_id");
                
                if (!$update_balance) {
                    $error = "❌ Failed to update balance: " . mysqli_error($conn);
                } else {
                    // ✅ Record transaction
                    $description = "Penalty payment via " . ucfirst(str_replace('_', ' ', $payment_method));
                    if ($payment_method == 'telebirr') {
                        $description .= " | Phone: $phone_number";
                    } elseif ($payment_method == 'cbe_birr') {
                        $description .= " | Account: $account_number";
                    } elseif ($payment_method == 'bank_transfer') {
                        $description .= " | Account: $account_number | Bank: $bank_name_input";
                    }
                    
                    mysqli_query($conn, "INSERT INTO driver_transactions (user_id, transaction_type, amount, payment_method, transaction_id, description, status) 
                                        VALUES ($user_id, 'payment', $fine_amount, '$payment_method', '$ref', '$description', 'completed')");
                    
                    // ✅ Generate Receipt Data
                    $receipt_data = [
                        'transaction_id' => $ref,
                        'driver_name' => $user['full_name'],
                        'driver_email' => $user['email'],
                        'driver_phone' => $user['phone'],
                        'violation' => $rule_name,
                        'amount' => $fine_amount,
                        'payment_method' => $payment_method,
                        'payment_date' => date('Y-m-d H:i:s'),
                        'status' => 'Completed',
                        'admin_name' => $admin_name,
                        'admin_phone' => $admin_phone,
                        'bank_name' => $bank_name,
                        'bank_account' => $bank_account,
                        'phone_number' => $phone_number,
                        'account_number' => $account_number,
                        'office_address' => $office_address,
                        'office_phone' => $office_phone,
                        'new_balance' => $new_balance
                    ];
                    
                    // ✅ Set success message
                    $_SESSION['payment_success'] = "✅ Payment successful! Amount: " . number_format($fine_amount, 2) . " ETB<br>";
                    $_SESSION['payment_success'] .= "🧾 Transaction ID: $ref<br>";
                    $_SESSION['payment_success'] .= "💳 Payment Method: " . ucfirst(str_replace('_', ' ', $payment_method)) . "<br>";
                    $_SESSION['payment_success'] .= "📊 New Balance: " . number_format($new_balance, 2) . " ETB";
                    
                    // ✅ Send notification to driver
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                        VALUES ($user_id, '✅ Penalty Paid', 
                                        'You have successfully paid the penalty of " . number_format($fine_amount, 2) . " ETB. 
                                        Transaction ID: $ref. Admin: $admin_name', 'payment')");
                    
                    // ✅ Log activity
                    mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, description) 
                                        VALUES ($user_id, 'pay_penalty', 'Paid penalty ID: $violation_id via $payment_method')");
                    
                    // ✅ DO NOT REDIRECT - Stay on page to show receipt
                    // header('Location: pay_penalty.php?success=1');
                    // exit();
                }
            } else {
                $error = "❌ Payment failed: " . mysqli_error($conn);
            }
        } else {
            $error = $validation_error;
        }
    } else {
        $error = "❌ This violation has already been paid or does not exist.";
    }
}

// ✅ Check for success message from session
if (isset($_SESSION['payment_success'])) {
    $success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']);
}

// ✅ GET ALL UNPAID VIOLATIONS (ONLY UNPAID)
$unpaid_violations = mysqli_query($conn, "
    SELECT v.*, r.rule_name, r.description as rule_description
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
      AND v.paid_status = 'unpaid' 
      AND v.status = 'verified'
    ORDER BY v.violation_date DESC
");

// ✅ GET PAID VIOLATIONS HISTORY
$paid_violations = mysqli_query($conn, "
    SELECT v.*, r.rule_name 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
      AND v.paid_status = 'paid'
    ORDER BY v.payment_date DESC
    LIMIT 10
");

// ✅ GET TOTAL FINES
$total_unpaid = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(fine_amount) as total FROM violations 
     WHERE user_id = $user_id 
       AND paid_status = 'unpaid' 
       AND status = 'verified'"
))['total'] ?? 0;

$total_paid = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(fine_amount) as total FROM violations 
     WHERE user_id = $user_id 
       AND paid_status = 'paid'"
))['total'] ?? 0;

$unpaid_count = mysqli_num_rows($unpaid_violations);
$total_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE user_id = $user_id"))['count'] ?? 0;

$page_title = 'Pay Penalties - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<style>
@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.receipt-box {
    border: 2px solid #2e7d32;
    padding: 20px;
    border-radius: 12px;
    background: #f8fff8;
    max-width: 550px;
    margin: 20px auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.receipt-box .header {
    text-align: center;
    border-bottom: 2px dashed #2e7d32;
    padding-bottom: 15px;
    margin-bottom: 15px;
}
.receipt-box .header h2 {
    color: #2e7d32;
    margin: 0;
    font-size: 24px;
}
.receipt-box .header p {
    color: #666;
    margin: 5px 0 0 0;
    font-size: 14px;
}
.receipt-box .details {
    padding: 10px 0;
}
.receipt-box .details table {
    width: 100%;
    border-collapse: collapse;
}
.receipt-box .details td {
    padding: 8px 5px;
    border-bottom: 1px solid #f0f0f0;
}
.receipt-box .details .label {
    color: #666;
    font-weight: 500;
}
.receipt-box .details .value {
    font-weight: 600;
    text-align: right;
}
.receipt-box .footer {
    text-align: center;
    border-top: 2px dashed #2e7d32;
    padding-top: 15px;
    margin-top: 15px;
    font-size: 12px;
    color: #999;
}
.qr-code {
    background: white;
    padding: 15px;
    border-radius: 8px;
    display: inline-block;
    border: 1px solid #ddd;
}
.payment-info-box {
    background: #e8f0fe;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #1a237e;
    margin-bottom: 20px;
}
.payment-method-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 2px solid #e0e0e0;
    transition: border-color 0.3s, transform 0.3s;
    cursor: pointer;
}
.payment-method-card:hover {
    border-color: #1a237e;
    transform: translateY(-3px);
}
.payment-method-card.selected {
    border-color: #1a237e;
    background: #f0f4ff;
}
.print-btn {
    background: #1a237e;
    color: white;
    padding: 10px 25px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
.print-btn:hover {
    background: #0d47a1;
}
.success-box {
    background: #e8f5e9;
    padding: 20px;
    border-radius: 8px;
    color: #2e7d32;
    margin-bottom: 20px;
    border-left: 4px solid #2e7d32;
    text-align: center;
}
</style>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-money-bill"></i> Pay Penalties
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <span style="background: <?php echo $unpaid_count > 0 ? '#c62828' : '#2e7d32'; ?>; color: white; padding: 8px 16px; border-radius: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $unpaid_count; ?> Unpaid
            </span>
            <span style="background: #1a237e; color: white; padding: 8px 16px; border-radius: 20px;">
                <i class="fas fa-wallet"></i> Balance: <?php echo number_format($driver_balance, 2); ?> ETB
            </span>
            <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="deposit.php" style="padding: 10px 20px; background: #f9a825; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-wallet"></i> Deposit
            </a>
        </div>
    </div>

    <!-- Admin Payment Info -->
    <div class="payment-info-box">
        <h4 style="margin: 0; color: #1a237e;">
            <i class="fas fa-info-circle"></i> Payment Information
        </h4>
        <table style="width: 100%; margin-top: 10px; font-size: 14px;">
            <tr>
                <td style="padding: 4px 0; color: #555; width: 150px;"><strong>Admin Name:</strong></td>
                <td style="padding: 4px 0;"><?php echo $admin_name; ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #555;"><strong>Admin Phone:</strong></td>
                <td style="padding: 4px 0;"><?php echo $admin_phone; ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #555;"><strong>Bank:</strong></td>
                <td style="padding: 4px 0;"><?php echo $bank_name; ?> - <?php echo $bank_account; ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #555;"><strong>Office:</strong></td>
                <td style="padding: 4px 0;"><?php echo $office_address; ?> | <?php echo $office_phone; ?></td>
            </tr>
        </table>
    </div>

    <!-- ✅ SUCCESS MESSAGE (shown when payment is successful) -->
    <?php if ($success): ?>
        <div class="success-box">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; color: #2e7d32; margin-bottom: 20px; border-left: 4px solid #2e7d32; text-align: center;">
            <h3 style="margin: 0;">✅ Payment Successful!</h3>
            <p style="margin: 5px 0 0 0;">Your penalty has been paid. The violation has been removed from your unpaid list.</p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828; margin-bottom: 20px; border-left: 4px solid #c62828;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Financial Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #c62828;">
            <div style="font-size: 28px; font-weight: bold; color: #c62828;"><?php echo number_format($total_unpaid, 2); ?> ETB</div>
            <div style="color: #666; font-size: 14px;">💰 Total Unpaid</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #2e7d32;">
            <div style="font-size: 28px; font-weight: bold; color: #2e7d32;"><?php echo number_format($total_paid, 2); ?> ETB</div>
            <div style="color: #666; font-size: 14px;">✅ Total Paid</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #1a237e;">
            <div style="font-size: 28px; font-weight: bold; color: #1a237e;"><?php echo $unpaid_count; ?></div>
            <div style="color: #666; font-size: 14px;">📋 Unpaid Violations</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #f9a825;">
            <div style="font-size: 28px; font-weight: bold; color: #f9a825;"><?php echo number_format($driver_balance, 2); ?> ETB</div>
            <div style="color: #666; font-size: 14px;">💳 Your Balance</div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ✅ RECEIPT DISPLAY (when payment is successful) -->
    <!-- ============================================ -->
    <?php if ($receipt_data): ?>
    <div style="background: white; border-radius: 12px; padding: 30px; margin: 30px 0; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: 2px solid #2e7d32;">
        <h2 style="text-align: center; color: #2e7d32; margin-bottom: 20px;">
            <i class="fas fa-receipt"></i> Payment Receipt
        </h2>
        <div class="receipt-box" id="receipt">
            <div class="header">
                <h2>✅ PAYMENT CONFIRMED</h2>
                <p><strong>Smart Driver - Traffic Management System</strong></p>
                <p style="font-size: 12px; color: #999;">Official Payment Receipt</p>
            </div>
            
            <div class="details">
                <table>
                    <tr>
                        <td class="label">🧾 Transaction ID</td>
                        <td class="value"><?php echo $receipt_data['transaction_id']; ?></td>
                    </tr>
                    <tr>
                        <td class="label">👤 Driver Name</td>
                        <td class="value"><?php echo $receipt_data['driver_name']; ?></td>
                    </tr>
                    <tr>
                        <td class="label">📧 Email</td>
                        <td class="value"><?php echo $receipt_data['driver_email']; ?></td>
                    </tr>
                    <tr>
                        <td class="label">📱 Phone</td>
                        <td class="value"><?php echo $receipt_data['driver_phone']; ?></td>
                    </tr>
                    <tr>
                        <td class="label">🚦 Violation</td>
                        <td class="value"><?php echo $receipt_data['violation']; ?></td>
                    </tr>
                    <tr>
                        <td class="label">💰 Amount Paid</td>
                        <td class="value" style="color: #c62828; font-size: 20px;"><?php echo number_format($receipt_data['amount'], 2); ?> ETB</td>
                    </tr>
                    <tr>
                        <td class="label">💳 Payment Method</td>
                        <td class="value">
                            <?php 
                                $method_icons = [
                                    'telebirr' => '📱 TeleBirr',
                                    'cbe_birr' => '🏦 CBE Birr',
                                    'bank_transfer' => '🏛️ Bank Transfer',
                                    'cash' => '💵 Cash',
                                    'credit_card' => '💳 Credit Card'
                                ];
                                echo $method_icons[$receipt_data['payment_method']] ?? $receipt_data['payment_method'];
                            ?>
                        </td>
                    </tr>
                    <?php if ($receipt_data['phone_number']): ?>
                    <tr>
                        <td class="label">📱 Phone Number</td>
                        <td class="value"><?php echo $receipt_data['phone_number']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($receipt_data['account_number']): ?>
                    <tr>
                        <td class="label">🏦 Account Number</td>
                        <td class="value"><?php echo $receipt_data['account_number']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label">📅 Payment Date</td>
                        <td class="value"><?php echo date('F d, Y h:i A', strtotime($receipt_data['payment_date'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label">📊 New Balance</td>
                        <td class="value" style="color: #2e7d32;"><?php echo number_format($receipt_data['new_balance'], 2); ?> ETB</td>
                    </tr>
                    <tr>
                        <td class="label">👨‍💼 Processed By</td>
                        <td class="value"><?php echo $receipt_data['admin_name']; ?></td>
                    </tr>
                </table>
            </div>
            
            <div style="text-align: center; margin: 15px 0;">
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('Transaction: ' . $receipt_data['transaction_id'] . ' | Amount: ' . $receipt_data['amount'] . ' ETB | Driver: ' . $receipt_data['driver_name']); ?>" 
                         alt="QR Code" width="150" height="150">
                </div>
                <p style="color: #999; font-size: 12px; margin-top: 5px;">Scan QR to verify payment</p>
            </div>
            
            <div class="footer">
                <p>This is a system-generated receipt. Please keep for your records.</p>
                <p>Smart Driver - <?php echo date('Y'); ?> | <?php echo $office_address; ?></p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" class="print-btn">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="pay_penalty.php" style="display: inline-block; padding: 10px 25px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none; margin-left: 10px; font-weight: 600;">
                <i class="fas fa-check"></i> Done
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- UNPAID VIOLATIONS LIST                       -->
    <!-- ============================================ -->
    <?php if ($unpaid_count > 0 && !$receipt_data): ?>
        <h3 style="color: #c62828; margin-bottom: 15px;">
            <i class="fas fa-exclamation-triangle"></i> Unpaid Violations
        </h3>
        
        <?php while ($v = mysqli_fetch_assoc($unpaid_violations)): ?>
            <div style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #c62828;">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: center;">
                    <div>
                        <h4 style="margin: 0; color: #1a237e;"><?php echo $v['rule_name']; ?></h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($v['violation_date'])); ?>
                            <i class="fas fa-map-marker-alt" style="margin-left: 15px;"></i> <?php echo $v['location'] ?? 'N/A'; ?>
                        </p>
                        <?php if ($v['description']): ?>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                <i class="fas fa-info-circle"></i> <?php echo $v['description']; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 22px; font-weight: bold; color: #c62828;">
                            <?php echo number_format($v['fine_amount'], 2); ?> ETB
                        </div>
                        <div style="color: #666; font-size: 12px;">Fine Amount</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 16px; font-weight: bold; color: #c62828;">
                            -<?php echo $v['points_deducted']; ?>
                        </div>
                        <div style="color: #666; font-size: 12px;">Points</div>
                    </div>
                    <div>
                        <?php if ($driver_balance >= $v['fine_amount']): ?>
                            <button onclick="openPaymentModal(<?php echo $v['id']; ?>, '<?php echo addslashes($v['rule_name']); ?>', <?php echo $v['fine_amount']; ?>, <?php echo $driver_balance; ?>)"
                                    style="padding: 12px 25px; background: #2e7d32; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px;">
                                💳 Pay Now
                            </button>
                        <?php else: ?>
                            <button disabled
                                    style="padding: 12px 25px; background: #9e9e9e; color: white; border: none; border-radius: 8px; cursor: not-allowed; font-weight: 600; font-size: 15px;">
                                💳 Insufficient Balance
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($driver_balance < $v['fine_amount']): ?>
                    <div style="background: #ffebee; padding: 12px 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #c62828;">
                        <p style="margin: 0; color: #c62828; font-weight: 500;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Insufficient balance! You need <strong><?php echo number_format($v['fine_amount'], 2); ?> ETB</strong>.
                            Your balance: <strong><?php echo number_format($driver_balance, 2); ?> ETB</strong>.
                            <br><span style="font-size: 13px; color: #666;">Shortfall: <strong style="color: #c62828;"><?php echo number_format($v['fine_amount'] - $driver_balance, 2); ?> ETB</strong></span>
                        </p>
                        <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="deposit.php" style="display: inline-block; padding: 8px 20px; background: #f9a825; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                <i class="fas fa-wallet"></i> Deposit Now
                            </a>
                            <a href="../dashboard.php" style="display: inline-block; padding: 8px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                <i class="fas fa-arrow-left"></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        
    <?php elseif (!$receipt_data): ?>
        <!-- NO UNPAID VIOLATIONS -->
        <div style="background: white; border-radius: 12px; padding: 60px 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <i class="fas fa-check-circle" style="font-size: 80px; color: #2e7d32; display: block; margin-bottom: 15px;"></i>
            <h2 style="color: #2e7d32; margin: 0;">🎉 No Unpaid Violations!</h2>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">
                <?php if ($total_violations == 0): ?>
                    You don't have any violations recorded yet. Keep driving safely! 🚗
                <?php else: ?>
                    All your violations have been paid. Great job! 🎉
                <?php endif; ?>
            </p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
                <a href="../dashboard.php" style="display: inline-block; padding: 12px 30px; background: #1a237e; color: white; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="pay_penalty.php" style="display: inline-block; padding: 12px 30px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-sync"></i> Refresh
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Paid Violations History -->
    <?php if (mysqli_num_rows($paid_violations) > 0): ?>
        <div style="background: white; border-radius: 12px; padding: 20px; margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3 style="color: #2e7d32;">
                <i class="fas fa-history"></i> Payment History
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #2e7d32; color: white;">
                        <tr>
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">Rule</th>
                            <th style="padding: 12px;">Fine</th>
                            <th style="padding: 12px;">Method</th>
                            <th style="padding: 12px;">Transaction ID</th>
                            <th style="padding: 12px;">Paid On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($v = mysqli_fetch_assoc($paid_violations)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;"><?php echo $v['rule_name']; ?></td>
                                <td style="padding: 12px; color: #2e7d32; font-weight: bold;">
                                    <?php echo number_format($v['fine_amount'], 2); ?> ETB
                                </td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; background: #e3f2fd; color: #0d47a1;">
                                        <?php 
                                            $methods = [
                                                'telebirr' => '📱 TeleBirr',
                                                'cbe_birr' => '🏦 CBE Birr',
                                                'bank_transfer' => '🏦 Bank Transfer',
                                                'cash' => '💵 Cash',
                                                'credit_card' => '💳 Credit Card'
                                            ];
                                            echo $methods[$v['payment_method']] ?? $v['payment_method'] ?? 'N/A';
                                        ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: #666;">
                                    <?php echo $v['transaction_id'] ?? 'N/A'; ?>
                                </td>
                                <td style="padding: 12px;"><?php echo date('M d, Y h:i A', strtotime($v['payment_date'] ?? $v['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- PAYMENT MODAL WITH ALL METHODS              -->
<!-- ============================================ -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 16px; padding: 30px; max-width: 550px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; animation: slideDown 0.3s ease;">
        
        <button onclick="closePaymentModal()" style="position: absolute; top: 15px; right: 20px; font-size: 28px; background: none; border: none; cursor: pointer; color: #999; transition: color 0.3s;">
            <i class="fas fa-times"></i>
        </button>
        
        <h2 style="color: #1a237e; margin-bottom: 5px;">
            <i class="fas fa-credit-card"></i> Pay Penalty
        </h2>
        <p style="color: #666; margin-bottom: 20px;">Complete your payment securely</p>
        
        <div style="background: #f5f5f5; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #1a237e;">
            <p style="margin: 0 0 5px 0;"><strong>Violation:</strong> <span id="modal_rule" style="color: #1a237e;"></span></p>
            <p style="margin: 5px 0 0 0;"><strong>Amount:</strong> <span id="modal_amount" style="color: #c62828; font-weight: bold; font-size: 20px;"></span></p>
            <p style="margin: 5px 0 0 0;"><strong>Your Balance:</strong> <span id="modal_balance" style="color: #1a237e; font-weight: bold;"></span></p>
        </div>
        
        <form method="POST" id="paymentForm" onsubmit="return validatePayment()">
            <input type="hidden" name="violation_id" id="modal_violation_id">
            <input type="hidden" name="driver_balance" id="modal_driver_balance">
            
            <!-- Payment Method Selection -->
            <div class="form-group">
                <label style="font-weight: 600; display: block; margin-bottom: 10px;">Select Payment Method *</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div class="payment-method-card" onclick="selectPaymentMethod('telebirr')" id="card_telebirr">
                        <div style="text-align: center;">
                            <div style="font-size: 32px;">📱</div>
                            <div style="font-weight: 600; font-size: 14px;">TeleBirr</div>
                        </div>
                    </div>
                    <div class="payment-method-card" onclick="selectPaymentMethod('cbe_birr')" id="card_cbe_birr">
                        <div style="text-align: center;">
                            <div style="font-size: 32px;">🏦</div>
                            <div style="font-weight: 600; font-size: 14px;">CBE Birr</div>
                        </div>
                    </div>
                    <div class="payment-method-card" onclick="selectPaymentMethod('bank_transfer')" id="card_bank_transfer">
                        <div style="text-align: center;">
                            <div style="font-size: 32px;">🏛️</div>
                            <div style="font-weight: 600; font-size: 14px;">Bank Transfer</div>
                        </div>
                    </div>
                    <div class="payment-method-card" onclick="selectPaymentMethod('cash')" id="card_cash">
                        <div style="text-align: center;">
                            <div style="font-size: 32px;">💵</div>
                            <div style="font-weight: 600; font-size: 14px;">Cash</div>
                        </div>
                    </div>
                    <div class="payment-method-card" onclick="selectPaymentMethod('credit_card')" id="card_credit_card">
                        <div style="text-align: center;">
                            <div style="font-size: 32px;">💳</div>
                            <div style="font-weight: 600; font-size: 14px;">Credit Card</div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="payment_method" id="selected_payment_method" value="">
            </div>
            
            <!-- TeleBirr Fields -->
            <div id="telebirr_fields" style="display: none;">
                <div class="form-group">
                    <label>TeleBirr Phone Number *</label>
                    <input type="text" name="phone_number" id="telebirr_phone" class="form-control" placeholder="e.g., 0912345678" 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <small style="color: #0d47a1;">Send payment to Admin's TeleBirr: <strong><?php echo $admin_phone; ?></strong></small>
                </div>
                <div style="background: #e3f2fd; padding: 12px; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #0d47a1;">
                        <i class="fas fa-info-circle"></i> You will receive a payment request on your TeleBirr account.
                    </p>
                </div>
            </div>
            
            <!-- CBE Birr Fields -->
            <div id="cbe_birr_fields" style="display: none;">
                <div class="form-group">
                    <label>CBE Birr Account Number *</label>
                    <input type="text" name="account_number" id="cbe_account" class="form-control" placeholder="e.g., 1000123456" 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <small style="color: #2e7d32;">Account must start with <strong>1000</strong> or <strong>10000</strong></small>
                </div>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #2e7d32;">
                        <i class="fas fa-info-circle"></i> Send payment to CBE Account: <strong><?php echo $bank_account; ?></strong>
                    </p>
                </div>
            </div>
            
            <!-- Bank Transfer Fields -->
            <div id="bank_transfer_fields" style="display: none;">
                <div class="form-group">
                    <label>Your Bank Account Number *</label>
                    <input type="text" name="account_number" id="bank_account_input" class="form-control" placeholder="Enter your account number" 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <small style="color: #e65100;">Minimum 8 digits</small>
                </div>
                <div class="form-group">
                    <label>Your Bank Name</label>
                    <select name="bank_name" id="bank_name_select" class="form-control" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <option value="cbe">Commercial Bank of Ethiopia</option>
                        <option value="awash">Awash Bank</option>
                        <option value="dashen">Dashen Bank</option>
                        <option value="wegagen">Wegagen Bank</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div style="background: #fff3e0; padding: 12px; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #e65100;">
                        <i class="fas fa-info-circle"></i> Bank Details: <?php echo $bank_name; ?> Account <strong><?php echo $bank_account; ?></strong><br>
                        Account Name: <strong><?php echo $bank_account_name; ?></strong>
                    </p>
                </div>
            </div>
            
            <!-- Cash Fields -->
            <div id="cash_fields" style="display: none;">
                <div style="background: #f5f5f5; padding: 15px; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #666;">
                        <i class="fas fa-info-circle"></i> Pay in person at our office:
                        <br><br>
                        <strong>📍 <?php echo $office_address; ?></strong>
                        <br><strong>🕐 Monday - Friday, 9AM - 5PM</strong>
                        <br><strong>📞 <?php echo $office_phone; ?></strong>
                    </p>
                </div>
            </div>
            
            <!-- Credit Card Fields -->
            <div id="credit_card_fields" style="display: none;">
                <div class="form-group">
                    <label>Card Number *</label>
                    <input type="text" name="card_number" id="card_number" class="form-control" placeholder="XXXX-XXXX-XXXX-XXXX" 
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Expiry Date *</label>
                        <input type="text" name="card_expiry" id="card_expiry" class="form-control" placeholder="MM/YY" 
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label>CVV *</label>
                        <input type="password" name="card_cvv" id="card_cvv" class="form-control" placeholder="***" maxlength="4" 
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                </div>
                <div style="background: #f3e5f5; padding: 12px; border-radius: 8px;">
                    <p style="margin: 0; font-size: 14px; color: #6a1b9a;">
                        <i class="fas fa-info-circle"></i> Credit card payment is processed securely.
                    </p>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="pay_penalty" id="confirmPaymentBtn" style="padding: 14px 30px; background: #2e7d32; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; font-size: 16px;">
                    <i class="fas fa-check"></i> Confirm Payment
                </button>
                <button type="button" onclick="closePaymentModal()" style="padding: 14px 30px; background: #c62828; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Payment method selection
function selectPaymentMethod(method) {
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    const card = document.getElementById('card_' + method);
    if (card) {
        card.classList.add('selected');
    }
    
    document.getElementById('selected_payment_method').value = method;
    
    document.getElementById('telebirr_fields').style.display = 'none';
    document.getElementById('cbe_birr_fields').style.display = 'none';
    document.getElementById('bank_transfer_fields').style.display = 'none';
    document.getElementById('cash_fields').style.display = 'none';
    document.getElementById('credit_card_fields').style.display = 'none';
    
    if (method === 'telebirr') {
        document.getElementById('telebirr_fields').style.display = 'block';
    } else if (method === 'cbe_birr') {
        document.getElementById('cbe_birr_fields').style.display = 'block';
    } else if (method === 'bank_transfer') {
        document.getElementById('bank_transfer_fields').style.display = 'block';
    } else if (method === 'cash') {
        document.getElementById('cash_fields').style.display = 'block';
    } else if (method === 'credit_card') {
        document.getElementById('credit_card_fields').style.display = 'block';
    }
}

// Open Payment Modal
function openPaymentModal(id, rule, amount, balance) {
    document.getElementById('modal_violation_id').value = id;
    document.getElementById('modal_rule').textContent = rule;
    document.getElementById('modal_amount').textContent = amount.toFixed(2) + ' ETB';
    document.getElementById('modal_balance').textContent = balance.toFixed(2) + ' ETB';
    document.getElementById('modal_driver_balance').value = balance;
    document.getElementById('paymentModal').style.display = 'flex';
    document.getElementById('paymentModal').style.justifyContent = 'center';
    document.getElementById('paymentModal').style.alignItems = 'center';
    document.getElementById('paymentForm').reset();
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.getElementById('selected_payment_method').value = '';
    document.getElementById('telebirr_fields').style.display = 'none';
    document.getElementById('cbe_birr_fields').style.display = 'none';
    document.getElementById('bank_transfer_fields').style.display = 'none';
    document.getElementById('cash_fields').style.display = 'none';
    document.getElementById('credit_card_fields').style.display = 'none';
}

// Close Payment Modal
function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.getElementById('paymentForm').reset();
    document.getElementById('telebirr_fields').style.display = 'none';
    document.getElementById('cbe_birr_fields').style.display = 'none';
    document.getElementById('bank_transfer_fields').style.display = 'none';
    document.getElementById('cash_fields').style.display = 'none';
    document.getElementById('credit_card_fields').style.display = 'none';
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
}

// ✅ Validate Payment
function validatePayment() {
    const method = document.getElementById('selected_payment_method').value;
    const amount = parseFloat(document.getElementById('modal_amount').textContent.replace(' ETB', ''));
    const balance = parseFloat(document.getElementById('modal_driver_balance').value);
    let error = '';
    
    if (!method) {
        error = 'Please select a payment method!';
    } else if (method === 'telebirr') {
        const phone = document.getElementById('telebirr_phone').value;
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        const adminPhone = '<?php echo preg_replace('/[^0-9]/', '', $admin_phone); ?>';
        if (!phone) {
            error = 'Please enter your TeleBirr phone number!';
        } else if (cleanPhone !== adminPhone) {
            error = 'Invalid TeleBirr number! Please use: <?php echo $admin_phone; ?> (Admin\'s number)';
        } else if (balance < amount) {
            error = 'Insufficient balance! You need ' + amount.toFixed(2) + ' ETB. Your balance: ' + balance.toFixed(2) + ' ETB';
        }
    } else if (method === 'cbe_birr') {
        const account = document.getElementById('cbe_account').value;
        if (!account) {
            error = 'Please enter your CBE Birr account number!';
        } else if (!/^1000[0-9]{6,}$/.test(account) && !/^10000[0-9]{5,}$/.test(account)) {
            error = 'Invalid CBE Birr account! Must start with 1000 or 10000';
        } else if (balance < amount) {
            error = 'Insufficient balance! You need ' + amount.toFixed(2) + ' ETB. Your balance: ' + balance.toFixed(2) + ' ETB';
        }
    } else if (method === 'bank_transfer') {
        const account = document.getElementById('bank_account_input').value;
        if (!account) {
            error = 'Please enter your bank account number!';
        } else if (account.length < 8) {
            error = 'Invalid account number! Must be at least 8 digits.';
        } else if (balance < amount) {
            error = 'Insufficient balance! You need ' + amount.toFixed(2) + ' ETB. Your balance: ' + balance.toFixed(2) + ' ETB';
        }
    } else if (method === 'cash') {
        if (balance < amount) {
            error = 'Insufficient balance! You need ' + amount.toFixed(2) + ' ETB. Your balance: ' + balance.toFixed(2) + ' ETB';
        }
    } else if (method === 'credit_card') {
        const cardNumber = document.getElementById('card_number').value;
        const cardExpiry = document.getElementById('card_expiry').value;
        const cardCvv = document.getElementById('card_cvv').value;
        if (!cardNumber || cardNumber.length < 16) {
            error = 'Please enter a valid card number!';
        } else if (!cardExpiry || !/^\d{2}\/\d{2}$/.test(cardExpiry)) {
            error = 'Please enter a valid expiry date (MM/YY)!';
        } else if (!cardCvv || cardCvv.length < 3) {
            error = 'Please enter a valid CVV!';
        } else if (balance < amount) {
            error = 'Insufficient balance! You need ' + amount.toFixed(2) + ' ETB. Your balance: ' + balance.toFixed(2) + ' ETB';
        }
    }
    
    if (error) {
        alert(error);
        return false;
    }
    return confirm('Confirm payment of ' + amount.toFixed(2) + ' ETB via ' + method.toUpperCase() + '?');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target === modal) {
        closePaymentModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>