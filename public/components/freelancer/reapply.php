<?php
session_start();
$config = require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['user_id']) || $_SESSION['user_id'] != $_POST['user_id']) {
    header('Location: /public/index.php');
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    // Eski freelancer kaydını sil
    $deleteQuery = "DELETE FROM freelancers WHERE user_id = ?";
    $stmt = $db->prepare($deleteQuery);
    $stmt->execute([$_SESSION['user_id']]);
    
    // Registration sayfasına yönlendir
    header('Location: registration.php');
    exit;
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}
?>