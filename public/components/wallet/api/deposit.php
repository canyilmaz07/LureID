<?php
// deposit.php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

function generateTransactionId()
{
    return mt_rand(10000000000, 99999999999);
}

function validateTransactionId($db, $transactionId)
{
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
    if (
        empty($_POST['cardNumber']) || empty($_POST['cardName']) ||
        empty($_POST['expiryDate']) || empty($_POST['cvv']) || empty($_POST['amount'])
    ) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    $amount = floatval($_POST['amount']);
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    try {
        $dbConfig = require '../../../../config/database.php'; // Path düzeltildi
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        $db->beginTransaction();

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
        if (isset($db)) {
            $db->rollBack();
        }
        error_log($e->getMessage()); // Hata loglanır
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f8f8;
        }

        .payment-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .payment-card {
            background: white;
            border: 1px solid #dedede;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
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
            border: 1px solid #dedede;
            border-radius: 8px;
            font-size: 14px;
        }

        .submit-button {
            width: 100%;
            padding: 15px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(79, 70, 229, 0.2);
        }

        .quick-amount-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-amount-button {
            padding: 10px;
            background: #f3f4f6;
            border: 1px solid #dedede;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .quick-amount-button:hover {
            background: #e5e7eb;
        }

        .quick-amount-button.active {
            background: #4F46E5;
            color: white;
            border-color: #4F46E5;
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <h2 style="font-size: 22px; margin-bottom: 30px; font-weight: 600;"><?= __('Deposit Funds') ?></h2>

        <form id="depositForm">
            <div class="payment-card">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;"><?= __('Amount') ?></h3>

                <div class="quick-amount-grid">
                    <button type="button" class="quick-amount-button" data-amount="100">₺100</button>
                    <button type="button" class="quick-amount-button" data-amount="200">₺200</button>
                    <button type="button" class="quick-amount-button" data-amount="500">₺500</button>
                    <button type="button" class="quick-amount-button" data-amount="1000">₺1,000</button>
                    <button type="button" class="quick-amount-button" data-amount="2000">₺2,000</button>
                    <button type="button" class="quick-amount-button" data-amount="5000">₺5,000</button>
                </div>

                <div class="form-group">
                    <label><?= __('Custom Amount') ?></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>

                <h3 style="font-size: 16px; font-weight: 600; margin: 30px 0 20px;"><?= __('Card Information') ?></h3>

                <div class="form-group">
                    <label><?= __('Card Number') ?></label>
                    <input type="text" name="cardNumber" id="cardNumber" required placeholder="1234 5678 9012 3456">
                </div>

                <div class="form-group">
                    <label><?= __('Cardholder Name') ?></label>
                    <input type="text" name="cardName" required placeholder="John Doe">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label><?= __('Expiry Date') ?></label>
                        <input type="text" name="expiryDate" id="expiryDate" required placeholder="MM/YY">
                    </div>

                    <div class="form-group">
                        <label><?= __('CVV') ?></label>
                        <input type="text" name="cvv" id="cvv" required maxlength="4" placeholder="123">
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-button"><?= __('Deposit Now') ?></button>
        </form>
    </div>

    <script>
        $(document).ready(function () {
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

            // Quick amount button handling
            $('.quick-amount-button').click(function () {
                const amount = $(this).data('amount');
                $('input[name="amount"]').val(amount);
                $('.quick-amount-button').removeClass('active');
                $(this).addClass('active');
            });

            // Custom amount input handling
            $('input[name="amount"]').on('input', function () {
                $('.quick-amount-button').removeClass('active');
            });

            $('#depositForm').submit(function (e) {
                e.preventDefault();

                $.post('deposit.php', $(this).serialize())
                    .done(function (response) {
                        try {
                            let result = JSON.parse(response);
                            if (result.success) {
                                alert(result.message);
                                window.location.href = '../wallet.php?section=wallet';
                            } else {
                                alert(result.message || 'İşlem sırasında bir hata oluştu');
                            }
                        } catch (e) {
                            console.error('JSON parsing error:', e);
                            alert('İşlem sırasında bir hata oluştu');
                        }
                    })
                    .fail(function (xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
                    });
            });
        });
    </script>
</body>

</html>