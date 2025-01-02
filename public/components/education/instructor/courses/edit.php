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

    // Kursu ve eğitmen bilgilerini getir
    $stmt = $conn->prepare("
        SELECT c.*, i.user_id, cc.name as category_name
        FROM courses c 
        JOIN instructors i ON c.instructor_id = i.instructor_id 
        LEFT JOIN course_categories cc ON c.category_id = cc.category_id
        WHERE c.course_id = ? AND i.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: ../dashboard.php');
        exit;
    }

    // Kategorileri getir
    $categoryQuery = "SELECT * FROM course_categories WHERE parent_id IS NULL";
    $categories = $conn->query($categoryQuery)->fetchAll();

    // Alt kategorileri getir
    $subcategoryQuery = "SELECT * FROM course_categories WHERE parent_id = ?";
    $stmt = $conn->prepare($subcategoryQuery);
    $stmt->execute([$course['category_id']]);
    $subcategories = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uploadImageDir = '../../uploads/images/';
        $uploadVideoDir = '../../uploads/videos/';
        $thumbnailName = $course['thumbnail_url'];
        $previewName = $course['preview_video_url'];

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
            // Eski resmi sil
            if ($course['thumbnail_url'] && file_exists($uploadImageDir . $course['thumbnail_url'])) {
                unlink($uploadImageDir . $course['thumbnail_url']);
            }

            $thumbnail = $_FILES['thumbnail'];
            $thumbnailExt = pathinfo($thumbnail['name'], PATHINFO_EXTENSION);
            $thumbnailName = 'thumb_' . uniqid() . '.' . $thumbnailExt;
            move_uploaded_file($thumbnail['tmp_name'], $uploadImageDir . $thumbnailName);
        }

        if (isset($_FILES['preview_video']) && $_FILES['preview_video']['error'] === 0) {
            // Eski videoyu sil
            if ($course['preview_video_url'] && file_exists($uploadVideoDir . $course['preview_video_url'])) {
                unlink($uploadVideoDir . $course['preview_video_url']);
            }

            $preview = $_FILES['preview_video'];
            $previewExt = pathinfo($preview['name'], PATHINFO_EXTENSION);
            $previewName = 'preview_' . uniqid() . '.' . $previewExt;
            move_uploaded_file($preview['tmp_name'], $uploadVideoDir . $previewName);
        }

        // Kurs güncelleme
        $stmt = $conn->prepare("
            UPDATE courses SET 
                title = ?,
                description = ?,
                price = ?,
                category_id = ?,
                subcategory_id = ?,
                thumbnail_url = ?,
                preview_video_url = ?,
                requirements = ?,
                what_will_learn = ?,
                level = ?,
                language = ?
            WHERE course_id = ?
        ");

        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['price'],
            $_POST['category_id'],
            $_POST['subcategory_id'] ?? null,
            $thumbnailName,
            $previewName,
            $_POST['requirements'],
            $_POST['what_will_learn'],
            $_POST['level'],
            $_POST['language'],
            $_GET['id']
        ]);

        header('Location: ../dashboard.php');
        exit;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurs Düzenle - <?= htmlspecialchars($course['title']) ?></title>
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

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #121212;
            padding-top: 0;
            z-index: 100;
        }

        .sidebar .logo {
            padding: 20px;
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .back-button {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--text-light);
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }

        .sidebar .back-button:hover {
            background: rgba(255,255,255,0.1);
            color: var(--primary-color);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
        }

        .edit-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(195, 255, 0, 0.2);
        }

        .preview-container {
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            color: var(--text-dark);
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: var(--text-dark);
            transform: translateY(-2px);
        }

        .media-preview {
            margin: 10px 0;
        }

        .media-preview img,
        .media-preview video {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            Lureid
        </div>
        <a href="../dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Panele Dön
        </a>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="edit-form">
                        <h2 class="mb-4"><i class="fas fa-edit"></i> Kurs Düzenle: <?= htmlspecialchars($course['title']) ?></h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title"><i class="fas fa-heading"></i> Kurs Başlığı</label>
                                        <input type="text" class="form-control" id="title" name="title" required
                                               value="<?= htmlspecialchars($course['title']) ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="price"><i class="fas fa-tag"></i> Fiyat (TL)</label>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required
                                               value="<?= $course['price'] ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description"><i class="fas fa-align-left"></i> Kurs Açıklaması</label>
                                <textarea class="form-control" id="description" name="description" required rows="5"><?= htmlspecialchars($course['description']) ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_id"><i class="fas fa-folder"></i> Ana Kategori</label>
                                        <select class="form-control" id="category_id" name="category_id" required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['category_id'] ?>" 
                                                    <?= $category['category_id'] == $course['category_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subcategory_id"><i class="fas fa-folder-open"></i> Alt Kategori</label>
                                        <select class="form-control" id="subcategory_id" name="subcategory_id">
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($subcategories as $subcategory): ?>
                                                <option value="<?= $subcategory['category_id'] ?>
                                                    <?= $subcategory['category_id'] == $course['subcategory_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($subcategory['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="thumbnail"><i class="fas fa-image"></i> Kurs Görseli</label>
                                        <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                        <?php if ($course['thumbnail_url']): ?>
                                            <div class="media-preview">
                                                <img src="../../uploads/images/<?= $course['thumbnail_url'] ?>" alt="Mevcut görsel" class="img-fluid" style="max-width: 200px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="preview_video"><i class="fas fa-video"></i> Tanıtım Videosu</label>
                                        <input type="file" class="form-control" id="preview_video" name="preview_video" accept="video/*">
                                        <?php if ($course['preview_video_url']): ?>
                                            <div class="media-preview">
                                                <video width="320" height="240" controls class="img-fluid">
                                                    <source src="../../uploads/videos/<?= $course['preview_video_url'] ?>" type="video/mp4">
                                                    Tarayıcınız video etiketini desteklemiyor.
                                                </video>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requirements"><i class="fas fa-list-check"></i> Ön Gereksinimler</label>
                                        <textarea class="form-control" id="requirements" name="requirements" rows="3"><?= htmlspecialchars($course['requirements']) ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="what_will_learn"><i class="fas fa-graduation-cap"></i> Kazanımlar</label>
                                        <textarea class="form-control" id="what_will_learn" name="what_will_learn" rows="3"><?= htmlspecialchars($course['what_will_learn']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="level"><i class="fas fa-layer-group"></i> Seviye</label>
                                        <select class="form-control" id="level" name="level" required>
                                            <option value="BEGINNER" <?= $course['level'] == 'BEGINNER' ? 'selected' : '' ?>>Başlangıç</option>
                                            <option value="INTERMEDIATE" <?= $course['level'] == 'INTERMEDIATE' ? 'selected' : '' ?>>Orta</option>
                                            <option value="ADVANCED" <?= $course['level'] == 'ADVANCED' ? 'selected' : '' ?>>İleri</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="language"><i class="fas fa-language"></i> Eğitim Dili</label>
                                        <select class="form-control" id="language" name="language" required>
                                            <option value="Türkçe" <?= $course['language'] == 'Türkçe' ? 'selected' : '' ?>>Türkçe</option>
                                            <option value="English" <?= $course['language'] == 'English' ? 'selected' : '' ?>>İngilizce</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Değişiklikleri Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>