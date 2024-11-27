<?php
// menu.php
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: #fff;
            padding-top: 50px;
        }

        /* Menu Wrapper & Container */
        .menu-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 0;
            display: flex;
            justify-content: center;
            transform: translateY(-100px);
            z-index: 1000;
        }

        .menu-container {
            width: 120px;
            height: 60px;
            background: #fff;
            border: 1px solid #dedede;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: visible;
            opacity: 0;
            transform: scale(0);
            margin-top: 20px;
            transition: border-radius 0.3s ease;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
        }

        /* Main Menu */
        .main-menu {
            width: 100%;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            background: #fff;
            position: relative;
            z-index: 2;
            border-radius: 15px;
            transition: border-radius 0.3s ease;
            overflow: hidden;
        }

        .left-menu {
            display: flex;
            gap: 24px;
            visibility: hidden;
            align-items: center;
        }

        /* Menu Items */
        .menu-item {
            color: #000;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 10px;
            transition: background 0.3s;
            cursor: pointer;
            opacity: 0;
            transform: translateY(-20px);
            font-size: 12px;
            font-weight: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .menu-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .menu-item.active {
            background: rgba(0, 0, 0, 0.05);
        }

        /* LURE Text */
        .lure-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Bebas Neue', sans-serif;
            font-size: 20px;
            color: #000;
            opacity: 0;
            padding: 0 5px;
            letter-spacing: 2px;
            white-space: nowrap;
            height: 100%;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        /* Search */
        .center-search {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            height: 36px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            font-size: 14px;
            font-weight: 500;
        }

        .ctrl-box {
            background: rgba(0, 0, 0, 0.05);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Right Icons */
        .right-icons {
            display: flex;
            gap: 24px;
            align-items: baseline;
            visibility: hidden;
        }

        .icon-item {
            cursor: pointer;
            opacity: 0;
            transform: translateY(-20px);
        }

        .submenu-container {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #fff;
            border: 1px solid #dedede;
            border-top: none;
            border-radius: 0 0 15px 15px;
            overflow: hidden;
            opacity: 0;
            height: 0;
            margin-top: -1px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .submenu {
            padding: 24px;
            opacity: 0;
            transform: translateY(20px);
            max-width: 1200px;
        }

        .submenu-layout {
            display: none;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .submenu-layout.active {
            display: grid;
        }

        /* Submenu Column */
        .submenu-column {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .submenu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #111;
            padding: 0 12px;
            margin-bottom: 4px;
            opacity: 0;
            transform: translateY(-10px);
            text-align: left;
            /* Sol hizalama için */
        }

        .submenu-header i {
            font-size: 20px;
            opacity: 0.8;
        }

        /* Submenu Items */
        .submenu-items {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0;
            transform: translateY(-10px);
            text-decoration: none;
            color: inherit;
            text-align: left;
            /* Sol hizalama için */
        }

        .submenu-item:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .submenu-item i {
            font-size: 20px;
            opacity: 0.7;
        }

        .submenu-content {
            flex: 1;
            text-align: left;
        }

        .submenu-content h4 {
            font-size: 13px;
            font-weight: 500;
            color: #111;
            margin-bottom: 2px;
            text-align: left;
        }

        .submenu-content p {
            font-size: 11px;
            color: #666;
            text-align: left;
        }

        /* Profile & Settings Styles */
        .profile {
            padding-left: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            font-size: 12px;
            text-align: right;
        }

        .profile img.avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            object-fit: cover;
        }

        .profile .icon {
            transition: transform 0.3s ease;
        }

        .profile.active .icon {
            transform: rotate(180deg);
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 240px;
            padding: 13px;
            background: #fff;
            border: 1px solid #dedede;
            border-radius: 15px;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.95);
            transform-origin: top right;
        }

        .profile-menu-item {
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-10px);
            margin: 4px 0;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
        }

        .profile-menu-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* Settings Dropdown */
        .settings-dropdown {
            background: rgba(0, 0, 0, 0.02);
            overflow: hidden;
            height: 0;
        }

        .setting-item {
            padding: 8px 15px;
            font-size: 12px;
            cursor: pointer;
            opacity: 0;
            transform: translateY(-10px);
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(0, 0, 0, 0.6);
            text-decoration: none;
        }

        .setting-item:hover {
            background: rgba(0, 0, 0, 0.05);
            color: rgba(0, 0, 0, 0.8);
        }

        /* Icon Styles */
        .icon {
            width: 24px;
            height: 24px;
            filter: brightness(0);
        }

        .white-icon {
            width: 20px;
            height: 20px;
        }

        .setting-item .white-icon {
            opacity: 0.6;
        }

        .setting-item:hover .white-icon {
            opacity: 0.8;
        }

        /* Wallet Dropdown */
        .wallet-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 180px;
            width: 400px;
            padding: 13px;
            background: #fff;
            border: 1px solid #dedede;
            border-radius: 15px;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.95);
            transform-origin: top right;
        }

        .wallet-header {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 10px;
        }

        .wallet-balance {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            margin: 4px 0;
            border-radius: 10px;
            opacity: 0;
            transform: translateY(-10px);
        }

        .wallet-balance:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* Wallet Components */
        .balance-info {
            display: flex;
            flex-direction: column;
            margin-left: 12px;
        }

        .balance-item {
            display: flex;
            align-items: center;
        }

        .white-icon.balance-icon {
            width: 20px;
            height: 20px;
        }

        .balance-label {
            font-size: 12px;
            color: #666;
        }

        .balance-amount {
            font-size: 16px;
            font-weight: 600;
        }

        /* Transaction Styles */
        .recent-transactions-title {
            padding: 10px 15px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #f0f0f0;
            margin-top: 10px;
        }

        .transaction-item {
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            opacity: 0;
            transform: translateY(-10px);
            border-radius: 10px;
        }

        .transaction-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .transaction-icon {
            width: 20px;
            height: 20px;
        }

        .transaction-amount {
            font-weight: 600;
        }

        .transaction-amount.positive {
            color: #22c55e;
        }

        .transaction-amount.negative {
            color: #ef4444;
        }

        /* View All Link */
        .view-all-link {
            display: block;
            text-align: center;
            padding: 10px 15px;
            color: #3b82f6;
            font-size: 12px;
            border-top: 1px solid #f0f0f0;
            margin-top: 10px;
            text-decoration: none;
        }

        .view-all-link:hover {
            background: rgba(59, 130, 246, 0.1);
            border-radius: 10px;
        }

        /* Special States */
        .settings-arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .settings-arrow.active {
            transform: rotate(180deg);
        }

        .logout-item {
            color: #ff4757;
        }

        .logout-item .white-icon {
            filter: invert(48%) sepia(54%) saturate(2673%) hue-rotate(325deg) brightness(101%) contrast(101%);
        }

        .logout-item:hover {
            background: rgba(255, 71, 87, 0.1);
        }
    </style>
</head>

<body>
    <div class="menu-wrapper">
        <div class="menu-container">
            <div class="main-menu">
                <div class="left-menu">
                    <a href="/public/index.php" class="menu-item">Ana Sayfa</a>
                    <a class="menu-item">Market</a>
                    <a class="menu-item">Topluluk</a>
                    <a class="menu-item">Eğitim</a>
                    <a class="menu-item">Projeler</a>
                </div>

                <div class="lure-text">LURE</div>
                <div class="center-search">
                    <div class="ctrl-box">CTRL</div>
                    <span>Arama</span>
                </div>

                <div class="right-icons">
                    <div class="icon-item" id="walletIcon">
                        <img src="/sources/icons/bulk/wallet.svg" alt="wallet" class="white-icon">
                    </div>
                    <div class="icon-item">
                        <img src="/sources/icons/bulk/notification.svg" alt="notification" class="white-icon">
                    </div>
                    <div class="icon-item">
                        <img src="/sources/icons/bulk/message.svg" alt="message" class="white-icon">
                    </div>
                    <div class="icon-item profile">
                        <img src="/sources/icons/bulk/arrow-down.svg" alt="arrow" class="icon">
                        <div class="profile-info">
                            <span><?php echo htmlspecialchars($_SESSION['user_data']['username']); ?></span>
                            <span><?php echo htmlspecialchars($_SESSION['user_data']['email']); ?></span>
                        </div>
                        <img src="/public/<?php echo htmlspecialchars($_SESSION['user_data']['profile_photo_url']); ?>"
                            alt="Profile" class="avatar">
                    </div>
                </div>
            </div>

            <!-- Submenu Container -->
            <div class="submenu-container">
                <div class="submenu">
                    <!-- Market Menüsü -->
                    <div class="submenu-layout market-layout">
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/shop.svg" alt="shop" class="white-icon">
                                Freelancer Pazarı
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/profile-2user.svg" alt="users" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Aktif Freelancerlar</h4>
                                        <p>Uzman freelancerlar ile iletişime geç</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/note-favorite.svg" alt="note" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Proje Teklifleri</h4>
                                        <p>Güncel proje tekliflerini incele</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/task-square.svg" alt="task" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Hizmetler</h4>
                                        <p>Kategorilere göre hizmetleri keşfet</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/gallery.svg" alt="gallery" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Portfolyolar</h4>
                                        <p>Freelancerların çalışmalarını incele</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/star-1.svg" alt="star" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Değerlendirmeler</h4>
                                        <p>Kullanıcı yorumları ve puanlamalar</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/chart-success.svg" alt="chart" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Başarı Hikayeleri</h4>
                                        <p>Tamamlanan projeler ve deneyimler</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/shopping-bag.svg" alt="shopping" class="white-icon">
                                Tema & Assetler
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/brush-2.svg" alt="brush" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Tema Mağazası</h4>
                                        <p>Özelleştirilebilir hazır temalar</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/bezier.svg" alt="bezier" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>UI Kit'ler</h4>
                                        <p>Kullanıma hazır arayüz bileşenleri</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/shapes-1.svg" alt="shapes" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Grafik Paketleri</h4>
                                        <p>İkon, illüstrasyon ve görseller</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/setting-2.svg" alt="setting" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Plugin/Eklentiler</h4>
                                        <p>Projeleriniz için hazır eklentiler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/code.svg" alt="code" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Site Şablonları</h4>
                                        <p>Sektörlere özel hazır şablonlar</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/code-circle.svg" alt="code-circle"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Premium Kod Blokları</h4>
                                        <p>Özelleştirilebilir kod parçacıkları</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/briefcase.svg" alt="briefcase" class="white-icon">
                                İş İlanları
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/edit.svg" alt="edit" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>İlan Oluştur/Düzenle</h4>
                                        <p>Yeni iş ilanı yayınla</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/wallet-money.svg" alt="wallet" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Bütçe Planlama</h4>
                                        <p>Proje bütçesi ve ödeme planı</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Topluluk Menüsü -->
                    <div class="submenu-layout community-layout">
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/element-4.svg" alt="element" class="white-icon">
                                İçerik Akışı
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/monitor-mobbile.svg" alt="monitor"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Proje Showcase</h4>
                                        <p>Topluluk projelerini keşfet</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/bezier.svg" alt="bezier" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>UI/UX Tasarımlar</h4>
                                        <p>İlham verici arayüz tasarımları</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/code-1.svg" alt="code" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Kod Parçacıkları</h4>
                                        <p>Faydalı kod örnekleri</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/heart.svg" alt="heart" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>İlham Veren Çalışmalar</h4>
                                        <p>Öne çıkan projeler</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/people.svg" alt="people" class="white-icon">
                                Sosyal Etkileşim
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/message-question.svg" alt="message"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Soru & Cevap</h4>
                                        <p>Toplulukla bilgi paylaşımı</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/hierarchy-square-2.svg" alt="hierarchy"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Problem Çözümleri</h4>
                                        <p>Sorunlara pratik çözümler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/refresh-square-2.svg" alt="refresh"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Code Review İstekleri</h4>
                                        <p>Kod inceleme ve öneriler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/star.svg" alt="star" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Bug Bounty</h4>
                                        <p>Hata bildirimleri ve ödüller</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/book-1.svg" alt="book" class="white-icon">
                                Bilgi Havuzu
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Yazılı Kaynaklar</h4>
                                        <p>Makaleler ve dökümanlar</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/video-square.svg" alt="video" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Video İçerikler</h4>
                                        <p>Eğitici video seriler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/headphones.svg" alt="headphones" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Podcast'ler</h4>
                                        <p>Sesli içerikler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/chart-square.svg" alt="chart" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Trend Teknolojiler</h4>
                                        <p>Güncel teknoloji haberleri</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Eğitim Menüsü -->
                    <div class="submenu-layout education-layout">
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/book.svg" alt="book" class="white-icon">
                                Öğrenme Merkezi
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/play-circle.svg" alt="play" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Video Kurslar</h4>
                                        <p>Kapsamlı eğitim serileri</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/task.svg" alt="task" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Uygulama Görevleri</h4>
                                        <p>Pratik yapma imkanı</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/medal.svg" alt="medal" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Quiz & Testler</h4>
                                        <p>Kendini değerlendir</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/award.svg" alt="award" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Sertifika Programları</h4>
                                        <p>Uzmanlığını belgele</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/video.svg" alt="video" class="white-icon">
                                Sanal Sınıflar
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/video-circle.svg" alt="video" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Canlı Dersler</h4>
                                        <p>Etkileşimli online eğitim</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/document-copy.svg" alt="document"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Ödev Takibi</h4>
                                        <p>Proje ve ödev yönetimi</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/people.svg" alt="people" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Grup Projeleri</h4>
                                        <p>Takım çalışmaları</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/chart-success.svg" alt="chart" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Öğrenci İlerlemesi</h4>
                                        <p>Gelişim takibi</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/teacher.svg" alt="teacher" class="white-icon">
                                Eğitmen Merkezi
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/profile-circle.svg" alt="profile"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Eğitmen Profili</h4>
                                        <p>Profil ve portfolio yönetimi</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Ders İçerik Yönetimi</h4>
                                        <p>Müfredat ve içerik planlama</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/calendar.svg" alt="calendar" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Ders Programı</h4>
                                        <p>Zaman planlaması</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/box-1.svg" alt="box" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Materyal Üretimi</h4>
                                        <p>Eğitim içeriği hazırlama</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Projeler Menüsü -->
                    <div class="submenu-layout projects-layout">
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/code.svg" alt="code" class="white-icon">
                                Web IDE
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/code-circle.svg" alt="code" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Çoklu Dil Desteği</h4>
                                        <p>Tüm web teknolojileri</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/magic-star.svg" alt="magic" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Otomatik Tamamlama</h4>
                                        <p>Akıllı kod önerileri</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/monitor.svg" alt="monitor" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Canlı Önizleme</h4>
                                        <p>Gerçek zamanlı sonuç</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/warning-2.svg" alt="debug" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Hata Ayıklama</h4>
                                        <p>Debug araçları</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/hierarchy.svg" alt="hierarchy" class="white-icon">
                                Proje Yönetimi
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/task-square.svg" alt="task" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Task Takibi</h4>
                                        <p>Görev yönetimi</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/timer-1.svg" alt="timer" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Timeline</h4>
                                        <p>Zaman çizelgesi</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/people.svg" alt="people" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Ekip Yönetimi</h4>
                                        <p>Takım organizasyonu</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document"
                                        class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Dokümentasyon</h4>
                                        <p>Proje belgeleri</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="submenu-column">
                            <h3 class="submenu-header">
                                <img src="/sources/icons/bulk/box-1.svg" alt="box" class="white-icon">
                                Resource Hub
                            </h3>
                            <div class="submenu-items">
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/component.svg" alt="component" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Component Library</h4>
                                        <p>Hazır bileşenler</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/code-1.svg" alt="code" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Code Snippets</h4>
                                        <p>Kod parçacıkları</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/shapes-1.svg" alt="shapes" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Design Assets</h4>
                                        <p>Tasarım kaynakları</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/grid-edit.svg" alt="extension" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Extension Market</h4>
                                        <p>Eklenti mağazası</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-dropdown">
                <a href="/<?php echo htmlspecialchars($_SESSION['user_data']['username']); ?>"
                    class="profile-menu-item">
                    <img src="/sources/icons/bulk/user.svg" alt="profile" class="white-icon">
                    <span>Profili Görüntüle</span>
                </a>
                <div class="profile-menu-item settings-trigger">
                    <img src="/sources/icons/bulk/setting.svg" alt="settings" class="white-icon">
                    <span>Ayarlar</span>
                    <img src="/sources/icons/bulk/arrow-down.svg" alt="arrow" class="white-icon settings-arrow">
                </div>
                <div class="settings-dropdown">
                    <a href="components/settings/settings.php?tab=profile" class="setting-item">
                        <img src="/sources/icons/bulk/user-edit.svg" alt="profile" class="white-icon">
                        Profil Ayarları
                    </a>
                    <a href="components/settings/settings.php?tab=security" class="setting-item">
                        <img src="/sources/icons/bulk/shield-tick.svg" alt="security" class="white-icon">
                        Güvenlik
                    </a>
                    <a href="components/settings/settings.php?tab=notifications" class="setting-item">
                        <img src="/sources/icons/bulk/notification-bing.svg" alt="notification" class="white-icon">
                        Bildirim Ayarları
                    </a>
                    <a href="components/settings/settings.php?tab=payment" class="setting-item">
                        <img src="/sources/icons/bulk/wallet-money.svg" alt="payment" class="white-icon">
                        Ödeme ve Finansal İşlemler
                    </a>
                    <a href="components/settings/settings.php?tab=privacy" class="setting-item">
                        <img src="/sources/icons/bulk/lock.svg" alt="privacy" class="white-icon">
                        Gizlilik Ayarları
                    </a>
                    <a href="components/settings/settings.php?tab=account" class="setting-item">
                        <img src="/sources/icons/bulk/cloud.svg" alt="account" class="white-icon">
                        Hesap ve Veriler
                    </a>
                    <a href="components/settings/settings.php?tab=language" class="setting-item">
                        <img src="/sources/icons/bulk/language-square.svg" alt="language" class="white-icon">
                        Dil ve Bölge
                    </a>
                    <a href="components/settings/settings.php?tab=appearance" class="setting-item">
                        <img src="/sources/icons/bulk/brush.svg" alt="theme" class="white-icon">
                        Görünüm Tema
                    </a>
                </div>
                <?php if (isset($isFreelancer) && $isFreelancer): ?>
                    <a href="/public/components/freelancer/dashboard.php" class="profile-menu-item">
                        <img src="/sources/icons/bulk/briefcase.svg" alt="freelancer" class="white-icon">
                        <span>Freelancer</span>
                    </a>
                <?php else: ?>
                    <a href="/public/components/freelancer/registration.php" class="profile-menu-item">
                        <img src="/sources/icons/bulk/briefcase.svg" alt="freelancer" class="white-icon">
                        <span>Freelancer Ol</span>
                    </a>
                <?php endif; ?>
                <form method="POST" action="/auth/logout.php" class="profile-menu-item logout-item">
                    <img src="/sources/icons/bulk/logout.svg" alt="logout" class="white-icon">
                    <button type="submit"
                        style="background: none; border: none; color: inherit; width: 100%; text-align: left;">
                        Çıkış Yap
                    </button>
                </form>
            </div>
            <div class="wallet-dropdown">
                <div class="wallet-header">
                    <h3 class="text-sm font-semibold">Cüzdan</h3>
                </div>
                <div class="wallet-balance">
                    <div class="balance-item">
                        <img src="/sources/icons/bulk/wallet.svg" alt="wallet" class="white-icon balance-icon">
                        <div class="balance-info">
                            <span class="balance-label">Bakiye</span>
                            <span class="balance-amount">₺<span id="dropdownBalance">0.00</span></span>
                        </div>
                    </div>
                </div>
                <div class="wallet-balance">
                    <div class="balance-item">
                        <img src="/sources/icons/bulk/coin.svg" alt="coin" class="white-icon balance-icon">
                        <div class="balance-info">
                            <span class="balance-label">Jeton</span>
                            <span class="balance-amount"><span id="dropdownCoins">0</span> 🪙</span>
                        </div>
                    </div>
                </div>
                <div class="recent-transactions-title">
                    Son İşlemler
                </div>
                <div id="dropdownTransactions"></div>
                <a href="components/wallet/wallet.php" class="view-all-link">
                    Tümünü Görüntüle
                </a>
            </div>
        </div>
    </div>

    <script>
        // Center search HTML'ini güncelleyelim
        document.querySelector('.center-search').innerHTML = `
    <div class="ctrl-box">CTRL</div>
    <span>Arama</span>
    <div class="search-container" style="display: none">
        <input type="text" class="search-input" placeholder="Aramak için yazın...">
        <img src="/sources/icons/bulk/search-normal.svg" alt="search" class="search-icon white-icon">
    </div>
`;

        // CSS eklemeleri
        const style = document.createElement('style');
        style.textContent = `
.center-search {
    position: relative;
    min-width: 260px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.search-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transform: scale(0.95);
}

.search-input {
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: 8px;
    padding: 0 40px 0 12px;
    font-size: 14px;
    color: #000;
    outline: none;
}

.search-input::placeholder {
    color: rgba(0, 0, 0, 0.5);
}

.search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    opacity: 0.6;
    pointer-events: none;
}
`;
        document.head.appendChild(style);

        // Arama kontrolü için event listener'ları ve animasyonları güncelleyelim
        document.addEventListener('DOMContentLoaded', () => {
            const centerSearch = document.querySelector('.center-search');
            const ctrlBox = centerSearch.querySelector('.ctrl-box');
            const searchText = centerSearch.querySelector('span');
            const searchContainer = centerSearch.querySelector('.search-container');
            const searchInput = centerSearch.querySelector('.search-input');
            let isSearchActive = false;

            function openSearch() {
                if (!isSearchActive) {
                    const tl = gsap.timeline();

                    // CTRL box ve text animasyonu
                    tl.to([ctrlBox, searchText], {
                        opacity: 0,
                        scale: 0.8,
                        duration: 0.2,
                        ease: 'power2.in',
                        onComplete: () => {
                            ctrlBox.style.display = 'none';
                            searchText.style.display = 'none';
                            searchContainer.style.display = 'block';
                        }
                    })
                        // Search container animasyonu
                        .fromTo(searchContainer, {
                            opacity: 0,
                            scale: 0.95,
                            display: 'block'
                        }, {
                            opacity: 1,
                            scale: 1,
                            duration: 0.3,
                            ease: 'power2.out',
                            onComplete: () => {
                                searchInput.focus();
                            }
                        });

                    isSearchActive = true;
                }
            }

            function closeSearch() {
                if (isSearchActive) {
                    const tl = gsap.timeline();

                    // Search container animasyonu
                    tl.to(searchContainer, {
                        opacity: 0,
                        scale: 0.95,
                        duration: 0.2,
                        ease: 'power2.in',
                        onComplete: () => {
                            searchContainer.style.display = 'none';
                            ctrlBox.style.display = 'block';
                            searchText.style.display = 'block';
                        }
                    })
                        // CTRL box ve text animasyonu
                        .fromTo([ctrlBox, searchText], {
                            opacity: 0,
                            scale: 0.8,
                        }, {
                            opacity: 1,
                            scale: 1,
                            duration: 0.3,
                            ease: 'power2.out'
                        });

                    isSearchActive = false;
                }
            }

            // CTRL tuşu kontrolü
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Control') {
                    if (!isSearchActive) {
                        openSearch();
                    } else {
                        closeSearch();
                        searchInput.value = ''; // Input'u temizle
                    }
                    e.preventDefault();
                }

                // ESC tuşu ile kapatma
                if (e.key === 'Escape' && isSearchActive) {
                    closeSearch();
                    searchInput.value = ''; // Input'u temizle
                }
            });

            // Input dışına tıklanınca kapatma
            document.addEventListener('click', (e) => {
                if (isSearchActive && !centerSearch.contains(e.target)) {
                    closeSearch();
                    searchInput.value = ''; // Input'u temizle
                }
            });

            // Center search tıklama ile açma
            centerSearch.addEventListener('click', () => {
                if (!isSearchActive) {
                    openSearch();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const tl = gsap.timeline();
            const menuItems = document.querySelectorAll('.menu-item');
            const menuContainer = document.querySelector('.menu-container');
            const submenuContainer = document.querySelector('.submenu-container');
            const submenuLayouts = document.querySelectorAll('.submenu-layout');
            let activeButton = null;
            let isSubmenuOpen = false;

            // İlk animasyon
            tl.to('.menu-wrapper', {
                height: '80px',
                duration: 0.1,
                ease: 'none'
            })
                .to('.menu-wrapper', {
                    y: 0,
                    duration: 0.6,
                    ease: 'power3.inOut'
                })
                .to(['.menu-container', '.lure-text'], {
                    opacity: 1,
                    scale: 1.1,
                    duration: 0.4,
                    ease: 'power3.out'
                })
                .to('.menu-container', {
                    scale: 1,
                    duration: 0.2,
                    ease: 'power3.out'
                })
                .to('.menu-container', {
                    width: '1700px',
                    duration: 0.8,
                    ease: 'power4.inOut'
                })
                .to('.left-menu, .right-icons', {
                    visibility: 'visible',
                    duration: 0
                })
                .to('.menu-item', {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.1,
                    ease: 'power3.out'
                }, '-=0.2')
                .to('.icon-item', {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.1,
                    ease: 'power3.out'
                }, '-=0.3')
                .add(() => {
                    setTimeout(() => {
                        gsap.to('.lure-text', {
                            y: '100%',
                            opacity: 0,
                            duration: 0.5,
                            ease: 'power3.in',
                            onComplete: () => {
                                gsap.fromTo('.center-search',
                                    {
                                        y: '-100%',
                                        opacity: 0,
                                        visibility: 'visible'
                                    },
                                    {
                                        y: 0,
                                        opacity: 1,
                                        duration: 0.5,
                                        ease: 'power3.out'
                                    }
                                );
                            }
                        });
                    }, 2000);
                });

            // Submenu helpers
            function getSubmenuHeight(layout) {
                // Reset any height restrictions temporarily to get true height
                layout.style.display = 'grid';
                const trueHeight = layout.offsetHeight;

                // Add padding for container (24px top + 24px bottom = 48px)
                return trueHeight + 48;
            }

            function animateSubmenu(button, layout) {
                // Hide other layouts and show current one
                submenuLayouts.forEach(l => {
                    l.style.display = 'none';
                    l.classList.remove('active');
                });
                layout.classList.add('active');
                layout.style.display = 'grid';

                const submenuHeight = getSubmenuHeight(layout);
                const headers = layout.querySelectorAll('.submenu-header');
                const items = layout.querySelectorAll('.submenu-item');

                // Reset states
                gsap.set([headers, items], {
                    opacity: 0,
                    y: -10
                });

                // Animate submenu
                const tl = gsap.timeline();
                button.classList.add('active');

                tl.to('.menu-container', {
                    borderRadius: '15px 15px 0 0',
                    duration: 0.3,
                    ease: 'power3.out'
                })
                    .to('.main-menu', {
                        borderRadius: '15px 15px 0 0',
                        duration: 0.3,
                        ease: 'power3.out'
                    })
                    .to(submenuContainer, {
                        height: submenuHeight,
                        opacity: 1,
                        duration: 0.5,
                        ease: 'power3.out'
                    })
                    .to('.submenu', {
                        opacity: 1,
                        y: 0,
                        duration: 0.3,
                        ease: 'power3.out'
                    })
                    .to(headers, {
                        opacity: 1,
                        y: 0,
                        duration: 0.3,
                        stagger: 0.1,
                        ease: 'power3.out'
                    })
                    .to(items, {
                        opacity: 1,
                        y: 0,
                        duration: 0.3,
                        stagger: {
                            each: 0.05,
                            grid: [items.length / 3, 3],
                            from: "start"
                        },
                        ease: 'power3.out'
                    }, '-=0.2');

                isSubmenuOpen = true;
                activeButton = button;
            }

            function closeSubmenu(button, callback) {
                const tl = gsap.timeline({
                    onComplete: () => {
                        submenuLayouts.forEach(layout => layout.classList.remove('active'));
                        if (button) button.classList.remove('active');
                        if (callback) callback();
                    }
                });

                tl.to('.submenu-item', {
                    opacity: 0,
                    y: -10,
                    duration: 0.3,
                    stagger: 0.03,
                    ease: 'power3.in'
                })
                    .to('.submenu-header', {
                        opacity: 0,
                        y: -10,
                        duration: 0.3,
                        stagger: 0.03,
                        ease: 'power3.in'
                    }, '-=0.2')
                    .to(submenuContainer, {
                        height: 0,
                        opacity: 0,
                        duration: 0.5,  // Süreyi arttırdım
                        ease: 'power3.inOut'  // Ease'i değiştirdim
                    })
                    .to(['.menu-container', '.main-menu'], {
                        borderRadius: '15px',
                        duration: 0.3,
                        ease: 'power3.out'
                    });

                isSubmenuOpen = false;
                activeButton = null;
            }

            // Menu click handlers
            menuItems.forEach((button, index) => {
                if (index === 0) return; // Skip Ana Sayfa

                button.addEventListener('click', () => {
                    const targetLayout = submenuLayouts[index - 1];

                    if (isSubmenuOpen) {
                        if (activeButton === button) {
                            closeSubmenu(button);
                        } else {
                            closeSubmenu(activeButton, () => {
                                animateSubmenu(button, targetLayout);
                            });
                        }
                    } else {
                        animateSubmenu(button, targetLayout);
                    }
                });
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (isSubmenuOpen &&
                    !e.target.closest('.submenu-container') &&
                    !e.target.closest('.menu-item')) {
                    closeSubmenu(activeButton);
                }
            });

            // Hover animations
            const hoverableElements = document.querySelectorAll('.menu-item, .center-search, .icon-item');
            hoverableElements.forEach(element => {
                element.addEventListener('mouseenter', () => {
                    if (!element.classList.contains('active')) {
                        gsap.to(element, {
                            scale: 1.1,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    }
                });

                element.addEventListener('mouseleave', () => {
                    if (!element.classList.contains('active')) {
                        gsap.to(element, {
                            scale: 1,
                            duration: 0.3,
                            ease: 'power2.out'
                        });
                    }
                });
            });

            // Profile and Settings functionality
            const profileButton = document.querySelector('.profile');
            const profileDropdown = document.querySelector('.profile-dropdown');
            const settingsTrigger = document.querySelector('.settings-trigger');
            const settingsDropdown = document.querySelector('.settings-dropdown');
            const settingsItems = document.querySelectorAll('.setting-item');
            const settingsArrow = document.querySelector('.settings-arrow');
            const profileMenuItems = document.querySelectorAll('.profile-menu-item');
            let isProfileOpen = false;
            let isSettingsOpen = false;

            function openProfileMenu() {
                const tl = gsap.timeline();
                profileDropdown.style.visibility = 'visible';
                profileButton.classList.add('active');

                tl.to(profileDropdown, {
                    opacity: 1,
                    scale: 1,
                    duration: 0.3,
                    ease: 'back.out(1.7)'
                })
                    .to(profileMenuItems, {
                        opacity: 1,
                        y: 0,
                        duration: 0.2,
                        stagger: 0.05,
                        ease: 'power3.out'
                    });

                isProfileOpen = true;
            }

            function closeProfileMenu() {
                const tl = gsap.timeline({
                    onComplete: () => {
                        profileDropdown.style.visibility = 'hidden';
                        profileButton.classList.remove('active');
                        if (isSettingsOpen) {
                            closeSettings(true);
                        }
                    }
                });

                tl.to(profileMenuItems, {
                    opacity: 0,
                    y: -10,
                    duration: 0.2,
                    stagger: 0.03,
                    ease: 'power3.in'
                })
                    .to(profileDropdown, {
                        opacity: 0,
                        scale: 0.95,
                        duration: 0.2,
                        ease: 'power3.in'
                    });

                isProfileOpen = false;
            }

            profileButton.addEventListener('click', () => {
                if (isSubmenuOpen) {
                    closeSubmenu(activeButton, () => {
                        activeButton = null;
                        isSubmenuOpen = false;
                        openProfileMenu();
                    });
                } else if (!isProfileOpen) {
                    openProfileMenu();
                } else {
                    closeProfileMenu();
                }
            });

            function openSettings() {
                const tl = gsap.timeline();
                settingsArrow.classList.add('active');

                tl.to(settingsDropdown, {
                    height: 'auto',
                    duration: 0.3,
                    ease: 'power3.out'
                })
                    .to(settingsItems, {
                        opacity: 1,
                        y: 0,
                        duration: 0.2,
                        stagger: 0.03,
                        ease: 'power3.out'
                    }, '-=0.1');

                isSettingsOpen = true;
            }

            function closeSettings(immediate = false) {
                const duration = immediate ? 0 : 0.2;
                const tl = gsap.timeline();
                settingsArrow.classList.remove('active');

                tl.to(settingsItems, {
                    opacity: 0,
                    y: -10,
                    duration: duration,
                    stagger: 0.02,
                    ease: 'power3.in'
                })
                    .to(settingsDropdown, {
                        height: 0,
                        duration: duration,
                        ease: 'power3.in'
                    });

                isSettingsOpen = false;
            }

            settingsTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!isSettingsOpen) {
                    openSettings();
                } else {
                    closeSettings();
                }
            });

            // Close dropdowns on outside click
            document.addEventListener('click', (e) => {
                if (isProfileOpen &&
                    !profileDropdown.contains(e.target) &&
                    !profileButton.contains(e.target)) {
                    closeProfileMenu();
                }
            });

            // Wallet functionality
            const walletButton = document.getElementById('walletIcon');
            const walletDropdown = document.querySelector('.wallet-dropdown');
            let isWalletOpen = false;

            function openWalletMenu() {
                walletDropdown.style.visibility = 'visible';
                updateWalletData().then(() => {
                    const tl = gsap.timeline();

                    gsap.set([
                        '.wallet-header',
                        '.wallet-balance',
                        '.recent-transactions-title',
                        '.transaction-item',
                        '.view-all-link'
                    ], {
                        opacity: 0,
                        y: -20
                    });

                    tl.to(walletDropdown, {
                        opacity: 1,
                        scale: 1,
                        duration: 0.3,
                        ease: 'back.out(1.7)'
                    })
                        .to('.wallet-header', {
                            opacity: 1,
                            y: 0,
                            duration: 0.3,
                            ease: 'power3.out'
                        })
                        .to('.wallet-balance', {
                            opacity: 1,
                            y: 0,
                            duration: 0.3,
                            stagger: 0.15,
                            ease: 'power3.out'
                        })
                        .to('.recent-transactions-title', {
                            opacity: 1,
                            y: 0,
                            duration: 0.3,
                            ease: 'power3.out'
                        })
                        .to('.transaction-item', {
                            opacity: 1,
                            y: 0,
                            duration: 0.3,
                            stagger: 0.1,
                            ease: 'power3.out'
                        })
                        .to('.view-all-link', {
                            opacity: 1,
                            y: 0,
                            duration: 0.3,
                            ease: 'power3.out'
                        });
                });

                isWalletOpen = true;
            }

            function closeWalletMenu() {
                const tl = gsap.timeline({
                    onComplete: () => {
                        walletDropdown.style.visibility = 'hidden';
                        gsap.set([
                            '.wallet-header',
                            '.wallet-balance',
                            '.recent-transactions-title',
                            '.transaction-item',
                            '.view-all-link'
                        ], {
                            clearProps: "all"
                        });
                    }
                });

                tl.to('.view-all-link', {
                    opacity: 0,
                    y: -10,
                    duration: 0.2,
                    ease: 'power3.in'
                })
                    .to('.transaction-item', {
                        opacity: 0,
                        y: -10,
                        duration: 0.2,
                        stagger: 0.05,
                        ease: 'power3.in'
                    }, '-=0.1')
                    .to('.recent-transactions-title', {
                        opacity: 0,
                        y: -10,
                        duration: 0.2,
                        ease: 'power3.in'
                    }, '-=0.1')
                    .to('.wallet-balance', {
                        opacity: 0,
                        y: -10,
                        duration: 0.2,
                        stagger: 0.05,
                        ease: 'power3.in'
                    }, '-=0.1')
                    .to('.wallet-header', {
                        opacity: 0,
                        y: -10,
                        duration: 0.2,
                        ease: 'power3.in'
                    }, '-=0.1')
                    .to(walletDropdown, {
                        opacity: 0,
                        scale: 0.95,
                        duration: 0.2,
                        ease: 'power3.in'
                    }, '-=0.1');

                isWalletOpen = false;
            }

            walletButton.addEventListener('click', () => {
                if (isSubmenuOpen) {
                    closeSubmenu(activeButton, () => {
                        activeButton = null;
                        isSubmenuOpen = false;
                        openWalletMenu();
                    });
                } else if (!isWalletOpen) {
                    openWalletMenu();
                } else {
                    closeWalletMenu();
                }
            });

            document.addEventListener('click', (e) => {
                if (isWalletOpen &&
                    !walletDropdown.contains(e.target) &&
                    !walletButton.contains(e.target)) {
                    closeWalletMenu();
                }
            });

            // Hover animations for all menu items
            document.querySelectorAll('.submenu-item, .wallet-balance, .transaction-item, .view-all-link').forEach(item => {
                item.addEventListener('mouseenter', () => {
                    gsap.to(item, {
                        scale: 1.02,
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });

                item.addEventListener('mouseleave', () => {
                    gsap.to(item, {
                        scale: 1,
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });
            });

            // Wallet data update function
            function updateWalletData() {
                return fetch('components/wallet/get_wallet_data.php')
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('dropdownBalance').textContent =
                            new Intl.NumberFormat('tr-TR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(data.balance);

                        document.getElementById('dropdownCoins').textContent =
                            new Intl.NumberFormat('tr-TR').format(data.coins);

                        const transactionsHtml = data.recent_transactions
                            .slice(0, 3)
                            .map(transaction => {
                                let icon, amountClass, amountPrefix, description, currency;

                                const isCoinsTransaction =
                                    transaction.transaction_type === 'REFERRAL_REWARD' ||
                                    (transaction.description &&
                                        (transaction.description.toLowerCase().includes('referral') ||
                                            transaction.description.toLowerCase().includes('coins')));

                                if (isCoinsTransaction) {
                                    icon = 'medal-star';
                                    amountClass = 'positive';
                                    amountPrefix = '+';
                                    currency = ' 🪙';
                                    description = transaction.description;
                                } else {
                                    currency = '₺';
                                    switch (transaction.transaction_type) {
                                        case 'DEPOSIT':
                                            icon = 'arrow-down';
                                            amountClass = 'positive';
                                            amountPrefix = '+';
                                            description = 'Deposit to Wallet';
                                            break;
                                        case 'WITHDRAWAL':
                                            icon = 'arrow-up';
                                            amountClass = 'negative';
                                            amountPrefix = '-';
                                            description = 'Withdrawal from Wallet';
                                            break;
                                        case 'TRANSFER':
                                            if (transaction.sender_id == data.user_id) {
                                                icon = 'export';
                                                amountClass = 'negative';
                                                amountPrefix = '-';
                                                description = `Transfer to ${transaction.receiver_username}`;
                                            } else {
                                                icon = 'import';
                                                amountClass = 'positive';
                                                amountPrefix = '+';
                                                description = `Transfer from ${transaction.sender_username}`;
                                            }
                                            break;
                                        case 'PAYMENT':
                                            icon = 'card';
                                            amountClass = transaction.sender_id == data.user_id ? 'negative' : 'positive';
                                            amountPrefix = transaction.sender_id == data.user_id ? '-' : '+';
                                            description = transaction.description;
                                            break;
                                        default:
                                            icon = 'refresh';
                                            amountClass = transaction.sender_id == data.user_id ? 'negative' : 'positive';
                                            amountPrefix = transaction.sender_id == data.user_id ? '-' : '+';
                                            description = transaction.description;
                                    }
                                }

                                return `
                           <div class="transaction-item">
                               <div class="transaction-info">
                                   <img src="/sources/icons/bulk/${icon}.svg" alt="${transaction.transaction_type}" class="transaction-icon white-icon">
                                   <div>
                                       <span class="transaction-description">${description}</span>
                                       <div class="transaction-date text-xs text-gray-500">
                                           ${new Date(transaction.transaction_date).toLocaleString('tr-TR')}
                                       </div>
                                   </div>
                               </div>
                               <div class="transaction-amount ${amountClass}">
                                   ${amountPrefix}${currency}${isCoinsTransaction
                                        ? parseInt(transaction.amount).toString()
                                        : parseFloat(transaction.amount).toFixed(2)
                                    }
                               </div>
                           </div>
                       `;
                            }).join('');

                        document.getElementById('dropdownTransactions').innerHTML = transactionsHtml;
                    });
            }
        });
    </script>
</body>

</html>