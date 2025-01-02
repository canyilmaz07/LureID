<?php
session_start();
require_once '../../../../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: /instructor/dashboard.php');
    exit;
}

try {
    $dbConfig = require '../../../../../config/database.php';
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Kursu ve eğitmen kontrolünü yap
    $stmt = $conn->prepare("
        SELECT c.*, i.user_id 
        FROM courses c 
        JOIN instructors i ON c.instructor_id = i.instructor_id 
        WHERE c.course_id = ? AND i.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: /instructor/dashboard.php');
        exit;
    }

    // Kayıtlı öğrenci kontrolü
    if (json_decode($course['enrolled_students'], true)) {
        $_SESSION['error'] = "Kayıtlı öğrencisi olan kurs silinemez.";
        header('Location: /instructor/dashboard.php');
        exit;
    }

    // İçerikleri sil
    $stmt = $conn->prepare("
        SELECT cs.section_id, cc.content_id, cc.content_type, cc.content_data
        FROM course_sections cs
        LEFT JOIN course_contents cc ON cs.section_id = cc.section_id
        WHERE cs.course_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $contents = $stmt->fetchAll();

    foreach ($contents as $content) {
        if ($content['content_id']) {
            $contentData = json_decode($content['content_data'], true);
            
            // Dosyaları sil
            if ($content['content_type'] === 'VIDEO' && isset($contentData['file_path'])) {
                @unlink('../../uploads/videos/' . $contentData['file_path']);
            } else if (isset($contentData['file_path'])) {
                @unlink('../../uploads/files/' . $contentData['file_path']);
            }
            
            // İçeriği veritabanından sil
            $stmt = $conn->prepare("DELETE FROM course_contents WHERE content_id = ?");
            $stmt->execute([$content['content_id']]);
        }
        
        // Bölümü sil
        $stmt = $conn->prepare("DELETE FROM course_sections WHERE section_id = ?");
        $stmt->execute([$content['section_id']]);
    }

    // Kurs görselini ve videosunu sil
    if ($course['thumbnail_url']) {
        @unlink('../../uploads/images/' . $course['thumbnail_url']);
    }
    if ($course['preview_video_url']) {
        @unlink('../../uploads/videos/' . $course['preview_video_url']);
    }

    // Kursu sil
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->execute([$_GET['id']]);

    $_SESSION['success'] = "Kurs başarıyla silindi.";
    header('Location: /instructor/dashboard.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    header('Location: /instructor/dashboard.php');
    exit;
}