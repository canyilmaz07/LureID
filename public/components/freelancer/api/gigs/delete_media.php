<?php
// api/delete_media.php
session_start();
header('Content-Type: application/json');

// Güvenlik kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

require_once '../../../../../config/database.php';

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['temp_id']) || !isset($data['type']) || !isset($data['index'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler']);
        exit;
    }

    // Temp gig'in kullanıcıya ait olduğunu kontrol et
    $stmt = $db->prepare("
        SELECT tg.media_data 
        FROM temp_gigs tg
        JOIN freelancers f ON tg.freelancer_id = f.freelancer_id
        WHERE tg.temp_gig_id = ? AND f.user_id = ?
    ");
    $stmt->execute([$data['temp_id'], $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'İlan bulunamadı']);
        exit;
    }

    $mediaData = json_decode($result['media_data'], true) ?: ['images' => [], 'video' => null];

    if ($data['type'] === 'image') {
        if (isset($mediaData['images'][$data['index']])) {
            $filePath = $mediaData['images'][$data['index']];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            array_splice($mediaData['images'], $data['index'], 1);
        }
    } elseif ($data['type'] === 'video') {
        if ($mediaData['video'] && file_exists($mediaData['video'])) {
            unlink($mediaData['video']);
            $mediaData['video'] = null;
        }
    }

    // Veritabanını güncelle
    $stmt = $db->prepare("UPDATE temp_gigs SET media_data = ? WHERE temp_gig_id = ?");
    $stmt->execute([json_encode($mediaData), $data['temp_id']]);

    echo json_encode(['success' => true, 'message' => 'Medya başarıyla silindi']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
}
?>