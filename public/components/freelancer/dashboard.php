<?php
// dashboard.php
session_start();
$config = require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LureID</title>
</head>

<body>
    <div class="flex min-h-screen bg-gray-100">
        <?php include 'components/menu.php'; ?>
        <div class="flex-1 ml-[280px] p-6">
            <!-- İçerik buraya -->
        </div>
    </div>
</body>

</html>