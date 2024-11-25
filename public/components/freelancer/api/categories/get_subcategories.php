<?php
// public/components/freelancer/api/categories/get_subcategories.php

session_start();
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT']);
$config = require_once BASE_PATH . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$category = $_GET['category'] ?? '';

if (!$category) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    
    $db = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // Debug için kategori bilgisini logla
    error_log("Selected Category: " . $category);

    // Ana kategorinin ID'sini al
    $parentQuery = "SELECT category_id FROM gig_categories WHERE name = ? AND parent_id IS NULL";
    $stmt = $db->prepare($parentQuery);
    $stmt->execute([$category]);
    $parentCategory = $stmt->fetch();

    // Debug için parent category bilgisini logla
    error_log("Parent Category ID: " . json_encode($parentCategory));

    if ($parentCategory) {
        // Alt kategorileri getir
        $query = "SELECT name FROM gig_categories WHERE parent_id = ? ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->execute([$parentCategory['category_id']]);
        $subcategories = $stmt->fetchAll();

        // Debug için subcategories bilgisini logla
        error_log("Subcategories: " . json_encode($subcategories));
        
        echo json_encode($subcategories);
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>