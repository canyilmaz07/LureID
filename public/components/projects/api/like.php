<?php
// components/projects/api/like.php

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
    
    if (!isset($data['project_id'])) {
        throw new Exception('Project ID is required');
    }
    
    $result = $projectUtils->toggleLike($data['project_id'], $_SESSION['user_id']);
    
    if ($result) {
        $stmt = $db->prepare("
            SELECT JSON_LENGTH(likes_data) as like_count,
                   IF(JSON_SEARCH(likes_data, 'one', ?) IS NOT NULL, 1, 0) as is_liked
            FROM projects 
            WHERE project_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $data['project_id']]);
        $likeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'like_count' => $likeInfo['like_count'],
            'is_liked' => $likeInfo['is_liked']
        ]);
    } else {
        throw new Exception('Failed to update like status');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>