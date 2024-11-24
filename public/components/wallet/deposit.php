<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

function generateTransactionId() {
    return mt_rand(10000000000, 99999999999);
}

function validateTransactionId($db, $transactionId) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetchColumn() == 0;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    if (empty($_POST['cardNumber']) || empty($_POST['cardName']) || 
        empty($_POST['expiryDate']) || empty($_POST['cvv']) || empty($_POST['amount'])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    $amount = floatval($_POST['amount']);
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    $dbConfig = require '../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $db->beginTransaction();
    try {
        do {
            $transactionId = generateTransactionId();
        } while (!validateTransactionId($db, $transactionId));

        // Update wallet balance
        $stmt = $db->prepare("
            UPDATE wallet 
            SET balance = balance + ?, 
                updated_at = CURRENT_TIMESTAMP,
                last_transaction_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $_SESSION['user_id']]);

        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO transactions (
                transaction_id, sender_id, receiver_id, amount, 
                transaction_type, status, description
            ) VALUES (?, ?, ?, ?, 'DEPOSIT', 'COMPLETED', ?)
        ");
        $stmt->execute([
            $transactionId,
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $amount,
            'Credit card deposit to wallet'
        ]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Deposit successful']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Transaction failed']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Deposit Funds') ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-lg">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-gray-900">
                    <?= __('Deposit Funds') ?>
                </h2>
            </div>
            <form id="depositForm" class="mt-8 space-y-6">
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700"><?= __('Amount') ?></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <span class="text-gray-500 sm:text-sm">₺</span>
                            </div>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700"><?= __('Card Number') ?></label>
                        <input type="text" name="cardNumber" id="cardNumber" required
                            class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                            placeholder="1234 5678 9012 3456">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700"><?= __('Cardholder Name') ?></label>
                        <input type="text" name="cardName" required
                            class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                            placeholder="John Doe">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700"><?= __('Expiry Date') ?></label>
                            <input type="text" name="expiryDate" id="expiryDate" required
                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                placeholder="MM/YY">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700"><?= __('CVV') ?></label>
                            <input type="text" name="cvv" id="cvv" required maxlength="4"
                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                placeholder="123">
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-500"><?= __('Your payment information is secured with SSL encryption') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </span>
                        <?= __('Deposit Now') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Cleave.js for card number formatting
            new Cleave('#cardNumber', {
                creditCard: true,
            });

            // Initialize Cleave.js for expiry date formatting
            new Cleave('#expiryDate', {
                date: true,
                datePattern: ['m', 'y']
            });

            // Initialize Cleave.js for CVV formatting
            new Cleave('#cvv', {
                numeral: true,
                numeralPositiveOnly: true
            });

            $('#depositForm').submit(function(e) {
                e.preventDefault();
                
                $.post('deposit.php', $(this).serialize())
                    .done(function(response) {
                        try {
                            response = JSON.parse(response);
                            if (response.success) {
                                alert(response.message);
                                setTimeout(function() {
                                    window.location.href = 'wallet.php';
                                }, 3000);
                            } else {
                                alert(response.message);
                            }
                        } catch (e) {
                            alert('An error occurred. Please try again.');
                        }
                    })
                    .fail(function() {
                        alert('An error occurred. Please try again.');
                    });
            });
        });
    </script>
</body>
</html>