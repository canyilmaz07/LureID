<?php
// validate-username.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$username = $_POST['username'] ?? '';

if (strlen($username) < 2) {
    echo json_encode(['users' => []]);
    exit;
}

$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

// Aranan kelimeye benzer kullanıcıları getir
$stmt = $db->prepare("
    SELECT u.user_id, u.username, u.full_name, up.profile_photo_url 
    FROM users u 
    LEFT JOIN user_profiles up ON u.user_id = up.user_id 
    WHERE u.username LIKE ? AND u.user_id != ? 
    LIMIT 5
"); 

$stmt->execute([$username . '%', $_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['users' => $users]);
?>