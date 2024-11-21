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

function formatExperienceTime($days) {
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
        up.profile_photo_url,
        up.city,
        up.country
    FROM works w
    LEFT JOIN works_media wm ON w.work_id = wm.work_id
    LEFT JOIN freelancers f ON w.freelancer_id = f.freelancer_id
    LEFT JOIN users u ON f.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE w.work_id = ? AND w.visibility = 1
");
$stmt->execute([$workId]);
$work = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$work) {
    header('Location: /');
    exit;
}

// Freelancer'ın diğer işlerini getir
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
                    <a href="/public/index.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Dashboard</a>
                    <a href="/public/views/auth/logout.php" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
                <?php else: ?>
                    <a href="/public/views/auth/login.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                                    <img 
                                        src="/<?php echo htmlspecialchars($path); ?>" 
                                        alt="Work image"
                                        class="absolute inset-0 w-full h-full object-cover rounded"
                                    >
                                </div>
                            <?php 
                                endif;
                            endforeach;
                            
                            if (isset($mediaPaths['video'])):
                            ?>
                                <div class="relative pt-[56.25%] col-span-2">
                                    <video 
                                        src="/<?php echo htmlspecialchars($mediaPaths['video']); ?>" 
                                        controls
                                        class="absolute inset-0 w-full h-full rounded"
                                    >
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
                        <img 
                            src="/public/<?php echo $work['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>" 
                            alt="<?php echo htmlspecialchars($work['username']); ?>"
                            class="w-16 h-16 rounded-full object-cover"
                        >
                        <div>
                            <h3 class="font-bold text-lg">
                                <a href="/<?php echo htmlspecialchars($work['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($work['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600">@<?php echo htmlspecialchars($work['username']); ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        <?php if ($work['city'] || $work['country']): ?>
                            <p>📍 <?php 
                                echo htmlspecialchars(
                                    implode(', ', array_filter([$work['city'], $work['country']]))
                                ); 
                            ?></p>
                        <?php endif; ?>
                        <p>⭐ <?php echo formatExperienceTime($work['experience_time']); ?> experience</p>
                        <p>💰 Base rate: ₺<?php echo number_format($work['freelancer_rate'], 2); ?>/day</p>
                    </div>

                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $work['freelancer_user_id']): ?>
                        <a href="mailto:contact@example.com" class="block w-full bg-blue-500 text-white text-center px-4 py-2 rounded hover:bg-blue-600">
                            Contact Freelancer
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Diğer İşler -->
                <?php if (!empty($otherWorks)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-bold text-lg mb-4">Other Works by <?php echo htmlspecialchars($work['username']); ?></h3>
                        <div class="space-y-4">
                            <?php foreach ($otherWorks as $otherWork): ?>
                                <a href="/public/views/work.php?id=<?php echo $otherWork['work_id']; ?>" class="block hover:bg-gray-50 p-3 rounded">
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