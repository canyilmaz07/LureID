<?php
// api/remove-member.php
session_start();
require_once '../../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $dbConfig = require '../../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'];
    $projectId = $data['project_id'];

    // Projeyi ve sahibini kontrol et
    $stmt = $db->prepare("
        SELECT collaborators 
        FROM projects 
        WHERE project_id = :project_id 
        AND owner_id = :owner_id
    ");
    $stmt->execute([
        'project_id' => $projectId,
        'owner_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        $collaborators = json_decode($project['collaborators'], true) ?? [];
        $collaborators = array_values(array_filter($collaborators, function($id) use ($userId) {
            return $id != $userId;
        }));

        $stmt = $db->prepare("
            UPDATE projects 
            SET collaborators = :collaborators 
            WHERE project_id = :project_id
        ");
        $stmt->execute([
            'collaborators' => json_encode($collaborators),
            'project_id' => $projectId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Üye başarıyla kaldırıldı'
        ]);
    } else {
        throw new Exception('Proje bulunamadı veya yetkiniz yok');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>