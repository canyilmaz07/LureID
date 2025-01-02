<?php
// dashboard.php
session_start();
require_once '../../../../config/database.php';

// Eğitmen kontrolü ve yetkilendirme
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$showCourseCreatedAlert = false;
if (isset($_SESSION['course_created'])) {
    $showCourseCreatedAlert = true;
    unset($_SESSION['course_created']);
}

try {
    $dbConfig = require '../../../../config/database.php';
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Eğitmen kontrolü
    $stmt = $conn->prepare("SELECT * FROM instructors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instructor) {
        header('Location: /');
        exit;
    }

    // Eğitmenin kurslarını getir
    $coursesQuery = "
        SELECT 
            c.*,
            JSON_LENGTH(c.enrolled_students) as total_students,
            cc.name as category_name
        FROM courses c
        JOIN course_categories cc ON c.category_id = cc.category_id
        WHERE c.instructor_id = ?
        ORDER BY c.created_at DESC
    ";
    $stmt = $conn->prepare($coursesQuery);
    $stmt->execute([$instructor['instructor_id']]);
    $courses = $stmt->fetchAll();

    // Son forum aktivitelerini getir
    $forumQuery = "
        SELECT 
            cf.*,
            u.username,
            c.title as course_title
        FROM course_forum cf
        JOIN users u ON cf.user_id = u.user_id
        JOIN courses c ON cf.course_id = c.course_id
        WHERE c.instructor_id = ?
        ORDER BY cf.created_at DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($forumQuery);
    $stmt->execute([$instructor['instructor_id']]);
    $forumPosts = $stmt->fetchAll();

    // Toplam gelir ve istatistikleri hesapla
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ce.user_id) as total_unique_students,
            (SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'PUBLISHED') as total_active_courses,
            COUNT(DISTINCT cf.post_id) as total_forum_posts,
            COUNT(DISTINCT cr.review_id) as total_reviews,
            COALESCE(AVG(cr.rating), 0) as average_rating
        FROM courses c
        LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
        LEFT JOIN course_forum cf ON c.course_id = cf.course_id
        LEFT JOIN course_reviews cr ON c.course_id = cr.course_id
        WHERE c.instructor_id = ?
    ";
    $stmt = $conn->prepare($statsQuery);
    $stmt->execute([$instructor['instructor_id'], $instructor['instructor_id']]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitmen Paneli - Eğitim Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
            --primary-color: #c3ff00;
            --primary-dark: #9ec700;
            --primary-light: #d4ff33;
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            --bg-light: #f8f9fc;
            --bg-dark: #2c2c2c;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            margin: 0;
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #121212;
            padding-top: 0;
            z-index: 100;
            transition: all 0.3s;
        }

        .sidebar .logo {
            padding: 20px;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar .back-button {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--text-light);
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .sidebar .back-button:hover {
            background: rgba(255,255,255,0.1);
            color: var(--primary-color);
        }

        .sidebar .back-button i {
            margin-right: 10px;
        }

        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar nav ul li {
            margin: 4px 16px;
        }

        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .sidebar nav ul li a:hover {
            background-color: var(--primary-color);
            color: var(--text-dark);
        }

        .sidebar nav ul li a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .dashboard-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            padding-top: calc(var(--header-height) + 30px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .stat-card p {
            color: var(--text-dark);
            font-size: 2rem;
            font-weight: 600;
            margin: 15px 0 0 0;
        }

        /* Course Table */
        .courses-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .courses-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .courses-table th, 
        .courses-table td {
            padding: 18px;
            text-align: left;
            border-bottom: 1px solid #eef0f7;
        }

        .courses-table th {
            background-color: var(--primary-color);
            color: var(--text-dark);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .courses-table tr:hover {
            background-color: #f8f9fc;
        }

        .courses-table td a {
            color: var(--text-dark);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            margin-right: 8px;
            background-color: var(--primary-color);
            transition: all 0.3s;
        }

        .courses-table td a:hover {
            background-color: var(--primary-dark);
        }

        /* Forum Activity */
        .forum-activity {
            display: grid;
            gap: 25px;
        }

        .forum-post {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .forum-post:hover {
            transform: translateY(-3px);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .post-header h4 {
            margin: 0;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .course-name {
            background-color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .post-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
        }

        .post-meta span {
            display: flex;
            align-items: center;
        }

        .post-meta i {
            margin-right: 5px;
        }

        .post-content {
            color: var(--text-dark);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .view-post {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .view-post:hover {
            background-color: var(--primary-dark);
            color: var(--text-dark);
        }

        .view-post i {
            margin-left: 8px;
        }

        /* Create Course Section */
        .course-creation-steps {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .create-course-btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .create-course-btn i {
            margin-right: 8px;
        }

        .create-course-btn:hover {
            background-color: var(--primary-dark);
            color: var(--text-dark);
            transform: translateY(-2px);
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .step {
            text-align: center;
            padding: 30px;
            background-color: var(--bg-light);
            border-radius: 12px;
            transition: all 0.3s;
        }

        .step:hover {
            transform: translateY(-5px);
            background-color: var(--primary-color);
        }

        .step i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .step:hover i {
            color: var(--text-dark);
        }

        .step h4 {
            color: var(--text-dark);
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }

        .step p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }

        /* Dashboard Section Headers */
        .dashboard-section {
            margin-bottom: 40px;
        }

        .dashboard-section h2 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .dashboard-section h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .dashboard-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>

<body>
    <?php if ($showCourseCreatedAlert): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i> Kurs başarıyla oluşturuldu! 
        </div>
    <?php endif; ?>

    <div class="instructor-dashboard">
        <aside class="sidebar">
            <div class="logo">
                Lureid
            </div>
            <a href="../courses.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
            <nav>
                <ul>
                    <li><a href="#overview"><i class="fas fa-home"></i> Genel Bakış</a></li>
                    <li><a href="#courses"><i class="fas fa-book"></i> Kurslarım</a></li>
                    <li><a href="#create-course"><i class="fas fa-plus-circle"></i> Yeni Kurs</a></li>
                    <li><a href="#forum"><i class="fas fa-comments"></i> Forum</a></li>
                    <li><a href="#analytics"><i class="fas fa-chart-line"></i> Analitik</a></li>
                </ul>
            </nav>
        </aside>

        <main class="dashboard-content">
            <!-- Genel Bakış -->
            <section id="overview" class="dashboard-section">
                <h2><i class="fas fa-tachometer-alt"></i> Genel Bakış</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><i class="fas fa-users"></i> Toplam Öğrenci</h3>
                        <p><?= number_format($stats['total_unique_students']) ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-book-open"></i> Aktif Kurslar</h3>
                        <p><?= number_format($stats['total_active_courses']) ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-star"></i> Ortalama Puan</h3>
                        <p><?= number_format($stats['average_rating'], 1) ?> ⭐</p>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-comments"></i> Forum Aktiviteleri</h3>
                        <p><?= number_format($stats['total_forum_posts']) ?></p>
                    </div>
                </div>
            </section>

            <!-- Kurslar -->
            <section id="courses" class="dashboard-section">
                <h2><i class="fas fa-graduation-cap"></i> Kurslarım</h2>
                <div class="courses-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Kurs Adı</th>
                                <th>Kategori</th>
                                <th>Öğrenci Sayısı</th>
                                <th>Puan</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['title']) ?></td>
                                    <td><i class="fas fa-folder"></i> <?= htmlspecialchars($course['category_name']) ?></td>
                                    <td><i class="fas fa-user-graduate"></i> <?= $course['total_students'] ?></td>
                                    <td><i class="fas fa-star"></i> <?= number_format($course['rating'], 1) ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower($course['status']) ?>">
                                            <?= $course['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="courses/edit.php?id=<?= $course['course_id'] ?>">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </a>
                                        <a href="courses/content.php?id=<?= $course['course_id'] ?>">
                                            <i class="fas fa-file-alt"></i> İçerik
                                        </a>
                                        <a href="courses/analytics.php?id=<?= $course['course_id'] ?>">
                                            <i class="fas fa-chart-line"></i> Analitik
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Yeni Kurs Oluştur -->
            <section id="create-course" class="dashboard-section">
                <h2><i class="fas fa-plus-circle"></i> Yeni Kurs Oluştur</h2>
                <div class="course-creation-steps">
                    <a href="courses/create.php" class="create-course-btn">
                        <i class="fas fa-plus"></i> Yeni Kurs Oluştur
                    </a>
                    <div class="steps">
                        <div class="step">
                            <i class="fas fa-info-circle"></i>
                            <h4>1. Kurs Bilgileri</h4>
                            <p>Temel kurs bilgilerini girin</p>
                        </div>
                        <div class="step">
                            <i class="fas fa-upload"></i>
                            <h4>2. İçerik Yükleme</h4>
                            <p>Video ve dökümanları yükleyin</p>
                        </div>
                        <div class="step">
                            <i class="fas fa-tags"></i>
                            <h4>3. Fiyatlandırma</h4>
                            <p>Kurs fiyatını belirleyin</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Forum Aktiviteleri -->
            <section id="forum" class="dashboard-section">
                <h2><i class="fas fa-comments"></i> Son Forum Aktiviteleri</h2>
                <div class="forum-activity">
                    <?php foreach ($forumPosts as $post): ?>
                        <div class="forum-post">
                            <div class="post-header">
                                <h4><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($post['title']) ?></h4>
                                <span class="course-name">
                                    <i class="fas fa-book"></i> <?= htmlspecialchars($post['course_title']) ?>
                                </span>
                            </div>
                            <div class="post-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($post['username']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></span>
                            </div>
                            <div class="post-content">
                                <?= nl2br(htmlspecialchars(mb_substr($post['content'], 0, 200))) ?>...
                            </div>
                            <a href="forum/post.php?id=<?= $post['post_id'] ?>" class="view-post">
                                Görüntüle ve Yanıtla <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Analitik -->
            <section id="analytics" class="dashboard-section">
                <h2><i class="fas fa-chart-line"></i> Kurs Analitiği</h2>
                <div class="analytics-overview">
                    <div class="chart-container">
                        <!-- Öğrenci kayıt grafiği -->
                    </div>
                    <div class="chart-container">
                        <!-- Kurs tamamlama oranları -->
                    </div>
                    <div class="chart-container">
                        <!-- Öğrenci etkileşim metrikleri -->
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Dashboard için gerekli JavaScript kodları
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar navigasyon
            const navLinks = document.querySelectorAll('.sidebar a');
            navLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    const targetId = this.getAttribute('href').substring(1);
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) {
                        e.preventDefault();
                        targetSection.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>

</html>