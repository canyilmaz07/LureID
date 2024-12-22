<?php
// api/generate-invite-link.php
session_start();
require_once '../../../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $projectId = $data['project_id'];

    // Generate unique invite code
    $inviteCode = bin2hex(random_bytes(8));

    $stmt = $db->prepare("
        UPDATE projects 
        SET invite_code = :invite_code,
            invite_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
        WHERE project_id = :project_id 
        AND owner_id = :user_id
    ");

    $result = $stmt->execute([
        'invite_code' => $inviteCode,
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'invite_code' => $inviteCode
        ]);
    } else {
        throw new Exception('Davet linki oluşturulamadı');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>