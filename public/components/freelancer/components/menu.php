<?php
// menu.php
$menuStructure = [
    [
        'type' => 'back',
        'text' => '< Ana sayfaya dön',
        'url' => '/public/index.php'
    ],
    [
        'type' => 'section',
        'title' => 'GENEL',
        'items' => [
            [
                'text' => 'Panel',
                'icon' => 'home-2.svg',
                'url' => 'dashboard.php'
            ]
        ]
    ],
    [
        'type' => 'section',
        'title' => 'Freelancer Paneli',
        'items' => [
            [
                'text' => 'İlanlarım',
                'icon' => 'note-2.svg',
                'url' => 'gigs.php'
            ],
            [
                'text' => 'İşler',
                'icon' => 'task-square.svg',
                'url' => 'jobs.php'
            ]
        ]
    ],
    [
        'type' => 'section',
        'title' => 'Ürün & Satış Merkezi',
        'items' => [
            [
                'text' => 'Ürünlerim',
                'icon' => 'shop.svg',
                'url' => 'my-products.php'
            ],
            [
                'text' => 'Siparişler',
                'icon' => 'shopping-cart.svg',
                'url' => 'orders.php'
            ],
            [
                'text' => 'Müşteriler',
                'icon' => 'profile-2user.svg',
                'url' => 'customers.php'
            ]
        ]
    ],
    [
        'type' => 'section',
        'title' => 'Hesap & Veriler',
        'items' => [
            [
                'text' => 'Ayarlar',
                'icon' => 'setting-2.svg',
                'url' => 'settings.php'
            ]
        ]
    ]
];

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentPage = basename($currentPath);
$isHome = $currentPage === 'dashboard.php' || $currentPage === 'index.php';
?>

<style>
    *,
    body,
    html {
        padding: 0;
        margin: 0;
        font-family: 'Poppins', sans-serif;
    }

    .sidebar {
        position: fixed;
        width: 280px;
        height: 100vh;
        background-color: white;
        border-right: 1px solid #dedede;
        display: flex;
        flex-direction: column;
        padding: 20px 0;
        box-sizing: border-box;
    }

    .logo {
        font-family: 'Bebas Neue', sans-serif;
        letter-spacing: 2px;
        text-align: center;
        font-size: 24px;
        margin-bottom: 30px;
        flex-shrink: 0;
    }

    .main-content {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding-bottom: 20px;
    }

    .back-button {
        display: flex;
        align-items: center;
        padding: 10px 20px;
        font-size: 12px;
        color: #666;
        text-decoration: none;
        margin: 0 20px 15px 20px;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .back-button:hover {
        color: #000;
    }

    .menu-section {
        margin: 15px 20px 10px 40px;
        font-size: 13px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 10px 20px;
        font-size: 12px;
        color: #000;
        text-decoration: none;
        transition: all 0.3s ease;
        margin: 2px 20px;
        gap: 10px;
        flex-shrink: 0;
    }

    .menu-item:hover {
        background-color: #f5f5f5;
        border-radius: 12px;
    }

    .menu-item.active {
        background-color: #f5f5f5;
        border-radius: 12px;
        font-weight: 600;
    }

    .menu-item img {
        width: 20px;
        height: 20px;
        opacity: 0.7;
    }

    .create-button {
        margin: 0 20px 20px 20px;
        padding: 12px;
        background-color: #4F46E5;
        color: white;
        text-decoration: none;
        text-align: center;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .create-button:hover {
        opacity: 0.9;
    }
</style>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">LUREID</div>

    <div class="main-content">
        <?php foreach ($menuStructure as $menu): ?>
            <?php if ($menu['type'] === 'back'): ?>
                <a href="<?= $menu['url'] ?>" class="back-button">
                    <?= $menu['text'] ?>
                </a>
            <?php elseif ($menu['type'] === 'section'): ?>
                <div class="menu-section"><?= $menu['title'] ?></div>
                <?php foreach ($menu['items'] as $item): ?>
                    <a href="<?= $item['url'] ?>" class="menu-item <?= $currentPage === $item['url'] ? 'active' : '' ?>">
                        <img src="/sources/icons/bulk/<?= $item['icon'] ?>" alt="<?= $item['text'] ?>">
                        <?= $item['text'] ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <a href="#" class="create-button">Oluştur</a>
</div>