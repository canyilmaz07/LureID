<?php
// get_follows.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id']) || !isset($_GET['type'])) {
    echo json_encode([]);
    exit;
}

try {
    $dbConfig = require '../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $userId = $_GET['user_id'];
    $type = $_GET['type']; // followers veya following
    $currentUserId = $_SESSION['user_id'] ?? null;

    // Followers/Following listesini al
    $stmt = $db->prepare("SELECT $type FROM follows WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo json_encode([]);
        exit;
    }

    $userIds = json_decode($data[$type], true);

    if (empty($userIds)) {
        echo json_encode([]);
        exit;
    }

    // Kullanıcı bilgilerini getir
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.full_name,
            CONCAT('profile/avatars/', u.user_id, '.jpg') as profile_photo_url
        FROM users u
        WHERE u.user_id IN ($placeholders)
    ");
    $stmt->execute($userIds);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eğer giriş yapmış kullanıcı varsa takip durumlarını kontrol et
    if ($currentUserId) {
        $stmt = $db->prepare("SELECT following FROM follows WHERE user_id = ?");
        $stmt->execute([$currentUserId]);
        $currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);
        $followingList = json_decode($currentUserData['following'] ?? '[]', true);

        foreach ($users as &$user) {
            $user['is_following'] = in_array($user['user_id'], $followingList);
            $user['can_follow'] = $currentUserId && $user['user_id'] != $currentUserId;
        }
    }

    if ($type === 'counts') {
        $followsStmt = $db->prepare("SELECT following, followers FROM follows WHERE user_id = ?");
        $followsStmt->execute([$userId]);
        $followsData = $followsStmt->fetch(PDO::FETCH_ASSOC);

        $following = json_decode($followsData['following'] ?? '[]', true);
        $followers = json_decode($followsData['followers'] ?? '[]', true);

        echo json_encode([
            'followers' => count($followers),
            'following' => count($following)
        ]);
        exit;
    }

    echo json_encode($users);
} catch (PDOException $e) {
    error_log('Get Follows Error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?>