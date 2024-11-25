<?php
session_start();
require_once '../../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı']);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );

    $input = json_decode(file_get_contents('php://input'), true);
    $gig_id = $input['gig_id'] ?? null;
    $status = $input['status'] ?? null;
    $freelancer_id = $input['freelancer_id'] ?? null;

    if (!$gig_id || !$status || !$freelancer_id) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler']);
        exit;
    }

    // İlanın freelancer'a ait olduğunu kontrol et
    $checkQuery = "SELECT gig_id FROM gigs WHERE gig_id = ? AND freelancer_id = ?";
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([$gig_id, $freelancer_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz yok']);
        exit;
    }

    // Durumu güncelle
    $updateQuery = "UPDATE gigs SET status = ?, updated_at = NOW() WHERE gig_id = ?";
    $stmt = $db->prepare($updateQuery);
    $result = $stmt->execute([$status, $gig_id]);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'İlan durumu güncellendi',
            'status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Güncelleme başarısız oldu']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>