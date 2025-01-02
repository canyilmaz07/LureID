<?php
// content.php
session_start();
require_once '../../../../../config/database.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id'])) {
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

    // Kurs ID kontrolü
    if (!isset($_GET['id'])) {
        header('Location: ../dashboard.php');
        exit;
    }

    $courseId = $_GET['id'];

    // Eğitmen ve kurs kontrolü 
    $stmt = $conn->prepare("
        SELECT c.*, i.user_id 
        FROM courses c
        JOIN instructors i ON c.instructor_id = i.instructor_id
        WHERE c.course_id = ? AND i.user_id = ?
    ");
    $stmt->execute([$courseId, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: ../dashboard.php');
        exit;
    }

    // Bölümleri getir
    $stmt = $conn->prepare("
        SELECT * FROM course_sections 
        WHERE course_id = ? 
        ORDER BY order_number
    ");
    $stmt->execute([$courseId]);
    $sections = $stmt->fetchAll();

    // Her bölüm için içerikleri getir
    $contents = [];
    if ($sections) {
        $stmt = $conn->prepare("
            SELECT * FROM course_contents 
            WHERE section_id = ? 
            ORDER BY order_number
        ");

        foreach ($sections as $section) {
            $stmt->execute([$section['section_id']]);
            $contents[$section['section_id']] = $stmt->fetchAll();
        }
    }

    // AJAX İsteklerini İşle
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];

        switch ($_POST['action']) {
            case 'add_section':
                if (isset($_POST['title'])) {
                    try {
                        // Son sıra numarasını bul
                        $stmt = $conn->prepare("
                            SELECT MAX(order_number) FROM course_sections 
                            WHERE course_id = ?
                        ");
                        $stmt->execute([$courseId]);
                        $maxOrder = $stmt->fetchColumn() ?: 0;

                        // Yeni bölüm ekle
                        $stmt = $conn->prepare("
                            INSERT INTO course_sections (
                                course_id, title, description, 
                                order_number, section_type
                            ) VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $courseId,
                            $_POST['title'],
                            $_POST['description'] ?? '',
                            $maxOrder + 1,
                            'VIDEO_CONTENT'
                        ]);

                        $response = [
                            'success' => true,
                            'message' => 'Bölüm başarıyla eklendi',
                            'section_id' => $conn->lastInsertId()
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'Bölüm eklenirken hata oluştu';
                    }
                }
                break;

            case 'update_section':
                if (isset($_POST['section_id'], $_POST['title'])) {
                    try {
                        $stmt = $conn->prepare("
                            UPDATE course_sections 
                            SET title = ?, description = ? 
                            WHERE section_id = ? AND course_id = ?
                        ");
                        $stmt->execute([
                            $_POST['title'],
                            $_POST['description'] ?? '',
                            $_POST['section_id'],
                            $courseId
                        ]);

                        $response = [
                            'success' => true,
                            'message' => 'Bölüm başarıyla güncellendi'
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'Bölüm güncellenirken hata oluştu';
                    }
                }
                break;

            case 'delete_section':
                if (isset($_POST['section_id'])) {
                    try {
                        $stmt = $conn->prepare("
                            DELETE FROM course_sections 
                            WHERE section_id = ? AND course_id = ?
                        ");
                        $stmt->execute([$_POST['section_id'], $courseId]);

                        $response = [
                            'success' => true,
                            'message' => 'Bölüm başarıyla silindi'
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'Bölüm silinirken hata oluştu';
                    }
                }
                break;

            case 'add_content':
                if (isset($_POST['section_id'], $_POST['title'], $_POST['content_type'])) {
                    try {
                        // Son sıra numarasını bul
                        $stmt = $conn->prepare("
                            SELECT MAX(order_number) FROM course_contents 
                            WHERE section_id = ?
                        ");
                        $stmt->execute([$_POST['section_id']]);
                        $maxOrder = $stmt->fetchColumn() ?: 0;

                        // İçerik verilerini hazırla
                        $contentData = [
                            'text' => $_POST['content'] ?? '',
                            'video_url' => $_POST['video_url'] ?? '',
                            'duration' => $_POST['duration'] ?? 0,
                            'is_free' => isset($_POST['is_free']) ? 1 : 0
                        ];

                        // Yeni içerik ekle
                        $stmt = $conn->prepare("
                            INSERT INTO course_contents (
                                section_id, title, content_type,
                                content_data, duration, order_number,
                                is_free
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_POST['section_id'],
                            $_POST['title'],
                            $_POST['content_type'],
                            json_encode($contentData),
                            $_POST['duration'] ?? 0,
                            $maxOrder + 1,
                            isset($_POST['is_free']) ? 1 : 0
                        ]);

                        $response = [
                            'success' => true,
                            'message' => 'İçerik başarıyla eklendi',
                            'content_id' => $conn->lastInsertId()
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'İçerik eklenirken hata oluştu';
                    }
                }
                break;

            case 'update_content':
                if (isset($_POST['content_id'], $_POST['title'])) {
                    try {
                        // Debug log
                        error_log('Update Content POST data: ' . print_r($_POST, true));

                        $contentData = [
                            'text' => $_POST['content'] ?? '',
                            'video_url' => $_POST['video_url'] ?? '',
                            'duration' => $_POST['duration'] ?? 0,
                        ];

                        $stmt = $conn->prepare("
                                UPDATE course_contents 
                                SET title = ?,
                                    content_type = ?,
                                    content_data = ?,
                                    duration = ?,
                                    is_free = ?
                                WHERE content_id = ?
                            ");

                        $stmt->execute([
                            $_POST['title'],
                            $_POST['content_type'],
                            json_encode($contentData),
                            $_POST['duration'] ?? 0,
                            isset($_POST['is_free']) ? 1 : 0,
                            $_POST['content_id']
                        ]);

                        $response = [
                            'success' => true,
                            'message' => 'İçerik başarıyla güncellendi'
                        ];
                    } catch (PDOException $e) {
                        error_log('Update Content Error: ' . $e->getMessage());
                        $response = [
                            'success' => false,
                            'message' => 'İçerik güncellenirken hata oluştu: ' . $e->getMessage()
                        ];
                    }
                }
                break;

            case 'delete_content':
                if (isset($_POST['content_id'])) {
                    try {
                        $stmt = $conn->prepare("
                            DELETE FROM course_contents 
                            WHERE content_id = ?
                        ");
                        $stmt->execute([$_POST['content_id']]);

                        $response = [
                            'success' => true,
                            'message' => 'İçerik başarıyla silindi'
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'İçerik silinirken hata oluştu';
                    }
                }
                break;

            case 'reorder_sections':
                if (isset($_POST['order'])) {
                    try {
                        $order = json_decode($_POST['order'], true);
                        $stmt = $conn->prepare("
                            UPDATE course_sections 
                            SET order_number = ? 
                            WHERE section_id = ?
                        ");

                        foreach ($order as $index => $sectionId) {
                            $stmt->execute([$index + 1, $sectionId]);
                        }

                        $response = [
                            'success' => true,
                            'message' => 'Sıralama başarıyla güncellendi'
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'Sıralama güncellenirken hata oluştu';
                    }
                }
                break;

            case 'reorder_contents':
                if (isset($_POST['order'])) {
                    try {
                        $order = json_decode($_POST['order'], true);
                        $stmt = $conn->prepare("
                            UPDATE course_contents 
                            SET order_number = ? 
                            WHERE content_id = ?
                        ");

                        foreach ($order as $index => $contentId) {
                            $stmt->execute([$index + 1, $contentId]);
                        }

                        $response = [
                            'success' => true,
                            'message' => 'İçerik sıralaması başarıyla güncellendi'
                        ];
                    } catch (PDOException $e) {
                        $response['message'] = 'İçerik sıralaması güncellenirken hata oluştu';
                    }
                }
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

} catch (PDOException $e) {
    die("Veritabanı Hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurs İçeriği - <?= htmlspecialchars($course['title']) ?></title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.2/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #c3ff00;
            --primary-dark: #9ec700;
            --text-dark: #1a1a1a;
            --bg-light: #f8f9fc;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Poppins', sans-serif;
        }

        .navbar {
            background: #121212 !important;
            padding: 1rem 2rem;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        .container {
            max-width: 1200px;
            padding: 2rem;
        }

        .course-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .section-title {
            font-weight: 600;
            margin: 0;
        }

        .content-item {
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: white;
        }

        .content-item:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            color: var(--text-dark);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: var(--text-dark);
        }

        .sortable-ghost {
            opacity: 0.5;
            background: var(--primary-color);
        }

        .drag-handle {
            cursor: move;
            color: #999;
        }

        .section-actions,
        .content-actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal-content {
            border-radius: 12px;
        }

        .modal-header {
            background: var(--bg-light);
            border-radius: 12px 12px 0 0;
        }

        .form-label {
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(195, 255, 0, 0.25);
        }

        .modal-backdrop {
            --bs-backdrop-zindex: 1040;
        }

        .modal {
            --bs-modal-zindex: 1045;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?= htmlspecialchars($course['title']) ?> - İçerik Yönetimi</a>
            <a href="../dashboard.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left"></i> Panele Dön</a>
        </div>
    </nav>

    <!-- Ana İçerik -->
    <div class="container mt-4">
        <div class="course-content">
            <!-- Üst Bilgi -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>
                    <i class="fas fa-book"></i> Kurs İçeriği
                </h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="fas fa-plus"></i> Yeni Bölüm Ekle
                </button>
            </div>

            <!-- Bölümler Listesi -->
            <div id="sections-container">
                <?php foreach ($sections as $section): ?>
                    <div class="section mb-4" data-section-id="<?= $section['section_id'] ?>"
                        data-description="<?= htmlspecialchars($section['description']) ?>">
                        <div class="section-header">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-grip-vertical drag-handle me-3"></i>
                                <h5 class="section-title">
                                    <?= htmlspecialchars($section['title']) ?>
                                </h5>
                            </div>
                            <div class="section-actions">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#addContentModal" data-section-id="<?= $section['section_id'] ?>">
                                    <i class="fas fa-plus"></i> İçerik Ekle
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editSection(<?= $section['section_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger"
                                    onclick="deleteSection(<?= $section['section_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Bölüm İçerikleri -->
                        <div class="content-list" data-section-id="<?= $section['section_id'] ?>">
                            <?php if (isset($contents[$section['section_id']])): ?>
                                <?php foreach ($contents[$section['section_id']] as $content): ?>
                                    <?php
                                    $contentData = json_decode($content['content_data'], true);
                                    ?>
                                    <div class="content-item d-flex justify-content-between align-items-center"
                                        data-content-id="<?= $content['content_id'] ?>" data-type="<?= $content['content_type'] ?>"
                                        data-free="<?= $content['is_free'] ? 'true' : 'false' ?>"
                                        data-content='<?= htmlspecialchars(json_encode($contentData)) ?>'>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-grip-vertical drag-handle me-3"></i>
                                            <div>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $icon = match ($content['content_type']) {
                                                        'VIDEO' => 'fas fa-video',
                                                        'TEXT' => 'fas fa-file-alt',
                                                        'QUIZ' => 'fas fa-question-circle',
                                                        'EXAMPLE' => 'fas fa-code',
                                                        'PRACTICE' => 'fas fa-laptop-code',
                                                        'ASSIGNMENT' => 'fas fa-tasks',
                                                        default => 'fas fa-file'
                                                    };
                                                    ?>
                                                    <i class="<?= $icon ?> me-2"></i>
                                                    <span><?= htmlspecialchars($content['title']) ?></span>
                                                </div>
                                                <?php if ($content['duration']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= $content['duration'] ?> dakika
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="content-actions">
                                            <button class="btn btn-sm btn-warning"
                                                onclick="editContent(<?= $content['content_id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteContent(<?= $content['content_id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Bölüm Ekleme Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Bölüm Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addSectionForm">
                        <div class="mb-3">
                            <label class="form-label">Bölüm Başlığı</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="submitSection()">Ekle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- İçerik Ekleme Modal -->
    <div class="modal fade" id="addContentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni İçerik Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addContentForm">
                        <input type="hidden" name="section_id">
                        <div class="mb-3">
                            <label class="form-label">İçerik Başlığı</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İçerik Tipi</label>
                            <select class="form-select" name="content_type" required
                                onchange="toggleContentFields(this.value)">
                                <option value="VIDEO">Video</option>
                                <option value="TEXT">Metin</option>
                                <option value="QUIZ">Quiz</option>
                                <option value="EXAMPLE">Örnek</option>
                                <option value="PRACTICE">Uygulama</option>
                                <option value="ASSIGNMENT">Ödev</option>
                            </select>
                        </div>
                        <div id="videoFields">
                            <div class="mb-3">
                                <label class="form-label">Video URL</label>
                                <input type="url" class="form-control" name="video_url">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Süre (Dakika)</label>
                                <input type="number" class="form-control" name="duration" min="0">
                            </div>
                        </div>
                        <div id="textFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">İçerik</label>
                                <textarea class="form-control" name="content" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_free" id="is_free">
                            <label class="form-check-label" for="is_free">Ücretsiz İçerik</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="submitContent()">Ekle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bölüm Düzenleme Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bölüm Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editSectionForm">
                        <input type="hidden" name="section_id">
                        <div class="mb-3">
                            <label class="form-label">Bölüm Başlığı</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="updateSection()">Güncelle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- İçerik Düzenleme Modal -->
    <div class="modal fade" id="editContentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">İçerik Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editContentForm">
                        <input type="hidden" name="content_id">
                        <div class="mb-3">
                            <label class="form-label">İçerik Başlığı</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İçerik Tipi</label>
                            <select class="form-select" name="content_type" required
                                onchange="toggleEditContentFields(this.value)">
                                <option value="VIDEO">Video</option>
                                <option value="TEXT">Metin</option>
                                <option value="QUIZ">Quiz</option>
                                <option value="EXAMPLE">Örnek</option>
                                <option value="PRACTICE">Uygulama</option>
                                <option value="ASSIGNMENT">Ödev</option>
                            </select>
                        </div>
                        <div id="editVideoFields">
                            <div class="mb-3">
                                <label class="form-label">Video URL</label>
                                <input type="url" class="form-control" name="video_url">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Süre (Dakika)</label>
                                <input type="number" class="form-control" name="duration" min="0">
                            </div>
                        </div>
                        <div id="editTextFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">İçerik</label>
                                <textarea class="form-control" name="content" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_free" id="edit_is_free">
                            <label class="form-check-label" for="edit_is_free">Ücretsiz İçerik</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="updateContent()">Güncelle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.2/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Sürükle-Bırak İşlemleri
        document.addEventListener('DOMContentLoaded', function () {
            // Bölüm sıralaması
            new Sortable(document.getElementById('sections-container'), {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function () {
                    const sections = document.querySelectorAll('.section');
                    const order = Array.from(sections).map(section => section.dataset.sectionId);

                    updateOrder('reorder_sections', order);
                }
            });

            // İçerik sıralaması
            document.querySelectorAll('.content-list').forEach(el => {
                new Sortable(el, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function () {
                        const contents = el.querySelectorAll('.content-item');
                        const order = Array.from(contents).map(content => content.dataset.contentId);

                        updateOrder('reorder_contents', order);
                    }
                });
            });
        });

        // İçerik tipine göre form alanlarını göster/gizle
        function toggleContentFields(type) {
            const videoFields = document.getElementById('videoFields');
            const textFields = document.getElementById('textFields');

            switch (type) {
                case 'VIDEO':
                    videoFields.style.display = 'block';
                    textFields.style.display = 'none';
                    break;
                case 'TEXT':
                case 'QUIZ':
                case 'EXAMPLE':
                case 'PRACTICE':
                case 'ASSIGNMENT':
                    videoFields.style.display = 'none';
                    textFields.style.display = 'block';
                    break;
            }
        }

        // AJAX İstekleri
        function makeRequest(action, data) {
            return fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: action,
                    ...data
                })
            })
                .then(response => response.json());
        }

        // Sıralama güncelleme
        function updateOrder(action, order) {
            makeRequest(action, {
                order: JSON.stringify(order)
            })
                .then(response => {
                    if (!response.success) {
                        Swal.fire('Hata', response.message, 'error');
                    }
                });
        }

        // Bölüm işlemleri
        function submitSection() {
            const form = document.getElementById('addSectionForm');
            const formData = new FormData(form);

            makeRequest('add_section', Object.fromEntries(formData))
                .then(response => {
                    if (response.success) {
                        Swal.fire('Başarılı', response.message, 'success')
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Hata', response.message, 'error');
                    }
                });
        }

        function editSection(sectionId) {
            // Bölüm verilerini getir
            const section = document.querySelector(`.section[data-section-id="${sectionId}"]`);
            const title = section.querySelector('.section-title').textContent.trim();
            const description = section.getAttribute('data-description') || '';

            // Form alanlarını doldur
            const form = document.getElementById('editSectionForm');
            form.querySelector('[name="section_id"]').value = sectionId;
            form.querySelector('[name="title"]').value = title;
            form.querySelector('[name="description"]').value = description;

            // Modalı göster
            new bootstrap.Modal(document.getElementById('editSectionModal')).show();
        }

        // İçerik düzenleme
        function editContent(contentId) {
            // İçerik verilerini getir
            const contentItem = document.querySelector(`.content-item[data-content-id="${contentId}"]`);
            const contentData = JSON.parse(contentItem.getAttribute('data-content'));

            // Form alanlarını doldur
            const form = document.getElementById('editContentForm');
            form.querySelector('[name="content_id"]').value = contentId;
            form.querySelector('[name="title"]').value = contentItem.querySelector('span').textContent.trim();

            const contentType = contentItem.getAttribute('data-type');
            const contentTypeSelect = form.querySelector('[name="content_type"]');
            contentTypeSelect.value = contentType;

            // İçerik tipine göre alanları göster/gizle ve doldur
            toggleEditContentFields(contentType);

            if (contentType === 'VIDEO') {
                form.querySelector('[name="video_url"]').value = contentData.video_url || '';
                form.querySelector('[name="duration"]').value = contentData.duration || '';
            } else {
                form.querySelector('[name="content"]').value = contentData.text || '';
            }

            form.querySelector('[name="is_free"]').checked = contentItem.getAttribute('data-free') === 'true';

            // Modalı göster
            const modal = new bootstrap.Modal(document.getElementById('editContentModal'));
            modal.show();
        }

        function toggleEditContentFields(type) {
            const videoFields = document.getElementById('editVideoFields');
            const textFields = document.getElementById('editTextFields');

            switch (type) {
                case 'VIDEO':
                    videoFields.style.display = 'block';
                    textFields.style.display = 'none';
                    break;
                default:
                    videoFields.style.display = 'none';
                    textFields.style.display = 'block';
                    break;
            }
        }

        function updateContent() {
            const form = document.getElementById('editContentForm');
            const formData = new FormData(form);

            console.log('Form Data:', Object.fromEntries(formData)); // Debug log

            makeRequest('update_content', Object.fromEntries(formData))
                .then(response => {
                    console.log('Server Response:', response); // Debug log
                    if (response.success) {
                        Swal.fire('Başarılı', response.message, 'success')
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Hata', response.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error); // Debug log
                    Swal.fire('Hata', 'Bir hata oluştu', 'error');
                });
        }

        function updateSection() {
            const form = document.getElementById('editSectionForm');
            const formData = new FormData(form);

            makeRequest('update_section', Object.fromEntries(formData))
                .then(response => {
                    if (response.success) {
                        Swal.fire('Başarılı', response.message, 'success')
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Hata', response.message, 'error');
                    }
                });
        }

        function deleteSection(sectionId) {
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu bölüm ve içindeki tüm içerikler silinecek!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    makeRequest('delete_section', { section_id: sectionId })
                        .then(response => {
                            if (response.success) {
                                Swal.fire('Başarılı', response.message, 'success')
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        });
                }
            });
        }

        // İçerik işlemleri
        function submitContent() {
            const form = document.getElementById('addContentForm');
            const formData = new FormData(form);

            makeRequest('add_content', Object.fromEntries(formData))
                .then(response => {
                    if (response.success) {
                        Swal.fire('Başarılı', response.message, 'success')
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Hata', response.message, 'error');
                    }
                });
        }

        function deleteContent(contentId) {
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu içerik silinecek!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    makeRequest('delete_content', { content_id: contentId })
                        .then(response => {
                            if (response.success) {
                                Swal.fire('Başarılı', response.message, 'success')
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        });
                }
            });
        }

        // Modal işlemleri
        document.getElementById('addContentModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const sectionId = button.getAttribute('data-section-id');
            this.querySelector('[name="section_id"]').value = sectionId;
        });
    </script>
</body>

</html>