<?php
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
    $theme = isset($_POST['theme']) ? $_POST['theme'] : null;
    $fontFamily = isset($_POST['font_family']) ? $_POST['font_family'] : null;

    // Basit validasyon
    if (!$theme || !$fontFamily) {
        throw new Exception('Geçersiz parametreler');
    }

    // Tema değerini kontrol et
    if (!in_array($theme, ['light', 'dark'])) {
        throw new Exception('Geçersiz tema değeri');
    }

    // Font değerini kontrol et
    $allowedFonts = ['Inter', 'Roboto', 'Open Sans', 'Montserrat', 'Poppins'];
    if (!in_array($fontFamily, $allowedFonts)) {
        throw new Exception('Geçersiz font değeri');
    }

    // Önce kullanıcının ayarları var mı kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // Güncelle
        $stmt = $db->prepare("
            UPDATE user_settings 
            SET theme = ?, font_family = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ");
    } else {
        // Yeni kayıt oluştur
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, theme, font_family) 
            VALUES (?, ?, ?)
        ");
    }

    $result = $stmt->execute([
        $exists ? $theme : $_SESSION['user_id'],
        $exists ? $fontFamily : $theme,
        $exists ? $_SESSION['user_id'] : $fontFamily
    ]);

    if ($result) {
        // Ayarları oturuma kaydet
        $_SESSION['theme'] = $theme;
        $_SESSION['font_family'] = $fontFamily;
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Veritabanı güncelleme hatası');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>