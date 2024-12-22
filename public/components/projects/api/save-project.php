<?php
// /public/components/projects/api/save-project.php
session_start();
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    die(json_encode(['error' => 'Direct access not allowed']));
}

try {
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No input data received');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    $project_id = $data['project_id'] ?? null;
    $projectData = $data['data'] ?? null;

    if (!$project_id || !$projectData) {
        throw new Exception('Missing project_id or project data');
    }

    // Storage yolları
    $storageDir = __DIR__ . '/../../../../storage/projects';
    $uploadsDir = __DIR__ . '/../../../../storage/uploads';

    // Dizinleri oluştur
    foreach ([$storageDir, $uploadsDir] as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }
        if (!is_writable($dir)) {
            throw new Exception("Directory not writable: $dir");
        }
    }

    // JSON dosyasını kaydet
    $projectFileName = md5($project_id . '_' . time()) . '.json';
    $projectFilePath = $storageDir . '/' . $projectFileName;
    
    // Preview image'ı JSON'dan çıkar
    $previewImage = $projectData['preview_image'] ?? null;
    unset($projectData['preview_image']);

    $jsonContent = json_encode($projectData, JSON_PRETTY_PRINT);
    if ($jsonContent === false) {
        throw new Exception('Failed to encode project data');
    }

    if (file_put_contents($projectFilePath, $jsonContent) === false) {
        throw new Exception('Failed to write project file');
    }

    // Veritabanı bağlantısı
    $dbConfig = require __DIR__ . '/../../../../config/database.php';
    
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Yetki kontrolü
    $stmt = $pdo->prepare("SELECT owner_id FROM projects WHERE project_id = ? AND owner_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Permission denied');
    }

    // Preview image'ı kaydet
    $imageFileName = null;
    if ($previewImage) {
        $imageData = str_replace('data:image/jpeg;base64,', '', $previewImage);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            throw new Exception('Invalid image data');
        }

        $imageFileName = md5($project_id . '_preview_' . time()) . '.jpg';
        $imageFilePath = $uploadsDir . '/' . $imageFileName;

        if (file_put_contents($imageFilePath, $decodedImage) === false) {
            throw new Exception('Failed to save preview image');
        }
    }

    // Veritabanını güncelle
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET file_path = ?,
            preview_image = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE project_id = ? 
        AND owner_id = ?
    ");

    if (!$stmt->execute([$projectFileName, $imageFileName, $project_id, $_SESSION['user_id']])) {
        throw new Exception('Failed to update database');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Save Project Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'details' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}