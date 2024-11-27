<?php
// components/search.php

header('Content-Type: application/json');

// Veritabanı bağlantısı için config dosyasını dahil et
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
        $query = '%' . $_POST['query'] . '%';
        
        $dbConfig = require '../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        // Normal kullanıcıları ara
        $userStmt = $db->prepare("
            SELECT u.username, u.full_name, ued.profile_photo_url
            FROM users u
            LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
            WHERE (u.username LIKE ? OR u.full_name LIKE ?)
            AND u.user_id NOT IN (
                SELECT user_id FROM freelancers
            )
            LIMIT 5
        ");
        $userStmt->execute([$query, $query]);
        $profiles = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        // Freelancerları ara
        $freelancerStmt = $db->prepare("
            SELECT u.username, u.full_name, ued.profile_photo_url
            FROM users u
            INNER JOIN freelancers f ON u.user_id = f.user_id
            LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
            WHERE (u.username LIKE ? OR u.full_name LIKE ?)
            AND f.approval_status = 'APPROVED'
            AND f.status = 'ACTIVE'
            LIMIT 5
        ");
        $freelancerStmt->execute([$query, $query]);
        $freelancers = $freelancerStmt->fetchAll(PDO::FETCH_ASSOC);

        // JSON olarak sonuçları döndür
        echo json_encode([
            'profiles' => $profiles,
            'freelancers' => $freelancers
        ]);
    }
} catch (PDOException $e) {
    // Hata durumunda hata mesajı döndür
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>