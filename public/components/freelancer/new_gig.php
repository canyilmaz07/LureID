<?php
// new_gig.php
session_start();

// Doğru yolu belirtin
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT']);
$config = require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/languages/language_handler.php';

// Kullanıcı ve freelancer kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

try {
    // PDO bağlantısı için karakter setini belirle
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";

    $db = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}

// Freelancer ID'sini al
$freelancerQuery = "SELECT freelancer_id FROM freelancers WHERE user_id = ? AND approval_status = 'APPROVED'";
$stmt = $db->prepare($freelancerQuery);
$stmt->execute([$_SESSION['user_id']]);
$freelancer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$freelancer) {
    header('Location: dashboard.php');
    exit;
}

$freelancer_id = $freelancer['freelancer_id'];

// Temp gig kontrolü
$temp_id = $_GET['temp_id'] ?? null;
$currentStep = 1;
$formData = [];
$mediaData = ['images' => [], 'video' => null];

if ($temp_id) {
    $tempQuery = "SELECT * FROM temp_gigs WHERE temp_gig_id = ? AND freelancer_id = ?";
    $stmt = $db->prepare($tempQuery);
    $stmt->execute([$temp_id, $freelancer_id]);
    $tempGig = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tempGig) {
        $currentStep = $tempGig['current_step'];
        $formData = json_decode($tempGig['form_data'], true);
        $mediaData = json_decode($tempGig['media_data'], true) ?? ['images' => [], 'video' => null];
    }
}

// Ajax medya yükleme işlemi
if (isset($_POST['ajax_upload'])) {
    $response = ['status' => 'error', 'message' => ''];

    // Medya silme işlemleri
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'remove_image') {
            $index = $_POST['index'] ?? -1;
            if ($index >= 0 && isset($mediaData['images'][$index])) {
                // Dosyayı fiziksel olarak sil
                if (file_exists($mediaData['images'][$index])) {
                    unlink($mediaData['images'][$index]);
                }
                // Diziyi güncelle
                array_splice($mediaData['images'], $index, 1);
                $response['status'] = 'success';
            }
        } elseif ($_POST['action'] === 'remove_video') {
            if (!empty($mediaData['video']) && file_exists($mediaData['video'])) {
                unlink($mediaData['video']);
                $mediaData['video'] = null;
                $response['status'] = 'success';
            }
        }

        // Temp_gigs tablosunu güncelle
        if ($temp_id && $response['status'] === 'success') {
            $updateQuery = "UPDATE temp_gigs SET media_data = ? WHERE temp_gig_id = ?";
            $stmt = $db->prepare($updateQuery);
            $stmt->execute([json_encode($mediaData), $temp_id]);
        }

        echo json_encode($response);
        exit;
    }

    if ($_POST['type'] == 'image') {
        if (count($mediaData['images']) >= 3) {
            $response['message'] = 'En fazla 3 fotoğraf yükleyebilirsiniz.';
            echo json_encode($response);
            exit;
        }

        if (!empty($_FILES['file'])) {
            $file = $_FILES['file'];
            $tempPath = 'uploads/temps/' . uniqid() . '_' . $file['name'];

            if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                $mediaData['images'][] = $tempPath;
                $response['status'] = 'success';
                $response['file_path'] = $tempPath;
                $response['message'] = 'Fotoğraf başarıyla yüklendi.';
            }
        }
    } elseif ($_POST['type'] == 'video') {
        if ($mediaData['video']) {
            $response['message'] = 'Zaten bir video yüklenmiş.';
            echo json_encode($response);
            exit;
        }

        if (!empty($_FILES['file'])) {
            $file = $_FILES['file'];
            $tempPath = 'uploads/temps/' . uniqid() . '_' . $file['name'];

            if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                $mediaData['video'] = $tempPath;
                $response['status'] = 'success';
                $response['file_path'] = $tempPath;
                $response['message'] = 'Video başarıyla yüklendi.';
            }
        }
    }

    // Temp_gigs tablosundaki media_data'yı güncelle
    if ($temp_id) {
        $updateQuery = "UPDATE temp_gigs SET media_data = ? WHERE temp_gig_id = ?";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([json_encode($mediaData), $temp_id]);
    }

    echo json_encode($response);
    exit;
}

// Form işleme kısmını güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_upload'])) {
    $step = $_POST['step'] ?? 1;
    $formData = $_POST;
    unset($formData['step']);

    try {
        $db->beginTransaction();

        if ($temp_id) {
            // Mevcut form verilerini al
            $tempGigQuery = "SELECT form_data, milestones_data, nda_data FROM temp_gigs WHERE temp_gig_id = ? AND freelancer_id = ?";
            $stmt = $db->prepare($tempGigQuery);
            $stmt->execute([$temp_id, $freelancer_id]);
            $tempGigData = $stmt->fetch(PDO::FETCH_ASSOC);

            $existingFormData = json_decode($tempGigData['form_data'], true) ?? [];
            $existingMilestonesData = json_decode($tempGigData['milestones_data'], true) ?? [];
            $existingNdaData = json_decode($tempGigData['nda_data'], true) ?? [];

            // Yeni form verilerini mevcut verilerle birleştir
            $formData = array_merge($existingFormData, $formData);

            if ($step == 6) {
                // Anlaşma kontrolü
                if (!isset($_POST['agreement_accepted'])) {
                    throw new Exception(__('Lütfen iş süreci anlaşmasını kabul edin.'));
                }

                // Milestone verilerini hazırla
                $milestones = [];
                $milestoneTitles = $_POST['milestone_titles'] ?? [];
                $milestoneDescriptions = $_POST['milestone_descriptions'] ?? [];

                for ($i = 0; $i < count($milestoneTitles); $i++) {
                    $milestones[] = [
                        'title' => $milestoneTitles[$i],
                        'description' => $milestoneDescriptions[$i],
                        'order_number' => $i + 1
                    ];
                }

                // NDA verilerini hazırla
                $ndaData = [
                    'required' => isset($_POST['nda_required']),
                    'text' => $_POST['nda_text'] ?? ''
                ];

                // Gig oluştur
                $insertGigQuery = "INSERT INTO gigs (
                    freelancer_id, title, category, subcategory, description,
                    requirements, price, pricing_type, delivery_time, revision_count,
                    media_data, status, agreement_accepted, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_REVIEW', ?, NOW())";

                $stmt = $db->prepare($insertGigQuery);
                $stmt->execute([
                    $freelancer_id,
                    $formData['title'],
                    $formData['category'],
                    $formData['subcategory'],
                    $formData['description'],
                    $formData['requirements'],
                    $formData['price'],
                    $formData['pricing_type'],
                    $formData['delivery_time'],
                    $formData['revision_count'],
                    $tempGigData['media_data'],
                    true
                ]);

                $gigId = $db->lastInsertId();

                // Milestone'ları kaydet
                foreach ($milestones as $milestone) {
                    $insertMilestoneQuery = "INSERT INTO gig_milestones (
                        gig_id, title, description, order_number
                    ) VALUES (?, ?, ?, ?)";

                    $stmt = $db->prepare($insertMilestoneQuery);
                    $stmt->execute([
                        $gigId,
                        $milestone['title'],
                        $milestone['description'],
                        $milestone['order_number']
                    ]);
                }

                // NDA gereksinimlerini kaydet
                if ($ndaData['required']) {
                    $insertNdaQuery = "INSERT INTO gig_nda_requirements (
                        gig_id, nda_text, is_required
                    ) VALUES (?, ?, ?)";

                    $stmt = $db->prepare($insertNdaQuery);
                    $stmt->execute([
                        $gigId,
                        $ndaData['text'],
                        true
                    ]);
                }

                // Medya dosyalarını taşı
                $mediaData = json_decode($tempGig['media_data'], true) ?? ['images' => [], 'video' => null];
                $finalMediaData = ['images' => [], 'video' => null];

                // Fotoğrafları taşı
                if (!empty($mediaData['images'])) {
                    foreach ($mediaData['images'] as $tempPath) {
                        if (file_exists($tempPath)) {
                            $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                            $fileName = 'gig_' . $gigId . '_' . uniqid() . '.' . $extension;
                            $finalPath = 'uploads/photos/' . $fileName;

                            if (!is_dir('uploads/photos')) {
                                mkdir('uploads/photos', 0777, true);
                            }

                            if (rename($tempPath, $finalPath)) {
                                $finalMediaData['images'][] = $finalPath;
                            }
                        }
                    }
                }

                // Videoyu taşı
                if (!empty($mediaData['video']) && file_exists($mediaData['video'])) {
                    $extension = pathinfo($mediaData['video'], PATHINFO_EXTENSION);
                    $fileName = 'gig_' . $gigId . '_' . uniqid() . '.' . $extension;
                    $finalPath = 'uploads/videos/' . $fileName;

                    if (!is_dir('uploads/videos')) {
                        mkdir('uploads/videos', 0777, true);
                    }

                    if (rename($mediaData['video'], $finalPath)) {
                        $finalMediaData['video'] = $finalPath;
                    }
                }

                // Gig'i güncelle
                $updateGigQuery = "UPDATE gigs SET media_data = ? WHERE gig_id = ?";
                $stmt = $db->prepare($updateGigQuery);
                $stmt->execute([json_encode($finalMediaData), $gigId]);

                // Temp gig'i sil
                $deleteQuery = "DELETE FROM temp_gigs WHERE temp_gig_id = ? AND freelancer_id = ?";
                $stmt = $db->prepare($deleteQuery);
                $stmt->execute([$temp_id, $freelancer_id]);

                $db->commit();
                $_SESSION['success'] = __('İlanınız başarıyla oluşturuldu ve onay sürecine alındı.');
                header('Location: dashboard.php');
                exit;
            } else {
                // Normal adım güncelleme
                $updateData = [
                    'current_step' => $step + 1,
                    'form_data' => json_encode($formData)
                ];

                if ($step == 5) {
                    $updateData['milestones_data'] = json_encode($milestones ?? []);
                    $updateData['nda_data'] = json_encode($ndaData ?? []);
                }

                $updateQuery = "UPDATE temp_gigs SET " .
                    implode(', ', array_map(function ($key) {
                        return "$key = ?";
                    }, array_keys($updateData))) .
                    " WHERE temp_gig_id = ?";

                $stmt = $db->prepare($updateQuery);
                $stmt->execute([...array_values($updateData), $temp_id]);
            }
        } else {
            // İlk temp gig oluşturma
            $insertQuery = "INSERT INTO temp_gigs (freelancer_id, current_step, form_data) VALUES (?, ?, ?)";
            $stmt = $db->prepare($insertQuery);
            $stmt->execute([$freelancer_id, $step + 1, json_encode($formData)]);
            $temp_id = $db->lastInsertId();
        }

        $db->commit();

        if ($step < 6) {
            header("Location: new_gig.php?temp_id=" . $temp_id);
            exit;
        }
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: new_gig.php" . ($temp_id ? "?temp_id=" . $temp_id : ""));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Yeni İş İlanı Oluştur') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- İlerleme Çubuğu -->
            <div class="mb-8">
                <div class="flex justify-between mb-2">
                    <?php
                    $steps = [
                        1 => __('Temel Bilgiler'),
                        2 => __('Detaylar'),
                        3 => __('Gereksinimler'),
                        4 => __('Fiyat ve Teslimat'),
                        5 => __('Medya'),
                        6 => __('İş Süreci ve Anlaşma')
                    ];
                    foreach ($steps as $stepNum => $stepName):
                        ?>
                        <div class="flex flex-col items-center">
                            <div
                                class="w-8 h-8 rounded-full <?= $stepNum <= $currentStep ? 'bg-blue-600' : 'bg-gray-300' ?> flex items-center justify-center text-white">
                                <?= $stepNum ?>
                            </div>
                            <span
                                class="text-sm mt-1 <?= $stepNum <= $currentStep ? 'text-blue-600' : 'text-gray-500' ?>"><?= $stepName ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= ($currentStep / 6) * 100 ?>%"></div>
                </div>
            </div>

            <!-- Form -->
            <form action="new_gig.php<?= $temp_id ? "?temp_id=$temp_id" : '' ?>" method="POST"
                enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
                <input type="hidden" name="step" value="<?= $currentStep ?>">

                <?php if ($currentStep == 1): ?>
                    <!-- Temel Bilgiler -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('İlan Başlığı') ?></label>
                            <input type="text" name="title" value="<?= htmlspecialchars($formData['title'] ?? '') ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Kategori') ?></label>
                            <select name="category"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                                <option value=""><?= __('Kategori Seçin') ?></option>
                                <?php
                                $categoryQuery = "SELECT name FROM gig_categories WHERE parent_id IS NULL ORDER BY name";
                                $categories = $db->query($categoryQuery)->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($categories as $category):
                                    $selected = ($formData['category'] ?? '') == $category['name'] ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($category['name']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Alt Kategori') ?></label>
                            <select name="subcategory"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                                <option value=""><?= __('Önce Kategori Seçin') ?></option>
                            </select>
                        </div>
                    </div>

                <?php elseif ($currentStep == 2): ?>
                    <!-- Detaylar -->
                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"><?= __('Detaylı Açıklama') ?></label>
                            <textarea name="description" rows="6"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                <?php elseif ($currentStep == 3): ?>
                    <!-- Gereksinimler -->
                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"><?= __('Müşteriden Beklenen Bilgiler') ?></label>
                            <textarea name="requirements" rows="4"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required><?= htmlspecialchars($formData['requirements'] ?? '') ?></textarea>
                        </div>
                    </div>

                <?php elseif ($currentStep == 4): ?>
                    <!-- Fiyat ve Teslimat -->
                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"><?= __('Fiyatlandırma Tipi') ?></label>
                            <select name="pricing_type"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                                <option value="ONE_TIME" <?= ($formData['pricing_type'] ?? '') == 'ONE_TIME' ? 'selected' : '' ?>><?= __('Tek Seferlik') ?></option>
                                <option value="DAILY" <?= ($formData['pricing_type'] ?? '') == 'DAILY' ? 'selected' : '' ?>>
                                    <?= __('Günlük') ?>
                                </option>
                                <option value="WEEKLY" <?= ($formData['pricing_type'] ?? '') == 'WEEKLY' ? 'selected' : '' ?>>
                                    <?= __('Haftalık') ?>
                                </option>
                                <option value="MONTHLY" <?= ($formData['pricing_type'] ?? '') == 'MONTHLY' ? 'selected' : '' ?>><?= __('Aylık') ?></option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Fiyat (₺)') ?></label>
                            <input type="number" name="price" value="<?= htmlspecialchars($formData['price'] ?? '') ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"><?= __('Teslimat Süresi (Gün)') ?></label>
                            <input type="number" name="delivery_time"
                                value="<?= htmlspecialchars($formData['delivery_time'] ?? '') ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Revizyon Sayısı') ?></label>
                            <input type="number" name="revision_count"
                                value="<?= htmlspecialchars($formData['revision_count'] ?? '1') ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                        </div>
                    </div>

                <?php elseif ($currentStep == 5): ?>
                    <!-- Medya -->
                    <div class="space-y-6">
                        <!-- Fotoğraf Yükleme Alanı -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <?= __('Fotoğraflar (En az 1, en fazla 3)') ?>
                            </label>
                            <div class="mt-2 space-y-4">
                                <!-- Yüklenen Fotoğraflar -->
                                <div id="uploadedImages" class="grid grid-cols-3 gap-4 mb-4">
                                    <?php if (!empty($mediaData['images'])): ?>
                                        <?php foreach ($mediaData['images'] as $index => $imagePath): ?>
                                            <div class="relative" id="image-container-<?= $index ?>">
                                                <img src="<?= htmlspecialchars($imagePath) ?>" alt="Uploaded Image"
                                                    class="w-full h-32 object-cover rounded-lg">
                                                <button type="button" onclick="removeImage(<?= $index ?>)"
                                                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Fotoğraf Yükleme Butonu -->
                                <div class="flex items-center justify-center" id="imageUploadContainer"
                                    <?= (count($mediaData['images'] ?? []) >= 3) ? 'style="display: none;"' : '' ?>>
                                    <label
                                        class="w-full flex flex-col items-center px-4 py-6 bg-white rounded-lg shadow-lg tracking-wide border border-blue cursor-pointer hover:bg-blue-50">
                                        <svg class="w-8 h-8 text-blue-500" fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path
                                                d="M16.88 9.1A4 4 0 0 1 16 17H5a5 5 0 0 1-1-9.9V7a3 3 0 0 1 4.52-2.59A4.98 4.98 0 0 1 17 8c0 .38-.04.74-.12 1.1zM11 11h3l-4-4-4 4h3v3h2v-3z" />
                                        </svg>
                                        <span class="mt-2 text-sm text-gray-600">Fotoğraf Seç</span>
                                        <input type="file" id="imageUpload" class="hidden" accept="image/*">
                                    </label>
                                </div>

                                <!-- Yükleme Göstergesi -->
                                <div id="imageUploadProgress" class="hidden">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">Yükleniyor...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Video Yükleme Alanı -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <?= __('Video (Opsiyonel, maksimum 1 adet)') ?>
                            </label>
                            <div class="mt-2">
                                <!-- Yüklenen Video -->
                                <div id="uploadedVideo" class="mb-4">
                                    <?php if (!empty($mediaData['video'])): ?>
                                        <div class="relative" id="video-container">
                                            <video src="<?= htmlspecialchars($mediaData['video']) ?>" controls
                                                class="w-full rounded-lg"></video>
                                            <button type="button" onclick="removeVideo()"
                                                class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Video Yükleme Butonu -->
                                <div class="flex items-center justify-center" id="videoUploadContainer"
                                    <?= !empty($mediaData['video']) ? 'style="display: none;"' : '' ?>>
                                    <label
                                        class="w-full flex flex-col items-center px-4 py-6 bg-white rounded-lg shadow-lg tracking-wide border border-blue cursor-pointer hover:bg-blue-50">
                                        <svg class="w-8 h-8 text-blue-500" fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path
                                                d="M16.88 9.1A4 4 0 0 1 16 17H5a5 5 0 0 1-1-9.9V7a3 3 0 0 1 4.52-2.59A4.98 4.98 0 0 1 17 8c0 .38-.04.74-.12 1.1zM11 11h3l-4-4-4 4h3v3h2v-3z" />
                                        </svg>
                                        <span class="mt-2 text-sm text-gray-600">Video Seç</span>
                                        <input type="file" id="videoUpload" class="hidden" accept="video/*">
                                    </label>
                                </div>

                                <!-- Video Yükleme Göstergesi -->
                                <div id="videoUploadProgress" class="hidden">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">Video yükleniyor...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($currentStep == 6): ?>
                    <div class="space-y-6">
                        <!-- İş Süreci Aşamaları -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4"><?= __('İş Süreci Aşamaları') ?></h3>
                            <div id="milestones-container" class="space-y-4">
                                <!-- Sabit Başlangıç Aşaması -->
                                <div class="milestone-item bg-gray-50 p-4 rounded-lg">
                                    <div class="flex items-center gap-4">
                                        <span class="font-medium">1. <?= __('Başlangıç') ?></span>
                                        <input type="hidden" name="milestone_titles[]" value="Başlangıç">
                                        <input type="text" name="milestone_descriptions[]"
                                            placeholder="<?= __('Müşteri ilanı satın alır ve süreç başlar') ?>"
                                            class="flex-1 rounded-md border-gray-300"
                                            value="<?= htmlspecialchars($formData['milestone_descriptions'][0] ?? '') ?>"
                                            required>
                                    </div>
                                </div>

                                <!-- Dinamik Aşamalar -->
                                <div id="dynamic-milestones">
                                    <?php
                                    $milestones = json_decode($formData['milestones'] ?? '[]', true);
                                    foreach ($milestones as $index => $milestone):
                                        if ($index === 0 || $index === count($milestones) - 1)
                                            continue; // Başlangıç ve bitiş aşamalarını atla
                                        ?>
                                        <div class="milestone-item p-4 rounded-lg border">
                                            <div class="flex items-center gap-4">
                                                <input type="text" name="milestone_titles[]"
                                                    placeholder="<?= __('Aşama başlığı') ?>"
                                                    class="w-1/3 rounded-md border-gray-300"
                                                    value="<?= htmlspecialchars($milestone['title']) ?>" required>
                                                <input type="text" name="milestone_descriptions[]"
                                                    placeholder="<?= __('Aşama açıklaması') ?>"
                                                    class="flex-1 rounded-md border-gray-300"
                                                    value="<?= htmlspecialchars($milestone['description']) ?>" required>
                                                <button type="button" onclick="removeMilestone(this)"
                                                    class="text-red-600 hover:text-red-800">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Sabit Teslim Aşaması -->
                                <div class="milestone-item bg-gray-50 p-4 rounded-lg">
                                    <div class="flex items-center gap-4">
                                        <span class="font-medium milestone-end-number"></span>
                                        <input type="hidden" name="milestone_titles[]" value="Teslim">
                                        <input type="text" name="milestone_descriptions[]"
                                            placeholder="<?= __('İş teslim edilir ve müşteri onayı beklenir') ?>"
                                            class="flex-1 rounded-md border-gray-300"
                                            value="<?= htmlspecialchars($formData['milestone_descriptions'][count($milestones)] ?? '') ?>"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <button type="button" onclick="addMilestone()"
                                class="mt-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                <?= __('Yeni Aşama Ekle') ?>
                            </button>
                        </div>

                        <!-- NDA Gereksinimleri -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4"><?= __('Gizlilik Anlaşması (NDA)') ?></h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="nda_required"
                                            class="rounded border-gray-300 text-blue-600" <?= ($formData['nda_required'] ?? '') ? 'checked' : '' ?>>
                                        <span class="ml-2"><?= __('NDA gerekli') ?></span>
                                    </label>
                                </div>
                                <div>
                                    <textarea name="nda_text" rows="4" class="w-full rounded-md border-gray-300"
                                        placeholder="<?= __('NDA metnini girin...') ?>"><?= htmlspecialchars($formData['nda_text'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- İş Süreci Anlaşması -->
                        <div class="bg-yellow-50 p-4 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800"><?= __('İş Süreci Anlaşması') ?></h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p><?= __('Bu ilanı yayınlamadan önce, belirtilen iş sürecini ve koşulları kabul ettiğinizi onaylamanız gerekmektedir. İlanınız yönetici onayından sonra yayınlanacaktır.') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="agreement_accepted" required
                                        class="rounded border-gray-300 text-blue-600">
                                    <span class="ml-2"><?= __('İş süreci ve koşullarını okudum, kabul ediyorum.') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <script>
                        function addMilestone() {
                            const container = document.getElementById('dynamic-milestones');
                            const milestoneCount = container.children.length + 2; // +2 for start and end milestones

                            const newMilestone = document.createElement('div');
                            newMilestone.className = 'milestone-item p-4 rounded-lg border';
                            newMilestone.innerHTML = `
                                        <div class="flex items-center gap-4">
                                            <input type="text" name="milestone_titles[]" 
                                                   placeholder="<?= __('Aşama başlığı') ?>"
                                                   class="w-1/3 rounded-md border-gray-300"
                                                   required>
                                            <input type="text" name="milestone_descriptions[]" 
                                                   placeholder="<?= __('Aşama açıklaması') ?>"
                                                   class="flex-1 rounded-md border-gray-300"
                                                   required>
                                            <button type="button" onclick="removeMilestone(this)" 
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    `;

                            container.appendChild(newMilestone);
                            updateMilestoneNumbers();
                        }

                        function removeMilestone(button) {
                            button.closest('.milestone-item').remove();
                            updateMilestoneNumbers();
                        }

                        function updateMilestoneNumbers() {
                            const milestones = document.getElementsByClassName('milestone-item');
                            for (let i = 0; i < milestones.length; i++) {
                                const numberElement = milestones[i].querySelector('.milestone-number');
                                if (numberElement) {
                                    numberElement.textContent = (i + 1) + '.';
                                }
                            }
                        }
                    </script>
                <?php endif; ?>

                <div class="mt-6 flex justify-between">
                    <?php if ($currentStep > 1): ?>
                        <button type="button" onclick="history.back()"
                            class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                            <?= __('Geri') ?>
                        </button>
                    <?php else: ?>
                        <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                            <?= __('İptal') ?>
                        </a>
                    <?php endif; ?>

                    <button type="submit" id="submitButton"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700" <?= ($currentStep == 5 && empty($mediaData['images'])) ? 'disabled' : '' ?>>
                        <?= $currentStep === 6 ? __('İlanı Yayınla') : __('Devam') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>

    <!-- Medya Yükleme JavaScript Kodu -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Fotoğraf yükleme işlemleri
            const imageUpload = document.getElementById('imageUpload');
            const imageProgress = document.getElementById('imageUploadProgress');
            const uploadedImages = document.getElementById('uploadedImages');
            const imageUploadContainer = document.getElementById('imageUploadContainer');
            const ndaCheckbox = document.querySelector('input[name="nda_required"]');
            const ndaText = document.querySelector('textarea[name="nda_text"]');

            if (ndaCheckbox && ndaText) {
                ndaCheckbox.addEventListener('change', function () {
                    ndaText.required = this.checked;
                    if (this.checked) {
                        ndaText.focus();
                    }
                });
            }

            if (imageUpload) {
                imageUpload.addEventListener('change', function () {
                    const file = this.files[0];
                    if (file) {
                        uploadMedia(file, 'image');
                    }
                });
            }

            // Video yükleme işlemleri
            const videoUpload = document.getElementById('videoUpload');
            const videoProgress = document.getElementById('videoUploadProgress');
            const uploadedVideo = document.getElementById('uploadedVideo');
            const videoUploadContainer = document.getElementById('videoUploadContainer');

            if (videoUpload) {
                videoUpload.addEventListener('change', function () {
                    const file = this.files[0];
                    if (file) {
                        uploadMedia(file, 'video');
                    }
                });
            }

            // Medya yükleme fonksiyonu
            function uploadMedia(file, type) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('ajax_upload', '1');
                formData.append('type', type);

                const progress = type === 'image' ? imageProgress : videoProgress;
                const progressBar = progress.querySelector('.bg-blue-600');

                progress.classList.remove('hidden');

                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);

                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                };

                xhr.onload = function () {
                    progress.classList.add('hidden');

                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);

                        if (response.status === 'success') {
                            if (type === 'image') {
                                addImageToPreview(response.file_path);
                                updateImageUploadVisibility();
                            } else {
                                addVideoToPreview(response.file_path);
                                videoUploadContainer.style.display = 'none';
                            }
                        } else {
                            alert(response.message || 'Yükleme hatası oluştu.');
                        }
                    }
                };

                xhr.send(formData);
            }

            // Fotoğraf önizleme ekleme
            function addImageToPreview(imagePath) {
                const imageCount = uploadedImages.children.length;
                const container = document.createElement('div');
                container.className = 'relative';
                container.id = `image-container-${imageCount}`;

                container.innerHTML = `
                    <img src="${imagePath}" alt="Uploaded Image" class="w-full h-32 object-cover rounded-lg">
                    <button type="button" onclick="removeImage(${imageCount})" 
                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;

                uploadedImages.appendChild(container);
                updateSubmitButton();
            }

            // Video önizleme ekleme
            function addVideoToPreview(videoPath) {
                const container = document.createElement('div');
                container.className = 'relative';
                container.id = 'video-container';

                container.innerHTML = `
                    <video src="${videoPath}" controls class="w-full rounded-lg"></video>
                    <button type="button" onclick="removeVideo()" 
                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;

                uploadedVideo.innerHTML = '';
                uploadedVideo.appendChild(container);
            }

            // Fotoğraf yükleme butonunun görünürlüğünü güncelleme
            function updateImageUploadVisibility() {
                const imageCount = uploadedImages.children.length;
                imageUploadContainer.style.display = imageCount >= 3 ? 'none' : 'block';
            }

            // Submit butonunu güncelleme
            function updateSubmitButton() {
                const submitButton = document.getElementById('submitButton');
                const imageCount = uploadedImages.children.length;
                submitButton.disabled = imageCount === 0;
            }

            // Kategori seçimi değiştiğinde alt kategorileri güncelle
            const categorySelect = document.querySelector('select[name="category"]');
            if (categorySelect) {
                categorySelect.addEventListener('change', function () {
                    updateSubcategories(this.value);
                });

                // Sayfa yüklendiğinde seçili kategori varsa alt kategorileri yükle
                if (categorySelect.value) {
                    updateSubcategories(categorySelect.value);
                }
            }

            // Alt kategorileri güncelleme fonksiyonu
            function updateSubcategories(category) {
                const subcategorySelect = document.querySelector('select[name="subcategory"]');
                if (!subcategorySelect) return;

                subcategorySelect.innerHTML = '<option value=""><?= __("Alt Kategori Yükleniyor...") ?></option>';
                subcategorySelect.disabled = true;

                fetch(`/public/components/freelancer/api/categories/get_subcategories.php?category=${encodeURIComponent(category)}`)
                    .then(response => response.json())
                    .then(data => {
                        subcategorySelect.innerHTML = '<option value=""><?= __("Alt Kategori Seçin") ?></option>';

                        if (Array.isArray(data)) {
                            data.forEach(subcategory => {
                                const option = document.createElement('option');
                                option.value = subcategory.name;
                                option.textContent = subcategory.name;

                                if (subcategory.name === '<?= htmlspecialchars($formData['subcategory'] ?? '') ?>') {
                                    option.selected = true;
                                }

                                subcategorySelect.appendChild(option);
                            });
                        }

                        subcategorySelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Alt kategoriler yüklenirken hata:', error);
                        subcategorySelect.innerHTML = '<option value=""><?= __("Alt Kategoriler Yüklenemedi") ?></option>';
                        subcategorySelect.disabled = false;
                    });
            }

            document.querySelector('form').addEventListener('submit', function (e) {
                const ndaRequired = document.querySelector('input[name="nda_required"]');
                const ndaText = document.querySelector('textarea[name="nda_text"]');

                if (ndaRequired && ndaRequired.checked && (!ndaText.value.trim())) {
                    e.preventDefault();
                    alert('NDA gerekli olarak işaretlendiğinde NDA metni de girilmelidir.');
                    ndaText.focus();
                }
            });
        });

        // Fotoğraf silme fonksiyonu
        function removeImage(index) {
            const container = document.getElementById(`image-container-${index}`);
            if (container) {
                // Ajax ile sunucuya silme isteği gönder
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ajax_upload: 1,
                        action: 'remove_image',
                        index: index
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            container.remove();
                            document.getElementById('imageUploadContainer').style.display = 'block';

                            // Submit butonunu güncelle
                            const uploadedImages = document.getElementById('uploadedImages');
                            const submitButton = document.getElementById('submitButton');
                            submitButton.disabled = uploadedImages.children.length === 0;
                        }
                    })
                    .catch(error => {
                        console.error('Fotoğraf silinirken hata:', error);
                        alert('Fotoğraf silinirken bir hata oluştu.');
                    });
            }
        }

        // Video silme fonksiyonu
        function removeVideo() {
            const container = document.getElementById('video-container');
            if (container) {
                // Ajax ile sunucuya silme isteği gönder
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ajax_upload: 1,
                        action: 'remove_video'
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            container.remove();
                            document.getElementById('videoUploadContainer').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Video silinirken hata:', error);
                        alert('Video silinirken bir hata oluştu.');
                    });
            }
        }

        // Milestone numaralandırma güncellemesi
        function updateMilestoneNumbers() {
            const dynamicMilestones = document.getElementById('dynamic-milestones').children;
            const endNumberElement = document.querySelector('.milestone-end-number');

            // Son numarayı güncelle
            if (endNumberElement) {
                endNumberElement.textContent = `${dynamicMilestones.length + 2}. Teslim`;
            }
        }
    </script>
</body>

</html>