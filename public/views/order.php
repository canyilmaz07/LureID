<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Get gig ID from URL
$gigId = isset($_GET['gig']) ? intval($_GET['gig']) : null;

if (!$gigId) {
    header('Location: /');
    exit;
}

try {
    $dbConfig = require '../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database connection failed');
}

// Get gig and user details
$stmt = $db->prepare("
    SELECT 
        g.*,
        f.freelancer_id,
        f.user_id as freelancer_user_id,
        w.balance as user_balance
    FROM gigs g
    JOIN freelancers f ON g.freelancer_id = f.freelancer_id
    JOIN wallet w ON w.user_id = :user_id
    WHERE g.gig_id = :gig_id AND g.status = 'APPROVED'
");

$stmt->execute([
    ':gig_id' => $gigId,
    ':user_id' => $_SESSION['user_id']
]);

$orderData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orderData || $orderData['freelancer_user_id'] == $_SESSION['user_id']) {
    header('Location: /');
    exit;
}

function generateTransactionId()
{
    return mt_rand(10000000000, 99999999999);
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentType = $_POST['payment_type'] ?? '';
    $amount = $orderData['price'];

    try {
        $db->beginTransaction();

        // Validate balance if using wallet
        if ($paymentType === 'wallet' && $orderData['user_balance'] < $amount) {
            throw new Exception('Insufficient balance');
        }

        // Generate unique transaction ID
        do {
            $transactionId = generateTransactionId();
        } while (!validateTransactionId($db, $transactionId));

        // Process wallet payment
        if ($paymentType === 'wallet') {
            // Deduct from user's wallet
            $stmt = $db->prepare("
                UPDATE wallet 
                SET balance = balance - :amount,
                    last_transaction_date = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':amount' => $amount,
                ':user_id' => $_SESSION['user_id']
            ]);
        } else {
            // Validate card details
            if (
                empty($_POST['cardNumber']) ||
                empty($_POST['cardName']) ||
                empty($_POST['expiryDate']) ||
                empty($_POST['cvv'])
            ) {
                throw new Exception('All card fields are required');
            }
        }

        // Create transaction records
        // 1. Client's payment (COMPLETED)
        $stmt = $db->prepare("
            INSERT INTO transactions (
                transaction_id, 
                sender_id, 
                receiver_id, 
                amount, 
                transaction_type, 
                status, 
                description
            ) VALUES (
                :transaction_id,
                :sender_id,
                :receiver_id,
                :amount,
                'PAYMENT',
                'COMPLETED',
                :description
            )
        ");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':sender_id' => $_SESSION['user_id'],
            ':receiver_id' => $orderData['freelancer_user_id'],
            ':amount' => $amount,
            ':description' => 'Payment for gig: ' . $orderData['title']
        ]);

        // Calculate delivery deadline
        $deliveryDeadline = date('Y-m-d H:i:s', strtotime("+{$orderData['delivery_time']} days"));

        // Create job record
        $stmt = $db->prepare("
            INSERT INTO jobs (
                gig_id,
                client_id,
                freelancer_id,
                title,
                description,
                requirements,
                category,
                subcategory,
                budget,
                status,
                delivery_deadline,
                max_revisions,
                milestones_data,
                transaction_id
            ) VALUES (
                :gig_id,
                :client_id,
                :freelancer_id,
                :title,
                :description,
                :requirements,
                :category,
                :subcategory,
                :budget,
                'PENDING',
                :delivery_deadline,
                :max_revisions,
                :milestones_data,
                :transaction_id
            )
        ");

        $stmt->execute([
            ':gig_id' => $gigId,
            ':client_id' => $_SESSION['user_id'],
            ':freelancer_id' => $orderData['freelancer_id'],
            ':title' => $orderData['title'],
            ':description' => $orderData['description'],
            ':requirements' => $orderData['requirements'],
            ':category' => $orderData['category'],
            ':subcategory' => $orderData['subcategory'],
            ':budget' => $amount,
            ':delivery_deadline' => $deliveryDeadline,
            ':max_revisions' => $orderData['revision_count'],
            ':milestones_data' => $orderData['milestones_data'],
            ':transaction_id' => $transactionId
        ]);

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Payment successful'
        ]);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

function validateTransactionId($db, $transactionId)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_id = ?");
    $stmt->execute([$transactionId]);
    return $stmt->fetchColumn() == 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Order - LureID</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f8f8f8;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .order-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .payment-methods {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .payment-tab {
            padding: 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-tab.active {
            background: #f8f8f8;
        }

        .payment-tab-content {
            padding: 20px;
            display: none;
        }

        .payment-tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .complete-order-btn {
            width: 100%;
            padding: 15px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .complete-order-btn:hover {
            background: #4338CA;
        }

        .complete-order-btn:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .success-icon {
            width: 60px;
            height: 60px;
            background: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon svg {
            width: 30px;
            height: 30px;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="text-2xl font-bold mb-8">Complete Your Order</h1>

        <div class="order-grid">
            <!-- Payment Methods Section -->
            <div class="payment-methods">
                <!-- Wallet Payment Tab -->
                <div class="payment-tab active" data-tab="wallet">
                    <h3 class="text-lg font-semibold">Pay with Wallet Balance</h3>
                    <p class="text-sm text-gray-600 mt-1">Available balance:
                        ₺<?php echo number_format($orderData['user_balance'], 2); ?></p>
                </div>
                <div class="payment-tab-content active" id="wallet-content">
                    <?php if ($orderData['user_balance'] >= $orderData['price']): ?>
                        <p class="text-green-600">You have sufficient balance for this purchase.</p>
                    <?php else: ?>
                        <p class="text-red-600">Insufficient balance. Please add funds or use a credit card.</p>
                    <?php endif; ?>
                </div>

                <!-- Credit Card Tab -->
                <div class="payment-tab" data-tab="card">
                    <h3 class="text-lg font-semibold">Pay with Credit Card</h3>
                </div>
                <div class="payment-tab-content" id="card-content">
                    <form id="cardForm">
                        <div class="form-group">
                            <label>Card Number</label>
                            <input type="text" name="cardNumber" id="cardNumber" placeholder="1234 5678 9012 3456">
                        </div>

                        <div class="form-group">
                            <label>Cardholder Name</label>
                            <input type="text" name="cardName" placeholder="John Doe">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="text" name="expiryDate" id="expiryDate" placeholder="MM/YY">
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="text" name="cvv" id="cvv" maxlength="4" placeholder="123">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary Section -->
            <div class="order-summary">
                <h3 class="text-xl font-semibold mb-6">Order Summary</h3>

                <div class="summary-item">
                    <span>Gig Price</span>
                    <span>₺<?php echo number_format($orderData['price'], 2); ?></span>
                </div>

                <div class="summary-item">
                    <span>Service Fee</span>
                    <span>₺0.00</span>
                </div>

                <div class="summary-item">
                    <span class="font-semibold">Total</span>
                    <span class="font-semibold">₺<?php echo number_format($orderData['price'], 2); ?></span>
                </div>

                <button id="completeOrderBtn" class="complete-order-btn mt-6">
                    Complete Order
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold mb-2">Payment Successful!</h3>
            <p class="text-gray-600 mb-4">Your order has been placed successfully.</p>
            <button onclick="window.location.href='/public/index.php'" class="complete-order-btn">
                Continue to Dashboard
            </button>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Initialize Cleave.js
            new Cleave('#cardNumber', {
                creditCard: true
            });

            new Cleave('#expiryDate', {
                date: true,
                datePattern: ['m', 'y']
            });

            new Cleave('#cvv', {
                numeral: true,
                numeralPositiveOnly: true
            });

            // Payment tab switching
            $('.payment-tab').click(function () {
                $('.payment-tab').removeClass('active');
                $('.payment-tab-content').removeClass('active');

                $(this).addClass('active');
                $('#' + $(this).data('tab') + '-content').addClass('active');
            });

            // Complete order button click handler
            $('#completeOrderBtn').click(function () {
                const activeTab = $('.payment-tab.active').data('tab');
                let formData = new FormData();

                formData.append('payment_type', activeTab);

                if (activeTab === 'card') {
                    // Validate card details
                    const cardForm = $('#cardForm')[0];
                    const cardNumber = cardForm.cardNumber.value.replace(/\s/g, '');
                    const cardName = cardForm.cardName.value;
                    const expiryDate = cardForm.expiryDate.value;
                    const cvv = cardForm.cvv.value;

                    if (!cardNumber || !cardName || !expiryDate || !cvv) {
                        alert('Please fill in all card details');
                        return;
                    }

                    // Basic card validation
                    if (cardNumber.length < 16) {
                        alert('Invalid card number');
                        return;
                    }

                    if (cvv.length < 3) {
                        alert('Invalid CVV');
                        return;
                    }

                    // Add card details to form data
                    formData.append('cardNumber', cardNumber);
                    formData.append('cardName', cardName);
                    formData.append('expiryDate', expiryDate);
                    formData.append('cvv', cvv);
                } else {
                    // Validate wallet balance
                    const balance = <?php echo $orderData['user_balance']; ?>;
                    const price = <?php echo $orderData['price']; ?>;

                    if (balance < price) {
                        alert('Insufficient wallet balance');
                        return;
                    }
                }

                // Disable button and show loading state
                const $button = $(this);
                $button.prop('disabled', true).html('Processing...');

                // Send payment request
                $.ajax({
                    url: 'order.php?gig=<?php echo $gigId; ?>',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Show success modal
                                $('#successModal').css('display', 'flex').hide().fadeIn(300);

                                // Set cookie to show dashboard modal
                                document.cookie = "show_order_success=1; path=/";
                            } else {
                                alert(result.message || 'Payment failed. Please try again.');
                                $button.prop('disabled', false).html('Complete Order');
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('An error occurred. Please try again.');
                            $button.prop('disabled', false).html('Complete Order');
                        }
                    },
                    error: function () {
                        alert('Connection error. Please try again.');
                        $button.prop('disabled', false).html('Complete Order');
                    }
                });
            });

            // Close modal when clicking outside
            $(window).click(function (event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').fadeOut(300);
                }
            });
        });
    </script>
</body>

</html>