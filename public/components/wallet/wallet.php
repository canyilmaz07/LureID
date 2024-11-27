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
    <style>
        @font-face {
            font-family: 'Anton';
            src: url('../fonts/Anton.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        /* Content Wrapper */
        .wallet-content-wrapper {
            position: absolute;
            top: 95px;
            /* menu height (80px) + 15px gap */
            left: 50%;
            transform: translateX(-850px);
            /* Half of menu width (1700/2) */
            width: 1700px;
            padding-left: 255px;
            /* sidebar width (240px) + 15px gap */
            padding-right: 15px;
            padding-bottom: 80px;
            /* Same as top menu gap */
            opacity: 0;
        }

        .balance-cards {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
            justify-content: center;
            width: 50%;
            position: relative;
            /* Added for tooltip positioning context */
            z-index: 1;
            /* Ensure the cards stay above other content */
        }

        /* Ensure tooltips appear above everything */
        .balance-cards::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
        }

        .balance-card {
            flex: 1;
            background: #fff;
            border-radius: 15px;
            padding: 32px;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: visible;
            /* Changed from hidden to allow tooltips to overflow */
            min-height: 200px;
        }

        .balance-card:first-child .action-button {
            background: rgb(0 0 0 / 40%);
        }

        .balance-card:first-child .action-button:hover {
            background: rgb(0 0 0 / 20%);
        }

        .balance-card:first-child .action-button img {
            opacity: 1;
        }

        /* Second balance card (coins) specific styles */
        .balance-card:last-child .coin-action-button {
            background: rgb(0 0 0 / 40%);
        }

        .balance-card:last-child .coin-action-button:hover {
            background: rgb(0 0 0 / 20%);
        }

        .balance-card:last-child .coin-action-button img {
            opacity: 1;
        }

        .balance-card:first-child::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at var(--position) 125%,
                    rgba(219, 234, 254, 0.95) 0%,
                    /* Açık mavi */
                    rgba(147, 197, 253, 0.95) 20%,
                    /* Orta-açık mavi */
                    rgba(59, 130, 246, 0.95) 40%,
                    /* Mavi */
                    rgba(29, 78, 216, 0.95) 60%,
                    /* Koyu mavi */
                    rgba(30, 58, 138, 0.95) 80%,
                    /* Daha koyu mavi */
                    rgba(17, 24, 39, 0.95) 100%
                    /* En koyu mavi */
                );
            animation: moveLight 30s ease-in-out infinite;
            z-index: 0;
            border-radius: 15px;
        }

        .balance-card:last-child::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at var(--position) 125%,
                    rgba(254, 234, 219, 0.95) 0%,
                    /* Açık turuncu */
                    rgba(253, 186, 147, 0.95) 20%,
                    /* Orta-açık turuncu */
                    rgba(246, 130, 59, 0.95) 40%,
                    /* Turuncu */
                    rgba(216, 78, 29, 0.95) 60%,
                    /* Koyu turuncu */
                    rgba(180, 50, 20, 0.95) 80%,
                    /* Daha koyu turuncu */
                    rgba(39, 17, 17, 0.95) 100%
                    /* En koyu turuncu */
                );
            animation: moveLight 30s ease-in-out infinite;
            z-index: 0;
            border-radius: 15px;
        }

        @property --position {
            syntax: '<percentage>';
            inherits: false;
            initial-value: 0%;
        }

        @keyframes moveLight {
            0% {
                --position: 0%;
            }

            40% {
                --position: 100%;
            }

            45% {
                --position: 100%;
            }

            85% {
                --position: 0%;
            }

            90% {
                --position: 0%;
            }

            100% {
                --position: 0%;
            }
        }

        .card-header {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .card-header img {
            width: 24px;
            height: 24px;
            opacity: 0.8;
            filter: brightness(0) invert(1);
            /* İkonları beyaz yapmak için */
        }

        .card-header h3 {
            font-size: 14px;
            font-weight: 500;
            color: white;
        }

        .balance-amount {
            position: relative;
            z-index: 1;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 40px;
            /* Add space for action buttons */
        }

        #currentBalance,
        #currentCoins {
            font-family: 'Anton', sans-serif;
            font-size: 48px;
            /* Increased font size */
        }

        .currency {
            font-family: 'Anton', sans-serif;
            font-size: 32px;
            opacity: 0.9;
        }

        .coin-icon {
            font-size: 32px;
            display: inline-flex;
            align-items: center;
        }

        .action-buttons-container {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 12px;
            z-index: 2;
        }

        .action-button {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            /* Daha düşük opaklık */
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .action-button:hover {
            background: rgba(255, 255, 255, 0.25);
            /* Hover'da daha yüksek opaklık */
        }

        .action-button img {
            width: 20px;
            height: 20px;
            filter: brightness(0) invert(1);
            /* Beyaz ikon */
            opacity: 1;
            /* Tam opak */
        }

        .action-button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .action-button:hover::after,
        .coin-action-button:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 10px);
            /* Butonun üstünde konumlandır */
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 9999;
            pointer-events: none;
            animation: fadeIn 0.2s ease-in-out;
        }

        .action-button::after,
        .coin-action-button::after {
            bottom: calc(100% + 10px);
            /* Position above the button */
            left: 50%;
        }

        .coin-action-button {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            /* Daha düşük opaklık */
            border: none;
            cursor: pointer;
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .coin-action-button:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }


        .coin-action-button img {
            width: 20px;
            height: 20px;
            filter: brightness(0) invert(1);
            /* Beyaz ikon */
            opacity: 1;
            /* Tam opak */
        }

        /* Transactions Section */
        .transactions-section {
            background: #fff;
            border-radius: 15px;
            padding: 24px;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(20px);
        }

        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .transactions-header h3 {
            font-size: 14px;
            font-weight: 500;
        }

        .transactions-header a {
            color: #3b82f6;
            font-size: 13px;
            text-decoration: none;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .transaction-info img {
            width: 20px;
            height: 20px;
            opacity: 0.7;
        }

        .transaction-text {
            font-size: 13px;
            font-weight: 500;
        }

        .transaction-date {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }

        .transaction-amount {
            text-align: right;
        }

        .amount {
            font-size: 14px;
            font-weight: 600;
        }

        .amount.positive {
            color: #22c55e;
        }

        .amount.negative {
            color: #ef4444;
        }

        .transaction-status {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }

        /* Form Styles */
        .transaction-forms {
            background: #fff;
            border-radius: 15px;
            margin-bottom: 24px;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            opacity: 0;
            height: 0;
            transform: translateY(-20px);
        }

        .form-container {
            padding: 24px;
        }

        .form-header {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 13px;
        }

        .form-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .sidebar-wrapper {
            position: fixed;
            /* Menu width is 1700px, so align with its left edge */
            left: 50%;
            transform: translateX(-850px);
            /* Half of menu width (1700/2) */
            top: 95px;
            /* menu height (80px) + 15px gap */
            opacity: 0;
            z-index: 900;
        }

        .sidebar-container {
            background: #fff;
            border: 1px solid #dedede;
            border-radius: 15px;
            padding: 12px;
            width: 240px;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
        }

        .sidebar-menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            /* Reduced gap */
            padding: 8px 12px;
            /* Reduced padding */
            margin: 5px 0;
            /* Reduced margin */
            border-radius: 10px;
            cursor: pointer;
            opacity: 0;
            transform: translateY(-10px);
            color: #000;
            text-decoration: none;
            font-size: 12px;
            /* Matches menu.php */
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .sidebar-menu-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .sidebar-menu-item.active {
            background: rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }

        .sidebar-menu-item img {
            width: 20px;
            /* Matches menu.php icon size */
            height: 20px;
            opacity: 0.7;
        }

        .sidebar-menu-item:hover img {
            opacity: 0.9;
        }
    </style>
</head>

<body>

    <?php include '../menu.php'; ?>
    <div class="sidebar-wrapper">
        <div class="sidebar-container">
            <a href="#home" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/home-2.svg" alt="home" class="white-icon">
                Ana Sayfa
            </a>
            <a href="#withdraw" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/money-send.svg" alt="withdraw" class="white-icon">
                Para Çekme
            </a>
            <a href="#deposit" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/money-recive.svg" alt="deposit" class="white-icon">
                Para Yatırma
            </a>
            <a href="#transfer" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/export.svg" alt="transfer" class="white-icon">
                Transfer
            </a>
            <a href="#transactions" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/timer-1.svg" alt="transactions" class="white-icon">
                Hareketler
            </a>
            <a href="#cards" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/card.svg" alt="cards" class="white-icon">
                Kayıtlı Kartlar
            </a>
            <a href="#subscriptions" class="sidebar-menu-item">
                <img src="/sources/icons/bulk/receipt-2.svg" alt="subscriptions" class="white-icon">
                Abonelikler
            </a>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="wallet-content-wrapper">
            <div class="balance-cards">
                <div class="balance-card">
                    <div class="card-header">
                        <img src="/sources/icons/bulk/wallet.svg" alt="wallet">
                        <h3><?= __('Wallet Balance') ?></h3>
                    </div>
                    <div class="balance-amount">
                        <span class="currency">₺</span>
                        <span id="currentBalance"><?php echo number_format($walletData['balance'], 2); ?></span>
                    </div>
                    <div class="action-buttons-container">
                        <button class="action-button" data-tooltip="Para Yatırma" id="showDeposit">
                            <img src="/sources/icons/bulk/money-recive.svg" alt="deposit">
                        </button>
                        <button class="action-button" data-tooltip="Çekme" id="showWithdraw">
                            <img src="/sources/icons/bulk/money-send.svg" alt="withdraw">
                        </button>
                        <button class="action-button" data-tooltip="Transfer" id="showTransfer">
                            <img src="/sources/icons/bulk/export.svg" alt="transfer">
                        </button>
                    </div>
                </div>

                <div class="balance-card">
                    <div class="card-header">
                        <img src="/sources/icons/bulk/coin.svg" alt="coin">
                        <h3><?= __('Coins Balance') ?></h3>
                    </div>
                    <div class="balance-amount">
                        <span id="currentCoins"><?php echo number_format($walletData['coins']); ?></span>
                        <span class="coin-icon">🪙</span>
                    </div>
                    <button class="coin-action-button" data-tooltip="İlanı Önce Çıkar">
                        <img src="/sources/icons/bulk/trend-up.svg" alt="use coins">
                    </button>
                </div>
            </div>

            <!-- Transaction Forms -->
            <div class="transaction-forms">
                <!-- Withdraw Form -->
                <form id="withdrawForm" class="form-container hidden">
                    <h4 class="form-header"><?= __('Withdraw Funds') ?></h4>
                    <div class="form-group">
                        <label class="form-label"><?= __('Amount') ?></label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="form-input">
                    </div>
                    <button type="submit" class="form-button withdraw">
                        <?= __('Confirm Withdrawal') ?>
                    </button>
                </form>

                <!-- Transfer Form -->
                <form id="transferForm" class="form-container hidden">
                    <h4 class="form-header"><?= __('Transfer Funds') ?></h4>
                    <div class="form-group recipient-search">
                        <label class="form-label"><?= __('Recipient Username') ?></label>
                        <input type="text" name="receiver" class="form-input"
                            placeholder="<?= __('Search username...') ?>">
                        <div id="searchResults" class="hidden mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto">
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
                            <button type="button" id="changeRecipient" class="text-sm text-red-600 hover:text-red-800">
                                <?= __('Change Recipient') ?>
                            </button>
                        </div>
                    </div>

                    <div id="amountSection" class="form-group hidden">
                        <label class="form-label"><?= __('Amount') ?></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-input">
                    </div>

                    <button type="submit" id="transferButton" class="form-button transfer hidden">
                        <?= __('Confirm Transfer') ?>
                    </button>
                </form>
            </div>

            <!-- Transactions Section -->
            <div class="transactions-section">
                <div class="transactions-header">
                    <h3><?= __('Recent Transactions') ?></h3>
                    <a href="recent-transactions.php" class="view-all"><?= __('View All') ?></a>
                </div>
                <div class="transactions-list">
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <?php
                                switch ($transaction['transaction_type']) {
                                    case 'DEPOSIT':
                                        echo '<img src="/sources/icons/bulk/arrow-down.svg" alt="deposit" />';
                                        echo '<div><div class="transaction-text">' . __('Deposit to Wallet') . '</div>';
                                        break;
                                    case 'WITHDRAWAL':
                                        echo '<img src="/sources/icons/bulk/arrow-up.svg" alt="withdrawal" />';
                                        echo '<div><div class="transaction-text">' . __('Withdrawal from Wallet') . '</div>';
                                        break;
                                    case 'TRANSFER':
                                        if ($transaction['sender_id'] == $_SESSION['user_id']) {
                                            echo '<img src="/sources/icons/bulk/export.svg" alt="transfer" />';
                                            echo '<div><div class="transaction-text">' . __("Transfer to") . " {$transaction['receiver_username']}</div>";
                                        } else {
                                            echo '<img src="/sources/icons/bulk/import.svg" alt="transfer" />';
                                            echo '<div><div class="transaction-text">' . __("Transfer from") . " {$transaction['sender_username']}</div>";
                                        }
                                        break;
                                    case 'PAYMENT':
                                        echo '<img src="/sources/icons/bulk/card.svg" alt="payment" />';
                                        echo '<div><div class="transaction-text">' . $transaction['description'] . '</div>';
                                        break;
                                    case 'REFERRAL_REWARD':
                                        echo '<img src="/sources/icons/bulk/medal-star.svg" alt="referral" />';
                                        echo '<div><div class="transaction-text">' . $transaction['description'] . '</div>';
                                        break;
                                    default:
                                        echo '<img src="/sources/icons/bulk/refresh.svg" alt="transaction" />';
                                        echo '<div><div class="transaction-text">' . $transaction['description'] . '</div>';
                                }
                                ?>
                                <div class="transaction-date">
                                    <?php echo date('F j, Y g:i a', strtotime($transaction['transaction_date'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="transaction-amount">
                            <div class="amount <?php
                            if (
                                $transaction['transaction_type'] == 'REFERRAL_REWARD' ||
                                (strpos(strtolower($transaction['description']), 'referral bonus') !== false)
                            ) {
                                echo 'positive';
                            } elseif (
                                $transaction['sender_id'] == $_SESSION['user_id'] &&
                                $transaction['transaction_type'] != 'DEPOSIT'
                            ) {
                                echo 'negative';
                            } else {
                                echo 'positive';
                            }
                            ?>">
                                <?php
                                if (
                                    $transaction['transaction_type'] == 'REFERRAL_REWARD' ||
                                    strpos(strtolower($transaction['description']), 'referral bonus') !== false
                                ) {
                                    echo '+' . (int) $transaction['amount'] . ' 🪙';
                                } else {
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
                            </div>
                            <div class="transaction-status"><?php echo $transaction['status']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Wait for sidebar animation to complete (2000ms + 800ms for sidebar animation)
            setTimeout(() => {
                const contentTimeline = gsap.timeline();

                contentTimeline
                    .to('.wallet-content-wrapper', {
                        opacity: 1,
                        duration: 0.5,
                        ease: 'power3.out'
                    })
                    .to('.balance-cards', {
                        opacity: 1,
                        y: 0,
                        duration: 0.5,
                        ease: 'power3.out'
                    })
                    .to('.action-buttons', {
                        opacity: 1,
                        y: 0,
                        duration: 0.5,
                        ease: 'power3.out'
                    }, '-=0.3')
                    .to('.transactions-section', {
                        opacity: 1,
                        y: 0,
                        duration: 0.5,
                        ease: 'power3.out'
                    }, '-=0.3');

                // Action button hover animations
                document.querySelectorAll('.action-button').forEach(button => {
                    button.addEventListener('mouseenter', () => {
                        gsap.to(button, {
                            scale: 1.02,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    });

                    button.addEventListener('mouseleave', () => {
                        gsap.to(button, {
                            scale: 1,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    });
                });

                // Form animations
                const showForm = (formType) => {
                    const formTimeline = gsap.timeline();

                    formTimeline
                        .to('.transaction-forms', {
                            height: 'auto',
                            opacity: 1,
                            y: 0,
                            duration: 0.5,
                            ease: 'power3.out'
                        });
                };

                // Add click handlers for buttons
                document.querySelector('.action-button.withdraw').addEventListener('click', () => showForm('withdraw'));
                document.querySelector('.action-button.transfer').addEventListener('click', () => showForm('transfer'));
            }, 2800); // 2000ms menu + 800ms sidebar
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarWrapper = document.querySelector('.sidebar-wrapper');
            const sidebarItems = document.querySelectorAll('.sidebar-menu-item');

            setTimeout(() => {
                const sidebarTimeline = gsap.timeline();

                sidebarTimeline
                    .to(sidebarWrapper, {
                        opacity: 1,
                        x: -850, // Match the transform in CSS
                        duration: 0.5,
                        ease: 'power3.out'
                    })
                    .to(sidebarItems, {
                        opacity: 1,
                        y: 0,
                        duration: 0.3,
                        stagger: 0.1,
                        ease: 'power3.out'
                    }, '-=0.2');
            }, 2000);

            sidebarItems.forEach(item => {
                item.addEventListener('click', () => {
                    sidebarItems.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                });

                item.addEventListener('mouseenter', () => {
                    if (!item.classList.contains('active')) {
                        gsap.to(item, {
                            scale: 1.02,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    }
                });

                item.addEventListener('mouseleave', () => {
                    if (!item.classList.contains('active')) {
                        gsap.to(item, {
                            scale: 1,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    }
                });
            });
        });
    </script>
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