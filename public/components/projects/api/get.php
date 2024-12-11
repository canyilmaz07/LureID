<?php
// components/projects/api/get.php

require_once '../../../../config/database.php';
session_start();

try {
    $projectUtils = new ProjectUtils($db);
    $projectId = $_GET['project_id'] ?? null;
    
    if (!$projectId) {
        throw new Exception('Project ID is required');
    }
    
    // Proje detaylarını al
    $project = $projectUtils->getProjectPreview($projectId);
    
    if (!$project) {
        throw new Exception('Project not found');
    }
    
    // Eğer proje private ise ve kullanıcı sahibi değilse erişimi engelle
    if ($project['visibility'] === 'PRIVATE' && 
        (!isset($_SESSION['user_id']) || $project['user_id'] != $_SESSION['user_id'])) {
        throw new Exception('Unauthorized access to private project');
    }
    
    // Proje verilerini yükle
    $projectData = $projectUtils->loadProjectData($project['project_root']);
    
    if (!$projectData) {
        throw new Exception('Project data not found');
    }
    
    // Görüntülenme sayısını artır
    if (isset($_SESSION['user_id']) && $project['user_id'] != $_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE projects SET views = views + 1 WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $project['views']++;
    }
    
    echo json_encode([
        'status' => 'success',
        'project' => array_merge($project, ['data' => $projectData])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>