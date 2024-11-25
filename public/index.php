<?php
// index.php
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
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100">
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

    <!-- Main Dashboard -->
    <div id="mainDashboard">
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <h1 class="text-xl font-bold">LUREID</h1>
                <div class="flex items-center gap-4">
                    <!-- Search Bar -->
                    <div class="relative">
                        <input type="text" id="searchUsers"
                            class="w-64 px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            placeholder="<?= __('Search users...') ?>">
                        <div id="searchResults"
                            class="hidden absolute w-full mt-1 bg-white border rounded-lg shadow-lg z-50"></div>
                    </div>

                    <!-- Wallet Button -->
                    <a href="components/wallet/wallet.php"
                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                            <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
                            <path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path>
                        </svg>
                        <?= __('Wallet') ?>
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-3 focus:outline-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                            <div class="text-right">
                                <div class="font-semibold">
                                    <?= htmlspecialchars($_SESSION['user_data']['username']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= htmlspecialchars($_SESSION['user_data']['email']); ?>
                                </div>
                            </div>
                            <img src="<?= htmlspecialchars($_SESSION['user_data']['profile_photo_url']); ?>"
                                alt="Profile" class="w-10 h-10 rounded-full object-cover">
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false"
                            class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg py-1 z-50">
                            <!-- Account Settings Group -->
                            <div class="px-4 py-3 border-b">
                                <div class="text-sm font-semibold text-gray-500"><?= __('You\'re Profile') ?></div>
                                <a href="/<?= htmlspecialchars($_SESSION['user_data']['username']); ?>"
                                    class="block w-full text-left px-4 py-2 hover:bg-gray-50">
                                    <?= __('Profile') ?>
                                </a>
                                <div class="text-sm font-semibold text-gray-500"><?= __('Account Settings') ?></div>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md mt-2"
                                    data-tab="profile">
                                    <?= __('Profile Settings') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="security">
                                    <?= __('Security') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="notifications">
                                    <?= __('Notification Settings') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="payment">
                                    <?= __('Payment & Financial Transactions') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="privacy">
                                    <?= __('Privacy Settings') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="account">
                                    <?= __('Account and Data') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="language">
                                    <?= __('Language and Region') ?>
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="appearance">
                                    <?= __('Appearance and Theme') ?>
                                </button>
                            </div>

                            <!-- Freelancer Section -->
                            <div class="px-4 py-3 border-b">
                                <?php
                                // Kullanıcının freelancer olup olmadığını kontrol et
                                $checkFreelancer = $db->prepare("SELECT freelancer_id FROM freelancers WHERE user_id = ?");
                                $checkFreelancer->execute([$_SESSION['user_id']]);
                                $isFreelancer = $checkFreelancer->rowCount() > 0;
                                ?>

                                <?php if ($isFreelancer): ?>
                                    <a href="components/freelancer/dashboard.php"
                                        class="block w-full text-left px-4 py-2 text-green-500 hover:bg-gray-50">
                                        <?= __('Go to Freelancer Dashboard') ?>
                                    </a>
                                <?php else: ?>
                                    <a href="components/freelancer/registration.php"
                                        class="block w-full text-left px-4 py-2 text-blue-500 hover:bg-gray-50">
                                        <?= __('Freelancer Registration') ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Logout -->
                            <div class="px-4 py-3">
                                <form method="POST" action="../auth/logout.php">
                                    <button type="submit"
                                        class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-50">
                                        <?= __('Logout') ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <?= __('Welcome') ?>, <?php echo htmlspecialchars($_SESSION['user_data']['username']); ?>!
                </h2>
                <!-- Client Works Section in index.php after welcome message -->
                <div class="mt-6">

                </div>
        </main>
    </div>

    <script>
        $(document).ready(function () {
            // Settings Tab Click Handler
            $('.settingsTab').click(function () {
                const tab = $(this).data('tab');
                window.location.href = 'components/settings/settings.php?tab=' + tab;
            });
        });

        // Search functionality
        let searchTimeout;
        $('#searchUsers').on('input', function () {
            clearTimeout(searchTimeout);
            const query = $(this).val();
            const $results = $('#searchResults');

            if (query.length < 2) {
                $results.html('').addClass('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                $.get('components/search_users.php', { query: query }, function (data) {
                    if (data.length > 0) {
                        let html = '';
                        data.forEach(user => {
                            html += `
                    <a href="/${user.username}" class="flex items-center gap-3 p-3 hover:bg-gray-50">
                        <img src="${user.profile_photo_url}" 
                             alt="${user.username}" 
                             class="w-8 h-8 rounded-full">
                        <div>
                            <div class="font-semibold">${user.username}</div>
                            <div class="text-sm text-gray-500">${user.full_name}</div>
                        </div>
                    </a>
                `;
                        });
                        $results.html(html).removeClass('hidden');
                    } else {
                        $results.html('<div class="p-3 text-gray-500">No users found</div>').removeClass('hidden');
                    }
                });
            }, 300);
        });

        $(document).click(function (e) {
            if (!$(e.target).closest('#searchUsers, #searchResults').length) {
                $('#searchResults').addClass('hidden');
            }
        });
    </script>
</body>

</html>