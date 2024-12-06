<?php
// wallet.php
session_start();
require_once '../../../config/database.php';
require_once 'wallet_comps.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/login.php');
    exit;
}

$activeSection = isset($_GET['section']) ? $_GET['section'] : 'wallet';

// Database bağlantısı
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("
    SELECT u.*, ued.profile_photo_url, w.coins, w.balance, u.subscription_plan
    FROM users u
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id 
    LEFT JOIN wallet w ON u.user_id = w.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Subscription plan text
$planText = 'Basic';
if ($userData['subscription_plan'] == 'id_plus') {
    $planText = 'ID+';
} else if ($userData['subscription_plan'] == 'id_plus_pro') {
    $planText = 'ID+ Pro';
}

$menuItems = [
    ['section' => 'wallet', 'icon' => 'wallet-2.svg', 'text' => 'Cüzdan'],
    ['section' => 'transactions', 'icon' => 'timer-1.svg', 'text' => 'Hareketler'],
    ['section' => 'subscriptions', 'icon' => 'receipt-2.svg', 'text' => 'Abonelikler'],
];
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Sidebar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        *,
        body,
        html {
            padding: 0;
            margin: 0;
            font-family: 'Poppins', sans-serif;
        }

        .sidebar {
            position: fixed;
            width: 320px;
            height: 100vh;
            background-color: white;
            border-right: 1px solid #dedede;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .logo {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
        }

        <?php
        $badgeClass = "plan-badge";
        $planStyle = "";

        switch ($userData['subscription_plan']) {
            case 'basic':
                $planText = 'Basic';
                $planStyle = "background-color: #e3e3e3; color: #000;";
                break;

            case 'id_plus':
                $planText = 'ID+';
                $planStyle = "background-color: #4F46E5; color: white;";
                break;

            case 'id_plus_pro':
                $planText = 'ID+ Pro';
                $badgeClass .= " pro";
                break;

            default:
                $planText = 'Basic';
                $planStyle = "background-color: #e3e3e3; color: #000;";
        }
        ?>

        .plan-badge {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 600;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            margin-bottom: 8px;
            text-align: center;
            width: fit-content;
            min-width: 175px;
            display: flex;
            justify-content: center;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        /* Basic plan stili */
        .plan-badge.basic {
            background-color: #e3e3e3;
            color: #000;
        }

        /* ID+ plan stili */
        .plan-badge.plus {
            background-color: #4F46E5;
            color: white;
        }

        /* ID+ Pro plan stili */
        .plan-badge.pro {
            background: linear-gradient(90deg,
                    #FFD700 0%,
                    #FFD700 30%,
                    #FDB931 50%,
                    #FFD700 70%,
                    #FFD700 100%);
            color: #000;
            animation: shine 4s ease infinite;
            background-size: 200% 100%;
        }

        @keyframes shine {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .profile-section {
            background-color: #f8f8f8;
            padding: 12px;
            border-radius: 12px;
            width: 100%;
            margin-top: -10px;
            margin-bottom: 20px;
            position: relative;
            z-index: 9;
        }

        .profile-container {
            margin: 0 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-photo {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
            color: #111;
        }

        .profile-email {
            font-size: 10px;
            color: #666;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
        }

        .home-button {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #000;
            text-decoration: none;
            margin: 0 20px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .home-button:hover {
            background-color: #f5f5f5;
            border-radius: 15px;
        }

        .home-button img {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            transform: scaleX(-1);
            opacity: 0.7;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            font-size: 12px;
            color: #000;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 20px;
        }

        .menu-item:hover {
            background-color: #f5f5f5;
            border-radius: 15px;
        }

        .menu-item img {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            opacity: 0.7;
        }

        .menu-item.active {
            background-color: #f5f5f5;
            border-radius: 15px;
            font-weight: 600;
        }

        .menu-item.active img {
            opacity: 0.9;
        }

        <?php
        $buttonText = ($userData['subscription_plan'] == 'basic') ? 'ID+ Edin' : 'ID+ Hesabım';
        $buttonUrl = ($userData['subscription_plan'] == 'basic') ? '/upgrade' : '/id-plus-account';
        ?>

        .upgrade-button {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            font-size: 12px;
            color: white;
            text-decoration: none;
            background-color: #4F46E5;
            margin: 5px 20px;
            border-radius: 15px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .upgrade-button:hover {
            opacity: 0.9;
        }

        .upgrade-button img {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            opacity: 0.9;
            filter: brightness(0) invert(1);
        }

        .bottom-section {
            margin-top: auto;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">LUREID</div>

            <div class="main-content">
                <a href="/public/index.php" class="home-button">
                    <img src="/sources/icons/bulk/arrow-right.svg" alt="Geri">
                    Ana Sayfaya Dön
                </a>

                <?php foreach ($menuItems as $item): ?>
                    <a href="?section=<?php echo $item['section']; ?>"
                        class="menu-item <?php echo $activeSection === $item['section'] ? 'active' : ''; ?>">
                        <img src="/sources/icons/bulk/<?php echo $item['icon']; ?>" alt="<?php echo $item['text']; ?>">
                        <?php echo $item['text']; ?>
                    </a>
                <?php endforeach; ?>

                <?php if ($userData['subscription_plan'] === 'basic'): ?>
                    <a href="?section=upgrade" class="upgrade-button">
                        <img src="/sources/icons/bulk/flash-circle.svg" alt="ID+ Edin">
                        ID+ Edin
                    </a>
                <?php endif; ?>
            </div>

            <!-- Profile section -->
            <div class="bottom-section">
                <div class="profile-container">
                    <div class="<?php echo $badgeClass; ?>" style="<?php echo $planStyle; ?>">
                        <?php echo htmlspecialchars($planText); ?>
                    </div>
                    <div class="profile-section">
                        <div class="profile-header">
                            <img src="/public/<?php echo $userData['profile_photo_url'] !== 'undefined' ? $userData['profile_photo_url'] : 'sources/defaults/avatar.jpg'; ?>"
                                alt="Profile" class="profile-photo">
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($userData['full_name']); ?></div>
                                <div class="profile-email"><?php echo htmlspecialchars($userData['email']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 bg-gray-100 min-h-screen">
            <?php echo getWalletContent($activeSection, $userData); ?>
        </div>
    </div>

    <?php
    if (isset($_SESSION['show_subscription_welcome'])) {
        $planName = $userData['subscription_plan'] == 'id_plus' ? 'ID+' : 'ID+ Pro';
        ?>
        <div id="welcome-modal"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div style="background: white; padding: 30px; border-radius: 15px; max-width: 400px; text-align: center;">
                <img src="/sources/icons/bulk/crown.svg" style="width: 48px; height: 48px; margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 10px;">🎉 <?php echo $planName; ?> Ailesine Hoş
                    Geldiniz!</h2>
                <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                    <?php if ($userData['subscription_plan'] == 'id_plus_pro'): ?>
                        Artık tüm premium özelliklere sınırsız erişiminiz var.
                    <?php else: ?>
                        ID+ üyesi olarak özel ayrıcalıklardan yararlanabilirsiniz.
                    <?php endif; ?>
                </p>
                <button onclick="closeModal()"
                    style="background: #4F46E5; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px;">Anladım</button>
            </div>
        </div>

        <script>
            function closeModal() {
                document.getElementById('welcome-modal').style.display = 'none';
                fetch('clear_welcome_modal.php');
            }
        </script>
        <?php
        unset($_SESSION['show_subscription_welcome']);
    }
    ?>
</body>

</html>