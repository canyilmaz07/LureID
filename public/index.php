<?php
// public/index.php
session_start();
require_once '../config/database.php';
require_once '../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Database connection and user data fetch remain the same until the HTML part
try {
    $dbConfig = require '../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $stmt = $db->prepare("
    SELECT u.*, ued.*, w.balance, w.coins
    FROM users u
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    LEFT JOIN wallet w ON u.user_id = w.user_id
    WHERE u.user_id = ?
");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database error occurred');
}

// User_extended_details tablosunu kontrol et
$checkProfileStmt = $db->prepare("SELECT COUNT(*) FROM user_extended_details WHERE user_id = ?");
$checkProfileStmt->execute([$_SESSION['user_id']]);
if ($checkProfileStmt->fetchColumn() == 0) {
    $createProfileStmt = $db->prepare("
        INSERT INTO user_extended_details (
            user_id,
            profile_photo_url,
            basic_info,
            network_links,
            skills_matrix
        ) VALUES (
            ?,
            'undefined',
            JSON_OBJECT(
                'full_name', (SELECT full_name FROM users WHERE user_id = ?),
                'age', NULL,
                'biography', NULL,
                'location', JSON_OBJECT('city', NULL, 'country', NULL),
                'contact', JSON_OBJECT('email', NULL, 'website', NULL),
                'languages', JSON_ARRAY()
            ),
            JSON_OBJECT(
                'professional', JSON_OBJECT(),
                'social', JSON_OBJECT(),
                'portfolio_sites', JSON_OBJECT()
            ),
            JSON_OBJECT(
                'technical_skills', JSON_ARRAY(),
                'soft_skills', JSON_ARRAY(),
                'tools', JSON_ARRAY()
            )
        )
    ");
    $createProfileStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
}

// Follows tablosunu kontrol et
$checkFollowsStmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE user_id = ?");
$checkFollowsStmt->execute([$_SESSION['user_id']]);
if ($checkFollowsStmt->fetchColumn() == 0) {
    $createFollowsStmt = $db->prepare("
        INSERT INTO follows (user_id, following, followers) 
        VALUES (?, '[]', '[]')
    ");
    $createFollowsStmt->execute([$_SESSION['user_id']]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID - Dashboard</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-white">
    <?php include 'components/menu.php'; ?>
    <?php
    if (isset($_SESSION['user_data']['profile_photo_url']) && $_SESSION['user_data']['profile_photo_url'] === 'undefined') {
        echo "<script>
        $(document).ready(function() {
            function checkAndCreateAvatar() {
                $.ajax({
                    url: 'components/create-avatar.php',
                    type: 'POST',
                    data: {
                        check_avatar: true,
                        user_id: '" . $_SESSION['user_id'] . "',
                        full_name: '" . addslashes($_SESSION['user_data']['full_name']) . "' 
                    },
                    success: function(response) {
                        if(response.needsAvatar) {
                            createAvatar();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Avatar check error:', error);
                    }
                });
            }

            function createAvatar() {
                $.ajax({
                    url: 'components/create-avatar.php',
                    type: 'POST',
                    data: {
                        create_avatar: true,
                        user_id: '" . $_SESSION['user_id'] . "',
                        full_name: '" . addslashes($_SESSION['user_data']['full_name']) . "' 
                    },
                    success: function(response) {
                        if(response.success) {
                            console.log('Avatar created successfully:', response);
                            location.reload(); // Sayfayı yenile
                        } else {
                            console.error('Avatar creation failed:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Avatar creation error:', error);
                    }
                });
            }

            checkAndCreateAvatar();
        });
        </script>";
    }
    ?>

    <script>
        $(document).ready(function () {
            // Settings Tab Click Handler
            $('.settingsTab').click(function () {
                const tab = $(this).data('tab');
                window.location.href = 'components/settings/settings.php?tab=' + tab;
            });
        });
    </script>
</body>

</html>