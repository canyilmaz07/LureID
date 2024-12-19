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

// Freelancer bilgilerini al
$userId = $_SESSION['user_id'];
$freelancerQuery = "SELECT freelancer_id FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($freelancerQuery);
$stmt->execute([$userId]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($freelancerData) {
    $freelancer_id = $freelancerData['freelancer_id'];
} else {
    $freelancer_id = null;
}

// Freelancer durumunu kontrol et
$checkFreelancerQuery = "SELECT freelancer_id, approval_status FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($checkFreelancerQuery);
$stmt->execute([$userId]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcının freelancer kaydı yoksa ana sayfaya yönlendir
if (!$freelancerData) {
    header('Location: /public/index.php');
    exit;
}

$userQuery = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $db->prepare($userQuery);
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Duruma göre görüntülenecek içeriği belirle
$status = $freelancerData['approval_status'];

if ($status === 'PENDING') {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $current_language ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hesap İnceleniyor - LureID</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                font-family: 'Poppins', sans-serif;
            }

            .container-shadow {
                box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            }

            .review-heading {
                font-size: 22px;
            }

            .review-text,
            .review-button {
                font-size: 12px;
                text-align: center;
            }

            .review-img {
                height: 250px;
                width: auto;
                margin: 0 auto;
                display: block;
            }

            .page-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .content-container {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 2rem;
            }
        </style>
    </head>

    <body class="bg-gray-100">
        <div class="page-container">
            <div class="max-w-md w-full bg-white rounded-lg container-shadow content-container">
                <div class="mb-6 flex items-center justify-center">
                    <img src="review.svg" alt="Review Icon" class="review-img">
                </div>
                <h2 class="review-heading font-bold text-gray-900 mb-4">Hesabınız İnceleniyor</h2>
                <p class="review-text text-gray-600 mb-6">Başvurunuz şu anda inceleme aşamasında. Hesabınız onaylandığında
                    size bildirim göndereceğiz.</p>
                <a href="/public/index.php"
                    class="review-button inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
} elseif ($status === 'REJECTED') {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $current_language ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hesap Onaylanmadı - LureID</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                font-family: 'Poppins', sans-serif;
            }

            .container-shadow {
                box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            }

            .review-heading {
                font-size: 22px;
            }

            .review-text,
            .review-button {
                font-size: 12px;
                text-align: center;
            }

            .review-img {
                height: 250px;
                width: auto;
                margin: 0 auto;
                display: block;
            }

            .page-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .content-container {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 2rem;
            }
        </style>
    </head>

    <body class="bg-gray-100">
        <div class="page-container">
            <div class="max-w-md w-full bg-white rounded-lg container-shadow content-container">
                <div class="mb-6 flex items-center justify-center">
                    <img src="sad.svg" alt="Rejection Icon" class="review-img">
                </div>
                <h2 class="review-heading font-bold text-gray-900 mb-4">Hesabınız Onaylanmadı</h2>
                <p class="review-text text-gray-600 mb-6">Üzgünüz, freelancer başvurunuz onaylanmadı. Yeniden başvuru
                    yapabilirsiniz.</p>
                <div class="space-x-4">
                    <a href="/public/index.php"
                        class="review-button inline-block bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                        Ana Sayfaya Dön
                    </a>
                    <form action="reapply.php" method="POST" class="inline-block">
                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                        <button type="submit"
                            class="review-button bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Yeniden Başvur
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
} elseif ($status !== 'APPROVED') {
    header('Location: /public/index.php');
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