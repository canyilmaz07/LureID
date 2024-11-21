<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
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

    // Transaction başlat
    $db->beginTransaction();

    // Users tablosunu güncelle
    $stmtUsers = $db->prepare("
        UPDATE users 
        SET username = ?, email = ?, full_name = ?
        WHERE user_id = ?
    ");

    $stmtUsers->execute([
        $_POST['username'],
        $_POST['email'],
        $_POST['fullName'],
        $_SESSION['user_id']
    ]);

    // User profiles tablosunu güncelle
    $stmtProfiles = $db->prepare("
        UPDATE user_profiles 
        SET biography = ?, website = ?, city = ?, country = ?, age = ?
        WHERE user_id = ?
    ");

    $stmtProfiles->execute([
        $_POST['biography'],
        $_POST['website'],
        $_POST['city'],
        $_POST['country'],
        $_POST['age'] ? (int)$_POST['age'] : null,
        $_SESSION['user_id']
    ]);

    // Transaction'ı tamamla
    $db->commit();

    // Session'daki user_data'yı güncelle
    $_SESSION['user_data'] = array_merge($_SESSION['user_data'], [
        'username' => $_POST['username'],
        'email' => $_POST['email'],
        'full_name' => $_POST['fullName'],
        'biography' => $_POST['biography'],
        'website' => $_POST['website'],
        'city' => $_POST['city'],
        'country' => $_POST['country'],
        'age' => $_POST['age'] ? (int)$_POST['age'] : null
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => $_SESSION['user_data']
    ]);

} catch (PDOException $e) {
    // Hata durumunda geri al
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>