<?php
session_start();
require_once '../../../config/database.php';

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

// Kategori ID'sini al 
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : null;

// Kategori bilgilerini çek
$categoryStmt = $db->prepare("
    SELECT 
        c1.*, 
        c2.name as parent_name 
    FROM course_categories c1 
    LEFT JOIN course_categories c2 ON c1.parent_id = c2.category_id 
    WHERE c1.category_id = ?
");
$categoryStmt->execute([$categoryId]);
$category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: /');
    exit;
}

// Kategorideki kursları çek
$coursesStmt = $db->prepare("
    SELECT 
        c.*,
        i.user_id as instructor_user_id,
        u.username as instructor_username,
        u.full_name as instructor_name,
        ued.profile_photo_url as instructor_photo,
        JSON_LENGTH(c.enrolled_students) as student_count
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.instructor_id
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    WHERE (c.category_id = ? OR c.subcategory_id = ?)
    AND c.status = 'PUBLISHED'
    ORDER BY c.rating DESC, student_count DESC
");
$coursesStmt->execute([$categoryId, $categoryId]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// Alt kategorileri çek
$subcategoriesStmt = $db->prepare("
    SELECT * FROM course_categories 
    WHERE parent_id = ?
    ORDER BY name
");
$subcategoriesStmt->execute([$categoryId]);
$subcategories = $subcategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> Courses - LureID</title>
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
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Dashboard</a>
                    <a href="/auth/logout.php"
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">Logout</a>
                <?php else: ?>
                    <a href="/auth/login.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Kategori Başlığı -->
        <div class="mb-8">
            <?php if ($category['parent_id']): ?>
                <p class="text-gray-500 mb-2">
                    <a href="/public/views/categories.php?id=<?php echo $category['parent_id']; ?>" class="hover:underline">
                        <?php echo htmlspecialchars($category['parent_name']); ?>
                    </a>
                </p>
            <?php endif; ?>
            <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h1>
        </div>

        <!-- Alt Kategoriler -->
        <?php if (!empty($subcategories)): ?>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Subcategories</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($subcategories as $sub): ?>
                        <a href="/public/views/categories.php?id=<?php echo $sub['category_id']; ?>"
                            class="p-4 bg-white rounded-lg shadow hover:shadow-md transition flex items-center gap-3">
                            <span class="text-lg"><?php echo htmlspecialchars($sub['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Kurslar -->
        <?php if (!empty($courses)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($courses as $course): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                        <!-- Kurs Resmi -->
                        <?php if ($course['thumbnail_url']): ?>
                            <img src="/public/components/education/uploads/images/<?php echo htmlspecialchars($course['thumbnail_url']); ?>"
                                alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-48 object-cover">
                        <?php endif; ?>

                        <div class="p-6">
                            <h2 class="text-xl font-bold mb-2">
                                <a href="/public/views/course.php?id=<?php echo $course['course_id']; ?>"
                                    class="hover:text-blue-600 transition">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </a>
                            </h2>

                            <!-- Eğitmen Bilgisi -->
                            <div class="flex items-center gap-3 mb-4">
                                <a href="/public/profile/view.php?username=<?php echo urlencode($course['instructor_username']); ?>"
                                    class="flex items-center gap-2 text-gray-600 hover:text-blue-600">
                                    <img src="/public/<?php echo $course['instructor_photo'] ?: 'profile/avatars/default.jpg'; ?>"
                                        alt="<?php echo htmlspecialchars($course['instructor_name']); ?>"
                                        class="w-8 h-8 rounded-full object-cover">
                                    <span><?php echo htmlspecialchars($course['instructor_name']); ?></span>
                                </a>
                            </div>

                            <!-- Kurs Meta Bilgileri -->
                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    <span><?php echo $course['student_count']; ?> students</span>
                                </div>

                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    <span><?php echo number_format($course['rating'], 1); ?></span>
                                </div>
                            </div>

                            <!-- Fiyat -->
                            <div class="flex justify-between items-center">
                                <span class="text-2xl font-bold">
                                    ₺<?php echo number_format($course['price'], 2); ?>
                                </span>
                                <a href="course.php?id=<?php echo $course['course_id']; ?>"
                                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                    İncele
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-medium text-gray-900 mb-2">Bu kategoride henüz kurs bulunmuyor</h3>
                <p class="text-gray-500 max-w-md mx-auto">
                    Bu kategoride şu an için yayınlanmış bir kurs yok.
                    Daha sonra tekrar kontrol edebilir veya başka kategorilere göz atabilirsiniz.
                </p>
                <a href="/" class="inline-flex items-center mt-6 text-blue-600 hover:text-blue-500">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Ana Sayfaya Dön
                </a>
            </div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="text-center py-12">
                <h3 class="text-xl text-gray-600">No courses found in this category yet.</h3>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>