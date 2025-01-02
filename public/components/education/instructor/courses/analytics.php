<?php
session_start();
require_once '../../../../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    $dbConfig = require '../../../../../config/database.php';
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Kurs ve eğitmen kontrolü
    $stmt = $conn->prepare("
        SELECT c.*, i.user_id 
        FROM courses c 
        JOIN instructors i ON c.instructor_id = i.instructor_id 
        WHERE c.course_id = ? AND i.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: /instructor/dashboard.php');
        exit;
    }

    // Toplam öğrenci sayısı
    $stmt = $conn->prepare("SELECT JSON_LENGTH(enrolled_students) as total FROM courses WHERE course_id = ?");
    $stmt->execute([$_GET['id']]);
    $totalStudents = $stmt->fetchColumn();

    // Son 30 günlük kayıtlar
    $stmt = $conn->prepare("
        SELECT 
            DATE(ce.enrollment_date) as date,
            COUNT(*) as count
        FROM course_enrollments ce
        WHERE ce.course_id = ?
        GROUP BY DATE(ce.enrollment_date)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$_GET['id']]);
    $enrollments = $stmt->fetchAll();

    // Puanlama istatistikleri
    $stmt = $conn->prepare("
        SELECT 
            rating,
            COUNT(*) as count
        FROM course_reviews
        WHERE course_id = ?
        GROUP BY rating
    ");
    $stmt->execute([$_GET['id']]);
    $ratings = $stmt->fetchAll();

    // Forum istatistikleri
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_posts,
               COUNT(DISTINCT user_id) as unique_posters
        FROM course_forum
        WHERE course_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $forumStats = $stmt->fetch();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurs Analitiği - <?= htmlspecialchars($course['title']) ?></title>
</head>
<body>
    <div class="container">
        <h1>Kurs Analitiği: <?= htmlspecialchars($course['title']) ?></h1>

        <div class="analytics-section">
            <h2>Genel Bakış</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Toplam Öğrenci</h3>
                    <p><?= number_format($totalStudents) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Ortalama Puan</h3>
                    <p>⭐ <?= number_format($course['rating'], 1) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Forum Gönderileri</h3>
                    <p><?= number_format($forumStats['total_posts']) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Aktif Öğrenciler</h3>
                    <p><?= number_format($forumStats['unique_posters']) ?></p>
                </div>
            </div>
        </div>

        <div class="analytics-section">
            <h2>Kayıt İstatistikleri</h2>
            <div class="chart">
                <!-- Burada kayıt grafiği gösterilecek -->
                <canvas id="enrollmentChart"></canvas>
            </div>
        </div>

        <div class="analytics-section">
            <h2>Puanlama Dağılımı</h2>
            <div class="ratings-chart">
                <?php foreach ($ratings as $rating): ?>
                    <div class="rating-bar">
                        <span class="stars"><?= str_repeat('⭐', $rating['rating']) ?></span>
                        <div class="bar" style="width: <?= ($rating['count'] / $totalStudents * 100) ?>%"></div>
                        <span class="count"><?= $rating['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Kayıt grafiği
        const enrollmentData = <?= json_encode($enrollments) ?>;
        new Chart(document.getElementById('enrollmentChart'), {
            type: 'line',
            data: {
                labels: enrollmentData.map(item => item.date),
                datasets: [{
                    label: 'Günlük Kayıtlar',
                    data: enrollmentData.map(item => item.count),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>