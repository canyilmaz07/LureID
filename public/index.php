<?php
// public/index.php
session_start();
require_once '../config/database.php';
require_once '../languages/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

try {
    $dbConfig = require '../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $jobsStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM jobs 
    WHERE client_id = ? 
    AND status != 'COMPLETED' 
    AND status != 'CANCELLED'
");
    $jobsStmt->execute([$_SESSION['user_id']]);
    $hasActiveOrders = $jobsStmt->fetchColumn() > 0;

    // Fetch user data
    $stmt = $db->prepare("
        SELECT u.*, ued.*, w.balance, w.coins
        FROM users u
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
        LEFT JOIN wallet w ON u.user_id = w.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_data'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check and create user_extended_details if not exists
    $checkProfileStmt = $db->prepare("SELECT COUNT(*) FROM user_extended_details WHERE user_id = ?");
    $checkProfileStmt->execute([$_SESSION['user_id']]);
    if ($checkProfileStmt->fetchColumn() == 0) {
        $createProfileStmt = $db->prepare("
            INSERT INTO user_extended_details (
                user_id,
                profile_photo_url,
                basic_info,
                network_links,
                skills_matrix
            ) VALUES (
                ?,
                'undefined',
                JSON_OBJECT(
                    'full_name', (SELECT full_name FROM users WHERE user_id = ?),
                    'age', NULL,
                    'biography', NULL,
                    'location', JSON_OBJECT('city', NULL, 'country', NULL),
                    'contact', JSON_OBJECT('email', NULL, 'website', NULL),
                    'languages', JSON_ARRAY()
                ),
                JSON_OBJECT(
                    'professional', JSON_OBJECT(),
                    'social', JSON_OBJECT(),
                    'portfolio_sites', JSON_OBJECT()
                ),
                JSON_OBJECT(
                    'technical_skills', JSON_ARRAY(),
                    'soft_skills', JSON_ARRAY(),
                    'tools', JSON_ARRAY()
                )
            )
        ");
        $createProfileStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);

        // Refresh user data after creation
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check and create follows if not exists
    $checkFollowsStmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE user_id = ?");
    $checkFollowsStmt->execute([$_SESSION['user_id']]);
    if ($checkFollowsStmt->fetchColumn() == 0) {
        $createFollowsStmt = $db->prepare("
            INSERT INTO follows (user_id, following, followers) 
            VALUES (?, '[]', '[]')
        ");
        $createFollowsStmt->execute([$_SESSION['user_id']]);
    }

    // Check and create avatar if needed
    if (isset($_SESSION['user_data']['profile_photo_url']) && $_SESSION['user_data']['profile_photo_url'] === 'undefined') {
        require_once 'components/create-avatar.php';

        // Create avatar
        $avatarCreator = new AvatarCreator($db);
        $result = $avatarCreator->createAvatar($_SESSION['user_id'], $_SESSION['user_data']['full_name']);

        if ($result['success']) {
            $_SESSION['user_data']['profile_photo_url'] = $result['path'];
        } else {
            error_log("Avatar creation failed: " . json_encode($result));
        }
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database error occurred');
}
?>

<?php
// AJAX isteği kontrolü
if (isset($_POST['loadMore'])) {
    $offset = intval($_POST['offset']);
    $limit = intval($_POST['limit']);

    $output = '';
    for ($i = $offset + 1; $i <= $offset + $limit; $i++) {
        $output .= generateCard($i);
    }

    $response = [
        'html' => $output,
        'remaining' => true
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Kart oluşturma fonksiyonu
function generateCard($i)
{
    return '
    <div class="card">
        <div class="card-image"></div>
        <div class="tag-box hover-text" data-hover="Etiket">
            <span>Etiket</span>
        </div>
        <div class="action-icons">
            <div class="icon-wrapper">
                <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                <div class="icon-tooltip">Kaydet</div>
            </div>
            <div class="icon-wrapper">
                <img src="/sources/icons/linear/heart.svg" alt="Like">
                <div class="icon-tooltip">Beğen</div>
            </div>
        </div>
        <div class="card-content">
            <div class="card-meta">
                <div class="card-avatar"></div>
                <span class="card-username hover-text" data-hover="@kullanici' . $i . '">
                    <span>@kullanici' . $i . '</span>
                </span>
            </div>
            <h3 class="card-title hover-text" data-hover="Tasarım Başlığı ' . $i . '">
                <span>Tasarım Başlığı ' . $i . '</span>
            </h3>
            <div class="stats-container">
                <div class="stat-item">
                    <img src="/sources/icons/linear/heart.svg" alt="likes">
                    <span class="hover-text" data-hover="1.2K">
                        <span>1.2K</span>
                    </span>
                </div>
                <div class="stat-item">
                    <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                    <span class="hover-text" data-hover="234">
                        <span>234</span>
                    </span>
                </div>
                <div class="stat-item">
                    <img src="/sources/icons/linear/eye.svg" alt="views">
                    <span class="hover-text" data-hover="5.6K">
                        <span>5.6K</span>
                    </span>
                </div>
            </div>
        </div>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID - Dashboard</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/public_sources/css/main.css">
    <style>
        #orderSuccessModal {
            backdrop-filter: blur(5px);
        }

        @keyframes modal-appear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #orderSuccessModal>div>div {
            animation: modal-appear 0.3s ease-out;
        }
    </style>
</head>

<body class="bg-white">
    <?php include 'components/menu.php'; ?>

    <a href="components/projects/projects.php" style="position:absolute; top: 250px">Projeler</a>

    <div id="orderSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6 relative">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <!-- Title -->
                <h3 class="text-center text-xl font-semibold text-gray-900 mb-2">
                    Payment Successful!
                </h3>

                <!-- Message -->
                <div class="text-center mb-6">
                    <p class="text-gray-500">Your payment has been processed successfully. The amount is now in escrow
                        and will be released to the freelancer upon successful delivery.</p>
                </div>

                <!-- Timeline Steps -->
                <div class="space-y-4 mb-6">
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Payment Completed</p>
                            <p class="text-gray-500">Funds are securely held in escrow</p>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-medium">2</span>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Awaiting Delivery</p>
                            <p class="text-gray-500">Freelancer will start working on your order</p>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                            <span class="text-gray-500 font-medium">3</span>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">Release Payment</p>
                            <p class="text-gray-500">After you approve the delivery</p>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <button onclick="closeOrderModal()"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Got it, thanks!
                </button>
            </div>
        </div>
    </div>

    <?php if ($hasActiveOrders): ?>
        <a href="/public/views/orders.php"
            class="fixed bottom-8 right-8 bg-blue-600 text-white px-6 py-3 rounded-full shadow-lg hover:bg-blue-700 transition-all duration-300 flex items-center gap-2 group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            My Orders
            <span
                class="bg-white text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-semibold">
                <?php echo $hasActiveOrders; ?>
            </span>
        </a>
    <?php endif; ?>

    <div class="search-header">
        <h1 class="search-title">LureID'yi Keşfet</h1>
        <p class="search-subtitle">Projelerden eğitime, assetlerden ilhama; her şey tüm kullanıcılar için tek bir
            platformda!</p>

        <div class="search-box-container">
            <input type="text" class="search-box" placeholder="Ne arıyorsunuz?">
            <div class="search-type">
                <div class="search-type-trigger">
                    <span>Paylaşımlar</span>
                    <img src="/sources/icons/linear/arrow-down.svg" alt="arrow" class="dropdown-arrow">
                </div>
                <div class="search-type-dropdown">
                    <div class="search-type-item" data-value="boards">İlham Panoları</div>
                    <div class="search-type-item active" data-value="shots">Paylaşımlar</div>
                    <div class="search-type-item" data-value="courses">Eğitimler</div>
                    <div class="search-type-item" data-value="jobs">İş İlanları</div>
                    <div class="search-type-item" data-value="marketplace">Mağaza</div>
                </div>
            </div>
            <button class="search-button">
                <img src="/sources/icons/linear/search-normal.svg" alt="Search">
            </button>
        </div>

        <div class="trending-searches">
            <span class="trending-label">Popüler Aramalar:</span>
            <div class="trending-tag hover-text" data-hover="Logo Tasarım"><span>Logo Tasarım</span></div>
            <div class="trending-tag hover-text" data-hover="Web Arayüz"><span>Web Arayüz</span></div>
            <div class="trending-tag hover-text" data-hover="Mobil Uygulama"><span>Mobil Uygulama</span></div>
            <div class="trending-tag hover-text" data-hover="3D Modelleme"><span>3D Modelleme</span></div>
            <div class="trending-tag hover-text" data-hover="İllüstrasyon"><span>İllüstrasyon</span></div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-container">
            <div class="filter-left">
                <div class="view-type">
                    <div class="view-type-trigger">
                        <img src="/sources/icons/linear/arrow-down.svg" alt="arrow" class="dropdown-arrow">
                        <span>Takip edilenler</span>
                    </div>
                    <div class="view-type-dropdown">
                        <div class="view-type-item active" data-value="following">Takip edilenler</div>
                        <div class="view-type-item" data-value="new">Yeni</div>
                        <div class="view-type-item" data-value="popular">Popüler</div>
                    </div>
                </div>
            </div>
            <div class="filter-center">
                <h2>Keşfet</h2>
            </div>
            <div class="filter-right">
                <button class="filter-button">
                    <img src="/sources/icons/linear/filter.svg" alt="Filter">
                    <span>Filtrele</span>
                </button>
            </div>
        </div>

        <div class="filter-options" style="display: none;">
            <div class="filter-row">
                <div class="color-picker-container">
                    <label class="filter-label">Renk</label>
                    <div class="input-with-icon">
                        <img src="/sources/icons/linear/brush.svg" alt="color" class="input-icon">
                        <input type="text" class="hex-input" readonly>
                        <div class="color-dropdown">
                            <div class="color-option" style="background-color: #FF5733" data-color="#FF5733"></div>
                            <div class="color-option" style="background-color: #33FF57" data-color="#33FF57"></div>
                            <div class="color-option" style="background-color: #3357FF" data-color="#3357FF"></div>
                            <div class="color-option" style="background-color: #FF33F6" data-color="#FF33F6"></div>
                            <div class="color-option" style="background-color: #33FFF6" data-color="#33FFF6"></div>
                            <div class="color-option" style="background-color: #FFB533" data-color="#FFB533"></div>
                            <div class="color-option" style="background-color: #FF3333" data-color="#FF3333"></div>
                            <div class="color-option" style="background-color: #33FF33" data-color="#33FF33"></div>
                            <div class="color-option" style="background-color: #3333FF" data-color="#3333FF"></div>
                            <div class="color-option" style="background-color: #000000" data-color="#000000"></div>
                        </div>
                    </div>
                </div>
                <div class="tag-search-container">
                    <label class="filter-label">Etiketler</label>
                    <div class="input-with-icon">
                        <img src="/sources/icons/linear/search-normal.svg" alt="search" class="input-icon">
                        <input type="text" class="tag-search">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="discover-container">
        <div class="grid-container">
            <?php
            $itemsPerPage = 15;
            for ($i = 1; $i <= $itemsPerPage; $i++) {
                echo generateCard($i);
            }
            ?>
        </div>
    </div>

    <div class="load-more-container">
        <button class="load-more-button">
            <span>Daha fazla göster</span>
            <img src="/sources/icons/linear/arrow-down.svg" alt="arrow" class="load-more-arrow">
        </button>
    </div>

    <!-- Öne Çıkan Paylaşımlar -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Paylaşımlar</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Etiket">
                    <span>Etiket</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@tasarimci">
                            <span>@tasarimci</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="Öne Çıkan Tasarım">
                        <span>Öne Çıkan Tasarım</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="1.2K">
                                <span>1.2K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="234">
                                <span>234</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="5.6K">
                                <span>5.6K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan İlanlar -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan İlanlar</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="İlan">
                    <span>İlan</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@sirket">
                            <span>@sirket</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="UI/UX Tasarımcı Aranıyor">
                        <span>UI/UX Tasarımcı Aranıyor</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="856">
                                <span>856</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="123">
                                <span>123</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="3.2K">
                                <span>3.2K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan Freelancerlar -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Freelancerlar</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Freelancer">
                    <span>Freelancer</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@freelancer">
                            <span>@freelancer</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="Logo Tasarım Uzmanı">
                        <span>Logo Tasarım Uzmanı</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="2.1K">
                                <span>2.1K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="445">
                                <span>445</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="7.8K">
                                <span>7.8K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan İş İlanları -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan İş İlanları</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="İş İlanı">
                    <span>İş İlanı</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@teknofirma">
                            <span>@teknofirma</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="Senior Grafik Tasarımcı">
                        <span>Senior Grafik Tasarımcı</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="567">
                                <span>567</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="89">
                                <span>89</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="2.3K">
                                <span>2.3K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan Eğitimler -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Eğitimler</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Eğitim">
                    <span>Eğitim</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@egitmen">
                            <span>@egitmen</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="UI/UX Tasarım Eğitimi">
                        <span>UI/UX Tasarım Eğitimi</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="3.4K">
                                <span>3.4K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="678">
                                <span>678</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="9.1K">
                                <span>9.1K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan Eğitimciler -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Eğitimciler</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Eğitimci">
                    <span>Eğitimci</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@uzmanegitmen">
                            <span>@uzmanegitmen</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="Motion Grafik Uzmanı">
                        <span>Motion Grafik Uzmanı</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="4.5K">
                                <span>4.5K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="890">
                                <span>890</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="12.</span>
                       <span class=" hover-text" data-hover="12.4K">
                                <span>12.4K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan Assetler -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Assetler</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Asset">
                    <span>Asset</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@assetcreator">
                            <span>@assetcreator</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="3D Model Pack">
                        <span>3D Model Pack</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="2.8K">
                                <span>2.8K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="567">
                                <span>567</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="8.9K">
                                <span>8.9K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Öne Çıkan Projeler -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Öne Çıkan Projeler</h2>
            <a href="#" class="view-all">Tümünü Gör</a>
        </div>
        <div class="grid-container featured-grid">
            <div class="card">
                <div class="card-image"></div>
                <div class="tag-box hover-text" data-hover="Proje">
                    <span>Proje</span>
                </div>
                <div class="action-icons">
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/bookmark.svg" alt="Save">
                        <div class="icon-tooltip">Kaydet</div>
                    </div>
                    <div class="icon-wrapper">
                        <img src="/sources/icons/linear/heart.svg" alt="Like">
                        <div class="icon-tooltip">Beğen</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <div class="card-avatar"></div>
                        <span class="card-username hover-text" data-hover="@projectmaster">
                            <span>@projectmaster</span>
                        </span>
                    </div>
                    <h3 class="card-title hover-text" data-hover="E-Ticaret Projesi">
                        <span>E-Ticaret Projesi</span>
                    </h3>
                    <div class="stats-container">
                        <div class="stat-item">
                            <img src="/sources/icons/linear/heart.svg" alt="likes">
                            <span class="hover-text" data-hover="5.6K">
                                <span>5.6K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/bookmark.svg" alt="saves">
                            <span class="hover-text" data-hover="1.2K">
                                <span>1.2K</span>
                            </span>
                        </div>
                        <div class="stat-item">
                            <img src="/sources/icons/linear/eye.svg" alt="views">
                            <span class="hover-text" data-hover="15.3K">
                                <span>15.3K</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-top">
                <div class="footer-logo">
                    <h2>lureid</h2>
                    <p>Tasarımcılar için dijital platform</p>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h3>Platform</h3>
                        <ul>
                            <li><a href="#">Keşfet</a></li>
                            <li><a href="#">İş İlanları</a></li>
                            <li><a href="#">Freelancerlar</a></li>
                            <li><a href="#">Eğitimler</a></li>
                            <li><a href="#">Assetler</a></li>
                            <li><a href="#">Projeler</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>Şirket</h3>
                        <ul>
                            <li><a href="#">Hakkımızda</a></li>
                            <li><a href="#">Kariyer</a></li>
                            <li><a href="#">Blog</a></li>
                            <li><a href="#">İletişim</a></li>
                            <li><a href="#">Basın</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>Destek</h3>
                        <ul>
                            <li><a href="#">Yardım Merkezi</a></li>
                            <li><a href="#">Gizlilik Politikası</a></li>
                            <li><a href="#">Kullanım Şartları</a></li>
                            <li><a href="#">SSS</a></li>
                            <li><a href="#">Güvenlik</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>Topluluk</h3>
                        <ul>
                            <li><a href="#">Forum</a></li>
                            <li><a href="#">Discord</a></li>
                            <li><a href="#">Telegram</a></li>
                            <li><a href="#">Medium</a></li>
                            <li><a href="#">Reddit</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 LUREID. Tüm hakları saklıdır.</p>
                <div class="social-links">
                    <a href="#"><img src="/sources/icons/linear/twitter.svg" alt="Twitter"></a>
                    <a href="#"><img src="/sources/icons/linear/instagram.svg" alt="Instagram"></a>
                    <a href="#"><img src="/sources/icons/linear/linkedin.svg" alt="LinkedIn"></a>
                    <a href="#"><img src="/sources/icons/linear/youtube.svg" alt="YouTube"></a>
                    <a href="#"><img src="/sources/icons/linear/facebook.svg" alt="Facebook"></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Check for the success cookie
            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
            }

            if (getCookie('show_order_success') === '1') {
                // Show modal
                document.getElementById('orderSuccessModal').classList.remove('hidden');
                // Remove cookie
                document.cookie = "show_order_success=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            }
        });

        function closeOrderModal() {
            document.getElementById('orderSuccessModal').classList.add('hidden');
        }

        $(document).ready(function () {
            // Settings Tab Click Handler
            $('.settingsTab').click(function () {
                const tab = $(this).data('tab');
                window.location.href = 'components/settings/settings.php?tab=' + tab;
            });
        });
    </script>
    <script src="/public_sources/javascript/main.js"></script>
</body>

</html>