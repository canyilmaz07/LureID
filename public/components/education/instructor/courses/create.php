<?php
// create.php
session_start();
require_once '../../../../../config/database.php';

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

    // Eğitmen kontrolü
    $stmt = $conn->prepare("SELECT instructor_id FROM instructors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $instructorId = $stmt->fetchColumn();

    if (!$instructorId) {
        header('Location: /');
        exit;
    }

    // Kategorileri getir
    $categoryQuery = "SELECT * FROM course_categories WHERE parent_id IS NULL";
    $categories = $conn->query($categoryQuery)->fetchAll();

    // Alt kategorileri getir
    $subcategoryQuery = "SELECT * FROM course_categories WHERE parent_id IS NOT NULL";
    $subcategories = $conn->query($subcategoryQuery)->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Dosya yükleme işlemi
        $uploadImageDir = '../../uploads/images/';
        $uploadVideoDir = '../../uploads/videos/';
        $thumbnailName = null;
        $previewName = null;

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
            $thumbnail = $_FILES['thumbnail'];
            $thumbnailExt = pathinfo($thumbnail['name'], PATHINFO_EXTENSION);
            $thumbnailName = 'thumb_' . uniqid() . '.' . $thumbnailExt;
            move_uploaded_file($thumbnail['tmp_name'], $uploadImageDir . $thumbnailName);
        }

        if (isset($_FILES['preview_video']) && $_FILES['preview_video']['error'] === 0) {
            $preview = $_FILES['preview_video'];
            $previewExt = pathinfo($preview['name'], PATHINFO_EXTENSION);
            $previewName = 'preview_' . uniqid() . '.' . $previewExt;
            move_uploaded_file($preview['tmp_name'], $uploadVideoDir . $previewName);
        }

        // Kurs oluşturma
        $stmt = $conn->prepare("
            INSERT INTO courses (
                instructor_id,
                title,
                description,
                price,
                category_id,
                subcategory_id,
                thumbnail_url,
                preview_video_url,
                requirements,
                what_will_learn,
                level,
                language,
                enrolled_students,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', 'DRAFT')
        ");

        $stmt->execute([
            $instructorId,
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
            $_POST['language']
        ]);

        $courseId = $conn->lastInsertId();

        // İlk bölümü otomatik oluştur
        $stmt = $conn->prepare("
            INSERT INTO course_sections (
                course_id,
                title,
                description,
                order_number,
                section_type
            ) VALUES (?, 'Giriş', 'Kurs girişi', 1, 'VIDEO_CONTENT')
        ");
        $stmt->execute([$courseId]);

        $_SESSION['course_created'] = true;
        header("Location: ../dashboard.php");
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
    <title>Yeni Kurs Oluştur</title>

    <!-- CSS Kütüphaneleri -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.2/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #c3ff00;
            --primary-dark: #9ec700;
            --primary-light: #d4ff33;
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            --bg-light: #f8f9fc;
            --bg-dark: #121212;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            margin: 0;
            min-height: 100vh;
        }

        .navbar {
            background: var(--bg-dark) !important;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .navbar .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: var(--text-dark);
        }

        .container {
            max-width: 1200px;
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            background: white;
        }

        .card-title {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(195, 255, 0, 0.25);
        }

        .dropzone {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: var(--bg-light);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dropzone:hover, .dropzone.dragover {
            border-color: var(--primary-color);
            background: rgba(195, 255, 0, 0.1);
        }

        .dz-message {
            color: var(--text-dark);
            font-weight: 500;
        }

        .preview-image {
            max-width: 200px;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .preview-video {
            max-width: 100%;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            color: var(--text-dark);
            padding: 0.75rem 2rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: var(--text-dark);
            transform: translateY(-2px);
        }

        #editor, #requirementsEditor, #learningEditor {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .ql-toolbar {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            border-color: #e0e0e0;
        }

        .ql-container {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-color: #e0e0e0;
        }

        .ql-editor {
            min-height: 150px;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .dropzone {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Yeni Kurs Oluştur</a>
            <a href="../dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Ana Sayfa
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title mb-4">Yeni Kurs Oluştur</h3>

                <form method="POST" enctype="multipart/form-data" id="courseForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Kurs Başlığı</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kurs Açıklaması</label>
                                <div id="editor"></div>
                                <input type="hidden" name="description">
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Kategori Bilgileri</h5>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ana Kategori</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['category_id'] ?>">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Alt Kategori</label>
                                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($subcategories as $subcategory): ?>
                                                <option value="<?= $subcategory['category_id'] ?>"
                                                    data-parent="<?= $subcategory['parent_id'] ?>" style="display: none;">
                                                    <?= htmlspecialchars($subcategory['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Kurs Gereksinimleri ve Kazanımları</h5>

                                <div class="mb-3">
                                    <label class="form-label">Ön Gereksinimler</label>
                                    <div id="requirementsEditor"></div>
                                    <input type="hidden" name="requirements">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kazanımlar</label>
                                    <div id="learningEditor"></div>
                                    <input type="hidden" name="what_will_learn">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Kurs Detayları</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Fiyat (TL)</label>
                                        <input type="number" class="form-control" name="price" step="0.01" min="0"
                                            required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Seviye</label>
                                        <select class="form-select" name="level" required>
                                            <option value="BEGINNER">Başlangıç</option>
                                            <option value="INTERMEDIATE">Orta</option>
                                            <option value="ADVANCED">İleri</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Eğitim Dili</label>
                                        <select class="form-select" name="language" required>
                                            <option value="Türkçe">Türkçe</option>
                                            <option value="English">İngilizce</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dropzone" id="thumbnailDropzone">
                                <input type="file" name="thumbnail" accept="image/*" class="d-none" required>
                                <div class="dz-message">
                                    Kurs görselini buraya sürükleyin veya tıklayın
                                </div>
                                <div id="thumbnailPreview" class="mt-2"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="dropzone" id="videoDropzone">
                                <input type="file" name="preview_video" accept="video/*" class="d-none">
                                <div class="dz-message">
                                    Tanıtım videosunu buraya sürükleyin veya tıklayın
                                </div>
                                <div id="videoPreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Kurs Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Kütüphaneleri -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.2/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Editör konfigürasyonları
        const editorConfig = {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            },
            placeholder: 'Madde madde yazınız...'
        };

        // Editörleri başlat
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            }
        });

        const requirementsEditor = new Quill('#requirementsEditor', editorConfig);
        const learningEditor = new Quill('#learningEditor', editorConfig);

        // Dosya yükleme işlemleri
        function handleFiles(file, type) {
            const preview = document.getElementById(`${type}Preview`);
            preview.innerHTML = '';

            if (!file) return;

            if (type === 'thumbnail') {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'preview-image';
                preview.appendChild(img);
            } else {
                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.className = 'preview-video';
                video.controls = true;
                preview.appendChild(video);
            }
        }

        // Dropzone işlemleri
        ['thumbnail', 'video'].forEach(type => {
            const dropzone = document.getElementById(`${type}Dropzone`);
            if (!dropzone) return;

            const input = dropzone.querySelector('input');
            
            dropzone.addEventListener('click', () => input.click());
            
            dropzone.addEventListener('dragover', e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
            
            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('dragover');
            });
            
            dropzone.addEventListener('drop', e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                input.files = e.dataTransfer.files;
                handleFiles(e.dataTransfer.files[0], type);
            });
            
            input.addEventListener('change', e => {
                handleFiles(e.target.files[0], type);
            });
        });

        // Kategori değişimi
        const categorySelect = document.getElementById('category_id');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                const parentId = this.value;
                const subcategorySelect = document.getElementById('subcategory_id');
                
                Array.from(subcategorySelect.options).forEach(option => {
                    if (option.value === '') return;
                    option.style.display = option.dataset.parent === parentId ? '' : 'none';
                });
                
                subcategorySelect.value = '';
            });
        }

        // Form gönderimi
        const courseForm = document.getElementById('courseForm');
        if (courseForm) {
            courseForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Dosya kontrolleri
                const thumbnail = this.querySelector('[name="thumbnail"]').files[0];
                const previewVideo = this.querySelector('[name="preview_video"]').files[0];

                if (thumbnail && thumbnail.size > 5 * 1024 * 1024) {
                    Swal.fire('Hata', 'Kurs görseli 5MB\'dan büyük olamaz', 'error');
                    return;
                }

                if (previewVideo && previewVideo.size > 100 * 1024 * 1024) {
                    Swal.fire('Hata', 'Tanıtım videosu 100MB\'dan büyük olamaz', 'error');
                    return;
                }

                // Form verilerini hazırla
                const formData = new FormData(this);
                formData.append('description', quill.root.innerHTML);
                formData.append('requirements', requirementsEditor.root.innerHTML);
                formData.append('what_will_learn', learningEditor.root.innerHTML);

                // Form gönderimi
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    try {
                        const jsonData = JSON.parse(data);
                        if (jsonData.success) {
                            Swal.fire('Başarılı', 'Kurs başarıyla oluşturuldu', 'success')
                                .then(() => window.location.href = '../dashboard.php');
                        } else {
                            Swal.fire('Hata', jsonData.message || 'Bir hata oluştu', 'error');
                        }
                    } catch (e) {
                        window.location.href = '../dashboard.php';
                    }
                })
                .catch(error => {
                    console.error(error);
                    Swal.fire('Hata', 'Bir hata oluştu', 'error');
                });
            });
        }
    });
</script>
</body>

</html>