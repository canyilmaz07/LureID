<?php
// follow_user.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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

    // Hedef kullanıcının takipçi listesini al
    $targetStmt = $db->prepare("SELECT followers FROM follows WHERE user_id = ?");
    $targetStmt->execute([$_POST['user_id']]);
    $targetData = $targetStmt->fetch(PDO::FETCH_ASSOC);
    $targetFollowers = json_decode($targetData['followers'] ?? '[]', true);

    // Mevcut kullanıcının takip listesini al
    $currentStmt = $db->prepare("SELECT following FROM follows WHERE user_id = ?");
    $currentStmt->execute([$_SESSION['user_id']]);
    $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);
    $currentFollowing = json_decode($currentData['following'] ?? '[]', true);

    // Takip durumunu kontrol et
    $isFollowing = in_array($_SESSION['user_id'], $targetFollowers);

    if ($isFollowing) {
        // Takipten çıkar
        $targetFollowers = array_values(array_filter($targetFollowers, fn($id) => $id != $_SESSION['user_id']));
        $currentFollowing = array_values(array_filter($currentFollowing, fn($id) => $id != $_POST['user_id']));
        $action = 'unfollowed';
    } else {
        // Takip et
        $targetFollowers[] = $_SESSION['user_id'];
        $currentFollowing[] = (int)$_POST['user_id'];
        $action = 'followed';
    }

    // Hedef kullanıcının takipçilerini güncelle
    $updateTargetStmt = $db->prepare("UPDATE follows SET followers = ? WHERE user_id = ?");
    $updateTargetStmt->execute([json_encode($targetFollowers), $_POST['user_id']]);

    // Mevcut kullanıcının takip listesini güncelle
    $updateCurrentStmt = $db->prepare("UPDATE follows SET following = ? WHERE user_id = ?");
    $updateCurrentStmt->execute([json_encode($currentFollowing), $_SESSION['user_id']]);

    // Yeni takipçi ve takip edilen sayılarını al
    $followersCount = count($targetFollowers);
    $followingCount = count($currentFollowing);

    echo json_encode([
        'success' => true, 
        'action' => $action,
        'newCounts' => [
            'followers' => $followersCount
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>