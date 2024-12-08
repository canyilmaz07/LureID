<?php
// public/index.php
session_start();
require_once '../config/database.php';
require_once '../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

try {
    $dbConfig = require '../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $jobsStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM jobs 
    WHERE client_id = ? 
    AND status != 'COMPLETED' 
    AND status != 'CANCELLED'
");
    $jobsStmt->execute([$_SESSION['user_id']]);
    $hasActiveOrders = $jobsStmt->fetchColumn() > 0;

    // Fetch user data
    $stmt = $db->prepare("
        SELECT u.*, ued.*, w.balance, w.coins
        FROM users u
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
        LEFT JOIN wallet w ON u.user_id = w.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_data'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check and create user_extended_details if not exists
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

        // Refresh user data after creation
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check and create follows if not exists
    $checkFollowsStmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE user_id = ?");
    $checkFollowsStmt->execute([$_SESSION['user_id']]);
    if ($checkFollowsStmt->fetchColumn() == 0) {
        $createFollowsStmt = $db->prepare("
            INSERT INTO follows (user_id, following, followers) 
            VALUES (?, '[]', '[]')
        ");
        $createFollowsStmt->execute([$_SESSION['user_id']]);
    }

    // Check and create avatar if needed
    if (isset($_SESSION['user_data']['profile_photo_url']) && $_SESSION['user_data']['profile_photo_url'] === 'undefined') {
        require_once 'components/create-avatar.php';

        // Create avatar
        $avatarCreator = new AvatarCreator($db);
        $result = $avatarCreator->createAvatar($_SESSION['user_id'], $_SESSION['user_data']['full_name']);

        if ($result['success']) {
            $_SESSION['user_data']['profile_photo_url'] = $result['path'];
        } else {
            error_log("Avatar creation failed: " . json_encode($result));
        }
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database error occurred');
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
    <style>
        #orderSuccessModal {
            backdrop-filter: blur(5px);
        }

        @keyframes modal-appear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #orderSuccessModal>div>div {
            animation: modal-appear 0.3s ease-out;
        }
    </style>
</head>

<body class="bg-white">
    <?php include 'components/menu.php'; ?>


    <div id="orderSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6 relative">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <!-- Title -->
                <h3 class="text-center text-xl font-semibold text-gray-900 mb-2">
                    Payment Successful!
                </h3>

                <!-- Message -->
                <div class="text-center mb-6">
                    <p class="text-gray-500">Your payment has been processed successfully. The amount is now in escrow
                        and will be released to the freelancer upon successful delivery.</p>
                </div>

                <!-- Timeline Steps -->
                <div class="space-y-4 mb-6">
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Payment Completed</p>
                            <p class="text-gray-500">Funds are securely held in escrow</p>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-medium">2</span>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Awaiting Delivery</p>
                            <p class="text-gray-500">Freelancer will start working on your order</p>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                            <span class="text-gray-500 font-medium">3</span>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Release Payment</p>
                            <p class="text-gray-500">After you approve the delivery</p>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <button onclick="closeOrderModal()"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Got it, thanks!
                </button>
            </div>
        </div>
    </div>

    <?php if ($hasActiveOrders): ?>
        <a href="/public/views/orders.php"
            class="fixed bottom-8 right-8 bg-blue-600 text-white px-6 py-3 rounded-full shadow-lg hover:bg-blue-700 transition-all duration-300 flex items-center gap-2 group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            My Orders
            <span
                class="bg-white text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-semibold">
                <?php echo $hasActiveOrders; ?>
            </span>
        </a>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Check for the success cookie
            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
            }

            if (getCookie('show_order_success') === '1') {
                // Show modal
                document.getElementById('orderSuccessModal').classList.remove('hidden');
                // Remove cookie
                document.cookie = "show_order_success=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            }
        });

        function closeOrderModal() {
            document.getElementById('orderSuccessModal').classList.add('hidden');
        }

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