// api/update-project.php
<?php
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
    $projectId = $data['project_id'];
    $title = trim($data['title']);
    $description = trim($data['description']);
    $visibility = $data['visibility'];

    // Boş alan kontrolü
    if (empty($title) || empty($description)) {
        throw new Exception('Lütfen tüm alanları doldurun');
    }

    // Projeyi güncelle
    $stmt = $db->prepare("
    UPDATE projects 
    SET title = :title,
        description = :description,
        visibility = :visibility,
        updated_at = NOW()
    WHERE project_id = :project_id 
    AND owner_id = :user_id
");

    $result = $stmt->execute([
        'title' => $title,
        'description' => $description,
        'visibility' => $visibility,
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Proje başarıyla güncellendi'
        ]);
    } else {
        throw new Exception('Proje güncellenirken bir hata oluştu');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}