<?php
// Oturumu başlat
session_start();

// Veritabanı yapılandırmasını yükle
require_once '../../../config/database.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// POST'tan kullanıcı adını al
$username = $_POST['username'] ?? '';

// Base URL tanımı
define('BASE_URL', 'http://localhost/public');

// Minimum karakter kontrolü
if (strlen($username) < 2) {
    echo json_encode([
        'status' => 'success',
        'users' => []
    ]);
    exit;
}

try {
    // Veritabanı yapılandırmasını al
    $dbConfig = require '../../../config/database.php';
    
    // PDO bağlantısını oluştur
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Benzer kullanıcıları getir
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.full_name,
            ued.profile_photo_url,
            CASE 
                WHEN ued.basic_info IS NOT NULL THEN 
                    JSON_UNQUOTE(JSON_EXTRACT(ued.basic_info, '$.biography'))
                ELSE NULL 
            END as biography
        FROM users u
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
        WHERE u.username LIKE :username 
        AND u.user_id != :user_id
        AND u.is_verified = 1
        LIMIT 5
    ");

    $stmt->execute([
        ':username' => $username . '%',
        ':user_id' => $_SESSION['user_id']
    ]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kullanıcı profillerini formatlayarak döndür
    $formattedUsers = array_map(function($user) {
        $profile_photo_url = $user['profile_photo_url'] ?? 'undefined';
        
        // Eğer profil fotoğrafı varsa, tam URL oluştur
        if ($profile_photo_url !== 'undefined') {
            $profile_photo_url = BASE_URL . '/' . ltrim($profile_photo_url, '/');
        }

        return [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'profile_photo_url' => $profile_photo_url,
            'biography' => $user['biography'] ?? null
        ];
    }, $users);

    echo json_encode([
        'status' => 'success',
        'users' => $formattedUsers
    ]);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    ]);
}
?>