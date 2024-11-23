<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input validation
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $twoFactorAuth = isset($_POST['two_factor_auth']) ? (int)$_POST['two_factor_auth'] : 0;

    // Get current user data
    $stmt = $db->prepare("SELECT username, email, password FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validate username and email uniqueness (if changed)
    if ($username !== $currentUser['username']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            exit(json_encode(['success' => false, 'message' => 'Bu kullanıcı adı zaten kullanılıyor']));
        }
    }

    if ($email !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            exit(json_encode(['success' => false, 'message' => 'Bu e-posta adresi zaten kullanılıyor']));
        }
    }

    // Validate current password if trying to change password or sensitive info
    if ($currentPassword) {
        if (!password_verify($currentPassword, $currentUser['password'])) {
            exit(json_encode(['success' => false, 'message' => 'Mevcut şifreniz yanlış']));
        }
    } elseif ($username !== $currentUser['username'] || $email !== $currentUser['email']) {
        exit(json_encode(['success' => false, 'message' => 'Bilgilerinizi güncellemek için mevcut şifrenizi girmelisiniz']));
    }

    try {
        $db->beginTransaction();

        // Update password if provided
        if ($newPassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        }

        // Update other information
        $stmt = $db->prepare("
            UPDATE users 
            SET username = ?,
                email = ?,
                two_factor_auth = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $username,
            $email,
            $twoFactorAuth,
            $_SESSION['user_id']
        ]);

        $db->commit();
        exit(json_encode(['success' => true]));
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Bir hata oluştu']));
    }
}

exit(json_encode(['success' => false, 'message' => 'Invalid request method']));
?>