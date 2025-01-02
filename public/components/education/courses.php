<?php
session_start();
require_once '../../../config/database.php';

try {
    $dbConfig = require '../../../config/database.php';
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Kullanıcının eğitmen olup olmadığını kontrol et
    $isInstructor = false;
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM instructors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $isInstructor = $stmt->fetchColumn() > 0;
    }

    // Ana kategori ve alt kategorileri getir
    $categoryQuery = "
    SELECT c1.category_id, c1.name as main_category, 
           c2.category_id as sub_id, c2.name as sub_category
    FROM course_categories c1
    LEFT JOIN course_categories c2 ON c2.parent_id = c1.category_id
    WHERE c1.parent_id IS NULL
    ORDER BY c1.name, c2.name
";
    $categoryResult = $conn->query($categoryQuery);
    $categories = [];
    while ($row = $categoryResult->fetch()) {
        if (!isset($categories[$row['main_category']])) {
            $categories[$row['main_category']] = [
                'id' => $row['category_id'],
                'subcategories' => []
            ];
        }
        if ($row['sub_category']) {
            $categories[$row['main_category']]['subcategories'][] = [
                'id' => $row['sub_id'],
                'name' => $row['sub_category']
            ];
        }
    }

    // Öne çıkan kursları getir
    $featuredQuery = "
        SELECT 
            c.course_id,
            c.title,
            c.description,
            c.price,
            c.thumbnail_url,
            c.rating,
            JSON_LENGTH(c.enrolled_students) as student_count,
            i.instructor_id,
            u.username as instructor_name,
            cc.name as category_name,
            ued.profile_photo_url as instructor_photo
        FROM courses c
        JOIN instructors i ON c.instructor_id = i.instructor_id
        JOIN users u ON i.user_id = u.user_id
        JOIN course_categories cc ON c.category_id = cc.category_id
        JOIN user_extended_details ued ON u.user_id = ued.user_id
        WHERE c.status = 'PUBLISHED'
        ORDER BY c.rating DESC, student_count DESC
        LIMIT 8
    ";
    $featuredCourses = $conn->query($featuredQuery)->fetchAll();

    // Popüler eğitmenleri getir
    $instructorsQuery = "
        SELECT 
            i.instructor_id,
            u.username,
            ued.profile_photo_url,
            i.total_students,
            i.average_rating,
            COUNT(c.course_id) as course_count
        FROM instructors i
        JOIN users u ON i.user_id = u.user_id
        JOIN user_extended_details ued ON u.user_id = ued.user_id
        LEFT JOIN courses c ON i.instructor_id = c.instructor_id
        GROUP BY i.instructor_id
        ORDER BY i.total_students DESC
        LIMIT 4
    ";
    $popularInstructors = $conn->query($instructorsQuery)->fetchAll();

    // Son forum aktivitelerini getir
    $forumQuery = "
        SELECT 
            cf.post_id,
            cf.title,
            cf.content,
            cf.created_at,
            u.username,
            c.title as course_title
        FROM course_forum cf
        JOIN users u ON cf.user_id = u.user_id
        JOIN courses c ON cf.course_id = c.course_id
        WHERE cf.parent_id IS NULL
        ORDER BY cf.created_at DESC
        LIMIT 5
    ";
    $recentForumPosts = $conn->query($forumQuery)->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lureid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* Common Styles */
        :root {
            --primary-color: #0d6efd;
            --border-color: #eee;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-size: 16px;
            font-weight: 600;
        }

        /* Navbar Styles */
        .navbar {
            padding: 0;
            background-color: #fff;
            box-shadow: var(--shadow);
            flex-direction: column;
        }

        .navbar .container {
            max-width: 100%;
            padding: 0 20px;
        }

        /* Top Navigation */
        .top-nav {
            width: 100%;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .top-nav .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 20px;
            margin: 0;
            padding: 0;
        }

        /* Search Area */
        .search-area {
            flex: 1;
            max-width: 400px;
            margin: 0 20px;
        }

        .search-area .form-control {
            width: 100%;
            height: 38px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        /* Auth Buttons */
        .auth-buttons .btn {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Categories Navigation */
        .bottom-nav {
            width: 100%;
            padding: 10px 0;
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
        }

        .categories-nav {
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .category-item {
            position: relative;
        }

        .category-link {
            color: #333;
            text-decoration: none;
            padding: 5px 0;
            display: block;
        }

        .category-link:hover {
            color: var(--primary-color);
        }

        /* Dropdown Menu */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #fff;
            min-width: 200px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px 0;
            z-index: 1000;
        }

        .category-item:hover .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 8px 16px;
            color: #333;
            text-decoration: none;
            font-size: 12px;
        }

        .dropdown-menu a:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        /* Course Cards */
        .course-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .course-card:hover {
            transform: translateY(-5px);
        }

        .instructor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .hero {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        /* Instructor Cards */
        .instructor-card {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Forum Section */
        .forum-post {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .forum-post:last-child {
            border-bottom: none;
        }

        .post-meta {
            color: #666;
            font-size: 11px;
            margin: 5px 0;
        }

        .post-preview {
            color: #333;
            margin: 10px 0;
        }

        .post-date {
            color: #999;
            font-size: 11px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .navbar {
                padding: 0;
            }

            .top-nav .container {
                flex-wrap: wrap;
                gap: 10px;
            }

            .search-area {
                order: 3;
                max-width: 100%;
                margin: 10px 0;
            }

            .bottom-nav {
                overflow-x: auto;
            }

            .categories-nav {
                padding: 0 10px;
            }
        }

        .course-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .card-img-top {
            height: 160px;
            object-fit: cover;
        }

        .card-title {
            font-size: 1rem;
            line-height: 1.4;
            height: 2.8em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <!-- Üst Menü -->
        <div class="top-nav">
            <div class="container">
                <a class="navbar-brand" href="/">Lureid</a>

                <div class="search-area">
                    <input class="form-control" type="search" id="searchInput"
                        placeholder="Kurs, eğitmen veya konu ara...">
                </div>

                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($isInstructor): ?>
                            <a href="instructor/dashboard.php" class="btn btn-outline-primary">Eğitmen Paneli</a>
                        <?php else: ?>
                            <a href="become-instructor.php" class="btn btn-outline-primary">Eğitmen Ol</a>
                        <?php endif; ?>
                        <a href="student/my-courses.php" class="btn btn-outline-secondary">Kurslarım</a>
                        <a href="/auth/logout.php" class="btn btn-danger">Çıkış</a>
                    <?php else: ?>
                        <a href="/auth/login.php" class="btn btn-outline-primary">Giriş</a>
                        <a href="/auth/register.php" class="btn btn-primary">Kayıt Ol</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kategori menüsü HTML'i -->
        <div class="bottom-nav">
            <div class="container">
                <ul class="categories-nav">
                    <?php foreach ($categories as $mainCat => $data): ?>
                        <li class="category-item">
                            <a class="category-link" href="categories.php?id=<?= $data['id'] ?>">
                                <?= htmlspecialchars($mainCat) ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php foreach ($data['subcategories'] as $sub): ?>
                                    <li>
                                        <a class="dropdown-item" href="categories.php?id=<?= $sub['id'] ?>">
                                            <?= htmlspecialchars($sub['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="display-4 mb-4">Yeni Nesil Eğitim Platformu</h1>
            <p class="lead mb-4">Video eğitimler, pratik örnekler ve topluluk desteği ile öğren</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="/courses" class="btn btn-light btn-lg">Kurslara Göz At</a>
                <a href="/how-it-works" class="btn btn-outline-light btn-lg">Nasıl Çalışır?</a>
            </div>
        </div>
    </section>

    <!-- Featured Courses -->
    <section class="py-5">
        <div class="container">
            <h2 class="mb-4">Öne Çıkan Kurslar</h2>
            <div class="row g-4">
                <?php foreach ($featuredCourses as $course): ?>
                    <div class="col-md-3">
                        <a href="course.php?id=<?= $course['course_id'] ?>" class="text-decoration-none">
                            <div class="card course-card h-100">
                                <img src="/public/components/education/uploads/images/<?= htmlspecialchars($course['thumbnail_url']) ?>"
                                    class="card-img-top" alt="<?= htmlspecialchars($course['title']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title text-dark"><?= htmlspecialchars($course['title']) ?></h5>
                                    <div class="d-flex align-items-center mb-2">
                                        <a href="/public/profile/view.php?username=<?= urlencode($course['instructor_name']) ?>"
                                            class="d-flex align-items-center text-decoration-none"
                                            onclick="event.stopPropagation()">
                                            <img src="/public/<?= htmlspecialchars($course['instructor_photo']) ?>"
                                                class="instructor-avatar me-2">
                                            <span
                                                class="text-muted"><?= htmlspecialchars($course['instructor_name']) ?></span>
                                        </a>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark">
                                            <?= htmlspecialchars($course['category_name']) ?>
                                        </span>
                                        <span class="text-warning">⭐ <?= number_format($course['rating'], 1) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="text-primary fw-bold">
                                            <?= $course['price'] > 0 ? '₺' . number_format($course['price'], 2) : 'Ücretsiz' ?>
                                        </span>
                                        <span class="text-muted small">
                                            <?= $course['student_count'] ?> öğrenci
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Popular Instructors -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="mb-4">Popüler Eğitmenler</h2>
            <div class="row">
                <?php foreach ($popularInstructors as $instructor): ?>
                    <div class="col-md-3">
                        <a href="/public/profile/view.php?username=<?= urlencode($instructor['username']) ?>"
                            class="text-decoration-none">
                            <div class="instructor-card hover:shadow-lg transition-shadow">
                                <img src="/public/<?= htmlspecialchars($instructor['profile_photo_url']) ?>"
                                    alt="<?= htmlspecialchars($instructor['username']) ?>" class="instructor-avatar">
                                <h3 class="mt-2 text-dark"><?= htmlspecialchars($instructor['username']) ?></h3>
                                <div class="stats text-gray-600">
                                    <div><?= $instructor['course_count'] ?> Kurs</div>
                                    <div><?= $instructor['total_students'] ?> Öğrenci</div>
                                    <div>⭐ <?= number_format($instructor['average_rating'], 1) ?></div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Recent Forum Activity -->
    <section class="py-5">
        <div class="container">
            <h2 class="mb-4">Son Forum Aktiviteleri</h2>
            <div class="row">
                <?php foreach ($recentForumPosts as $post): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title"><?= htmlspecialchars($post['title']) ?></h4>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <?= htmlspecialchars($post['username']) ?> tarafından
                                        <?= htmlspecialchars($post['course_title']) ?> kursunda
                                    </small>
                                </p>
                                <p class="card-text">
                                    <?= mb_substr(strip_tags($post['content']), 0, 100) ?>...
                                </p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('input', function () {
            if (this.value.length >= 3) {
                fetch(`/api/search?q=${encodeURIComponent(this.value)}`)
                    .then(response => response.json())
                    .then(results => {
                        // Display search results
                    });
            }
        });
    </script>
</body>

</html>