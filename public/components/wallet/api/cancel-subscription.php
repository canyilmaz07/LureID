<?php
// public/components/wallet/api/cancel-subscription.php

// Session ve veritabanı bağlantısı
session_start();
require_once '../../../../config/database.php';

// JSON yanıtı için header
header('Content-Type: application/json');

// CORS ve cache headers
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Kullanıcının giriş yapmış olduğunu kontrol et
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

// JSON verisini al
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['subscription_id']) || !is_numeric($data['subscription_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz abonelik ID']);
    exit;
}

try {
    // Veritabanı bağlantısı
    $dbConfig = require '../../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Aboneliğin kullanıcıya ait olduğunu ve aktif olduğunu kontrol et
    $stmt = $db->prepare("
        SELECT subscription_id, status 
        FROM subscriptions 
        WHERE subscription_id = ? AND user_id = ? AND status = 'ACTIVE'
    ");
    $stmt->execute([$data['subscription_id'], $_SESSION['user_id']]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Abonelik bulunamadı veya zaten iptal edilmiş']);
        exit;
    }

    // Aboneliği iptal et
    $stmt = $db->prepare("
        UPDATE subscriptions 
        SET status = 'CANCELLED' 
        WHERE subscription_id = ? AND user_id = ?
    ");
    $result = $stmt->execute([$data['subscription_id'], $_SESSION['user_id']]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Abonelik başarıyla iptal edildi']);
    } else {
        throw new Exception('Abonelik iptal edilemedi');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>