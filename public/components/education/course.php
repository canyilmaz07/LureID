<?php
session_start();
require_once '../../../config/database.php';

// Database bağlantısı
try {
    $dbConfig = require '../../../config/database.php';
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

// Kurs ID'sini al
$courseId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$courseId) {
    header('Location: /');
    exit;
}

// Kurs detaylarını çek
$stmt = $db->prepare("
    SELECT 
        c.*,
        i.user_id as instructor_user_id,
        i.bio,
        i.expertise_areas,
        i.total_students,
        i.total_reviews,
        i.average_rating,
        u.username,
        u.full_name,
        u.email,
        ued.profile_photo_url,
        ued.basic_info,
        cc.name as category_name,
        sc.name as subcategory_name,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.course_id) as total_enrolled
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    LEFT JOIN course_categories cc ON c.category_id = cc.category_id
    LEFT JOIN course_categories sc ON c.subcategory_id = sc.category_id
    WHERE c.course_id = :course_id AND c.status = 'PUBLISHED'
");

$stmt->execute([':course_id' => $courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: /');
    exit;
}

// Kurs bölümlerini ve içeriklerini çek
$sectionsStmt = $db->prepare("
    SELECT 
        cs.*,
        (
            SELECT COUNT(*)
            FROM course_contents cc
            WHERE cc.section_id = cs.section_id
        ) as content_count,
        (
            SELECT SUM(duration)
            FROM course_contents cc
            WHERE cc.section_id = cs.section_id
        ) as total_duration
    FROM course_sections cs
    WHERE cs.course_id = ?
    ORDER BY cs.order_number
");
$sectionsStmt->execute([$courseId]);
$sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Her bölüm için içerikleri çek
foreach ($sections as &$section) {
    $contentsStmt = $db->prepare("
        SELECT *
        FROM course_contents
        WHERE section_id = ?
        ORDER BY order_number
    ");
    $contentsStmt->execute([$section['section_id']]);
    $section['contents'] = $contentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kursa kayıt olma fonksiyonu
if (isset($_POST['enroll']) && isset($_SESSION['user_id'])) {
    try {
        $db->beginTransaction();
        
        // Kullanıcının bakiyesini kontrol et
        $walletStmt = $db->prepare("SELECT balance FROM wallet WHERE user_id = ?");
        $walletStmt->execute([$_SESSION['user_id']]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet['balance'] < $course['price']) {
            throw new Exception("Insufficient balance");
        }
        
        // Transaction oluştur
        $transactionStmt = $db->prepare("
            INSERT INTO transactions (sender_id, receiver_id, amount, transaction_type, status, description)
            VALUES (?, ?, ?, 'PAYMENT', 'COMPLETED', ?)
        ");
        $transactionStmt->execute([
            $_SESSION['user_id'],
            $course['instructor_user_id'],
            $course['price'],
            "Course enrollment: " . $course['title']
        ]);
        $transactionId = $db->lastInsertId();
        
        // Kayıt oluştur
        $enrollStmt = $db->prepare("
            INSERT INTO course_enrollments (course_id, user_id, progress_data, transaction_id)
            VALUES (?, ?, '{}', ?)
        ");
        $enrollStmt->execute([$courseId, $_SESSION['user_id'], $transactionId]);
        
        // Bakiye güncelle
        $updateWalletStmt = $db->prepare("
            UPDATE wallet 
            SET balance = balance - ?, last_transaction_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $updateWalletStmt->execute([$course['price'], $_SESSION['user_id']]);
        
        $db->commit();
        header("Location: /public/views/course.php?id=" . $courseId);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - LureID</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <!-- Header -->
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Kurs Başlık Bölümü -->
        <div class="bg-gray-900 text-white py-12 px-8 rounded-t-2xl">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <nav class="flex mb-4 text-sm">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <?php echo htmlspecialchars($course['category_name']); ?>
                        </a>
                        <span class="mx-2 text-gray-500">/</span>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <?php echo htmlspecialchars($course['subcategory_name']); ?>
                        </a>
                    </nav>
                    
                    <h1 class="text-4xl font-bold mb-4">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h1>
                    
                    <p class="text-xl text-gray-300 mb-6">
                        <?php echo htmlspecialchars($course['description']); ?>
                    </p>
                    
                    <!-- Kurs İstatistikleri -->
                    <div class="flex items-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-yellow-400">★</span>
                            <span><?php echo number_format($course['rating'], 1); ?></span>
                            <span class="text-gray-400">(<?php echo $course['total_reviews']; ?> ratings)</span>
                        </div>
                        <div class="text-gray-300">
                            <?php echo number_format($course['total_enrolled']); ?> öğrenci
                        </div>
                    </div>
                </div>
                
                <!-- Kurs Video Önizleme -->
                <div class="relative">
                    <?php if ($course['preview_video_url']): ?>
                        <video 
                            src="/public/components/education/uploads/videos/<?php echo htmlspecialchars($course['preview_video_url']); ?>"
                            class="w-full rounded-lg shadow-lg"
                            controls
                        ></video>
                    <?php elseif ($course['thumbnail_url']): ?>
                        <img 
                            src="/public/components/education/uploads/images/<?php echo htmlspecialchars($course['thumbnail_url']); ?>"
                            alt="Course thumbnail"
                            class="w-full rounded-lg shadow-lg"
                        >
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ana İçerik Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
            <!-- Sol Kolon: Kurs İçeriği -->
            <div class="lg:col-span-2">
                <!-- Öğrenilecekler -->
                <?php if ($course['what_will_learn']): ?>
                    <section class="bg-white rounded-xl p-6 mb-8">
                        <h2 class="text-2xl font-bold mb-4">Öğrenecekleriniz</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            $learningPoints = explode("\n", $course['what_will_learn']);
                            foreach ($learningPoints as $point): 
                            ?>
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-green-500 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span><?php echo htmlspecialchars($point); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- Kurs İçeriği -->
                <section class="bg-white rounded-xl p-6">
                    <h2 class="text-2xl font-bold mb-4">Kurs İçeriği</h2>
                    <div class="space-y-4">
                        <?php foreach ($sections as $section): ?>
                            <div class="border rounded-lg">
                                <div class="flex justify-between items-center p-4 cursor-pointer" 
                                     onclick="toggleSection(<?php echo $section['section_id']; ?>)">
                                    <h3 class="font-semibold">
                                        <?php echo htmlspecialchars($section['title']); ?>
                                    </h3>
                                    <div class="text-sm text-gray-500">
                                        <?php echo count($section['contents']); ?> ders • 
                                        <?php echo floor($section['total_duration'] / 60); ?> dakika
                                    </div>
                                </div>
                                
                                <div id="section-<?php echo $section['section_id']; ?>" class="hidden">
                                    <div class="border-t px-4 py-2">
                                        <?php foreach ($section['contents'] as $content): ?>
                                            <div class="flex items-center gap-3 py-2">
                                                <?php if ($content['content_type'] === 'VIDEO'): ?>
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                <?php endif; ?>
                                                
                                                <span class="flex-1">
                                                    <?php echo htmlspecialchars($content['title']); ?>
                                                </span>
                                                
                                                <?php if ($content['duration']): ?>
                                                    <span class="text-sm text-gray-500">
                                                        <?php echo floor($content['duration'] / 60); ?>:<?php echo str_pad($content['duration'] % 60, 2, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            
            <!-- Sağ Kolon: Satın Alma & Eğitmen Bilgisi -->
            <div>
                <!-- Satın Alma Kartı -->
                <div class="bg-white rounded-xl p-6 shadow-lg sticky top-8">
                    <div class="text-center mb-6">
                        <h3 class="text-4xl font-bold mb-2">₺<?php echo number_format($course['price'], 2); ?></h3>
                    </div>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        // Kullanıcının kursa kayıtlı olup olmadığını kontrol et
                        $enrollmentCheck = $db->prepare("
                            SELECT * FROM course_enrollments 
                            WHERE course_id = ? AND user_id = ?
                        ");
                        $enrollmentCheck->execute([$courseId, $_SESSION['user_id']]);
                        $isEnrolled = $enrollmentCheck->fetch();
                        ?>
                        
                        <?php if ($isEnrolled): ?>
                            <a href="/public/views/learn.php?course=<?php echo $courseId; ?>" 
                            class="block w-full bg-green-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                                Öğrenmeye Devam Et
                            </a>
                        <?php else: ?>
                            <form method="POST" class="mb-4">
                                <button type="submit" name="enroll" 
                                        class="w-full bg-blue-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                                    Şimdi Kaydol
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/auth/login.php" 
                           class="block w-full bg-gray-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition">
                            Kaydolmak için Giriş Yapın
                        </a>
                    <?php endif; ?>
                    
                    <!-- Kurs Özellikleri -->
                    <div class="mt-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>
                                <?php 
                                $totalDuration = 0;
                                foreach ($sections as $section) {
                                    $totalDuration += $section['total_duration'];
                                }
                                echo floor($totalDuration / 60) . " toplam saat";
                                ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <span>
                                <?php 
                                $totalLectures = 0;
                                foreach ($sections as $section) {
                                    $totalLectures += count($section['contents']);
                                }
                                echo $totalLectures . " ders";
                                ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Ömür boyu tam erişim</span>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                            <span>Tamamlama sertifikası</span>
                        </div>
                    </div>
                </div>
                
                <!-- Eğitmen Bilgisi -->
                <div class="bg-white rounded-xl p-6 mt-8">
                    <h2 class="text-xl font-semibold mb-4">Eğitmen</h2>
                    <div class="flex items-center gap-4 mb-4">
                        <img src="/public/<?php echo $course['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($course['username']); ?>"
                             class="w-16 h-16 rounded-full object-cover">
                        <div>
                            <h3 class="font-semibold text-lg">
                                <a href="/<?php echo htmlspecialchars($course['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($course['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600">@<?php echo htmlspecialchars($course['username']); ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span><?php echo number_format($course['total_students']); ?> öğrenci</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <span><?php echo number_format($course['average_rating'], 1); ?> eğitmen puanı</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                            <span><?php echo number_format($course['total_reviews']); ?> değerlendirme</span>
                        </div>
                    </div>
                    
                    <?php if ($course['bio']): ?>
                        <p class="mt-4 text-gray-700"><?php echo htmlspecialchars($course['bio']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSection(sectionId) {
            const content = document.getElementById(`section-${sectionId}`);
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
            } else {
                content.classList.add('hidden');
            }
        }
    </script>
</body>
</html>