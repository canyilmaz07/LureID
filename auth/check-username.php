<?php
// check-username.php
header('Content-Type: application/json');

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    try {
        $dbConfig = require '../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        $username = $_POST['username'] ?? '';
        
        // Check in both users and temp_users tables
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM (
                SELECT username FROM users WHERE username = ?
                UNION
                SELECT username FROM temp_users WHERE username = ?
            ) as combined_users
        ");
        $stmt->execute([$username, $username]);
        $count = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'available' => $count === 0
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error checking username availability'
        ]);
    }
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
}