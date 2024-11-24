<?php
// wallet.php
session_start();
require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/login.php');
    exit;
}

// Database connection
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

    switch ($action) {
        // wallet.php içindeki withdraw case'ini güncelle
        case 'withdraw':
            $amount = str_replace([',', '.'], '', $_POST['amount']); // Önce tüm nokta ve virgülleri kaldır
            $amount = floatval($amount) / 100; // Son iki basamağı kuruş olarak ayır

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                exit;
            }

            $db->beginTransaction();
            try {
                do {
                    $transactionId = generateTransactionId();
                } while (!validateTransactionId($db, $transactionId));

                $stmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$_SESSION['user_id']]);
                $currentBalance = $stmt->fetchColumn();

                if ($currentBalance < $amount) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
                    exit;
                }

                // Update wallet balance
                $stmt = $db->prepare("
            UPDATE wallet 
            SET balance = balance - ?,
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
            ) VALUES (?, ?, ?, ?, 'WITHDRAWAL', 'COMPLETED', ?)
        ");
                $stmt->execute([
                    $transactionId,
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    $amount,
                    'Withdrawal from wallet'
                ]);

                $db->commit();

                // Get updated balance
                $stmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $newBalance = $stmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'newBalance' => $newBalance,
                    'message' => 'Withdrawal successful'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Transaction failed']);
            }
            break;

        case 'transfer':
            $amount = floatval($_POST['amount']);
            $receiverUsername = $_POST['receiver'];

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                exit;
            }

            $db->beginTransaction();
            try {
                do {
                    $transactionId = generateTransactionId();
                } while (!validateTransactionId($db, $transactionId));

                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$receiverUsername]);
                $receiverId = $stmt->fetchColumn();

                if (!$receiverId) {
                    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
                    exit;
                }

                if ($receiverId == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot transfer to yourself']);
                    exit;
                }

                // Check if sender has sufficient balance
                $stmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$_SESSION['user_id']]);
                $senderBalance = $stmt->fetchColumn();

                if ($senderBalance < $amount) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
                    exit;
                }

                // Deduct from sender
                $stmt = $db->prepare("
                    UPDATE wallet 
                    SET balance = balance - ?,
                        updated_at = CURRENT_TIMESTAMP,
                        last_transaction_date = CURRENT_TIMESTAMP
                    WHERE user_id = ?
                ");
                $stmt->execute([$amount, $_SESSION['user_id']]);

                // Add to receiver
                $stmt = $db->prepare("
                    UPDATE wallet 
                    SET balance = balance + ?,
                        updated_at = CURRENT_TIMESTAMP,
                        last_transaction_date = CURRENT_TIMESTAMP
                    WHERE user_id = ?
                ");
                $stmt->execute([$amount, $receiverId]);

                // Record transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        transaction_id, sender_id, receiver_id, amount, 
                        transaction_type, status, description
                    ) VALUES (?, ?, ?, ?, 'TRANSFER', 'COMPLETED', ?)
                ");
                $stmt->execute([
                    $transactionId,
                    $_SESSION['user_id'],
                    $receiverId,
                    $amount,
                    "Transfer to {$receiverUsername}"
                ]);

                $db->commit();

                // Get updated balance
                $stmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $newBalance = $stmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'newBalance' => $newBalance,
                    'message' => "Successfully transferred to {$receiverUsername}"
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Transaction failed']);
            }
            break;
    }
    exit;
}

// Load wallet content
$userData = $_SESSION['user_data'];

$stmt = $db->prepare("SELECT * FROM wallet WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$walletData = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch recent transactions
$stmt = $db->prepare("
    SELECT t.*, 
           u_sender.username as sender_username,
           u_receiver.username as receiver_username,
           t.created_at as transaction_date
    FROM transactions t
    LEFT JOIN users u_sender ON t.sender_id = u_sender.user_id
    LEFT JOIN users u_receiver ON t.receiver_id = u_receiver.user_id
    WHERE t.sender_id = ? OR t.receiver_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('LUREID - Wallet') ?></title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <a href="../../index.php" class="flex items-center text-gray-600 hover:text-gray-900">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <?= __('Back to Dashboard') ?>
                </a>
                <h1 class="text-xl font-semibold"><?= __('Wallet') ?></h1>
                <div></div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="space-y-6">
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium mb-4"><?= __('Wallet Balance') ?></h3>
                    <div class="text-3xl font-bold mb-4">
                        ₺<span id="currentBalance"><?php echo number_format($walletData['balance'], 2); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <a href="deposit.php"
                        class="bg-green-500 text-white px-4 py-3 rounded-lg hover:bg-green-600 transition-colors text-center">
                        <?= __('Deposit') ?>
                    </a>
                    <button id="showWithdraw"
                        class="bg-red-500 text-white px-4 py-3 rounded-lg hover:bg-red-600 transition-colors">
                        <?= __('Withdraw') ?>
                    </button>
                    <button id="showTransfer"
                        class="bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 transition-colors">
                        <?= __('Transfer') ?>
                    </button>
                </div>

                <div id="transactionForms" class="space-y-4 hidden">
                    <form id="withdrawForm" class="bg-white p-6 rounded-lg shadow hidden">
                        <h4 class="text-lg font-medium mb-4"><?= __('Withdraw Funds') ?></h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium"><?= __('Amount') ?></label>
                                <input type="number" step="0.01" min="0.01" name="amount" required
                                    class="mt-1 block w-full rounded border p-2">
                            </div>
                            <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                                <?= __('Confirm Withdrawal') ?>
                            </button>
                        </div>
                    </form>

                    <form id="transferForm" class="bg-white p-6 rounded-lg shadow hidden">
                        <h4 class="text-lg font-medium mb-4"><?= __('Transfer Funds') ?></h4>
                        <div class="space-y-4">
                            <div class="recipient-search">
                                <label class="block text-sm font-medium"><?= __('Recipient Username') ?></label>
                                <input type="text" name="receiver" class="mt-1 block w-full rounded border p-2"
                                    placeholder="<?= __('Search username...') ?>">

                                <div id="searchResults"
                                    class="hidden mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto">
                                </div>
                            </div>

                            <div id="recipientInfo" class="hidden bg-gray-50 p-4 rounded">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <img id="recipientAvatar" src="" class="w-12 h-12 rounded-full">
                                        <div>
                                            <p id="recipientFullName" class="font-medium"></p>
                                            <p id="recipientUsername" class="text-sm text-gray-600"></p>
                                            <p id="recipientId" class="text-xs text-gray-500"></p>
                                        </div>
                                    </div>
                                    <button type="button" id="changeRecipient"
                                        class="text-sm text-red-600 hover:text-red-800">
                                        <?= __('Change Recipient') ?>
                                    </button>
                                </div>
                            </div>

                            <div id="amountSection" class="hidden">
                                <label class="block text-sm font-medium"><?= __('Amount') ?></label>
                                <input type="number" step="0.01" min="0.01" name="amount"
                                    class="mt-1 block w-full rounded border p-2">
                            </div>

                            <button type="submit" id="transferButton"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 hidden">
                                <?= __('Confirm Transfer') ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium"><?= __('Recent Transactions') ?></h3>
                        <a href="recent-transactions.php"
                            class="text-blue-500 hover:text-blue-600"><?= __('View All') ?></a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="flex justify-between items-center p-4 bg-gray-50 rounded">
                                <div>
                                    <p class="font-medium">
                                        <?php
                                        switch ($transaction['transaction_type']) {
                                            case 'DEPOSIT':
                                                echo __('Deposit to Wallet');
                                                break;
                                            case 'WITHDRAWAL':
                                                echo __('Withdrawal from Wallet');
                                                break;
                                            case 'TRANSFER':
                                                if ($transaction['sender_id'] == $_SESSION['user_id']) {
                                                    echo __("Transfer to") . " {$transaction['receiver_username']}";
                                                } else {
                                                    echo __("Transfer from") . " {$transaction['sender_username']}";
                                                }
                                                break;
                                            case 'PAYMENT':
                                                echo $transaction['description'];
                                                break;
                                            default:
                                                echo $transaction['description'];
                                                break;
                                        }
                                        ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('F j, Y g:i a', strtotime($transaction['transaction_date'])); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium <?php
                                    if (
                                        $transaction['sender_id'] == $_SESSION['user_id'] &&
                                        $transaction['transaction_type'] != 'DEPOSIT'
                                    ) {
                                        echo 'text-red-600';
                                    } else {
                                        echo 'text-green-600';
                                    }
                                    ?>">
                                        <?php
                                        if (
                                            $transaction['sender_id'] == $_SESSION['user_id'] &&
                                            $transaction['transaction_type'] != 'DEPOSIT'
                                        ) {
                                            echo '-';
                                        } else {
                                            echo '+';
                                        }
                                        ?>
                                        ₺<?php echo number_format($transaction['amount'], 2); ?>
                                    </p>
                                    <p class="text-sm text-gray-600"><?php echo $transaction['status']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        $(document).ready(function () {
            $('#showWithdraw').click(function () {
                $('#transactionForms').removeClass('hidden');
                $('#withdrawForm').removeClass('hidden');
                $('#transferForm').addClass('hidden');
            });

            $('#showTransfer').click(function () {
                $('#transactionForms').removeClass('hidden');
                $('#transferForm').removeClass('hidden');
                $('#withdrawForm').addClass('hidden');
            });

            function parseCurrency(value) {
                // Virgül ve binlik ayırıcıları kaldır
                return parseFloat(value.replace(/[,.]/g, ''));
            }

            // Handle deposit form submission
            $('#depositForm').submit(function (e) {
                e.preventDefault();
                const amount = $(this).find('[name="amount"]').val();

                $.post('components/wallet/wallet.php', {
                    action: 'deposit',
                    amount: amount
                }).done(function (response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        // Update balance display
                        $('#currentBalance').text(parseFloat(response.newBalance).toFixed(2));

                        // Reset form and hide it
                        $('#depositForm')[0].reset();
                        $('#transactionForms').addClass('hidden');

                        // Show success message
                        alert(response.message);

                        // Refresh recent transactions
                        refreshTransactions();
                    } else {
                        alert(response.message);
                    }
                });
            });

            // Handle withdraw form submission
            $('#withdrawForm').submit(function (e) {
                e.preventDefault();
                // Input değerini direkt al, parseFloat kullanma
                const rawAmount = $(this).find('[name="amount"]').val();
                const amount = parseCurrency(rawAmount);

                $.post('wallet.php', {
                    action: 'withdraw',
                    amount: amount
                }).done(function (response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        // Balance'ı güncelle
                        $('#currentBalance').text(
                            new Intl.NumberFormat('tr-TR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(response.newBalance)
                        );

                        // Formu resetle ve gizle
                        $('#withdrawForm')[0].reset();
                        $('#transactionForms').addClass('hidden');
                        alert(response.message);
                        refreshTransactions();
                    } else {
                        alert(response.message);
                    }
                });
            });

            // Handle transfer form submission
            $('#transferForm').submit(function (e) {
                e.preventDefault();
                const amount = $(this).find('[name="amount"]').val();
                const receiver = $(this).find('[name="receiver"]').val();

                $.post('wallet.php', {
                    action: 'transfer',
                    amount: amount,
                    receiver: receiver
                }).done(function (response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        // Update balance display
                        $('#currentBalance').text(parseFloat(response.newBalance).toFixed(2));

                        // Reset form and hide it
                        $('#transferForm')[0].reset();
                        $('#transactionForms').addClass('hidden');

                        // Show success message
                        alert(response.message);

                        // Refresh recent transactions
                        refreshTransactions();
                    } else {
                        alert(response.message);
                    }
                });
            });

            // Function to refresh recent transactions
            function refreshTransactions() {
                $.get('recent-transactions.php', function (response) {
                    $('.recent-transactions').html(response);
                });
            }

            // Add real-time validation for amount inputs
            $('input[name="amount"]').on('input', function () {
                const rawAmount = $(this).val();
                const amount = parseCurrency(rawAmount);
                const currentBalance = parseCurrency($('#currentBalance').text());
                const form = $(this).closest('form');
                const submitBtn = form.find('button[type="submit"]');

                if (form.attr('id') !== 'depositForm') {
                    if (amount > currentBalance) {
                        $(this).addClass('border-red-500');
                        submitBtn.prop('disabled', true).addClass('opacity-50');
                    } else {
                        $(this).removeClass('border-red-500');
                        submitBtn.prop('disabled', false).removeClass('opacity-50');
                    }
                }
            });

            // Auto-format amounts to 2 decimal places
            $('input[name="amount"]').on('blur', function () {
                const amount = parseFloat($(this).val());
                if (!isNaN(amount)) {
                    $(this).val(amount.toFixed(2));
                }
            });

            let usernameTimeout;
            $('input[name="receiver"]').on('input', function () {
                const input = $(this);
                const username = input.val();

                clearTimeout(usernameTimeout);

                if (username.length < 2) {
                    $('#searchResults').addClass('hidden').html('');
                    return;
                }

                usernameTimeout = setTimeout(function () {
                    $.post('validate-username.php', {
                        username: username
                    }).done(function (response) {
                        response = JSON.parse(response);
                        if (response.users && response.users.length > 0) {
                            const resultsHtml = response.users.map(user => `
                    <div class="user-result p-3 hover:bg-gray-50 cursor-pointer" 
                         data-username="${user.username}"
                         data-fullname="${user.full_name}"
                         data-userid="${user.user_id}"
                         data-avatar="${user.profile_photo_url}">
                        <div class="flex items-center gap-3">
                            <img src="${user.profile_photo_url}" alt="Profile" class="w-8 h-8 rounded-full">
                            <div>
                                <p class="font-medium">${user.full_name}</p>
                                <p class="text-sm text-gray-600">@${user.username}</p>
                            </div>
                        </div>
                    </div>
                `).join('');
                            $('#searchResults').html(resultsHtml).removeClass('hidden');
                        } else {
                            $('#searchResults').html('<div class="p-3 text-gray-500">No users found</div>').removeClass('hidden');
                        }
                    });
                }, 300);
            });

            // Handle user selection
            $(document).on('click', '.user-result', function () {
                const user = $(this).data();

                $('#recipientAvatar').attr('src', user.avatar);
                $('#recipientFullName').text(user.fullname);
                $('#recipientUsername').text('@' + user.username);
                $('#recipientId').text('ID: ' + user.userid);

                $('input[name="receiver"]').val(user.username);
                $('#searchResults').addClass('hidden');
                $('#recipientInfo, #amountSection, #transferButton').removeClass('hidden');
            });

            // Change recipient button
            $('#changeRecipient').click(function () {
                $('input[name="receiver"]').val('').focus();
                $('#recipientInfo, #amountSection, #transferButton').addClass('hidden');
                $('input[name="amount"]').val('');
            });

            // Click outside search results to close
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#searchResults, input[name="receiver"]').length) {
                    $('#searchResults').addClass('hidden');
                }
            });
        });
    </script>