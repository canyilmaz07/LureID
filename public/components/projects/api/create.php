<?php
// components/projects/api/create.php

require_once '../../../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $projectUtils = new ProjectUtils($db);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['title']) || !isset($data['projectData']) || !isset($data['previewImage'])) {
        throw new Exception('Missing required fields');
    }
    
    $projectId = $projectUtils->generateProjectId();
    $jsonPath = $projectUtils->createJsonPath($_SESSION['user_id'], $projectId, $data['title']);
    $previewPath = $projectUtils->savePreviewImage($data['previewImage'], $projectId);
    
    // Proje verilerini kaydet
    $projectUtils->saveProjectData($jsonPath, $data['projectData']);
    
    // Veritabanına kaydet
    $stmt = $db->prepare("
        INSERT INTO projects (
            project_id, user_id, title, description, 
            project_root, preview_image, tags, visibility
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $projectId,
        $_SESSION['user_id'],
        $data['title'],
        $data['description'] ?? '',
        $jsonPath,
        $previewPath,
        json_encode($data['tags'] ?? []),
        $data['visibility'] ?? 'PUBLIC'
    ]);
    
    echo json_encode([
        'status' => 'success',
        'project_id' => $projectId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>