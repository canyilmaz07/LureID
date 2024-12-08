<?php
// gigs.php
session_start();
require_once 'includes/helpers.php';
$config = require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

// Kullanıcı kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}

// Freelancer kontrolü ve ID'sini al
$freelancerQuery = "SELECT freelancer_id FROM freelancers WHERE user_id = ? AND approval_status = 'APPROVED'";
$stmt = $db->prepare($freelancerQuery);
$stmt->execute([$_SESSION['user_id']]);
$freelancer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$freelancer) {
    header('Location: dashboard.php');
    exit;
}

$freelancer_id = $freelancer['freelancer_id'];

// User data
$userQuery = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $db->prepare($userQuery);
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Filtre parametresini al
$filter = $_GET['filter'] ?? 'all';

// İlanları filtreye göre al
$gigsQuery = "SELECT g.*, 
    JSON_EXTRACT(g.milestones_data, '$') as milestones,
    JSON_EXTRACT(g.nda_data, '$') as nda,
    JSON_EXTRACT(g.deliverables, '$') as deliverables,
    CASE 
        WHEN g.status = 'APPROVED' THEN 'Aktif'
        WHEN g.status = 'PENDING_REVIEW' THEN 'Onay Bekliyor'
        WHEN g.status = 'REJECTED' THEN 'Reddedildi'
        WHEN g.status = 'PAUSED' THEN 'Arşivlendi'
        WHEN g.status = 'DELETED' THEN 'Silindi'
    END as status_text,
    DATEDIFF(DATE_ADD(updated_at, INTERVAL 30 DAY), NOW()) as days_until_delete
FROM gigs g 
WHERE g.freelancer_id = ? ";

// Modify the query based on filter
switch ($filter) {
    case 'active':
        $gigsQuery .= "AND g.status IN ('APPROVED', 'ACTIVE')";
        break;
    case 'pending':
        $gigsQuery .= "AND g.status = 'PENDING_REVIEW'";
        break;
    case 'archived':
        $gigsQuery .= "AND g.status = 'PAUSED'";
        break;
    case 'rejected':
        $gigsQuery .= "AND g.status = 'REJECTED'";
        break;
    case 'deleted':
        $gigsQuery .= "AND g.status = 'DELETED'";
        break;
    default:
        $gigsQuery .= "AND g.status != 'DELETED'";
}

$gigsQuery .= " ORDER BY g.created_at DESC";

$stmt = $db->prepare($gigsQuery);
$stmt->execute([$freelancer_id]);
$gigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize gigs by status for potential future use
$activeGigs = array_filter($gigs, fn($gig) => in_array($gig['status'], ['APPROVED', 'PENDING_REVIEW', 'REJECTED']));
$archivedGigs = array_filter($gigs, fn($gig) => $gig['status'] === 'PAUSED');
$deletedGigs = array_filter($gigs, fn($gig) => $gig['status'] === 'DELETED');

// Yarım kalan ilanları al
$tempGigsQuery = "SELECT * FROM temp_gigs WHERE freelancer_id = ? ORDER BY updated_at DESC";
$stmt = $db->prepare($tempGigsQuery);
$stmt->execute([$freelancer_id]);
$tempGigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlanlarım - LureID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />

    <style>
        *,
        body,
        html {
            padding: 0;
            margin: 0;
            font-family: 'Poppins', sans-serif;
        }

        .page-container {
            display: flex;
            min-height: 100vh;
        }

        .content-wrapper {
            flex: 1;
            margin-left: 280px;
            padding: 40px 0 40px 50px;
            display: flex;
            flex-direction: column;
        }

        .content-container {
            width: 100%;
        }

        .header {
            margin-bottom: 2rem;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .filter-button {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
        }

        .filter-button.active {
            background-color: #4F46E5;
            color: white;
        }

        .filter-button:not(.active) {
            background-color: white;
            color: #4B5563;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
        }

        .new-gig-button {
            padding: 0.5rem 1rem;
            background-color: #4F46E5;
            color: white;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: auto;
            margin-right: 50px;
        }

        .new-gig-button:hover {
            opacity: 0.9;
        }

        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding-right: 50px;
        }

        .gig-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            min-height: 380px;
            width: 400px;
        }

        .gig-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .category-icon {
            width: 32px;
            height: 32px;
            opacity: 1;
            /* Tam siyah için opacity 1 yapıldı */
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .action-button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
        }

        .action-button img {
            width: 20px;
            height: 20px;
            transition: all 0.3s;
        }

        .action-button.delete img {
            opacity: 1;
            filter: invert(22%) sepia(78%) saturate(6123%) hue-rotate(355deg) brightness(94%) contrast(121%);
        }

        .action-button.archive img {
            opacity: 1;
            filter: invert(71%) sepia(78%) saturate(1261%) hue-rotate(339deg) brightness(101%) contrast(96%);
        }

        .action-button.restore img {
            opacity: 1;
            filter: invert(57%) sepia(82%) saturate(2273%) hue-rotate(87deg) brightness(119%) contrast(119%);
        }

        .action-button:hover img {
            opacity: 1;
        }

        .created-at {
            color: #6b7280;
            font-size: 13px;
            margin: 12px 0;
        }

        .gig-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }

        .gig-description {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: auto;
            /* Aşağıdaki içeriği alta sabitlemek için */
            min-height: 80px;
            /* Minimum açıklama yüksekliği */
        }

        .divider {
            height: 1px;
            background: #bebebe;
        }

        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: auto;
            /* Üstteki içeriğe göre otomatik boşluk */
        }

        .price-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .price-row {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .price {
            font-weight: 600;
            font-size: 18px;
            color: #111827;
        }

        .price-type {
            color: #6b7280;
            font-size: 13px;
        }

        .deliverables {
            color: #4b5563;
            font-size: 13px;
        }

        .edit-button {
            background: #111827;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .edit-button:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <div class="page-container">
        <?php include 'components/menu.php'; ?>

        <div class="content-wrapper">
            <div class="content-container">
                <!-- Başlık ve Filtreler -->
                <div class="header">
                    <div>
                        <h1 class="title">İş İlanlarım</h1>

                        <div class="filters-container">
                            <a href="?filter=all" class="filter-button <?= $filter == 'all' ? 'active' : '' ?>">
                                Tümü
                            </a>
                            <a href="?filter=active" class="filter-button <?= $filter == 'active' ? 'active' : '' ?>">
                                Aktif
                            </a>
                            <a href="?filter=pending" class="filter-button <?= $filter == 'pending' ? 'active' : '' ?>">
                                Onay Bekleyen
                            </a>
                            <a href="?filter=rejected"
                                class="filter-button <?= $filter == 'rejected' ? 'active' : '' ?>">
                                Reddedilen
                            </a>
                            <a href="?filter=archived"
                                class="filter-button <?= $filter == 'archived' ? 'active' : '' ?>">
                                Arşivlenen İlanlar
                            </a>
                            <a href="?filter=deleted" class="filter-button <?= $filter == 'deleted' ? 'active' : '' ?>">
                                Silinen İlanlar
                            </a>
                        </div>
                    </div>

                    <a href="new_gig.php" class="new-gig-button">
                        + Yeni İlan
                    </a>
                </div>

                <!-- İlanlar Grid -->
                <div class="gigs-grid">
                    <?php foreach ($gigs as $gig):
                        // Kategori ikonunu belirle
                        $categoryIcon = match ($gig['category']) {
                            'Web, Yazılım & Teknoloji' => 'code.svg',
                            'Grafik & Tasarım' => 'brush.svg',
                            'Dijital Pazarlama' => 'chart.svg',
                            'Yazı & Çeviri' => 'document-text.svg',
                            'Video & Animasyon' => 'video-play.svg',
                            'Müzik & Ses' => 'music.svg',
                            'İş & Yönetim' => 'briefcase.svg',
                            'Veri & Analiz' => 'data.svg',
                            'Eğitim & Öğretim' => 'book.svg',
                            'Danışmanlık & Hukuk' => 'shield.svg',
                            default => 'document.svg'
                        };

                        // Oluşturulma zamanını hesapla
                        $created = new DateTime($gig['created_at']);
                        $now = new DateTime();
                        $interval = $created->diff($now);

                        if ($interval->y > 0) {
                            $timeAgo = $interval->y . ' yıl önce';
                        } elseif ($interval->m > 0) {
                            $timeAgo = $interval->m . ' ay önce';
                        } elseif ($interval->d > 0) {
                            $timeAgo = $interval->d . ' gün önce';
                        } elseif ($interval->h > 0) {
                            $timeAgo = $interval->h . ' saat önce';
                        } else {
                            $timeAgo = 'Az önce';
                        }

                        // Fiyatlandırma tipini düzenle
                        $pricingTypeText = match ($gig['pricing_type']) {
                            'ONE_TIME' => 'Tek Seferlik',
                            'DAILY' => 'Günlük',
                            'WEEKLY' => 'Haftalık',
                            'MONTHLY' => 'Aylık',
                            default => ''
                        };

                        // Deliverables'ları parse et
                        $mediaData = json_decode($gig['media_data'], true);
                        $deliverables = json_decode($gig['deliverables'], true);
                        $deliverablesText = !empty($deliverables) ? implode(', ', $deliverables) . ' içerir.' : 'Teslimat içeriği belirtilmemiş.';

                        // Debug için
                        error_log('Media Data: ' . print_r($mediaData, true));
                        error_log('Deliverables: ' . print_r($deliverables, true));
                        ?>
                        <div class="gig-card">
                            <div class="gig-card-header">
                                <img src="/sources/icons/bulk/<?= $categoryIcon ?>" alt="<?= $gig['category'] ?>"
                                    class="category-icon">
                                <div class="action-buttons">
                                    <?php if ($gig['status'] === 'APPROVED'): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'PAUSED')"
                                            class="action-button archive" title="Arşivle">
                                            <img src="/sources/icons/bulk/archive-add.svg" alt="Arşivle">
                                        </button>
                                    <?php elseif ($gig['status'] === 'DELETED' && $gig['days_until_delete'] > 0): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'APPROVED')"
                                            class="action-button restore" title="Geri Yükle">
                                            <img src="/sources/icons/bulk/rotate-left.svg" alt="Geri Yükle">
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($gig['status'] !== 'DELETED'): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'DELETED')"
                                            class="action-button delete" title="Sil">
                                            <img src="/sources/icons/bulk/trash.svg" alt="Sil">
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="created-at"><?= $timeAgo ?></div>
                            <h3 class="gig-title"><?= htmlspecialchars($gig['title']) ?></h3>
                            <p class="gig-description"><?= substr(strip_tags($gig['description']), 0, 100) ?>...</p>

                            <div class="divider"></div>

                            <div class="price-section">
                                <div class="price-info">
                                    <div class="price-row">
                                        <span class="price">₺<?= number_format($gig['price'], 2) ?></span>
                                        <span class="price-type">/ <?= $pricingTypeText ?></span>
                                    </div>
                                    <span class="deliverables"><?= $deliverablesText ?></span>
                                </div>

                                <button onclick="editGig(<?= $gig['gig_id'] ?>)" class="edit-button">
                                    Düzenle
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Yarım Kalan İlanlar -->
                <?php if (count($tempGigs) > 0): ?>
                    <div class="mt-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Yarım Kalan İlanlar</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($tempGigs as $tempGig):
                                $formData = json_decode($tempGig['form_data'], true);
                                $stepText = match ($tempGig['current_step']) {
                                    1 => 'Temel Bilgiler',
                                    2 => 'Detaylar',
                                    3 => 'Gereksinimler',
                                    4 => 'Fiyat ve Teslimat',
                                    5 => 'Medya',
                                    6 => 'İş Süreci ve Anlaşma',
                                    default => 'Bilinmeyen Adım'
                                };
                                ?>
                                <div class="bg-white rounded-lg shadow-md p-4">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2">
                                        <?= htmlspecialchars($formData['title'] ?? 'Başlıksız İlan') ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 mb-4">
                                        Son düzenleme: <?= timeAgo($tempGig['updated_at']) ?>
                                    </p>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-blue-600">Kalan Adım: <?= $stepText ?></span>
                                        <a href="new_gig.php?temp_id=<?= $tempGig['temp_gig_id'] ?>"
                                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                            Devam Et
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>
        <script>
            function updateGigStatus(gigId, status) {
                const messages = {
                    'DELETED': 'Bu ilanı silmek istediğinizden emin misiniz? İlan 30 gün sonra kalıcı olarak silinecektir.',
                    'PAUSED': 'Bu ilanı arşivlemek istediğinizden emin misiniz?',
                    'APPROVED': status === 'DELETED' ? 'Bu ilanı geri yüklemek istediğinizden emin misiniz?' : 'Bu ilanı tekrar yayına almak istediğinizden emin misiniz?'
                };

                if (confirm(messages[status])) {
                    fetch('api/gigs/update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            gig_id: gigId,
                            status: status,
                            freelancer_id: <?= $freelancer_id ?>
                        })
                    })
                        .then(response => {
                            // Response'u önce text olarak alıp kontrol edelim
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('Invalid JSON response:', text);
                                    throw new Error('Sunucudan geçersiz yanıt alındı');
                                }
                            });
                        })
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message || 'İşlem başarısız oldu');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.');
                        });
                }
            }

            function editGig(gigId) {
                window.location.href = `edit_gig.php?id=${gigId}`;
            }

            function deleteGig(gigId) {
                if (confirm('<?= __("Bu ilanı silmek istediğinizden emin misiniz?") ?>')) {
                    fetch('api/gigs/update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ gig_id: gigId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message);
                            }
                        });
                }
            }
        </script>
</body>

</html>