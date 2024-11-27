<?php
// components/wallet/get_wallet_data.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

try {
    $dbConfig = require '../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Get wallet data
    $stmt = $db->prepare("SELECT * FROM wallet WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $walletData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent transactions
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
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'balance' => floatval($walletData['balance']),
        'coins' => intval($walletData['coins']),
        'recent_transactions' => $recentTransactions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>