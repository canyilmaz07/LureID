<?php 
// api/delete-project.php
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

    $stmt = $db->prepare("
        DELETE FROM projects 
        WHERE project_id = :project_id 
        AND owner_id = :user_id
    ");

    $result = $stmt->execute([
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Proje başarıyla silindi'
        ]);
    } else {
        throw new Exception('Proje silinemedi');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>