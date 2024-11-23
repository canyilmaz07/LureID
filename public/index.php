<?php
// index.php
session_start();
require_once '../config/database.php';

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

    <!-- Freelancer Section -->
    <div id="freelancerSection" class="hidden">
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <button id="backFromFreelancer" class="flex items-center text-gray-600 hover:text-gray-900">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </button>
                    <h1 class="text-xl font-semibold">Freelancer Registration</h1>
                    <div></div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div id="freelancerContent" class="bg-white rounded-lg shadow p-6">
                <!-- Freelancer content will be loaded here -->
            </div>
        </div>
    </div>

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
                            placeholder="Search users...">
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
                        Wallet
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
                                    <?php echo htmlspecialchars($_SESSION['user_data']['username']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($_SESSION['user_data']['email']); ?>
                                </div>
                            </div>
                            <img src="<?php echo htmlspecialchars($_SESSION['user_data']['profile_photo_url']); ?>"
                                alt="Profile" class="w-10 h-10 rounded-full object-cover">
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false"
                            class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg py-1 z-50">
                            <!-- Account Settings Group -->
                            <div class="px-4 py-3 border-b">
                                <div class="text-sm font-semibold text-gray-500">You'r Profile</div>
                                <a href="/<?php echo htmlspecialchars($_SESSION['user_data']['username']); ?>"
                                    class="block w-full text-left px-4 py-2 hover:bg-gray-50">
                                    Profile
                                </a>
                                <div class="text-sm font-semibold text-gray-500">Account Settings</div>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md mt-2"
                                    data-tab="profile">
                                    Profil Ayarları
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="security">
                                    Güvenlik Merkezi
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="notifications">
                                    Bildirim Ayarları
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="payment">
                                    Ödeme & Finansal İşlemler
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="privacy">
                                    Gizlilik Ayarları
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="account">
                                    Hesap ve Veriler
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="language">
                                    Dil ve Bölge
                                </button>
                                <button class="settingsTab w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    data-tab="appearance">
                                    Görünüm ve Tema
                                </button>
                            </div>

                            <!-- Freelancer Section -->
                            <div class="px-4 py-3 border-b">
                                <?php if (isset($_SESSION['user_data']['freelancer_id'])): ?>
                                    <button id="openFreelancer"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md">
                                        Freelancer Panel
                                    </button>
                                <?php else: ?>
                                    <button id="openFreelancer"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md">
                                        Become a Freelancer
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Support Section -->
                            <div class="px-4 py-3 border-b">
                                <button class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded-md"
                                    onclick="window.location.href='support.php'">
                                    Destek ve Yardım
                                </button>
                            </div>

                            <!-- Logout -->
                            <div class="px-4 py-3">
                                <a href="../auth/logout.php"
                                    class="block w-full text-left px-3 py-2 text-red-600 hover:bg-red-50 rounded-md">
                                    Çıkış Yap
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">
                    Welcome, <?php echo htmlspecialchars($_SESSION['user_data']['username']); ?>!
                </h2>
                <!-- Client Works Section in index.php after welcome message -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-4">Your Active Works</h3>
                    <?php
                    // Get works where user is client
                    $stmt = $db->prepare("
        SELECT j.*, u.username as freelancer_username, u.full_name as freelancer_name,
               w.title as work_title
        FROM jobs j
        JOIN freelancers f ON j.freelancer_id = f.freelancer_id
        JOIN users u ON f.user_id = u.user_id
        JOIN works w ON j.title = w.title
        WHERE j.user_id = :user_id 
        AND j.status IN ('IN_PROGRESS', 'DELIVERED')
        ORDER BY j.created_at DESC
    ");
                    $stmt->execute([':user_id' => $_SESSION['user_id']]);
                    $clientJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($clientJobs)): ?>
                        <div class="space-y-4">
                            <?php foreach ($clientJobs as $job): ?>
                                <div class="bg-white rounded-lg shadow p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium"><?php echo htmlspecialchars($job['work_title']); ?></h4>
                                            <p class="text-sm text-gray-600">Freelancer:
                                                <?php echo htmlspecialchars($job['freelancer_name']); ?>
                                                (@<?php echo htmlspecialchars($job['freelancer_username']); ?>)
                                            </p>
                                            <p class="text-sm text-gray-600">Budget:
                                                ₺<?php echo number_format($job['budget'], 2); ?></p>
                                            <p
                                                class="text-sm <?php echo $job['status'] === 'DELIVERED' ? 'text-yellow-600' : 'text-blue-600'; ?>">
                                                Status: <?php echo $job['status']; ?>
                                            </p>
                                        </div>
                                        <?php if ($job['status'] === 'DELIVERED'): ?>
                                            <button onclick="acceptDelivery(<?php echo $job['job_id']; ?>)"
                                                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                                Accept Delivery
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No active works found.</p>
                    <?php endif; ?>
                </div>

                <script>
                    function acceptDelivery(jobId) {
                        if (!confirm('Are you sure you want to accept this delivery? This will complete the work and release the payment.')) {
                            return;
                        }

                        $.post('components/job_actions.php', {
                            action: 'complete',
                            job_id: jobId
                        }).done(function (response) {
                            if (response.success) {
                                alert(response.message);
                                location.reload();
                            } else {
                                alert(response.message || 'An error occurred');
                            }
                        }).fail(function () {
                            alert('Network error occurred');
                        });
                    }
                </script>
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

            // Freelancer Panel Functions
            $('#openFreelancer').click(function () {
                $('#mainDashboard').hide();
                $('#settingsSection').hide();
                $('#walletSection').hide();
                $('#freelancerSection').show();
                loadFreelancer();
            });

            $('#backFromFreelancer').click(function () {
                $('#freelancerSection').hide();
                $('#mainDashboard').show();
            });

            function loadFreelancer() {
                $('#freelancerContent').html('<div class="text-center py-4">Loading...</div>');
                $.get('components/freelancer_subs.php', function (response) {
                    $('#freelancerContent').html(response);
                });
            }

            // Listen for wallet balance updates
            $(document).on('walletBalanceUpdated', function (e, newBalance) {
                $('#openWallet').html(`
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path>
                <path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path>
            </svg>
            Wallet ($${parseFloat(newBalance).toFixed(2)})
        `);
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