<?php
// delete_freelancer.php
session_start();
$config = require_once '../../../config/database.php';

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Kullanıcının REJECTED statusunu kontrol et
$checkStatusQuery = "SELECT approval_status FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($checkStatusQuery);
$stmt->execute([$userId]);
$freelancerStatus = $stmt->fetch(PDO::FETCH_ASSOC);

if ($freelancerStatus && $freelancerStatus['approval_status'] === 'REJECTED') {
    // Freelancer kaydını sil
    $deleteQuery = "DELETE FROM freelancers WHERE user_id = ?";
    $stmt = $db->prepare($deleteQuery);
    if ($stmt->execute([$userId])) {
        header('Location: registration.php');
        exit;
    } else {
        $_SESSION['error'] = "Bir hata oluştu. Lütfen tekrar deneyin.";
        header('Location: dashboard.php');
        exit;
    }
} else {
    header('Location: dashboard.php');
    exit;
}
?>