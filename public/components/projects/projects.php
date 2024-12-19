<?php
// components/projects/projects.php

session_start();

// Database konfigürasyonunu yükle
$dbConfig = require_once '../../../config/database.php';

// PDO bağlantısını oluştur
try {
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

// ProjectUtils sınıfını dahil et
require_once __DIR__ . '/api/ProjectUtils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$projectUtils = new ProjectUtils($db);

// Kullanıcının projelerini al
$stmt = $db->prepare("
    SELECT p.*, 
           JSON_LENGTH(p.likes_data) as like_count,
           IF(JSON_SEARCH(p.likes_data, 'one', ?) IS NOT NULL, 1, 0) as is_liked
    FROM projects p
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projelerim - LUREID</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        .project-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .like-button.liked {
            color: #ff4757;
        }
        
        .project-card .preview-overlay {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .project-card:hover .preview-overlay {
            opacity: 1;
        }
        
        @keyframes likeAnimation {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .like-animation {
            animation: likeAnimation 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../menu.php'; ?>

    <div class="container mx-auto px-4 py-8 mt-20">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Projelerim</h1>
            <a href="create.php" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                Yeni Proje
            </a>
        </div>

        <?php if (empty($projects)): ?>
        <div class="text-center py-20">
            <img src="empty.svg" alt="No projects" class="w-64 mx-auto mb-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Burası çok ıssız!</h2>
            <p class="text-gray-500 mb-8">İlk projenizi oluşturarak başlayın!</p>
            <a href="create.php" class="bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600 transition-colors">
                Proje Oluştur
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($projects as $project): ?>
            <div class="project-card bg-white rounded-xl overflow-hidden shadow-md" data-aos="fade-up">
                <div class="relative group">
                    <img src="/public/<?php echo htmlspecialchars($project['preview_image']); ?>" 
                         alt="<?php echo htmlspecialchars($project['title']); ?>"
                         class="w-full h-48 object-cover">
                    
                    <div class="preview-overlay absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center gap-4">
                        <a href="edit.php?id=<?php echo $project['project_id']; ?>" 
                           class="bg-white text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <img src="/sources/icons/bulk/edit-2.svg" alt="Edit" class="w-6 h-6">
                        </a>
                        <button onclick="deleteProject('<?php echo $project['project_id']; ?>')"
                                class="bg-white text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <img src="/sources/icons/bulk/trash.svg" alt="Delete" class="w-6 h-6">
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($project['title']); ?></h3>
                    <p class="text-gray-600 text-sm mb-4">
                        <?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 100)) . '...'; ?>
                    </p>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <button class="like-button flex items-center gap-2 text-gray-600 hover:text-red-500 transition-colors <?php echo $project['is_liked'] ? 'liked' : ''; ?>"
                                    onclick="toggleLike('<?php echo $project['project_id']; ?>', this)">
                                <img src="/sources/icons/bulk/heart.svg" alt="Like" class="w-5 h-5">
                                <span class="like-count"><?php echo $project['like_count']; ?></span>
                            </button>
                            
                            <div class="flex items-center gap-2 text-gray-600">
                                <img src="/sources/icons/bulk/eye.svg" alt="Views" class="w-5 h-5">
                                <span><?php echo $project['views']; ?></span>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-500">
                            <?php echo date('d.m.Y', strtotime($project['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            offset: 100,
            once: true
        });

        function toggleLike(projectId, button) {
            fetch('/public/components/projects/api/like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ project_id: projectId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const likeCount = button.querySelector('.like-count');
                    likeCount.textContent = data.like_count;
                    
                    button.classList.toggle('liked');
                    button.classList.add('like-animation');
                    setTimeout(() => button.classList.remove('like-animation'), 300);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function deleteProject(projectId) {
            if (!confirm('Bu projeyi silmek istediğinizden emin misiniz?')) {
                return;
            }

            fetch(`/public/components/projects/api/delete.php?project_id=${projectId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>