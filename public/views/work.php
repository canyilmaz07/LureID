<?php
// work.php
session_start();
require_once '../../config/database.php';

// Veritabanı bağlantısı
try {
    $dbConfig = require '../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database connection failed');
}

// URL'den work ID'sini al
$workId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$workId) {
    header('Location: /');
    exit;
}

// Purchase işlemi kontrolü
if (isset($_POST['purchase']) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // İş ve kullanıcı bilgilerini kontrol et
    $checkStmt = $db->prepare("
    SELECT 
        w.*, 
        f.freelancer_id,
        f.user_id as freelancer_user_id, # Bu satırı ekledik
        w.fixed_price, 
        wa.balance 
    FROM works w 
    JOIN freelancers f ON w.freelancer_id = f.freelancer_id 
    JOIN users u ON f.user_id = u.user_id
    JOIN wallet wa ON wa.user_id = :user_id
    WHERE w.work_id = :work_id
");
    $checkStmt->execute([':work_id' => $workId, ':user_id' => $userId]);
    $purchaseCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Hata durumlarını kontrol et
    $error = null;
    if ($purchaseCheck['freelancer_user_id'] == $userId) {
        $error = "You cannot purchase your own work!";
    } elseif ($purchaseCheck['balance'] < $purchaseCheck['fixed_price']) {
        $error = "Insufficient balance!";
    }

    if (!$error) {
        try {
            $db->beginTransaction();
        
            // Jobs tablosuna kayıt ekle
            $jobStmt = $db->prepare("
                INSERT INTO jobs (user_id, freelancer_id, title, description, requirements, 
                                category, budget, status, created_at)
                VALUES (:user_id, :freelancer_id, :title, :description, :requirements,
                        :category, :budget, 'IN_PROGRESS', CURRENT_TIMESTAMP)
            ");
        
            $jobStmt->execute([
                ':user_id' => $userId,
                ':freelancer_id' => $purchaseCheck['freelancer_id'],
                ':title' => $purchaseCheck['title'],
                ':description' => $purchaseCheck['description'],
                ':requirements' => $purchaseCheck['requirements'],
                ':category' => $purchaseCheck['category'],
                ':budget' => $purchaseCheck['fixed_price']
            ]);
        
            // Wallet'tan ücreti düş
            $walletStmt = $db->prepare("
                UPDATE wallet 
                SET balance = balance - :amount,
                    last_transaction_date = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");
        
            $walletStmt->execute([
                ':amount' => $purchaseCheck['fixed_price'],
                ':user_id' => $userId
            ]);
        
            // Transaction kaydı oluştur (status PENDING olarak)
            $transactionStmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_id,
                    sender_id,
                    receiver_id,
                    amount,
                    transaction_type,
                    status,
                    description,
                    created_at
                ) VALUES (
                    :transaction_id,
                    :sender_id,
                    :receiver_id,
                    :amount,
                    'PAYMENT',
                    'PENDING',
                    :description,
                    CURRENT_TIMESTAMP
                )
            ");
        
            $transactionId = mt_rand(10000000000, 99999999999);
        
            $transactionStmt->execute([
                ':transaction_id' => $transactionId,
                ':sender_id' => $userId,
                ':receiver_id' => $purchaseCheck['freelancer_user_id'],
                ':amount' => $purchaseCheck['fixed_price'],
                ':description' => 'Payment for work: ' . $purchaseCheck['title']
            ]);
        
            $db->commit();
            $success = "Purchase successful!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "An error occurred during purchase. Please try again.";
            error_log("Purchase Error: " . $e->getMessage());
        }
    }
}

function formatExperienceTime($days)
{
    if ($days < 1) {
        return "Just started";
    }

    $years = floor($days / 365);
    $months = floor(($days % 365) / 30);
    $weeks = floor(($days % 30) / 7);
    $remainingDays = $days % 7;

    $experience = [];

    if ($years > 0) {
        $experience[] = $years . ' ' . ($years == 1 ? 'year' : 'years');
    }
    if ($months > 0) {
        $experience[] = $months . ' ' . ($months == 1 ? 'month' : 'months');
    }
    if ($weeks > 0) {
        $experience[] = $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks');
    }
    if ($remainingDays > 0) {
        $experience[] = $remainingDays . ' ' . ($remainingDays == 1 ? 'day' : 'days');
    }

    return implode(', ', $experience);
}

// İş ilanı ve ilgili bilgileri getir
$stmt = $db->prepare("
    SELECT 
        w.*,
        wm.media_paths,
        f.user_id as freelancer_user_id,
        f.daily_rate as freelancer_rate,
        f.experience_time,
        u.username,
        u.full_name,
        ued.profile_photo_url,
        ued.basic_info,
        CASE 
            WHEN :logged_in_user IS NOT NULL THEN (
                SELECT balance 
                FROM wallet 
                WHERE user_id = :logged_in_user_balance
            )
            ELSE 0
        END as user_balance
    FROM works w
    LEFT JOIN works_media wm ON w.work_id = wm.work_id
    LEFT JOIN freelancers f ON w.freelancer_id = f.freelancer_id
    LEFT JOIN users u ON f.user_id = u.user_id
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    WHERE w.work_id = :work_id AND w.visibility = 1
");

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$stmt->bindParam(':work_id', $workId);
$stmt->bindParam(':logged_in_user', $userId);
$stmt->bindParam(':logged_in_user_balance', $userId);
$stmt->execute();
$work = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$work) {
    header('Location: /');
    exit;
}

// Parse basic_info JSON
$basicInfo = json_decode($work['basic_info'], true) ?? [];

// Freelancer'ın diğer işlerini getir - Bu sorgu aynı kalabilir
$stmt = $db->prepare("
    SELECT w.*, wm.media_paths
    FROM works w
    LEFT JOIN works_media wm ON w.work_id = wm.work_id
    WHERE w.freelancer_id = ? AND w.work_id != ? AND w.visibility = 1
    ORDER BY w.created_at DESC
    LIMIT 3
");
$stmt->execute([$work['freelancer_id'], $workId]);
$otherWorks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($work['title']); ?> - Work Details</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <div class="flex items-center gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/public/index.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Dashboard</a>
                    <a href="/auth/logout.php"
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
                <?php else: ?>
                    <a href="/views/auth/login.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Display error/success messages if they exist -->
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-8">
            <!-- Ana İçerik -->
            <div class="col-span-2">
                <!-- İlan Başlığı ve Fiyatlandırma -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($work['title']); ?></h1>
                    <div class="flex gap-4 text-lg mb-4">
                        <?php if ($work['daily_rate']): ?>
                            <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded">
                                Daily Rate: ₺<?php echo number_format($work['daily_rate'], 2); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($work['fixed_price']): ?>
                            <div class="bg-green-100 text-green-800 px-4 py-2 rounded">
                                Fixed Price: ₺<?php echo number_format($work['fixed_price'], 2); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Purchase Button -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" class="mt-4">
                            <button type="submit" name="purchase"
                                class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed"
                                <?php echo ($_SESSION['user_id'] === $work['freelancer_user_id'] || $work['user_balance'] < $work['fixed_price']) ? 'disabled' : ''; ?>>
                                <?php
                                if ($_SESSION['user_id'] === $work['freelancer_user_id']) {
                                    echo "You can't purchase your own work";
                                } elseif ($work['user_balance'] < $work['fixed_price']) {
                                    echo "Insufficient balance - Need ₺" . number_format($work['fixed_price'], 2);
                                } else {
                                    echo "Purchase for ₺" . number_format($work['fixed_price'], 2);
                                }
                                ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Etiketler -->
                    <?php if ($work['tags']): ?>
                        <div class="flex gap-2 mb-4">
                            <?php foreach (json_decode($work['tags'], true) as $tag): ?>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded text-sm">
                                    <?php echo htmlspecialchars($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Medya Galerisi -->
                <?php if (!empty($work['media_paths'])): ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-8">
                        <h2 class="text-xl font-bold mb-4">Gallery</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <?php
                            $mediaPaths = json_decode($work['media_paths'], true);
                            foreach ($mediaPaths as $type => $path):
                                if (strpos($type, 'image_') !== false):
                                    ?>
                                    <div class="relative pt-[56.25%]">
                                        <img src="/<?php echo htmlspecialchars($path); ?>" alt="Work image"
                                            class="absolute inset-0 w-full h-full object-cover rounded">
                                    </div>
                                    <?php
                                endif;
                            endforeach;

                            if (isset($mediaPaths['video'])):
                                ?>
                                <div class="relative pt-[56.25%] col-span-2">
                                    <video src="/<?php echo htmlspecialchars($mediaPaths['video']); ?>" controls
                                        class="absolute inset-0 w-full h-full rounded">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Açıklama ve Gereksinimler -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold mb-2">Description</h2>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($work['description'])); ?>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold mb-2">Requirements</h2>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($work['requirements'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yan Panel -->
            <div class="space-y-8">
                <!-- Freelancer Bilgileri -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="/public/<?php echo $work['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>"
                            alt="<?php echo htmlspecialchars($work['username']); ?>"
                            class="w-16 h-16 rounded-full object-cover">
                        <div>
                            <h3 class="font-bold text-lg">
                                <a href="/<?php echo htmlspecialchars($work['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($basicInfo['full_name'] ?? $work['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600">@<?php echo htmlspecialchars($work['username']); ?></p>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        <?php if (isset($basicInfo['location'])): ?>
                            <p>📍 <?php
                            echo htmlspecialchars(
                                implode(', ', array_filter([
                                    $basicInfo['location']['city'] ?? '',
                                    $basicInfo['location']['country'] ?? ''
                                ]))
                            );
                            ?></p>
                        <?php endif; ?>
                        
                        <p>⭐ <?php echo formatExperienceTime($work['experience_time']); ?> experience</p>
                        <p>💰 Base rate: ₺<?php echo number_format($work['freelancer_rate'], 2); ?>/day</p>

                        <?php if (isset($basicInfo['biography'])): ?>
                            <p class="text-gray-700 mt-3"><?php echo htmlspecialchars($basicInfo['biography']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($basicInfo['contact']) && isset($basicInfo['contact']['email'])): ?>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $work['freelancer_user_id']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($basicInfo['contact']['email']); ?>"
                               class="block w-full bg-blue-500 text-white text-center px-4 py-2 rounded hover:bg-blue-600">
                                Contact Freelancer
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Professional Info Section -->
                <?php
                $professionalProfile = json_decode($work['professional_profile'] ?? '{}', true);
                if (!empty($professionalProfile)):
                ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-bold text-lg mb-4">Professional Profile</h3>
                    <?php if (isset($professionalProfile['expertise_areas'])): ?>
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-2">Expertise</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($professionalProfile['expertise_areas'] as $area): ?>
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                        <?php echo htmlspecialchars($area); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($professionalProfile['summary'])): ?>
                        <p class="text-gray-600 text-sm">
                            <?php echo htmlspecialchars($professionalProfile['summary']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Diğer İşler -->
                <?php if (!empty($otherWorks)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-bold text-lg mb-4">Other Works by <?php echo htmlspecialchars($work['username']); ?>
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($otherWorks as $otherWork): ?>
                                <a href="/public/views/work.php?id=<?php echo $otherWork['work_id']; ?>"
                                    class="block hover:bg-gray-50 p-3 rounded">
                                    <h4 class="font-medium"><?php echo htmlspecialchars($otherWork['title']); ?></h4>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?php echo htmlspecialchars($otherWork['description']); ?>
                                    </p>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <?php echo date('M j, Y', strtotime($otherWork['created_at'])); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

</html>