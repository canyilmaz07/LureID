<?php
session_start();

// Oturum kontrolü
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['staff_role'])) {
    header('Location: ../staff-auth.php');
    exit;
}

// Sadece admin rolüne sahip kullanıcılara izin ver
if ($_SESSION['staff_role'] !== 'ADMIN') {
    header('Location: ../staff-auth.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/logger.php';

class AdminPanel {
    private $db;
    private $logger;
    private $currentPage;

    public function __construct($logger) {
        $this->logger = $logger;
        $this->currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
        try {
            $dbConfig = require '../../config/database.php';
            $this->db = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options']
            );
        } catch (PDOException $e) {
            $this->logger->log("Database connection failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Database connection failed");
        }
    }

    public function getCurrentPage() {
        return $this->currentPage;
    }

    public function getPendingCounts() {
        try {
            $counts = [
                'freelancers' => 0,
                'gigs' => 0
            ];

            $stmt = $this->db->query("SELECT COUNT(*) FROM freelancers WHERE approval_status = 'PENDING'");
            $counts['freelancers'] = $stmt->fetchColumn();

            $stmt = $this->db->query("SELECT COUNT(*) FROM gigs WHERE status = 'PENDING_REVIEW'");
            $counts['gigs'] = $stmt->fetchColumn();

            return $counts;
        } catch (PDOException $e) {
            $this->logger->log("Error fetching pending counts: " . $e->getMessage(), 'ERROR');
            return ['freelancers' => 0, 'gigs' => 0];
        }
    }

    public function getPendingFreelancers() {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, u.username, u.email, u.full_name, ued.*
                FROM freelancers f
                JOIN users u ON f.user_id = u.user_id
                LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
                WHERE f.approval_status = 'PENDING'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Error fetching pending freelancers: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function getFreelancerDetails($freelancerId) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, u.username, u.email, u.full_name, 
                       ued.basic_info, ued.education_history, ued.work_experience, 
                       ued.skills_matrix, ued.portfolio_showcase, ued.professional_profile,
                       ued.network_links, ued.achievements
                FROM freelancers f
                JOIN users u ON f.user_id = u.user_id
                LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
                WHERE f.freelancer_id = ?
            ");
            $stmt->execute([$freelancerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Error fetching freelancer details: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    public function getPendingGigs() {
        try {
            $stmt = $this->db->prepare("
                SELECT g.*, f.user_id, u.username, u.email, u.full_name,
                       gm.title as milestone_title, gm.description as milestone_description,
                       gnr.nda_text, g.media_data
                FROM gigs g
                JOIN freelancers f ON g.freelancer_id = f.freelancer_id
                JOIN users u ON f.user_id = u.user_id
                LEFT JOIN gig_milestones gm ON g.gig_id = gm.gig_id
                LEFT JOIN gig_nda_requirements gnr ON g.gig_id = gnr.gig_id
                WHERE g.status = 'PENDING_REVIEW'
                ORDER BY g.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Error fetching pending gigs: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function getGigDetails($gigId) {
        try {
            $stmt = $this->db->prepare("
                SELECT g.*, f.user_id, u.username, u.email, u.full_name,
                       gm.title as milestone_title, gm.description as milestone_description,
                       gnr.nda_text, g.media_data
                FROM gigs g
                JOIN freelancers f ON g.freelancer_id = f.freelancer_id
                JOIN users u ON f.user_id = u.user_id
                LEFT JOIN gig_milestones gm ON g.gig_id = gm.gig_id
                LEFT JOIN gig_nda_requirements gnr ON g.gig_id = gnr.gig_id
                WHERE g.gig_id = ?
            ");
            $stmt->execute([$gigId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Error fetching gig details: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    public function approveFreelancer($freelancerId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE freelancers 
                SET approval_status = 'APPROVED', 
                    updated_at = CURRENT_TIMESTAMP
                WHERE freelancer_id = ?
            ");
            return $stmt->execute([$freelancerId]);
        } catch (PDOException $e) {
            $this->logger->log("Error approving freelancer: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function rejectFreelancer($freelancerId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE freelancers 
                SET approval_status = 'REJECTED', 
                    updated_at = CURRENT_TIMESTAMP
                WHERE freelancer_id = ?
            ");
            return $stmt->execute([$freelancerId]);
        } catch (PDOException $e) {
            $this->logger->log("Error rejecting freelancer: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function approveGig($gigId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE gigs 
                SET status = 'ACTIVE', 
                    updated_at = CURRENT_TIMESTAMP
                WHERE gig_id = ?
            ");
            return $stmt->execute([$gigId]);
        } catch (PDOException $e) {
            $this->logger->log("Error approving gig: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function rejectGig($gigId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE gigs 
                SET status = 'REJECTED', 
                    updated_at = CURRENT_TIMESTAMP
                WHERE gig_id = ?
            ");
            return $stmt->execute([$gigId]);
        } catch (PDOException $e) {
            $this->logger->log("Error rejecting gig: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}

// AJAX işlemleri
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();
    $admin = new AdminPanel($logger);

    try {
        if (isset($_POST['action'])) {
            $response = ['success' => false];

            switch ($_POST['action']) {
                case 'approve_freelancer':
                    if (isset($_POST['freelancer_id'])) {
                        $response['success'] = $admin->approveFreelancer($_POST['freelancer_id']);
                    }
                    break;

                case 'reject_freelancer':
                    if (isset($_POST['freelancer_id'])) {
                        $response['success'] = $admin->rejectFreelancer($_POST['freelancer_id']);
                    }
                    break;

                case 'approve_gig':
                    if (isset($_POST['gig_id'])) {
                        $response['success'] = $admin->approveGig($_POST['gig_id']);
                    }
                    break;

                case 'reject_gig':
                    if (isset($_POST['gig_id'])) {
                        $response['success'] = $admin->rejectGig($_POST['gig_id']);
                    }
                    break;
            }

            echo json_encode($response);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

$logger = new Logger();
$admin = new AdminPanel($logger);
$pendingCounts = $admin->getPendingCounts();
$currentPage = $admin->getCurrentPage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LUREID</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, html, body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div id="toast-container" class="fixed bottom-5 right-5 space-y-2 z-50"></div>

    <!-- Top Menu -->
    <nav class="bg-white shadow-lg fixed w-full z-10">
        <div class="max-w-full mx-auto px-6">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold">LUREID Admin</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700"><?php echo $_SESSION['staff_username']; ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">Çıkış Yap</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="fixed left-0 top-16 h-full w-64 bg-white shadow-lg">
        <div class="py-4">
            <nav>
                <a href="?page=dashboard" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'dashboard' ? 'bg-gray-100' : ''; ?>">
                    <span>Dashboard</span>
                </a>
                
                <a href="?page=freelancers" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'freelancers' ? 'bg-gray-100' : ''; ?>">
                    <span>Freelancer Onayları</span>
                    <?php if ($pendingCounts['freelancers'] > 0): ?>
                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <?php echo $pendingCounts['freelancers']; ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <a href="?page=gigs" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'gigs' ? 'bg-gray-100' : ''; ?>">
                    <span>Gig Onayları</span>
                    <?php if ($pendingCounts['gigs'] > 0): ?>
                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <?php echo $pendingCounts['gigs']; ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <a href="?page=users" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'users' ? 'bg-gray-100' : ''; ?>">
                    <span>Kullanıcı Yönetimi</span>
                </a>
                
                <a href="?page=transactions" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'transactions' ? 'bg-gray-100' : ''; ?>">
                    <span>İşlem Geçmişi</span>
                </a>
                
                <a href="?page=settings" 
                   class="flex items-center justify-between px-6 py-3 text-gray-700 hover:bg-gray-100 <?php echo $currentPage === 'settings' ? 'bg-gray-100' : ''; ?>">
                    <span>Ayarlar</span>
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 pt-16 min-h-screen">
        <div class="p-6">
            <?php
            switch($currentPage) {
                case 'dashboard':
                    include 'pages/dashboard.php';
                    break;
                case 'freelancers':
                    include 'pages/freelancers.php';
                    break;
                case 'gigs':
                    include 'pages/gigs.php';
                    break;
                case 'users':
                    include 'pages/users.php';
                    break;
                case 'transactions':
                    include 'pages/transactions.php';
                    break;
                case 'settings':
                    include 'pages/settings.php';
                    break;
                default:
                    include 'pages/dashboard.php';
            }
            ?>
        </div>
    </main>

    <script>
        // Toast bildirimleri için temel fonksiyonlar
        const toast = {
            show(message, type = 'info') {
                const toastElement = document.createElement('div');
                toastElement.className = `max-w-xs p-4 text-sm rounded-lg ${this.getToastStyles(type)}`;
                toastElement.textContent = message;

                document.getElementById('toast-container').appendChild(toastElement);
                setTimeout(() => toastElement.remove(), 5000);
            },

            getToastStyles(type) {
                const styles = {
                    'success': 'bg-green-100 text-green-700',
                    'error': 'bg-red-100 text-red-700',
                    'info': 'bg-blue-100 text-blue-700',
                    'warning': 'bg-yellow-100 text-yellow-700'
                };
                return styles[type] || styles.info;
            }
        };

        // AJAX işlemleri için fonksiyonlar
        function approveFreelancer(freelancerId) {
            if (!confirm('Bu freelancer\'ı onaylamak istediğinize emin misiniz?')) return;

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'approve_freelancer',
                    freelancer_id: freelancerId
                },
                success: function(response) {
                    if (response.success) {
                        toast.show('Freelancer başarıyla onaylandı', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        toast.show('Onaylama işlemi başarısız oldu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        function rejectFreelancer(freelancerId) {
            if (!confirm('Bu freelancer\'ı reddetmek istediğinize emin misiniz?')) return;

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'reject_freelancer',
                    freelancer_id: freelancerId
                },
                success: function(response) {
                    if (response.success) {
                        toast.show('Freelancer başarıyla reddedildi', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        toast.show('Reddetme işlemi başarısız oldu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        function approveGig(gigId) {
            if (!confirm('Bu gig\'i onaylamak istediğinize emin misiniz?')) return;

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'approve_gig',
                    gig_id: gigId
                },
                success: function(response) {
                    if (response.success) {
                        toast.show('Gig başarıyla onaylandı', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        toast.show('Onaylama işlemi başarısız oldu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        function rejectGig(gigId) {
            if (!confirm('Bu gig\'i reddetmek istediğinize emin misiniz?')) return;

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'reject_gig',
                    gig_id: gigId
                },
                success: function(response) {
                    if (response.success) {
                        toast.show('Gig başarıyla reddedildi', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        toast.show('Reddetme işlemi başarısız oldu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        // Modal işlemleri için yardımcı fonksiyonlar
        function showModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Detay modallarını açma fonksiyonları
        function showFreelancerDetails(freelancerId) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_freelancer_details',
                    freelancer_id: freelancerId
                },
                success: function(response) {
                    if (response.success) {
                        document.getElementById('freelancerDetailsContent').innerHTML = response.html;
                        showModal('freelancerDetailsModal');
                    } else {
                        toast.show('Detaylar yüklenirken bir hata oluştu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        function showGigDetails(gigId) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_gig_details',
                    gig_id: gigId
                },
                success: function(response) {
                    if (response.success) {
                        document.getElementById('gigDetailsContent').innerHTML = response.html;
                        showModal('gigDetailsModal');
                    } else {
                        toast.show('Detaylar yüklenirken bir hata oluştu', 'error');
                    }
                },
                error: function() {
                    toast.show('Bir hata oluştu', 'error');
                }
            });
        }

        // Dosya önizleme fonksiyonu
        function previewFile(fileUrl) {
            const ext = fileUrl.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);
            
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';

            if (isImage) {
                const img = document.createElement('img');
                img.src = fileUrl;
                img.className = 'max-w-full h-auto';
                preview.appendChild(img);
            } else {
                const link = document.createElement('a');
                link.href = fileUrl;
                link.target = '_blank';
                link.textContent = 'Dosyayı Görüntüle';
                link.className = 'text-blue-600 hover:underline';
                preview.appendChild(link);
            }

            showModal('filePreviewModal');
        }

        // Sayfa yüklendiğinde çalışacak işlemler
        document.addEventListener('DOMContentLoaded', function() {
            // ESC tuşu ile modalları kapatma
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal').forEach(modal => {
                        modal.classList.add('hidden');
                    });
                }
            });

            // Modal dışına tıklandığında kapatma
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</body>
</html>