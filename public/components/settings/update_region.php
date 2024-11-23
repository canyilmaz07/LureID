<?php
// update_region.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

try {
    $dbConfig = require '../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Gelen verileri doğrula
    $language = isset($_POST['language']) ? $_POST['language'] : null;
    $timezone = isset($_POST['timezone']) ? $_POST['timezone'] : null;
    $region = isset($_POST['region']) ? $_POST['region'] : null;
    $dateFormat = isset($_POST['date_format']) ? $_POST['date_format'] : null;
    $timeFormat = isset($_POST['time_format']) ? $_POST['time_format'] : null;

    // Basit validasyon
    if (!$language || !$timezone || !$region || !$dateFormat || !$timeFormat) {
        throw new Exception('Tüm alanları doldurun');
    }

    // Timezone geçerliliğini kontrol et
    if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
        throw new Exception('Geçersiz saat dilimi');
    }

    // Ayarları güncelle
    $stmt = $db->prepare("
        UPDATE user_settings 
        SET language = ?, timezone = ?, region = ?, 
            date_format = ?, time_format = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE user_id = ?
    ");

    $result = $stmt->execute([
        $language,
        $timezone,
        $region,
        $dateFormat,
        $timeFormat,
        $_SESSION['user_id']
    ]);

    if ($result) {
        // Dil değişikliğini oturuma kaydet
        $_SESSION['language'] = $language;
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Veritabanı güncelleme hatası');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>