<?php
// api/update-project-name.php
session_start();
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$project_id = $data['project_id'] ?? null;
$title = $data['title'] ?? null;

if (!$project_id || !$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

try {
    // Veritabanı bağlantısı
    $dbConfig = require '../../../config/database.php';
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    $stmt = $db->prepare("
        UPDATE projects 
        SET title = :title 
        WHERE project_id = :project_id 
        AND owner_id = :user_id
    ");
    
    $stmt->execute([
        'title' => $title,
        'project_id' => $project_id,
        'user_id' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>