<?php
// components/wallet/recent-transactions.php
session_start();
require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

$currentTab = $_GET['tab'] ?? 'transactions';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Transaction History') ?> - LUREID</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <a href="wallet.php" class="flex items-center text-gray-600 hover:text-gray-900">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <?= __('Back to Wallet') ?>
                </a>
                <h1 class="text-xl font-semibold"><?= __('Wallet History') ?></h1>
                <div></div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex gap-6">
            <!-- Sidebar -->
            <div class="w-64 flex-shrink-0">
                <div class="bg-white rounded-lg shadow">
                    <nav class="space-y-1">
                        <a href="?tab=transactions"
                            class="block px-4 py-3 hover:bg-gray-50 transition-colors <?= $currentTab === 'transactions' ? 'bg-gray-50' : ''; ?>">
                            <?= __('Transactions') ?>
                        </a>
                        <a href="?tab=subscriptions"
                            class="block px-4 py-3 hover:bg-gray-50 transition-colors <?= $currentTab === 'subscriptions' ? 'bg-gray-50' : ''; ?>">
                            <?= __('Subscriptions') ?>
                        </a>
                        <a href="?tab=invoices"
                            class="block px-4 py-3 hover:bg-gray-50 transition-colors <?= $currentTab === 'invoices' ? 'bg-gray-50' : ''; ?>">
                            <?= __('Invoices') ?>
                        </a>
                        <a href="?tab=cards"
                            class="block px-4 py-3 hover:bg-gray-50 transition-colors <?= $currentTab === 'cards' ? 'bg-gray-50' : ''; ?>">
                            <?= __('Payment Methods') ?>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1">
                <div class="bg-white rounded-lg shadow p-6">
                    <?php if ($currentTab === 'transactions'):
                        // Fetch all transactions
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
");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>


                        <h2 class="text-xl font-semibold mb-6"><?= __('Transaction History') ?></h2>
                        <div class="space-y-4">
                            <?php foreach ($transactions as $transaction): ?>
                                <div
                                    class="flex justify-between items-center p-4 bg-gray-50 rounded hover:bg-gray-100 transition-colors">
                                    <div>
                                        <p class="font-medium">
                                            <?php
                                            switch ($transaction['transaction_type']) {
                                                case 'DEPOSIT':
                                                    echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/arrow-down.svg" class="w-4 h-4" /> ' . __('Deposit to Wallet') . '</div>';
                                                    break;
                                                case 'WITHDRAWAL':
                                                    echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/arrow-up.svg" class="w-4 h-4" /> ' . __('Withdrawal from Wallet') . '</div>';
                                                    break;
                                                case 'TRANSFER':
                                                    if ($transaction['sender_id'] == $_SESSION['user_id']) {
                                                        echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/export.svg" class="w-4 h-4" /> ' . __("Transfer to") . " {$transaction['receiver_username']}</div>";
                                                    } else {
                                                        echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/import.svg" class="w-4 h-4" /> ' . __("Transfer from") . " {$transaction['sender_username']}</div>";
                                                    }
                                                    break;
                                                case 'PAYMENT':
                                                    echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/card.svg" class="w-4 h-4" /> ' . $transaction['description'] . '</div>';
                                                    break;
                                                case 'REFERRAL_REWARD':
                                                    echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/medal-star.svg" class="w-4 h-4" /> ' . $transaction['description'] . '</div>';
                                                    break;
                                                default:
                                                    echo '<div class="flex items-center gap-2"><img src="../../../sources/icons/bulk/refresh.svg" class="w-4 h-4" /> ' . $transaction['description'] . '</div>';
                                                    break;
                                            }
                                            ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?= date('F j, Y g:i a', strtotime($transaction['transaction_date'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?= __('Transaction ID:') ?> #<?= $transaction['transaction_id']; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium <?php
                                        if (
                                            $transaction['transaction_type'] == 'REFERRAL_REWARD' ||
                                            strpos(strtolower($transaction['description']), 'referral bonus') !== false
                                        ) {
                                            echo 'text-green-600';  // Referral işlemleri her zaman yeşil
                                        } elseif (
                                            $transaction['sender_id'] == $_SESSION['user_id'] &&
                                            $transaction['transaction_type'] != 'DEPOSIT'
                                        ) {
                                            echo 'text-red-600';
                                        } else {
                                            echo 'text-green-600';
                                        }
                                        ?>">
                                            <?php
                                            // Referral ve coins işlemleri için özel görünüm
                                            if (
                                                $transaction['transaction_type'] == 'REFERRAL_REWARD' ||
                                                strpos(strtolower($transaction['description']), 'referral bonus') !== false
                                            ) {
                                                echo '+' . (int) $transaction['amount'] . ' 🪙'; // Referral bonusları her zaman pozitif ve coin olarak
                                            } else {
                                                // Normal para işlemleri için mevcut mantık
                                                if (
                                                    $transaction['sender_id'] == $_SESSION['user_id'] &&
                                                    $transaction['transaction_type'] != 'DEPOSIT'
                                                ) {
                                                    echo '-';
                                                } else {
                                                    echo '+';
                                                }
                                                echo '₺' . number_format($transaction['amount'], 2);
                                            }
                                            ?>
                                        </p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    <?php
                    switch ($transaction['status']) {
                        case 'COMPLETED':
                            echo 'bg-green-100 text-green-800';
                            break;
                        case 'PENDING':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                        case 'FAILED':
                            echo 'bg-red-100 text-red-800';
                            break;
                        default:
                            echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                                            <?= $transaction['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-semibold text-gray-900"><?= __('Coming Soon') ?></h3>
                            <p class="mt-1 text-sm text-gray-500"><?= __('This feature is currently under development.') ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>