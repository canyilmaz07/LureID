<?php
session_start();

if (!isset($_SESSION['staff_id'])) {
    header('Location: staff-auth.php');
    exit;
}

$role = $_SESSION['staff_role'] ?? '';
$username = $_SESSION['staff_username'] ?? '';

// Include panel files
require_once 'panels/admin_panel.php';
require_once 'panels/moderator_panel.php';
require_once 'panels/support_panel.php';

// Get panel content based on role
function getPanelContent($role) {
    switch ($role) {
        case 'ADMIN':
            return getAdminPanel();
        case 'MODERATOR':
            return getModeratorPanel();
        case 'SUPPORT':
            return getSupportPanel();
        default:
            return '<p class="text-gray-500">No panel available for your role.</p>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Panel - LUREID</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, html, body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="font-['Bebas_Neue'] text-xl">LUREID STAFF</div>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-500 mr-4"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <?php echo getPanelContent($role); ?>
        </div>
    </main>
</body>
</html>