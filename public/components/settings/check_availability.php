<?php
// check_availability.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $value = trim($_POST['value'] ?? '');
    $currentUserId = $_SESSION['user_id'];

    // Database connection
    $dbConfig = require '../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    try {
        // Input validation
        if (empty($type) || empty($value)) {
            throw new Exception('Invalid input');
        }

        if ($type === 'username') {
            // Username validation
            if (strlen($value) < 3) {
                throw new Exception('Username must be at least 3 characters');
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                throw new Exception('Username can only contain letters, numbers, and underscores');
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$value, $currentUserId]);
            
        } elseif ($type === 'email') {
            // Email validation
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$value, $currentUserId]);
            
        } else {
            throw new Exception('Invalid check type');
        }

        $count = $stmt->fetchColumn();
        
        exit(json_encode([
            'success' => true,
            'available' => ($count === 0)
        ]));

    } catch (Exception $e) {
        exit(json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]));
    }
}

exit(json_encode(['success' => false, 'message' => 'Invalid request method']));
?>