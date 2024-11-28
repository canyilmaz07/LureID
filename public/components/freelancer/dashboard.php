<?php
// dashboard.php
session_start();
$config = require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Freelancer bilgilerini al
$userId = $_SESSION['user_id'];
$freelancerQuery = "SELECT freelancer_id FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($freelancerQuery);
$stmt->execute([$userId]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($freelancerData) {
    $freelancer_id = $freelancerData['freelancer_id'];
} else {
    $freelancer_id = null;
}

// Freelancer durumunu kontrol et
$checkFreelancerQuery = "SELECT freelancer_id, approval_status FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($checkFreelancerQuery);
$stmt->execute([$userId]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcının freelancer kaydı yoksa ana sayfaya yönlendir
if (!$freelancerData) {
    header('Location: /public/index.php');
    exit;
}

$userQuery = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $db->prepare($userQuery);
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Duruma göre görüntülenecek içeriği belirle
$status = $freelancerData['approval_status'];

if ($status === 'PENDING') {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $current_language ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hesap İnceleniyor - LureID</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                font-family: 'Poppins', sans-serif;
            }

            .container-shadow {
                box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            }

            .review-heading {
                font-size: 22px;
            }

            .review-text,
            .review-button {
                font-size: 12px;
                text-align: center;
            }

            .review-img {
                height: 250px;
                width: auto;
                margin: 0 auto;
                display: block;
            }

            .page-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .content-container {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 2rem;
            }
        </style>
    </head>

    <body class="bg-gray-100">
        <div class="page-container">
            <div class="max-w-md w-full bg-white rounded-lg container-shadow content-container">
                <div class="mb-6 flex items-center justify-center">
                    <img src="review.svg" alt="Review Icon" class="review-img">
                </div>
                <h2 class="review-heading font-bold text-gray-900 mb-4">Hesabınız İnceleniyor</h2>
                <p class="review-text text-gray-600 mb-6">Başvurunuz şu anda inceleme aşamasında. Hesabınız onaylandığında
                    size bildirim göndereceğiz.</p>
                <a href="/public/index.php"
                    class="review-button inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
} elseif ($status === 'REJECTED') {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $current_language ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hesap Onaylanmadı - LureID</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                font-family: 'Poppins', sans-serif;
            }

            .container-shadow {
                box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            }

            .review-heading {
                font-size: 22px;
            }

            .review-text,
            .review-button {
                font-size: 12px;
                text-align: center;
            }

            .review-img {
                height: 250px;
                width: auto;
                margin: 0 auto;
                display: block;
            }

            .page-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .content-container {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 2rem;
            }
        </style>
    </head>

    <body class="bg-gray-100">
        <div class="page-container">
            <div class="max-w-md w-full bg-white rounded-lg container-shadow content-container">
                <div class="mb-6 flex items-center justify-center">
                    <img src="sad.svg" alt="Rejection Icon" class="review-img">
                </div>
                <h2 class="review-heading font-bold text-gray-900 mb-4">Hesabınız Onaylanmadı</h2>
                <p class="review-text text-gray-600 mb-6">Üzgünüz, freelancer başvurunuz onaylanmadı. Yeniden başvuru
                    yapabilirsiniz.</p>
                <div class="space-x-4">
                    <a href="/public/index.php"
                        class="review-button inline-block bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                        Ana Sayfaya Dön
                    </a>
                    <form action="reapply.php" method="POST" class="inline-block">
                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                        <button type="submit"
                            class="review-button bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Yeniden Başvur
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
} elseif ($status !== 'APPROVED') {
    header('Location: /public/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freelancer Dashboard - LureID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
    <!-- Chart.js için CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- ApexCharts için CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="bg-gray-100">
    <?php include 'components/freelancer_header.php'; ?>

    <div class="p-4 sm:ml-64 pt-20">
        <div class="p-4 flex flex-wrap gap-4">
            <!-- Hoşgeldin Kartı -->
            <div class="w-full p-4 bg-white border border-gray-200 rounded-lg shadow">
                <div class="flex items-center">
                    <div
                        class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 bg-blue-100 rounded-lg mr-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-xl font-bold text-gray-900">Merhaba,
                            <?= htmlspecialchars($userData['full_name']) ?>!
                        </h5>
                        <p class="mt-1 text-sm text-gray-600">Profiliniz %85 tamamlandı. Daha fazla iş fırsatı için
                            profilinizi güncelleyin.</p>
                    </div>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Aktif İşler -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <div class="flex items-center">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-12 w-12 bg-green-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Aktif İşler</p>
                            <p class="text-2xl font-semibold text-gray-900">8</p>
                        </div>
                    </div>
                </div>

                <!-- Toplam Kazanç -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <div class="flex items-center">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-12 w-12 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Aylık Kazanç</p>
                            <p class="text-2xl font-semibold text-gray-900">₺12,450</p>
                        </div>
                    </div>
                </div>

                <!-- Müşteri Memnuniyeti -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <div class="flex items-center">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-12 w-12 bg-yellow-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Ortalama Puan</p>
                            <p class="text-2xl font-semibold text-gray-900">4.9</p>
                        </div>
                    </div>
                </div>

                <!-- Tamamlanan İşler -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <div class="flex items-center">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-12 w-12 bg-purple-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Tamamlanan</p>
                            <p class="text-2xl font-semibold text-gray-900">156</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafikler Satırı -->
            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                <!-- Gelir Grafiği -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aylık Gelir Analizi</h3>
                    <canvas id="incomeChart" height="300"></canvas>
                </div>

                <!-- Sipariş İstatistikleri -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sipariş İstatistikleri</h3>
                    <div id="orderStats" class="h-[300px]"></div>
                </div>
            </div>

            <!-- Alt Widgets Satırı -->
            <div class="w-full grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
                <!-- Son Yorumlar -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Son Müşteri Yorumları</h3>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-semibold">AY</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Ayşe Y.</h4>
                                <div class="flex items-center">
                                    <div class="flex text-yellow-400">★★★★★</div>
                                    <span class="ml-1 text-sm text-gray-500">2 gün önce</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">Harika iş çıkardınız, tam istediğim gibiydi.
                                    Teşekkürler!</p>
                            </div>
                        </div>
                        <!-- Diğer yorumlar... -->
                    </div>
                </div>

                <!-- Aktif Siparişler -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aktif Siparişler</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <h4 class="font-medium text-gray-900">Web Site Tasarımı</h4>
                                <p class="text-sm text-gray-500">Teslim: 3 gün</p>
                            </div>
                            <span class="px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                Devam Ediyor
                            </span>
                        </div>
                        <!-- Diğer siparişler... -->
                    </div>
                </div>

                <!-- Performans Özeti -->
                <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performans Özeti</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700">Zamanında Teslim</span>
                                <span class="text-sm font-medium text-gray-700">98%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: 98%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700">Müşteri Memnuniyeti</span>
                                <span class="text-sm font-medium text-gray-700">95%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: 95%"></div>
                            </div>
                        </div>
                        <!-- Diğer metrikler... -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gelir Grafiği
        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        new Chart(incomeCtx, {
            type: 'line',
            data: {
                labels: ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran'],
                datasets: [{
                    label: 'Gelir (₺)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    fill: true,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgb(59, 130, 246)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Sipariş İstatistikleri
        const orderOptions = {
            series: [{
                name: 'Siparişler',
                data: [31, 40, 28, 51, 42, 109, 100]
            }, {
                name: 'Tamamlanan',
                data: [29, 38, 26, 47, 40, 103, 98]
            }],
            chart: {
                height: 300,
                type: 'area',
                toolbar: {
                    show: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            xaxis: {
                type: 'datetime',
                categories: [
                    "2024-01-19T00:00:00.000Z",
                    "2024-01-20T00:00:00.000Z",
                    "2024-01-21T00:00:00.000Z",
                    "2024-01-22T00:00:00.000Z",
                    "2024-01-23T00:00:00.000Z",
                    "2024-01-24T00:00:00.000Z",
                    "2024-01-25T00:00:00.000Z"
                ]
            },
            colors: ['#3b82f6', '#10b981'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 90, 100]
                }
            },
            tooltip: {
                x: {
                    format: 'dd MMM yyyy'
                },
            },
        };

        const orderStats = new ApexCharts(document.querySelector("#orderStats"), orderOptions);
        orderStats.render();

        // İstatistik Kartları için Animasyon
        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start).toString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // İstatistik kartlarındaki sayıları canlandır
        document.addEventListener('DOMContentLoaded', function () {
            const numberElements = document.querySelectorAll('.animate-value');
            numberElements.forEach(el => {
                const finalValue = parseInt(el.innerHTML);
                el.innerHTML = '0';
                animateValue(el, 0, finalValue, 1500);
            });
        });

        // Bildirim fonksiyonu
        function showNotification(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 p-4 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'
                } transition-opacity duration-500`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 500);
            }, 3000);
        }
    </script>
</body>

</html>