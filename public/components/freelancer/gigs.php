<?php
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
</head>

<body class="bg-gray-100">
    <?php include 'components/freelancer_header.php'; ?>

    <div class="p-4 sm:ml-64 pt-20">
        <div class="max-w-7xl mx-auto">
            <!-- Başlık ve Filtreler -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-gray-900">İş İlanlarım</h2>

                <div class="flex flex-wrap gap-2">
                    <!-- Filtre Butonları -->
                    <a href="?filter=all"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Tümü
                    </a>
                    <a href="?filter=active"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'active' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Aktif
                    </a>
                    <a href="?filter=pending"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'pending' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Onay Bekleyen
                    </a>
                    <a href="?filter=rejected"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'rejected' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Reddedilen
                    </a>
                    <a href="?filter=archived"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'archived' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Arşivlenen İlanlar
                    </a>
                    <a href="?filter=deleted"
                        class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter == 'deleted' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                        Silinen İlanlar
                    </a>

                    <a href="new_gig.php"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-green-600 text-white hover:bg-green-700 ml-2">
                        + Yeni İlan
                    </a>
                </div>
            </div>

            <!-- İlanlar Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($gigs as $gig):
                    $statusColorClass = match ($gig['status']) {
                        'APPROVED' => 'bg-green-100 text-green-800',
                        'PENDING_REVIEW' => 'bg-yellow-100 text-yellow-800',
                        'REJECTED' => 'bg-red-100 text-red-800',
                        'PAUSED' => 'bg-gray-100 text-gray-800',
                        default => 'bg-gray-100 text-gray-800'
                    };
                    ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <?php
                        $mediaData = json_decode($gig['media_data'], true);
                        if (empty($mediaData['images'][0])) {
                            continue; // Bu gig'i gösterme
                        }
                        $firstImage = $mediaData['images'][0];
                        ?>
                        <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($gig['title']) ?>"
                            class="w-full h-48 object-cover">

                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <?= htmlspecialchars($gig['title']) ?>
                                </h3>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColorClass ?>">
                                    <?= $gig['status_text'] ?>
                                </span>
                            </div>

                            <p class="text-gray-600 text-sm mb-4">
                                <?= substr(htmlspecialchars($gig['description']), 0, 100) ?>...
                            </p>

                            <div class="flex justify-between items-center mt-2">
                                <div class="flex items-center space-x-2">
                                    <span class="text-blue-600 font-bold">
                                        ₺<?= number_format($gig['price'], 2) ?>
                                    </span>
                                    <?php if ($gig['status'] === 'DELETED'): ?>
                                        <span class="text-red-500 text-xs">
                                            Kalıcı silinmeye <?= $gig['days_until_delete'] ?> gün kaldı
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex space-x-2">
                                    <?php if ($gig['status'] === 'APPROVED'): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'PAUSED')"
                                            class="text-gray-600 hover:text-yellow-600" title="Arşivle">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                            </svg>
                                        </button>
                                    <?php elseif ($gig['status'] === 'PAUSED'): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'APPROVED')"
                                            class="text-gray-600 hover:text-green-600" title="Yayına Al">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($gig['status'] !== 'DELETED'): ?>
                                        <button onclick="updateGigStatus(<?= $gig['gig_id'] ?>, 'DELETED')"
                                            class="text-gray-600 hover:text-red-600" title="Sil">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
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
                'APPROVED': 'Bu ilanı tekrar yayına almak istediğinizden emin misiniz?'
            };

            if (confirm(messages[status])) {
                // API yolunu düzelttim
                fetch('api/gigs/update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        gig_id: gigId,
                        status: status,
                        freelancer_id: <?= $freelancer_id ?>
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Bir hata oluştu');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('İşlem sırasında bir hata oluştu');
                    });
            }
        }

        function editGig(gigId) {
            window.location.href = `edit_gig.php?id=${gigId}`;
        }

        function deleteGig(gigId) {
            if (confirm('<?= __("Bu ilanı silmek istediğinizden emin misiniz?") ?>')) {
                fetch('/api/gigs/update_status.php', {
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