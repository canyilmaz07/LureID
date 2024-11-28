<?php
// Hata raporlamayı açalım
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Dizin yolunu kontrol edelim
    $currentDir = dirname(__FILE__);
    $configPath = dirname(dirname(dirname(__FILE__))) . '/config/database.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Database config file not found at: " . $configPath);
    }

    // Config dosyasını include et
    $dbConfig = require $configPath;

    if (!isset($dbConfig['host']) || !isset($dbConfig['dbname']) || !isset($dbConfig['username']) || !isset($dbConfig['password'])) {
        throw new Exception("Database configuration is incomplete");
    }

    // PDO bağlantısını kur
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Query parametresini kontrol et
    if (!isset($_GET['query'])) {
        throw new Exception("Query parameter is missing");
    }

    $query = $_GET['query'];
    $searchQuery = "%$query%";
    $currentUserId = $_SESSION['user_id'];

    // SQL sorgusunu hazırla
    $stmt = $db->prepare("
        SELECT 
            u.user_id, 
            u.username, 
            u.email, 
            u.full_name, 
            COALESCE(ued.profile_photo_url, 'undefined') as profile_photo_url
        FROM users u
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
        WHERE (
            u.username LIKE :query 
            OR u.email LIKE :query 
            OR u.full_name LIKE :query
        )
        AND u.user_id != :current_user_id
        LIMIT 5
    ");

    // Sorguyu çalıştır
    $stmt->execute([
        'query' => $searchQuery,
        'current_user_id' => $currentUserId
    ]);
    
    $results = $stmt->fetchAll();

    // Sonuçları döndür
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);

} catch (PDOException $e) {
    error_log("Database Error in search_dropdown.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Database error: ' . $e->getMessage(),
        'type' => 'PDOException'
    ]);
} catch (Exception $e) {
    error_log("General Error in search_dropdown.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'type' => 'Exception'
    ]);
}
?>