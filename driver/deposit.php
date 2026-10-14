<?php
session_start();
include '../config/database.php';

// Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// ✅ GET USER INFO
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$driver_balance = $user['driver_balance'] ?? 0;

// ✅ PROCESS DEPOSIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deposit'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $amount = (float)$_POST['amount'];
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $deposit_note = mysqli_real_escape_string($conn, $_POST['deposit_note'] ?? '');
    
    // ✅ VALIDATION
    $validation_error = '';
    
    if (empty($full_name)) {
        $validation_error = "❌ Full Name is required!";
    } elseif (empty($phone_number)) {
        $validation_error = "❌ Phone Number is required!";
    } elseif (empty($email)) {
        $validation_error = "❌ Email is required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_error = "❌ Invalid email format!";
    } elseif ($amount <= 0) {
        $validation_error = "❌ Amount must be greater than 0!";
    } elseif ($amount < 10) {
        $validation_error = "❌ Minimum deposit is 10 ETB!";
    } elseif (empty($payment_method)) {
        $validation_error = "❌ Please select a payment method!";
    } elseif ($payment_method == 'telebirr' && empty($account_number)) {
        $validation_error = "❌ TeleBirr account number is required!";
    } elseif ($payment_method == 'cbe_birr' && empty($account_number)) {
        $validation_error = "❌ CBE Birr account number is required!";
    } elseif ($payment_method == 'bank_transfer' && empty($account_number)) {
        $validation_error = "❌ Bank account number is required!";
    }
    
    if (empty($validation_error)) {
        // Generate transaction ID
        $ref = 'DEP-' . strtoupper(uniqid()) . '-' . date('Ymd');
        
        // ✅ Update driver balance
        $new_balance = $driver_balance + $amount;
        $update = mysqli_query($conn, "UPDATE users SET driver_balance = $new_balance WHERE id = $user_id");
        
        if ($update) {
            // ✅ Record transaction with all details
            $description = "Deposit by: $full_name | Phone: $phone_number | Email: $email | Address: $address | Note: $deposit_note";
            $insert = "INSERT INTO driver_transactions (user_id, transaction_type, amount, payment_method, transaction_id, description, status) 
                       VALUES ($user_id, 'deposit', $amount, '$payment_method', '$ref', '$description', 'completed')";
            mysqli_query($conn, $insert);
            
            // ✅ Send notification
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES ($user_id, '💰 Deposit Successful', 
                                'Dear $full_name, you have successfully deposited " . number_format($amount, 2) . " ETB. 
                                Transaction ID: $ref. New Balance: " . number_format($new_balance, 2) . " ETB', 'deposit')");
            
            $success = "✅ Deposit successful!<br>";
            $success .= "🧾 Transaction ID: $ref<br>";
            $success .= "👤 Depositor: $full_name<br>";
            $success .= "📱 Phone: $phone_number<br>";
            $success .= "📧 Email: $email<br>";
            $success .= "💰 Amount: " . number_format($amount, 2) . " ETB<br>";
            $success .= "💳 Method: " . ucfirst(str_replace('_', ' ', $payment_method)) . "<br>";
            $success .= "📊 New Balance: " . number_format($new_balance, 2) . " ETB";
            
            // Refresh balance
            $driver_balance = $new_balance;
        } else {
            $error = "❌ Deposit failed. Please try again.";
        }
    } else {
        $error = $validation_error;
    }
}

// ✅ GET TRANSACTION HISTORY
$transactions = mysqli_query($conn, "
    SELECT * FROM driver_transactions 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 20
");

// ✅ GET STATISTICS
$total_deposits = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(amount) as total FROM driver_transactions WHERE user_id = $user_id AND transaction_type = 'deposit' AND status = 'completed'"
))['total'] ?? 0;

$total_payments = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(amount) as total FROM driver_transactions WHERE user_id = $user_id AND transaction_type = 'payment' AND status = 'completed'"
))['total'] ?? 0;

$page_title = 'Deposit - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<style>
.deposit-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.balance-box {
    background: linear-gradient(135deg, #1a237e, #0d47a1);
    color: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 30px;
}
.balance-box h2 {
    margin: 0;
    font-size: 14px;
    opacity: 0.8;
}
.balance-box .amount {
    font-size: 48px;
    font-weight: 700;
    margin: 10px 0;
}
.balance-box .amount span {
    font-size: 24px;
    opacity: 0.7;
}
.required-star {
    color: #c62828;
    font-weight: bold;
}
</style>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-wallet"></i> Deposit / Charge Account
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Balance Box -->
    <div class="balance-box">
        <h2><i class="fas fa-wallet"></i> Your Current Balance</h2>
        <div class="amount">
            <?php echo number_format($driver_balance, 2); ?> <span>ETB</span>
        </div>
        <p style="margin: 5px 0 0 0; opacity: 0.7;">
            <i class="fas fa-info-circle"></i> Minimum deposit: 10 ETB
        </p>
    </div>

    <!-- Deposit Form -->
    <div class="deposit-card">
        <h3><i class="fas fa-plus-circle"></i> Add Money to Your Account</h3>
        
        <?php if ($success): ?>
            <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; color: #2e7d32; margin-bottom: 20px; border-left: 4px solid #2e7d32;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828; margin-bottom: 20px; border-left: 4px solid #c62828;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateDepositForm()">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <!-- Full Name -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-user"></i> Full Name <span class="required-star">*</span>
                    </label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                           placeholder="Enter your full name"
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                </div>
                
                <!-- Phone Number -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-phone"></i> Phone Number <span class="required-star">*</span>
                    </label>
                    <input type="tel" name="phone_number" class="form-control" required
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                           placeholder="e.g., +251911234567"
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                    <small style="color: #666;">We will send confirmation SMS to this number</small>
                </div>
                
                <!-- Email -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-envelope"></i> Email Address <span class="required-star">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           placeholder="e.g., youremail@example.com"
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                    <small style="color: #666;">We will send receipt to this email</small>
                </div>
                
                <!-- Address -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-home"></i> Address
                    </label>
                    <input type="text" name="address" class="form-control"
                           placeholder="e.g., Bole Road, Addis Ababa"
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                </div>
                
                <!-- Amount -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-money-bill"></i> Amount to Deposit (ETB) <span class="required-star">*</span>
                    </label>
                    <input type="number" name="amount" class="form-control" required min="10" step="1"
                           placeholder="Enter amount (minimum 10 ETB)"
                           style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
                </div>
                
                <!-- Payment Method -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-credit-card"></i> Payment Method <span class="required-star">*</span>
                    </label>
                    <select name="payment_method" id="deposit_payment_method" class="form-control" required 
                            style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;"
                            onchange="showDepositFields()">
                        <option value="">-- Select Payment Method --</option>
                        <option value="telebirr">📱 TeleBirr</option>
                        <option value="cbe_birr">🏦 CBE Birr</option>
                        <option value="bank_transfer">🏦 Bank Transfer</option>
                        <option value="cash">💵 Cash (In-Person)</option>
                        <option value="credit_card">💳 Credit Card (Coming Soon)</option>
                    </select>
                </div>
                
                <!-- Account Number Fields (Dynamic) -->
                <div id="deposit_account_fields" style="display: none; grid-column: span 2;">
                    <div class="form-group">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <i class="fas fa-hashtag"></i> Account Number <span class="required-star">*</span>
                        </label>
                        <input type="text" name="account_number" id="account_number" class="form-control"
                               placeholder="Enter your account number"
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                    </div>
                    
                    <!-- Bank Name (for Bank Transfer) -->
                    <div id="bank_name_field" style="display: none;">
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                <i class="fas fa-university"></i> Bank Name
                            </label>
                            <select name="bank_name" class="form-control" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                                <option value="cbe">Commercial Bank of Ethiopia</option>
                                <option value="awash">Awash Bank</option>
                                <option value="dashen">Dashen Bank</option>
                                <option value="wegagen">Wegagen Bank</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div style="background: #fff3e0; padding: 12px; border-radius: 8px; margin-top: 10px;">
                            <p style="margin: 0; font-size: 14px; color: #e65100;">
                                <i class="fas fa-info-circle"></i> Bank Details: CBE Account 1000 1234 5678, Account Name: Smart Driver
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- TeleBirr Info -->
                <div id="telebirr_info" style="display: none; grid-column: span 2;">
                    <div style="background: #e3f2fd; padding: 12px; border-radius: 8px;">
                        <p style="margin: 0; font-size: 14px; color: #0d47a1;">
                            <i class="fas fa-info-circle"></i> You will receive a payment request on your TeleBirr account.
                            <br>Admin's TeleBirr: <strong>0911234567</strong>
                        </p>
                    </div>
                </div>
                
                <!-- CBE Birr Info -->
                <div id="cbe_birr_info" style="display: none; grid-column: span 2;">
                    <div style="background: #e8f5e9; padding: 12px; border-radius: 8px;">
                        <p style="margin: 0; font-size: 14px; color: #2e7d32;">
                            <i class="fas fa-info-circle"></i> You will receive a payment request on your CBE Birr account.
                            <br>Admin's CBE Birr: <strong>0911234567</strong>
                        </p>
                    </div>
                </div>
                
                <!-- Cash Info -->
                <div id="cash_info" style="display: none; grid-column: span 2;">
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px;">
                        <p style="margin: 0; font-size: 14px; color: #666;">
                            <i class="fas fa-info-circle"></i> Pay in person at our office:
                            <br><br>
                            <strong>📍 Bole Road, Addis Ababa</strong>
                            <br><strong>🕐 Monday - Friday, 9AM - 5PM</strong>
                            <br><strong>📞 +251 911 234 567</strong>
                        </p>
                    </div>
                </div>
                
                <!-- Deposit Note -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <i class="fas fa-pen"></i> Additional Note
                    </label>
                    <textarea name="deposit_note" class="form-control" rows="2"
                              placeholder="Any additional information about this deposit..."
                              style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; resize: vertical;"></textarea>
                </div>
                
                <!-- Terms -->
                <div class="form-group" style="grid-column: span 2;">
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; border-left: 4px solid #1a237e;">
                        <p style="margin: 0; font-size: 13px; color: #666;">
                            <i class="fas fa-check-circle" style="color: #1a237e;"></i>
                            By clicking "Deposit Now", you confirm that the information provided is correct.
                            Your account will be credited within 24 hours after payment confirmation.
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="deposit" id="depositBtn" style="padding: 14px 30px; background: #2e7d32; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; font-size: 16px;">
                    <i class="fas fa-check"></i> Deposit Now
                </button>
                <a href="../dashboard.php" style="padding: 14px 30px; background: #c62828; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center;">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Quick Deposit Amounts -->
    <div class="deposit-card">
        <h4><i class="fas fa-bolt"></i> Quick Deposit</h4>
        <p style="color: #666; font-size: 14px; margin-top: 5px;">Click to set amount instantly</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
            <button onclick="setAmount(50)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">50 ETB</button>
            <button onclick="setAmount(100)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">100 ETB</button>
            <button onclick="setAmount(200)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">200 ETB</button>
            <button onclick="setAmount(500)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">500 ETB</button>
            <button onclick="setAmount(1000)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">1000 ETB</button>
            <button onclick="setAmount(2000)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">2000 ETB</button>
            <button onclick="setAmount(5000)" style="padding: 10px 20px; background: #e3f2fd; border: 2px solid #0d47a1; border-radius: 8px; cursor: pointer; font-weight: 600;">5000 ETB</button>
        </div>
    </div>

    <!-- Transaction History -->
    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <h3>
            <i class="fas fa-history"></i> Transaction History
            <span style="font-size: 14px; font-weight: normal; color: #666;">
                (Last 20 transactions)
            </span>
        </h3>
        
        <!-- Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="color: #2e7d32; font-weight: bold; font-size: 18px;">
                    <?php echo number_format($total_deposits, 2); ?> ETB
                </div>
                <div style="color: #666; font-size: 12px;">💰 Total Deposits</div>
            </div>
            <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="color: #c62828; font-weight: bold; font-size: 18px;">
                    <?php echo number_format($total_payments, 2); ?> ETB
                </div>
                <div style="color: #666; font-size: 12px;">💳 Total Payments</div>
            </div>
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="color: #0d47a1; font-weight: bold; font-size: 18px;">
                    <?php echo number_format($driver_balance, 2); ?> ETB
                </div>
                <div style="color: #666; font-size: 12px;">📊 Current Balance</div>
            </div>
        </div>

        <!-- Transactions Table -->
        <?php if (mysqli_num_rows($transactions) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">Type</th>
                            <th style="padding: 12px;">Amount</th>
                            <th style="padding: 12px;">Method</th>
                            <th style="padding: 12px;">Transaction ID</th>
                            <th style="padding: 12px;">Description</th>
                            <th style="padding: 12px;">Status</th>
                            <th style="padding: 12px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($t = mysqli_fetch_assoc($transactions)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $t['transaction_type'] == 'deposit' ? '#e8f5e9' : ($t['transaction_type'] == 'payment' ? '#ffebee' : '#fff3e0'); ?>; color: <?php echo $t['transaction_type'] == 'deposit' ? '#2e7d32' : ($t['transaction_type'] == 'payment' ? '#c62828' : '#e65100'); ?>;">
                                        <?php echo ucfirst($t['transaction_type']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; font-weight: bold; color: <?php echo $t['transaction_type'] == 'deposit' ? '#2e7d32' : '#c62828'; ?>;">
                                    <?php echo $t['transaction_type'] == 'deposit' ? '+' : '-'; ?> <?php echo number_format($t['amount'], 2); ?> ETB
                                </td>
                                <td style="padding: 12px;">
                                    <?php 
                                        $methods = [
                                            'telebirr' => '📱 TeleBirr',
                                            'cbe_birr' => '🏦 CBE Birr',
                                            'bank_transfer' => '🏦 Bank Transfer',
                                            'cash' => '💵 Cash',
                                            'credit_card' => '💳 Credit Card'
                                        ];
                                        echo $methods[$t['payment_method']] ?? $t['payment_method'] ?? 'N/A';
                                    ?>
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: #666;">
                                    <?php echo $t['transaction_id'] ?? 'N/A'; ?>
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: #666; max-width: 200px;">
                                    <?php echo substr($t['description'] ?? '', 0, 50) . (strlen($t['description'] ?? '') > 50 ? '...' : ''); ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $t['status'] == 'completed' ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $t['status'] == 'completed' ? '#2e7d32' : '#c62828'; ?>;">
                                        <?php echo ucfirst($t['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: #666;">
                                    <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 30px; color: #666;">
                <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px; color: #ccc;"></i>
                No transactions yet. Start by making a deposit!
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
function setAmount(amount) {
    document.querySelector('input[name="amount"]').value = amount;
}

function showDepositFields() {
    const method = document.getElementById('deposit_payment_method').value;
    
    // Hide all dynamic fields
    document.getElementById('deposit_account_fields').style.display = 'none';
    document.getElementById('bank_name_field').style.display = 'none';
    document.getElementById('telebirr_info').style.display = 'none';
    document.getElementById('cbe_birr_info').style.display = 'none';
    document.getElementById('cash_info').style.display = 'none';
    
    if (method === 'telebirr') {
        document.getElementById('deposit_account_fields').style.display = 'block';
        document.getElementById('telebirr_info').style.display = 'block';
        document.getElementById('account_number').placeholder = 'Enter your TeleBirr number';
        document.getElementById('account_number').value = '';
    } else if (method === 'cbe_birr') {
        document.getElementById('deposit_account_fields').style.display = 'block';
        document.getElementById('cbe_birr_info').style.display = 'block';
        document.getElementById('account_number').placeholder = 'Enter your CBE Birr number';
        document.getElementById('account_number').value = '';
    } else if (method === 'bank_transfer') {
        document.getElementById('deposit_account_fields').style.display = 'block';
        document.getElementById('bank_name_field').style.display = 'block';
        document.getElementById('account_number').placeholder = 'Enter your bank account number';
        document.getElementById('account_number').value = '';
    } else if (method === 'cash') {
        document.getElementById('cash_info').style.display = 'block';
    }
}

// ✅ Form Validation
function validateDepositForm() {
    const fullName = document.querySelector('input[name="full_name"]').value.trim();
    const phone = document.querySelector('input[name="phone_number"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    const amount = document.querySelector('input[name="amount"]').value;
    const paymentMethod = document.getElementById('deposit_payment_method').value;
    const accountNumber = document.getElementById('account_number')?.value.trim() || '';
    
    if (!fullName) {
        alert('❌ Please enter your full name!');
        document.querySelector('input[name="full_name"]').focus();
        return false;
    }
    
    if (!phone) {
        alert('❌ Please enter your phone number!');
        document.querySelector('input[name="phone_number"]').focus();
        return false;
    }
    
    if (phone.length < 10) {
        alert('❌ Please enter a valid phone number!');
        document.querySelector('input[name="phone_number"]').focus();
        return false;
    }
    
    if (!email) {
        alert('❌ Please enter your email address!');
        document.querySelector('input[name="email"]').focus();
        return false;
    }
    
    if (!email.includes('@') || !email.includes('.')) {
        alert('❌ Please enter a valid email address!');
        document.querySelector('input[name="email"]').focus();
        return false;
    }
    
    if (!amount || amount < 10) {
        alert('❌ Minimum deposit is 10 ETB!');
        document.querySelector('input[name="amount"]').focus();
        return false;
    }
    
    if (!paymentMethod) {
        alert('❌ Please select a payment method!');
        document.getElementById('deposit_payment_method').focus();
        return false;
    }
    
    if ((paymentMethod === 'telebirr' || paymentMethod === 'cbe_birr' || paymentMethod === 'bank_transfer') && !accountNumber) {
        alert('❌ Please enter your account number!');
        document.getElementById('account_number').focus();
        return false;
    }
    
    return confirm('Confirm deposit of ' + amount + ' ETB via ' + paymentMethod.toUpperCase() + '?');
}
</script>

<?php include '../includes/footer.php'; ?>