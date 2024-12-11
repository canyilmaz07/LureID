<?php
// components/projects/api/delete.php

require_once '../../../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $projectId = $_GET['project_id'] ?? null;
    
    if (!$projectId) {
        throw new Exception('Project ID is required');
    }
    
    // Projenin sahibi olduğunu kontrol et
    $stmt = $db->prepare("
        SELECT project_root, preview_image 
        FROM projects 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        throw new Exception('Project not found or unauthorized');
    }
    
    // JSON dosyasını sil
    $jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/projects/files/' . $project['project_root'];
    if (file_exists($jsonPath)) {
        unlink($jsonPath);
    }
    
    // Önizleme görselini sil
    $previewPath = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $project['preview_image'];
    if (file_exists($previewPath)) {
        unlink($previewPath);
    }
    
    // Veritabanından sil
    $stmt = $db->prepare("DELETE FROM projects WHERE project_id = ?");
    $stmt->execute([$projectId]);
    
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>