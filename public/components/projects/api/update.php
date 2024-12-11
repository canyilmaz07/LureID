<?php
// components/projects/api/update.php

require_once '../../../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    
    if (!isset($data['project_id'])) {
        throw new Exception('Project ID is required');
    }
    
    // Projenin sahibi olduğunu kontrol et
    $stmt = $db->prepare("SELECT user_id, project_root FROM projects WHERE project_id = ?");
    $stmt->execute([$data['project_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project || $project['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Project not found or unauthorized');
    }
    
    // Proje verilerini güncelle
    if (isset($data['projectData'])) {
        $projectUtils->saveProjectData($project['project_root'], $data['projectData']);
    }
    
    // Önizleme görselini güncelle
    if (isset($data['previewImage'])) {
        $previewPath = $projectUtils->savePreviewImage($data['previewImage'], $data['project_id']);
    }
    
    // Veritabanını güncelle
    $updates = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = $data['title'];
    }
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }
    if (isset($data['tags'])) {
        $updates[] = "tags = ?";
        $params[] = json_encode($data['tags']);
    }
    if (isset($data['visibility'])) {
        $updates[] = "visibility = ?";
        $params[] = $data['visibility'];
    }
    if (isset($data['previewImage'])) {
        $updates[] = "preview_image = ?";
        $params[] = $previewPath;
    }
    
    if (!empty($updates)) {
        $params[] = $data['project_id'];
        $sql = "UPDATE projects SET " . implode(", ", $updates) . " WHERE project_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}