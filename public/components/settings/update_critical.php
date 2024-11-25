<?php
// update_critical.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Database connection
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'];

    try {
        switch ($action) {
            case 'create_password':
                $password = $_POST['password'] ?? '';

                // Sunucu tarafında şifre validasyonu
                if (strlen($password) < 8) {
                    exit(json_encode(['success' => false, 'message' => 'Şifre en az 8 karakter olmalıdır']));
                }

                if (!preg_match('/[A-Z]/', $password)) {
                    exit(json_encode(['success' => false, 'message' => 'Şifre en az 1 büyük harf içermelidir']));
                }

                if (!preg_match('/[a-z]/', $password)) {
                    exit(json_encode(['success' => false, 'message' => 'Şifre en az 1 küçük harf içermelidir']));
                }

                // Kullanıcının Google hesabı olup olmadığını kontrol et
                $stmt = $db->prepare("SELECT google_id, username FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (empty($user['google_id'])) {
                    exit(json_encode(['success' => false, 'message' => 'Bu işlem sadece Google hesapları için geçerlidir']));
                }

                try {
                    // Şifreyi hashle ve güncelle - PASSWORD_DEFAULT ile hash'leme
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]);

                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $success = $stmt->execute([$hashedPassword, $userId]);

                    if ($success) {
                        // Başarılı işlemi logla
                        error_log(sprintf(
                            "Password created for Google user - User: %s (%d) - Date: %s",
                            $user['username'],
                            $userId,
                            date('Y-m-d H:i:s')
                        ));
                    }

                    exit(json_encode([
                        'success' => $success,
                        'message' => $success ? 'Şifre başarıyla oluşturuldu' : 'Şifre oluşturulurken bir hata oluştu'
                    ]));
                } catch (Exception $e) {
                    // Hata durumunu logla
                    error_log(sprintf(
                        "Password creation failed - User: %s (%d) - Error: %s",
                        $user['username'],
                        $userId,
                        $e->getMessage()
                    ));

                    exit(json_encode([
                        'success' => false,
                        'message' => 'Şifre oluşturulurken bir hata oluştu: ' . $e->getMessage()
                    ]));
                }

            case 'verify_attempt':
                $attemptId = $_POST['attempt_id'] ?? 0;

                // Giriş denemesini doğrula
                $stmt = $db->prepare("UPDATE login_attempts SET verified = 1 WHERE attempt_id = ? AND user_id = ?");
                $success = $stmt->execute([$attemptId, $userId]);

                exit(json_encode(['success' => $success]));

            case 'delete_attempt':
                $attemptId = $_POST['attempt_id'] ?? 0;

                // Giriş denemesini sil
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempt_id = ? AND user_id = ?");
                $success = $stmt->execute([$attemptId, $userId]);

                exit(json_encode(['success' => $success]));

            case 'delete_all_unverified':
                // Tüm onaylanmamış giriş denemelerini sil
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE user_id = ? AND verified = 0");
                $success = $stmt->execute([$userId]);

                exit(json_encode(['success' => $success]));

            case 'delete_all_verified':
                // Tüm onaylanmış giriş denemelerini sil
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE user_id = ? AND verified = 1");
                $success = $stmt->execute([$userId]);

                exit(json_encode(['success' => $success]));

            case 'verify_password':
                $password = $_POST['password'] ?? '';

                // Kullanıcının mevcut şifresini kontrol et
                $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $isValid = password_verify($password, $user['password']);
                exit(json_encode(['success' => $isValid]));

            case 'delete_account':
                $password = $_POST['password'] ?? '';

                if (empty($password)) {
                    exit(json_encode(['success' => false, 'message' => 'Şifre gerekli']));
                }

                // Şifre kontrolü
                $stmt = $db->prepare("SELECT password, username FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    exit(json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']));
                }

                if (!password_verify($password, $user['password'])) {
                    exit(json_encode(['success' => false, 'message' => 'Geçersiz şifre']));
                }

                try {
                    $db->beginTransaction();

                    // Silme işlemlerini gruplandıralım
                    $deletionSteps = [
                        // 1. Sosyal ve etkileşim verileri
                        [
                            'name' => 'follows',
                            'query' => "DELETE FROM follows WHERE user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'invitations',
                            'query' => "DELETE FROM invitations WHERE inviter_id = ? OR invited_user_id = ?",
                            'params' => [$userId, $userId]
                        ],

                        // 2. Güvenlik ve doğrulama verileri
                        [
                            'name' => 'login_attempts',
                            'query' => "DELETE FROM login_attempts WHERE user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'verification',
                            'query' => "DELETE FROM verification WHERE user_id = ?",
                            'params' => [$userId]
                        ],

                        // 3. İş ve freelancer verileri
                        [
                            'name' => 'gig_milestones',
                            'query' => "DELETE gm FROM gig_milestones gm 
                                           INNER JOIN gigs g ON gm.gig_id = g.gig_id 
                                           INNER JOIN freelancers f ON g.freelancer_id = f.freelancer_id 
                                           WHERE f.user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'gig_nda_requirements',
                            'query' => "DELETE gnr FROM gig_nda_requirements gnr 
                                           INNER JOIN gigs g ON gnr.gig_id = g.gig_id 
                                           INNER JOIN freelancers f ON g.freelancer_id = f.freelancer_id 
                                           WHERE f.user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'gigs',
                            'query' => "DELETE g FROM gigs g 
                                           INNER JOIN freelancers f ON g.freelancer_id = f.freelancer_id 
                                           WHERE f.user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'freelancers',
                            'query' => "DELETE FROM freelancers WHERE user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'referral_sources',
                            'query' => "DELETE FROM referral_sources WHERE user_id = ?",
                            'params' => [$userId]
                        ],

                        // 4. Finansal veriler
                        [
                            'name' => 'transactions',
                            'query' => "DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?",
                            'params' => [$userId, $userId]
                        ],
                        [
                            'name' => 'wallet',
                            'query' => "DELETE FROM wallet WHERE user_id = ?",
                            'params' => [$userId]
                        ],

                        // 5. Profil ve ayar verileri
                        [
                            'name' => 'user_extended_details',
                            'query' => "DELETE FROM user_extended_details WHERE user_id = ?",
                            'params' => [$userId]
                        ],
                        [
                            'name' => 'user_settings',
                            'query' => "DELETE FROM user_settings WHERE user_id = ?",
                            'params' => [$userId]
                        ],

                        // 6. Ana kullanıcı kaydı
                        [
                            'name' => 'users',
                            'query' => "DELETE FROM users WHERE user_id = ?",
                            'params' => [$userId]
                        ]
                    ];

                    // Her silme adımını gerçekleştir ve logla
                    foreach ($deletionSteps as $step) {
                        try {
                            $stmt = $db->prepare($step['query']);
                            $result = $stmt->execute($step['params']);

                            // Silme işlemini logla
                            error_log(sprintf(
                                "Account deletion step - User: %s (%d) - Table: %s - Success: %s",
                                $user['username'],
                                $userId,
                                $step['name'],
                                $result ? 'Yes' : 'No'
                            ));

                            if (!$result && $step['name'] === 'users') {
                                throw new Exception('Kullanıcı kaydı silinemedi');
                            }
                        } catch (Exception $e) {
                            throw new Exception($step['name'] . ' tablosunda hata: ' . $e->getMessage());
                        }
                    }

                    // İşlem başarılı - kaydet ve session'ı temizle
                    $db->commit();

                    // Son bir log kaydı
                    error_log(sprintf(
                        "Account successfully deleted - User: %s (%d) - Date: %s",
                        $user['username'],
                        $userId,
                        date('Y-m-d H:i:s')
                    ));

                    session_destroy();
                    session_unset();
                    setcookie(session_name(), '', time() - 3600, '/');

                    exit(json_encode(['success' => true]));

                } catch (Exception $e) {
                    $db->rollBack();

                    // Hata durumunu logla
                    error_log(sprintf(
                        "Account deletion failed - User: %s (%d) - Error: %s",
                        $user['username'],
                        $userId,
                        $e->getMessage()
                    ));

                    exit(json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]));
                }
        }
    } catch (Exception $e) {
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

exit(json_encode(['success' => false, 'message' => 'Invalid request method']));
?>