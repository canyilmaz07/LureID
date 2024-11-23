<?php
// update_profile.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Database connection
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

function handleImageUpload($file, $type = 'profile')
{
    $maxSize = 20 * 1024 * 1024; // 5MB

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size must be less than 5MB'];
    }

    if (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
        return ['success' => false, 'message' => 'Only JPG, JPEG & PNG files are allowed'];
    }

    $uploadDir = '../../../public/profile/' . ($type === 'profile' ? 'avatars' : 'covers') . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = $_SESSION['user_id'] . '.jpg';
    $uploadPath = $uploadDir . $fileName;

    $image = null;
    switch ($file['type']) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
    }

    if ($image) {
        // If it's a cover photo, resize to maintain aspect ratio but ensure minimum height
        if ($type === 'cover') {
            $width = imagesx($image);
            $height = imagesy($image);
            $newWidth = 1200;
            $newHeight = ($height / $width) * $newWidth;

            if ($newHeight < 300) {
                $newHeight = 300;
                $newWidth = ($width / $height) * $newHeight;
            }

            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $image = $newImage;
        }

        imagejpeg($image, $uploadPath, 90);
        imagedestroy($image);

        $dbPath = 'profile/' . ($type === 'profile' ? 'avatars' : 'covers') . '/' . $fileName;
        return ['success' => true, 'path' => $dbPath];
    }

    return ['success' => false, 'message' => 'Failed to process image'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'updateProfilePhoto':
            if (!isset($_FILES['profilePhoto'])) {
                exit(json_encode(['success' => false, 'message' => 'No file uploaded']));
            }

            $result = handleImageUpload($_FILES['profilePhoto'], 'profile');
            if ($result['success']) {
                $stmt = $db->prepare("
                    UPDATE user_extended_details 
                    SET profile_photo_url = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$result['path'], $_SESSION['user_id']]);
            }
            exit(json_encode($result));

        case 'updateCoverPhoto':
            if (!isset($_FILES['coverPhoto'])) {
                exit(json_encode(['success' => false, 'message' => 'No file uploaded']));
            }

            $result = handleImageUpload($_FILES['coverPhoto'], 'cover');
            if ($result['success']) {
                $stmt = $db->prepare("
                    UPDATE user_extended_details 
                    SET cover_photo_url = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$result['path'], $_SESSION['user_id']]);
            }
            exit(json_encode($result));

        case 'removeProfilePhoto':
            // Mevcut fotoğrafın yolunu al
            $stmt = $db->prepare("SELECT profile_photo_url FROM user_extended_details WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentPhoto = $stmt->fetchColumn();

            // Eğer fotoğraf varsa dosyayı sil
            if ($currentPhoto !== 'undefined') {
                $filePath = dirname(dirname(dirname(__DIR__))) . '/public/' . $currentPhoto;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Veritabanını güncelle
            $stmt = $db->prepare("
                    UPDATE user_extended_details 
                    SET profile_photo_url = 'undefined' 
                    WHERE user_id = ?
                ");
            $stmt->execute([$_SESSION['user_id']]);
            exit(json_encode(['success' => true]));

        case 'removeCoverPhoto':
            // Mevcut fotoğrafın yolunu al
            $stmt = $db->prepare("SELECT cover_photo_url FROM user_extended_details WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentPhoto = $stmt->fetchColumn();

            // Eğer fotoğraf varsa dosyayı sil
            if ($currentPhoto !== 'undefined') {
                $filePath = dirname(dirname(dirname(__DIR__))) . '/public/' . $currentPhoto;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Veritabanını güncelle
            $stmt = $db->prepare("
                    UPDATE user_extended_details 
                    SET cover_photo_url = 'undefined' 
                    WHERE user_id = ?
                ");
            $stmt->execute([$_SESSION['user_id']]);
            exit(json_encode(['success' => true]));

        case 'updateAllProfileInfo':
            $basicInfo = json_decode($_POST['basicInfo'], true);
            $educationHistory = json_decode($_POST['educationHistory'], true);
            $workExperience = json_decode($_POST['workExperience'], true);
            $skillsMatrix = json_decode($_POST['skillsMatrix'], true);
            $portfolioShowcase = json_decode($_POST['portfolioShowcase'], true);
            $professionalProfile = json_decode($_POST['professionalProfile'], true);
            $networkLinks = json_decode($_POST['networkLinks'], true);
            $achievements = json_decode($_POST['achievements'], true);

            $stmt = $db->prepare("
                    UPDATE user_extended_details 
                    SET basic_info = ?,
                        education_history = ?,
                        work_experience = ?,
                        skills_matrix = ?,
                        portfolio_showcase = ?,
                        professional_profile = ?,
                        network_links = ?,
                        achievements = ?
                    WHERE user_id = ?
                ");

            $result = $stmt->execute([
                json_encode($basicInfo),
                json_encode($educationHistory),
                json_encode($workExperience),
                json_encode($skillsMatrix),
                json_encode($portfolioShowcase),
                json_encode($professionalProfile),
                json_encode($networkLinks),
                json_encode($achievements),
                $_SESSION['user_id']
            ]);

            exit(json_encode(['success' => $result]));
    }
}
?>