<?php
session_start();

// Get database configuration
$config = require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

// Disable error reporting for clean JSON responses
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı']);
    exit;
}

try {
    // Correctly use the config array for PDO connection
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $db = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['gig_id'], $input['status'], $input['freelancer_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
        exit;
    }

    $gig_id = intval($input['gig_id']);
    $status = $input['status'];
    $freelancer_id = intval($input['freelancer_id']);

    // Validate status value - add DELETED to valid statuses that can be changed to APPROVED
    $valid_statuses = ['APPROVED', 'PAUSED', 'DELETED'];
    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz durum değeri']);
        exit;
    }

    // Check ownership and current status
    $checkQuery = "SELECT gig_id, status, DATEDIFF(DATE_ADD(updated_at, INTERVAL 30 DAY), NOW()) as days_until_delete 
                  FROM gigs 
                  WHERE gig_id = ? AND freelancer_id = ?";
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([$gig_id, $freelancer_id]);
    $gig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gig) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz yok']);
        exit;
    }

    // Check if trying to restore a deleted gig that's past its deletion period
    if ($gig['status'] === 'DELETED' && $gig['days_until_delete'] <= 0 && $status === 'APPROVED') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bu ilan kalıcı olarak silinmiş']);
        exit;
    }

    // Update status
    $updateQuery = "UPDATE gigs SET status = ?, updated_at = NOW() WHERE gig_id = ?";
    $stmt = $db->prepare($updateQuery);
    $result = $stmt->execute([$status, $gig_id]);

    if ($result) {
        $message = match($status) {
            'APPROVED' => $gig['status'] === 'DELETED' ? 'İlan başarıyla geri yüklendi' : 'İlan yayına alındı',
            'PAUSED' => 'İlan arşivlendi',
            'DELETED' => 'İlan silindi',
            default => 'İlan durumu güncellendi'
        };

        echo json_encode([
            'success' => true,
            'message' => $message,
            'status' => $status
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Güncelleme başarısız oldu']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage()
    ]);
}
?>