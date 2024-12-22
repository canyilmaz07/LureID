<?php
// components/projects/api/create-project.php
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
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $visibility = $_POST['visibility'];
    $tags = json_decode($_POST['tags'], true);
    
    // Boş alan kontrolü
    if (empty($title) || empty($description)) {
        throw new Exception('Lütfen tüm alanları doldurun');
    }
    
    $stmt = $db->prepare("
        INSERT INTO projects (
            title, 
            description, 
            tags,
            visibility,
            owner_id,
            collaborators,
            created_at
        ) VALUES (
            :title,
            :description,
            :tags,
            :visibility,
            :owner_id,
            '[]',
            NOW()
        )
    ");
    
    $result = $stmt->execute([
        'title' => $title,
        'description' => $description,
        'tags' => json_encode($tags),
        'visibility' => $visibility,
        'owner_id' => $_SESSION['user_id']
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'project_id' => $db->lastInsertId(),
            'message' => 'Proje başarıyla oluşturuldu'
        ]);
    } else {
        throw new Exception('Proje oluşturulurken bir hata oluştu');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>