<?php
// index.php
require_once 'config/database.php';
require_once 'languages/language_handler.php';

try {
    $dbConfig = require 'config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database error occurred');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public_sources/css/menu.css">
</head>

<body>
    <div class="menu-wrapper">
        <div class="menu-container">
            <div class="search-dropdown">
                <div id="searchResults"></div>
            </div>
            <div class="main-menu">
                <div class="left-menu">
                    <a href="/index.php" class="menu-item" data-hover="Ana Sayfa"><span>Ana Sayfa</span></a>
                    <a class="menu-item" data-hover="Market"><span>Market</span></a>
                    <a class="menu-item" data-hover="Topluluk"><span>Topluluk</span></a>
                    <a class="menu-item" data-hover="Eğitim"><span>Eğitim</span></a>
                    <a class="menu-item" data-hover="Projeler"><span>Projeler</span></a>
                </div>

                <div class="lure-text">LUREID</div>
                <div class="center-search">
                    <div class="ctrl-box">CTRL</div>
                    <span>Arama</span>
                </div>

                <div class="right-menu">
                    <a href="/auth/register.php" class="register-btn" data-hover="Üye Olun"><span>Üye Ol</span></a>
                    <a href="/auth/login.php" class="login-btn" data-hover="Giriş Yapın"><span>Giriş Yap</span></a>
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
                                    <img src="/sources/icons/bulk/code-circle.svg" alt="code-circle" class="white-icon">
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
                                    <img src="/sources/icons/bulk/monitor-mobbile.svg" alt="monitor" class="white-icon">
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
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document" class="white-icon">
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
                                    <img src="/sources/icons/bulk/document-copy.svg" alt="document" class="white-icon">
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
                                    <img src="/sources/icons/bulk/profile-circle.svg" alt="profile" class="white-icon">
                                    <div class="submenu-content">
                                        <h4>Eğitmen Profili</h4>
                                        <p>Profil ve portfolio yönetimi</p>
                                    </div>
                                </a>
                                <a href="#" class="submenu-item">
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document" class="white-icon">
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
                                    <img src="/sources/icons/bulk/document-text.svg" alt="document" class="white-icon">
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
        </div>
    </div>

    <script src="public_sources/javascript/script.js"></script>
</body>

</html>