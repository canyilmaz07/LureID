<?php
//login.php
session_start();

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $auth = new Auth(new Logger());
    $user = $auth->getUserByToken($_COOKIE['remember_token']);
    
    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        
        // Login sayfasında değilsek yönlendirmeye gerek yok
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage === 'login.php') {
            header('Location: ../public/index.php');
            exit;
        }
    } else {
        // Geçersiz token ise cookie'yi sil
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Eğer kullanıcı zaten giriş yapmışsa ve login sayfasındaysa indexe yönlendir
if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) === 'login.php') {
    header('Location: ../public/index.php');
    exit;
}

require_once '../vendor/autoload.php';
require_once '../config/logger.php';

$logger = new Logger();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Google Client Setup
function getGoogleClient()
{
    $config = require '../config/google.php';
    $client = new Google_Client();
    $client->setClientId($config['client_id']);
    $client->setClientSecret($config['client_secret']);
    $client->setRedirectUri($config['redirect_uri']);
    $client->setScopes($config['scopes']);
    return $client;
}

// Google Auth handler
if (isset($_GET['code'])) {
    try {
        $client = getGoogleClient();
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        // Get user info
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = $google_account_info->email;
        $logger->log("Google login attempt for: {$email}");
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;

        // Connect to database
        $dbConfig = require '../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        // Check if user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
        $stmt->execute([$google_id, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Existing user - Set session and redirect
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $logger->log("Successful Google login for: {$email}");

            echo "<script>
                window.opener.postMessage({
                    type: 'googleLoginComplete',
                    newUser: false
                }, '*');
                window.close();
            </script>";
        } else {
            $logger->log("New Google registration initiated for: {$email}");
            // Save to temp_users table
            $inviteCode = strtoupper(substr(uniqid() . bin2hex(random_bytes(8)), 0, 9));

            $stmt = $db->prepare("INSERT INTO temp_users (email, full_name, google_id, invite_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $name, $google_id, $inviteCode]);

            echo "<script>
                window.opener.postMessage({
                    type: 'googleLoginComplete',
                    newUser: true,
                    email: " . json_encode($email) . ",
                    name: " . json_encode($name) . ",
                    googleId: " . json_encode($google_id) . ",
                    inviteCode: " . json_encode($inviteCode) . "
                }, '*');
                window.close();
            </script>";
        }
        exit;
    } catch (Exception $e) {
        $logger->log("Google login error: " . $e->getMessage(), 'ERROR');
        echo "<script>
            window.opener.postMessage({
                type: 'googleLoginError',
                message: 'An error occurred during Google login'
            }, '*');
            window.close();
        </script>";
        exit;
    }
}

// Auth sınıfından önce ekleyin
class LocationFinder
{
    private $ip;
    private $data;

    public function __construct()
    {
        $this->ip = $this->getPublicIP();
    }

    private function getPublicIP()
    {
        $services = [
            'https://api.ipify.org?format=json',
            'https://api.my-ip.io/ip.json',
            'https://ip.seeip.org/json'
        ];

        foreach ($services as $service) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $service);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $response = curl_exec($ch);
                curl_close($ch);

                if ($response) {
                    $data = json_decode($response, true);
                    $ip = $data['ip'] ?? $data['origin'] ?? null;
                    if ($ip) {
                        return $ip;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        try {
            $ip = file_get_contents('https://api64.ipify.org?format=json');
            $data = json_decode($ip, true);
            return $data['ip'] ?? '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function getLocation()
    {
        try {
            if (empty($this->ip)) {
                throw new Exception('Could not detect public IP address');
            }

            error_log("Detected Public IP: " . $this->ip);

            $url = "http://ip-api.com/json/{$this->ip}?fields=status,message,country,countryCode,region,regionName,city,timezone,isp,query";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            if (!$response) {
                throw new Exception('Empty response from API');
            }

            $this->data = json_decode($response, true);

            if ($this->data['status'] === 'fail') {
                throw new Exception($this->data['message'] ?? 'API Error');
            }

            return [
                'success' => true,
                'ip' => $this->ip,
                'country' => $this->data['country'] ?? 'Unknown',
                'city' => $this->data['city'] ?? 'Unknown',
                'region' => $this->data['regionName'] ?? 'Unknown',
                'isp' => $this->data['isp'] ?? 'Unknown',
                'timezone' => $this->data['timezone'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => [
                    'ip' => $this->ip,
                    'error_details' => $e->getMessage()
                ]
            ];
        }
    }
}

class Auth
{
    public $db;
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
        try {
            $dbConfig = require '../config/database.php';
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

    // Auth sınıfına ekleyin
    private function getBrowserInfo()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        // Browser detection
        $browser = "Unknown";
        $version = "Unknown";
        $os = "Unknown";

        // Browser check
        if (preg_match('/MSIE/i', $userAgent)) {
            $browser = "Internet Explorer";
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = "Firefox";
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = "Chrome";
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = "Safari";
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $browser = "Opera";
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = "Edge";
        }

        // Version check
        if (preg_match('/' . $browser . '\/([0-9.]+)/i', $userAgent, $matches)) {
            $version = $matches[1];
        }

        // OS check
        if (preg_match('/Windows/i', $userAgent)) {
            $os = "Windows";
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = "Linux";
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = "Mac";
        } elseif (preg_match('/iOS/i', $userAgent)) {
            $os = "iOS";
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = "Android";
        }

        return [
            'browser' => $browser,
            'version' => $version,
            'os' => $os
        ];
    }

    public function verifyLogin($emailOrUsername, $password)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, email, password, two_factor_auth, username 
                FROM users 
                WHERE (email = ? OR username = ?) AND is_verified = 1
            ");
            $stmt->execute([$emailOrUsername, $emailOrUsername]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Konum bilgisini al
            $locationFinder = new LocationFinder();
            $locationData = $locationFinder->getLocation();

            // Tarayıcı bilgilerini al
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $browserInfo = $this->getBrowserInfo();

            if ($user && password_verify($password, $user['password'])) {
                // Başarılı giriş kaydı
                $stmt = $this->db->prepare("
            INSERT INTO login_attempts (
                user_id, ip_address, status, country, city, 
                region, isp, timezone, browser, browser_version, os
            ) VALUES (?, ?, 'SUCCESS', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
                $stmt->execute([
                    $user['user_id'],
                    $locationData['ip'],
                    $locationData['country'],
                    $locationData['city'],
                    $locationData['region'],
                    $locationData['isp'],
                    $locationData['timezone'],
                    $browserInfo['browser'],
                    $browserInfo['version'],
                    $browserInfo['os']
                ]);

                $this->logger->log("Successful login attempt for user: {$user['email']}");
                return [
                    'success' => true,
                    'requires_2fa' => (bool) $user['two_factor_auth'],
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ];
            }

            // Başarısız giriş kaydı
            if ($user) {
                $stmt = $this->db->prepare("
                    INSERT INTO login_attempts (
                        user_id, ip_address, status, country, city, 
                        region, isp, timezone, browser, browser_version, os
                    ) VALUES (?, ?, 'FAILED', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['user_id'],
                    $locationData['ip'],
                    $locationData['country'],
                    $locationData['city'],
                    $locationData['region'],
                    $locationData['isp'],
                    $locationData['timezone'],
                    $browser['browser'] ?? 'Unknown',
                    $browser['version'] ?? 'Unknown',
                    $browser['platform'] ?? 'Unknown'
                ]);
            }

            $this->logger->log("Failed login attempt for: {$emailOrUsername}");
            return ['success' => false];
        } catch (PDOException $e) {
            $this->logger->log("Login verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Login verification failed");
        }
    }

    public function generate2FACode($userId)
    {
        $code = sprintf("%06d", mt_rand(0, 999999));
        try {
            $stmt = $this->db->prepare("DELETE FROM verification WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $this->db->prepare("
                INSERT INTO verification (user_id, code, expires_at) 
                VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE))
            ");
            $stmt->execute([$userId, $code]);

            $this->logger->log("New 2FA code generated for user: $userId, code: $code");
            return $code;
        } catch (PDOException $e) {
            $this->logger->log("2FA code generation failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to generate 2FA code");
        }
    }

    public function verify2FACode($userId, $code)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT code, expires_at 
                FROM verification 
                WHERE user_id = ? AND code = ? AND expires_at > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $this->logger->log("Valid 2FA code for user: $userId");
                return true;
            }

            $this->logger->log("Invalid 2FA code attempt for user: $userId, code: $code");
            return false;
        } catch (PDOException $e) {
            $this->logger->log("2FA verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("2FA verification failed");
        }
    }

    public function getUserEmail($userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT email FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logger->log("Get user email failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to get user email");
        }
    }

    public function createRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET remember_token = ?, 
                remember_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
            WHERE user_id = ?
        ");
        $stmt->execute([$hashedToken, $userId]);
        
        return $token;
    }
    
    public function verifyRememberToken($userId, $token) {
        $stmt = $this->db->prepare("
            SELECT remember_token 
            FROM users 
            WHERE user_id = ? 
            AND remember_token IS NOT NULL 
            AND remember_token_expires_at > NOW()
        ");
        $stmt->execute([$userId]);
        $storedToken = $stmt->fetchColumn();
        
        return $storedToken && password_verify($token, $storedToken);
    }
    
    public function getUserByToken($token) {
        $stmt = $this->db->prepare("
            SELECT user_id, username, email 
            FROM users 
            WHERE remember_token IS NOT NULL 
            AND remember_token_expires_at > NOW()
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            if ($this->verifyRememberToken($user['user_id'], $token)) {
                return $user;
            }
        }
        
        return null;
    }
}

class LoginEmailService
{
    private $mailer;
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->mailer = new PHPMailer(true);

        try {
            $emailConfig = require '../config/email.php';

            $this->mailer->isSMTP();
            $this->mailer->Host = $emailConfig['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $emailConfig['username'];
            $this->mailer->Password = $emailConfig['password'];
            $this->mailer->SMTPSecure = $emailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $emailConfig['port'];
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        } catch (Exception $e) {
            $this->logger->log("Email service configuration failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Email service configuration failed");
        }
    }

    public function send2FACode($email, $username, $code)
    {
        try {
            $this->mailer->addAddress($email, $username);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your Login Verification Code - LUREID';
            $this->mailer->Body = $this->get2FATemplate($username, $code);

            $result = $this->mailer->send();
            $this->logger->log("2FA code sent to: $email");
            return $result;
        } catch (Exception $e) {
            $this->logger->log("Failed to send 2FA code: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to send verification code");
        }
    }

    private function get2FATemplate($username, $code)
    {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Login Verification</h2>
                <p>Hello {$username},</p>
                <p>Your verification code is:</p>
                <div style='background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 5px; margin: 20px 0;'>
                    <strong>{$code}</strong>
                </div>
                <p>This code will expire in 15 minutes.</p>
                <p>If you didn't attempt to log in, please secure your account immediately.</p>
                <p>Best regards,<br>LUREID Team</p>
            </div>";
    }
}

// AJAX İsteklerini İşle
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();
    $auth = new Auth($logger);

    try {
        // Normal Login
        if (isset($_POST['login'])) {
            $emailOrUsername = $_POST['email'];
            $password = $_POST['password'];

            $result = $auth->verifyLogin($emailOrUsername, $password);

            if ($result['success']) {
                if ($result['requires_2fa']) {
                    $code = $auth->generate2FACode($result['user_id']);
                    $emailService = new LoginEmailService($logger);
                    $emailService->send2FACode($result['email'], $result['username'], $code);

                    $_SESSION['pending_2fa'] = [
                        'user_id' => $result['user_id'],
                        'username' => $result['username'],
                        'email' => $result['email']
                    ];
                    $logger->log("2FA initiated for user: {$result['email']}");

                    echo json_encode([
                        'success' => true,
                        'requires_2fa' => true
                    ]);
                } else {
                    $_SESSION['user_id'] = $result['user_id'];
                    $_SESSION['username'] = $result['username'];
                    $logger->log("User logged in successfully: {$result['email']}");

                    if (isset($_POST['remember']) && $_POST['remember'] === 'true') {
                        $token = $auth->createRememberToken($result['user_id']);
                        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'requires_2fa' => false,
                        'redirect' => '../public/index.php'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]);
            }
            exit;
        }

        // 2FA Verification
        if (isset($_POST['verify_2fa'])) {
            if (!isset($_SESSION['pending_2fa'])) {
                throw new Exception("No pending 2FA verification");
            }

            $userId = $_SESSION['pending_2fa']['user_id'];
            $code = filter_var($_POST['verificationCode'], FILTER_SANITIZE_STRING);

            if ($auth->verify2FACode($userId, $code)) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $_SESSION['pending_2fa']['username'];
                unset($_SESSION['pending_2fa']);

                if (isset($_POST['remember']) && $_POST['remember'] === 'true') {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
                }

                echo json_encode([
                    'success' => true,
                    'redirect' => '../public/index.php'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ]);
            }
            exit;
        }

        // Update temp user with username
        if (isset($_POST['update_temp_user'])) {
            try {
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);

                $stmt = $auth->db->prepare("UPDATE temp_users SET username = ? WHERE email = ?");
                $success = $stmt->execute([$username, $email]);

                if ($success) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception("Failed to update username");
                }
            } catch (Exception $e) {
                $logger->log("Failed to update temp user: " . $e->getMessage(), 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
        }

        // username check endpoint - AJAX handlers kısmına eklenecek
        if (isset($_POST['check_username'])) {
            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);

            $stmt = $auth->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $exists = $stmt->fetchColumn() > 0;

            echo json_encode(['success' => true, 'available' => !$exists]);
            exit;
        }

        // Complete Google registration
        if (isset($_POST['complete_google_registration'])) {
            try {
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                $googleId = filter_var($_POST['google_id'], FILTER_SANITIZE_STRING);
                $referralCode = isset($_POST['referral_code']) ?
                    filter_var($_POST['referral_code'], FILTER_SANITIZE_STRING) : null;

                // Get temp user data
                $stmt = $auth->db->prepare("SELECT * FROM temp_users WHERE email = ? AND google_id = ?");
                $stmt->execute([$email, $googleId]);
                $tempUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$tempUser) {
                    throw new Exception("Temporary user not found");
                }

                // Begin transaction
                $auth->db->beginTransaction();

                try {
                    // Generate unique user ID
                    do {
                        $userId = mt_rand(100000000, 999999999);
                        $stmt = $auth->db->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    } while ($stmt->fetchColumn() > 0);

                    // Insert into users table
                    $stmt = $auth->db->prepare("
                        INSERT INTO users (
                            user_id, username, email, password, 
                            full_name, is_verified, google_id
                        ) VALUES (?, ?, ?, '', ?, 1, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $tempUser['username'],
                        $email,
                        $tempUser['full_name'],
                        $googleId
                    ]);

                    // Insert referral source
                    $stmt = $auth->db->prepare("
                        INSERT INTO referral_sources (
                            user_id, source_type, specific_source, is_referral_signup
                        ) VALUES (?, 'ORGANIC', ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $tempUser['invite_code'],
                        $referralCode ? 1 : 0
                    ]);

                    if ($referralCode) {
                        // Get referrer's user ID
                        $stmt = $auth->db->prepare("
                            SELECT user_id 
                            FROM referral_sources 
                            WHERE specific_source = ?
                        ");
                        $stmt->execute([$referralCode]);
                        $referrerId = $stmt->fetchColumn();

                        if ($referrerId) {
                            // Check if referrer has a wallet
                            $stmt = $auth->db->prepare("SELECT user_id FROM wallet WHERE user_id = ?");
                            $stmt->execute([$referrerId]);
                            if (!$stmt->fetch()) {
                                // Create wallet for referrer if not exists
                                $stmt = $auth->db->prepare("
                                    INSERT INTO wallet (user_id, coins, last_transaction_date) 
                                    VALUES (?, 0, CURRENT_TIMESTAMP)
                                ");
                                $stmt->execute([$referrerId]);
                            }

                            // Check if new user has a wallet
                            $stmt = $auth->db->prepare("SELECT user_id FROM wallet WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            if (!$stmt->fetch()) {
                                // Create wallet for new user if not exists
                                $stmt = $auth->db->prepare("
                                    INSERT INTO wallet (user_id, coins, last_transaction_date) 
                                    VALUES (?, 0, CURRENT_TIMESTAMP)
                                ");
                                $stmt->execute([$userId]);
                            }

                            // Give coins to referrer
                            $stmt = $auth->db->prepare("
                                UPDATE wallet 
                                SET coins = coins + 50,
                                    last_transaction_date = CURRENT_TIMESTAMP
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$referrerId]);

                            // Give coins to new user
                            $stmt = $auth->db->prepare("
                                UPDATE wallet 
                                SET coins = coins + 25,
                                    last_transaction_date = CURRENT_TIMESTAMP
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$userId]);

                            // Record invitation
                            $stmt = $auth->db->prepare("
                                INSERT INTO invitations (
                                    inviter_id, invited_user_id, invitation_code
                                ) VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$referrerId, $userId, $referralCode]);
                        }
                    }

                    // Delete temp user
                    $stmt = $auth->db->prepare("DELETE FROM temp_users WHERE email = ?");
                    $stmt->execute([$email]);

                    // Set session variables
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $tempUser['username'];

                    $auth->db->commit();
                    echo json_encode(['success' => true]);

                } catch (Exception $e) {
                    $auth->db->rollBack();
                    throw $e;
                }
            } catch (Exception $e) {
                $logger->log("Failed to complete Google registration: " . $e->getMessage(), 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
        }

        // Check referral code
        if (isset($_POST['check_referral'])) {
            $code = filter_var($_POST['referralCode'], FILTER_SANITIZE_STRING);
            $stmt = $auth->db->prepare("
                SELECT COUNT(*) FROM referral_sources 
                WHERE specific_source = ?
            ");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;

            echo json_encode(['success' => true, 'exists' => $exists]);
            exit;
        }

        // Resend Code
        if (isset($_POST['resend']) && isset($_SESSION['pending_2fa'])) {
            $userId = $_SESSION['pending_2fa']['user_id'];
            $username = $_SESSION['pending_2fa']['username'];
            $email = $_SESSION['pending_2fa']['email'];

            $code = $auth->generate2FACode($userId);
            $emailService = new LoginEmailService($logger);
            $emailService->send2FACode($email, $username, $code);

            echo json_encode([
                'success' => true,
                'message' => 'Verification code resent'
            ]);
            exit;
        }

    } catch (Exception $e) {
        $logger->log($e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - LUREID</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@400..900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../sources/css/slider.css">
    <style>
        *,
        html,
        body {
            font-family: 'Poppins', sans-serif;
        }

        .social-button,
        .bottom-links a,
        .form-button,
        label[for="remember"],
        a[href="forgot-password.php"] {
            cursor: pointer !important;
        }

        .hero-quote {
            font-family: 'Bebas Neue', sans-serif;
        }

        .quote-mark {
            font-family: 'Playfair Display', serif;
        }

        /* iPhone 14 Pro Max & similar devices */
        @media screen and (max-width: 428px) {
            body {
                overflow-y: auto;
            }

            .nav-back,
            .hero-section {
                display: none !important;
            }

            .content-section {
                width: 100% !important;
                padding: 24px !important;
                min-height: 100vh;
                display: flex;
                justify-content: center !important;
                align-items: center;
            }

            #login-form-container {
                padding-top: 0 !important;
                max-width: 100% !important;
                margin-top: -80px !important;
            }

            .form-title {
                font-size: 24px !important;
                margin-bottom: 8px !important;
            }

            .subtitle {
                font-size: 14px;
                margin-bottom: 32px !important;
            }

            .form-button {
                height: 52px !important;
            }

            .form-input {
                height: 48px !important;
                font-size: 14px !important;
            }

            .divider {
                margin: 24px 0 !important;
            }

            .input-icon {
                top: 16px !important;
            }

            .bottom-links {
                position: relative !important;
                bottom: auto !important;
                left: auto !important;
                width: 100% !important;
                padding: 0 !important;
                text-align: center !important;
                margin-top: 25px !important;
                background: transparent !important;
            }

            .space-y-4> :not([hidden])~ :not([hidden]) {
                margin-top: 16px !important;
            }

            .social-button {
                margin-bottom: 24px !important;
            }
        }

        /* 625px altındaki cihazlar için (slider gizleme ve content düzenlemeleri) */
        @media screen and (max-width: 624px) {
            body {
                overflow-y: auto;
            }

            .nav-back,
            .hero-section {
                display: none !important;
            }

            .content-section {
                width: 100% !important;
                padding: 24px !important;
                min-height: 100vh;
                display: flex;
                justify-content: center !important;
                align-items: center;
            }

            #login-form-container {
                padding-top: 0 !important;
                max-width: 100% !important;
                margin-top: -80px !important;
            }

            .form-title {
                font-size: 24px !important;
                margin-bottom: 8px !important;
            }

            .subtitle {
                font-size: 14px;
                margin-bottom: 32px !important;
            }

            .form-button {
                height: 52px !important;
            }

            .form-input {
                height: 48px !important;
                font-size: 14px !important;
            }

            .divider {
                margin: 24px 0 !important;
            }

            .input-icon {
                top: 16px !important;
            }

            .bottom-links {
                position: relative !important;
                bottom: auto !important;
                left: auto !important;
                width: 100% !important;
                padding: 0 !important;
                text-align: center !important;
                margin-top: 25px !important;
                background: transparent !important;
            }

            .space-y-4> :not([hidden])~ :not([hidden]) {
                margin-top: 16px !important;
            }

            .social-button {
                margin-bottom: 24px !important;
            }
        }

        /* Tablet & Small Laptop (625px - 1094px arası) */
        @media screen and (min-width: 625px) and (max-width: 1094px) {
            .hero-section {
                width: 55% !important;
                margin: 16px !important;
                display: block !important;
            }

            .content-section {
                width: 45% !important;
                padding: 20px !important;
            }

            #login-form-container {
                max-width: 380px !important;
                width: 100% !important;
                margin: 0 auto !important;
            }

            .form-title {
                font-size: 28px !important;
                margin-bottom: 12px !important;
            }

            .subtitle {
                font-size: 14px !important;
                margin-bottom: 24px !important;
            }

            .form-input,
            .form-button {
                height: 54px !important;
            }

            .social-button {
                height: 54px !important;
            }

            .input-icon {
                top: 19px !important;
            }

            .hero-quote {
                font-size: 32px !important;
                line-height: 1.3 !important;
            }

            .quote-mark {
                font-size: 40px !important;
            }

            .bottom-links {
                position: absolute !important;
                bottom: 24px !important;
                width: 100% !important;
                padding: 0 20px !important;
            }

            .divider {
                margin: 20px 0 !important;
            }

            .space-y-4>*+* {
                margin-top: 16px !important;
            }
        }

        /* Desktop (1095px ve üzeri) */
        @media screen and (min-width: 1095px) {
            .hero-section {
                display: block !important;
                width: 65% !important;
            }

            .content-section {
                width: 35% !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 40px !important;
            }

            #login-form-container {
                width: 100% !important;
                max-width: 400px !important;
                margin: 0 auto !important;
            }

            .form-title {
                font-size: 2em !important;
            }

            .form-input {
                height: 60px !important;
            }

            .form-button {
                height: 60px !important;
            }

            .input-icon {
                top: 22px !important;
            }

            .bottom-links {
                position: absolute !important;
                bottom: 40px !important;
                width: 100% !important;
                text-align: center !important;
            }
        }
    </style>
</head>

<body class="bg-[#f9f9f9] overflow-hidden">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 space-y-2 z-50"></div>

    <!-- Back Navigation -->
    <a href="../"
        class="nav-back absolute top-10 left-10 text-gray-800 hover:text-gray-600 font-semibold text-sm z-20">
        ← Get back
    </a>

    <!-- Main Layout -->
    <div class="flex min-h-screen">
        <!-- Content Section -->
        <section class="content-section flex flex-col justify-center items-center p-10 md:p-6 relative bg-[#f9f9f9]">
            <div id="login-form-container" class="max-w-[400px] md:max-w-full w-full md:-mt-20">
                <h1
                    class="form-title text-[2em] md:text-[24px] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue']">
                    Connect Your LUREID
                </h1>
                <p class="subtitle text-[#6B7280] text-center mb-12 md:mb-8 md:text-[14px]">Welcome back!</p>

                <!-- Google Sign In -->
                <?php
                $client = getGoogleClient();
                $authUrl = $client->createAuthUrl();
                ?>
                <a href="<?php echo htmlspecialchars($authUrl); ?>"
                    class="social-button w-full h-[60px] md:h-[52px] flex items-center justify-center gap-3 bg-[#4F46E5] text-white rounded-lg mb-5 text-sm font-semibold transition-all hover:bg-[#0b0086] shadow-lg">
                    <img src="../sources/icons/Logos/bold/google-1.svg" alt="Google"
                        class="h-5 w-5 brightness-0 invert">
                    Use your Google Account
                </a>

                <div class="divider relative my-6 md:my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-[#E5E7EB]"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="px-3 bg-[#f9f9f9] text-[#888] text-sm">or</span>
                    </div>
                </div>

                <!-- Login Form -->
                <form id="loginForm" class="space-y-4">
                    <div id="loginFields">
                        <div class="space-y-4">
                            <div class="relative flex items-center">
                                <img src="../sources/icons/bulk/user.svg" class="absolute left-[25px]" width="20"
                                    height="20" alt="user icon">
                                <input type="text" id="email" name="email"
                                    class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                    placeholder="Email or username" required>
                            </div>

                            <!-- Password input -->
                            <div class="relative flex items-center">
                                <img src="../sources/icons/bulk/lock.svg" class="absolute left-[25px]" width="20"
                                    height="20" alt="lock icon">
                                <input type="password" id="password" name="password"
                                    class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                    placeholder="Password" required>
                            </div>

                            <div class="flex items-center pl-[11px]">
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" id="remember" name="remember" class="hidden peer">
                                    <div class="w-5 h-5 flex items-center justify-center border-2 border-[#E5E7EB] rounded 
                                        group-hover:border-[#4F46E5] transition-colors
                                        peer-checked:bg-[#4F46E5] peer-checked:border-[#4F46E5]">
                                        <i class="fas fa-check text-white text-xs 
                                            peer-checked:opacity-100 opacity-0 transition-opacity"></i>
                                    </div>
                                    <span
                                        class="ml-2 text-[13px] pl-[6px] text-[#6B7280] font-medium group-hover:text-[#4F46E5] transition-colors">
                                        Remember me
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 2FA Fields -->
                    <div id="verificationFields" class="hidden space-y-6">
                        <div class="text-center">
                            <h2 class="text-2xl font-bold text-gray-900">Two Factor Authentication</h2>
                            <p class="mt-2 text-[13px] text-gray-600">
                                For added security, please enter the verification code sent to your email
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-center">
                                <div class="p-3 bg-blue-50 rounded-full">
                                    <img src="../sources/icons/bulk/shield.svg" width="32" height="32"
                                        alt="shield icon">
                                </div>
                            </div>

                            <div>
                                <label for="verificationCode" class="block text-[13px] font-medium text-gray-700">
                                    Verification Code
                                </label>
                                <input type="text" name="verificationCode" id="verificationCode" maxlength="6"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-center text-lg tracking-[0.5em]"
                                    placeholder="000000">
                                <p class="mt-2 text-[13px] text-gray-500">Enter the 6-digit code sent to your email</p>
                            </div>

                            <div class="flex items-center justify-between text-[13px]">
                                <button type="button" id="resendCode"
                                    class="text-blue-600 hover:text-blue-800 font-medium flex items-center">
                                    <img src="../sources/icons/bulk/refresh.svg" class="w-4 h-4 mr-1"
                                        alt="refresh icon">
                                    Resend Code
                                </button>
                                <p class="text-gray-500">Code expires in 15:00</p>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-yellow-50 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-[13px] text-yellow-700">
                                        For your security, please verify your identity with the code sent to your email.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                        class="form-button w-full h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg">
                        Sign In
                    </button>

                    <a href="forgot-password.php"
                        class="block text-center text-[13px] font-semibold text-[#333] hover:text-[#4F46E5] transition-colors">
                        Forgot password?
                    </a>
                </form>
            </div>

            <!-- Bottom Links -->
            <div
                class="bottom-links absolute bottom-10 md:fixed md:bottom-8 text-center text-[#888] text-[13px] w-full">
                <p>
                    Don't have an account yet?
                    <a href="register.php" class="text-[#333] font-semibold hover:text-[#4F46E5] transition-colors">
                        Create an account
                    </a>
                </p>
            </div>
        </section>

        <!-- Hero Section - Hidden on mobile -->
        <section class="hero-section m-6 rounded-[30px] relative overflow-hidden shadow-lg">
            <div class="hero-slider h-full w-full">
                <!-- Slide 1 -->
                <div class="slide active" style="background-image: url('../sources/images/bg1.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            LEARN, TEACH, DISCOVER, AND TURN YOUR SKILLS INTO PASSIVE INCOME AT LURE, WHERE
                            THOUSANDS OF DEVELOPERS AND EMPLOYERS MEET EVERY YEAR.
                        </p>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="slide" style="background-image: url('../sources/images/bg2.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            CONNECT WITH INDUSTRY EXPERTS, EXPAND YOUR NETWORK, AND BUILD A THRIVING
                            CAREER IN THE EVER-EVOLVING WORLD OF TECHNOLOGY.
                        </p>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="slide" style="background-image: url('../sources/images/bg3.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            JOIN OUR COMMUNITY OF INNOVATORS AND CREATORS, WHERE KNOWLEDGE
                            SHARING MEETS OPPORTUNITY IN THE DIGITAL AGE.
                        </p>
                    </div>
                </div>

                <div class="progress-bar">
                    <div class="progress"></div>
                </div>
            </div>

            <h2 class="font-['Bebas_Neue'] text-2xl absolute top-10 left-10 text-white font-bold tracking-wider z-10">
                LURE
            </h2>
        </section>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed bottom-5 right-5 space-y-2"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/CustomEase.min.js"></script>
    <script src="../sources/js/slider.js" defer></script>
    <script>
        // Toast notification system
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

        // Form validation helper
        const validate = {
            emailOrUsername: (value) => {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const usernamePattern = /^[a-zA-Z0-9_]{3,30}$/;

                if (value.includes('@')) {
                    return emailPattern.test(value);
                }
                return usernamePattern.test(value);
            }
        };

        // Google login popup handler
        function handleGoogleLogin(authUrl) {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-40';
            document.body.appendChild(overlay);

            // Calculate popup dimensions
            const width = 600;
            const height = 650;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;

            // Open popup
            const popup = window.open(
                authUrl,
                'GoogleLogin',
                `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no`
            );

            // Poll the popup and remove overlay when closed
            const popupTimer = setInterval(() => {
                if (popup.closed) {
                    clearInterval(popupTimer);
                    overlay.remove();
                }
            }, 500);
        }

        function createRegistrationModal(email, name, googleId, inviteCode) {
            const contentSection = document.querySelector('.content-section');

            const formContainer = document.getElementById('login-form-container');
            if (formContainer) {
                formContainer.style.display = 'none';
            }

            const existingModal = document.querySelector('.modal-container');
            if (existingModal) existingModal.remove();

            const modal = document.createElement('div');
            modal.className = 'modal-container max-w-[400px] w-full mx-auto';

            let currentStep = 'username';

            const setupInvitationHandlers = () => {
                const referralInput = document.getElementById('referral-input');
                const referralStatus = document.getElementById('referral-status');
                const completeButton = document.getElementById('complete-registration');
                let isReferralValid = true;

                referralInput.addEventListener('input', async () => {
                    const referralCode = referralInput.value.trim();

                    if (!referralCode) {
                        referralStatus.textContent = '';
                        isReferralValid = true;
                        completeButton.disabled = false;
                        return;
                    }

                    if (referralCode === inviteCode) {
                        referralStatus.className = 'mt-2 text-[13px] text-red-600';
                        referralStatus.textContent = 'You cannot use your own invite code';
                        isReferralValid = false;
                        completeButton.disabled = true;
                        return;
                    }

                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `check_referral=true&referralCode=${encodeURIComponent(referralCode)}`
                        });

                        const data = await response.json();
                        if (data.exists) {
                            referralStatus.className = 'mt-2 text-[13px] text-green-600';
                            referralStatus.textContent = 'Valid referral code';
                            isReferralValid = true;
                            completeButton.disabled = false;
                        } else {
                            referralStatus.className = 'mt-2 text-[13px] text-red-600';
                            referralStatus.textContent = 'Invalid referral code';
                            isReferralValid = false;
                            completeButton.disabled = true;
                        }
                    } catch (error) {
                        referralStatus.className = 'mt-2 text-[13px] text-red-600';
                        referralStatus.textContent = 'Error checking referral code';
                        isReferralValid = false;
                        completeButton.disabled = true;
                    }
                });

                completeButton.addEventListener('click', async () => {
                    const referralCode = referralInput.value.trim();
                    if (referralCode && !isReferralValid) {
                        toast.show('Please enter a valid referral code or leave it empty', 'error');
                        return;
                    }

                    try {
                        completeButton.disabled = true;
                        completeButton.textContent = 'Processing...';

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: `complete_google_registration=true&email=${encodeURIComponent(email)}&google_id=${encodeURIComponent(googleId)}&referral_code=${encodeURIComponent(referralCode)}`
                        });

                        const data = await response.json();
                        if (data.success) {
                            toast.show('Registration successful!', 'success');
                            window.location.href = '../public/index.php';
                        } else {
                            throw new Error(data.message || 'Registration failed');
                        }
                    } catch (error) {
                        toast.show(error.message, 'error');
                        completeButton.disabled = false;
                        completeButton.textContent = 'Complete Registration';
                    }
                });
            };

            const setupUsernameHandlers = () => {
                const usernameInput = document.getElementById('username-input');
                const usernameStatus = document.getElementById('username-status');
                const continueButton = document.getElementById('continue-button');
                let usernameCheckTimeout;

                usernameInput.addEventListener('input', () => {
                    clearTimeout(usernameCheckTimeout);
                    const username = usernameInput.value.trim();

                    if (username.length < 3) {
                        usernameStatus.className = 'mt-2 text-[13px] text-red-600';
                        usernameStatus.textContent = 'Username must be at least 3 characters long';
                        continueButton.disabled = true;
                        return;
                    }

                    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                        usernameStatus.className = 'mt-2 text-[13px] text-red-600';
                        usernameStatus.textContent = 'Username can only contain letters, numbers, and underscores';
                        continueButton.disabled = true;
                        return;
                    }

                    usernameStatus.className = 'mt-2 text-[13px] text-blue-600';
                    usernameStatus.textContent = 'Checking username availability...';
                    continueButton.disabled = true;

                    usernameCheckTimeout = setTimeout(async () => {
                        try {
                            const checkResponse = await fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: `check_username=true&username=${encodeURIComponent(username)}`
                            });
                            const checkData = await checkResponse.json();

                            if (checkData.success && checkData.available) {
                                const updateResponse = await fetch(window.location.href, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: `update_temp_user=true&email=${encodeURIComponent(email)}&username=${encodeURIComponent(username)}`
                                });
                                const updateData = await updateResponse.json();

                                if (updateData.success) {
                                    usernameStatus.className = 'mt-2 text-[13px] text-green-600';
                                    usernameStatus.textContent = 'Username is available';
                                    continueButton.disabled = false;
                                } else {
                                    throw new Error('Failed to update username');
                                }
                            } else {
                                usernameStatus.className = 'mt-2 text-[13px] text-red-600';
                                usernameStatus.textContent = 'Username is already taken';
                                continueButton.disabled = true;
                            }
                        } catch (error) {
                            usernameStatus.className = 'mt-2 text-[13px] text-red-600';
                            usernameStatus.textContent = 'Error checking username';
                            continueButton.disabled = true;
                        }
                    }, 500);
                });

                continueButton.addEventListener('click', () => {
                    currentStep = 'invitation';
                    updateModalContent();
                });
            };

            const getUsernameModalHTML = () => `
        <h1 class="text-[2em] md:text-[24px] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue'] mb-3">
            Choose Your Username
        </h1>
        <p class="text-[#6B7280] text-center mb-12 md:mb-8 md:text-[14px]">
            This username will be unique to your account
        </p>
        <div class="space-y-4">
            <div class="relative">
                <i class="fas fa-user absolute left-[25px] top-[22px] md:top-[16px] text-[#BEBEBE]"></i>
                <input type="text" id="username-input" 
                    class="w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                    placeholder="Enter username">
                <p id="username-status" class="mt-2 text-[13px]"></p>
            </div>
            <button id="continue-button" disabled
                class="w-full h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                Continue
            </button>
        </div>
    `;

            const getInvitationModalHTML = () => `
        <h1 class="text-[2em] md:text-[24px] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue'] mb-3">
            Your Invitation Code
        </h1>
        <p class="text-[#6B7280] text-center mb-12 md:mb-8 md:text-[14px]">
            Save this code - you'll need it to invite others
        </p>
        <div class="space-y-4">
            <div class="relative">
                <i class="fas fa-ticket absolute left-[25px] top-[22px] md:top-[16px] text-[#BEBEBE]"></i>
                <input type="text" value="${inviteCode}" readonly
                    class="w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors text-center font-mono">
            </div>
            
            <div class="relative mt-8">
                <i class="fas fa-user-plus absolute left-[25px] top-[22px] md:top-[16px] text-[#BEBEBE]"></i>
                <input type="text" id="referral-input" 
                    class="w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                    placeholder="Enter referral code (optional)">
                <p id="referral-status" class="mt-2 text-[13px]"></p>
            </div>
            
            <button id="complete-registration"
                class="w-full h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg">
                Complete Registration
            </button>
        </div>
    `;

            const updateModalContent = () => {
                modal.innerHTML = currentStep === 'username' ? getUsernameModalHTML() : getInvitationModalHTML();
                if (currentStep === 'username') {
                    setupUsernameHandlers();
                } else {
                    setupInvitationHandlers();
                }
            };

            contentSection.appendChild(modal);
            updateModalContent();
        }

        // Message handler for popup communication
        window.addEventListener('message', (event) => {
            // Remove any existing overlays
            const overlays = document.querySelectorAll('.bg-black.bg-opacity-50');
            overlays.forEach(overlay => overlay.remove());

            if (event.data.type === 'googleLoginComplete') {
                if (event.data.newUser) {
                    createRegistrationModal(
                        event.data.email,
                        event.data.name,
                        event.data.googleId,
                        event.data.inviteCode
                    );
                } else {
                    window.location.href = '../public/index.php';
                }
            } else if (event.data.type === 'googleLoginError') {
                toast.show(event.data.message || 'An error occurred during Google login', 'error');
            }
        });

        // Normal login form handler
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const loginFields = document.getElementById('loginFields');
            const verificationFields = document.getElementById('verificationFields');
            const submitButton = loginForm.querySelector('button[type="submit"]');
            const titleSection = document.querySelector('.form-title');
            const subtitleSection = document.querySelector('.subtitle');
            const googleSection = document.querySelector('.social-button');
            const dividerSection = document.querySelector('.divider');
            let is2FAMode = false;

            loginForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                submitButton.disabled = true;

                try {
                    if (is2FAMode) {
                        // 2FA verification
                        const code = document.getElementById('verificationCode').value.trim();
                        if (!code) {
                            throw new Error('Please enter verification code');
                        }

                        const remember = document.getElementById('remember').checked ? 'true' : 'false';
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `verify_2fa=true&verificationCode=${encodeURIComponent(code)}&remember=${remember}`
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            throw new Error(data.message || 'Invalid verification code');
                        }
                    } else {
                        // Initial login
                        const emailOrUsername = document.getElementById('email').value.trim();
                        const password = document.getElementById('password').value;
                        const remember = document.getElementById('remember').checked ? 'true' : 'false';

                        if (!validate.emailOrUsername(emailOrUsername)) {
                            throw new Error('Please enter a valid email address or username');
                        }

                        if (!password) {
                            throw new Error('Please enter your password');
                        }

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `login=true&email=${encodeURIComponent(emailOrUsername)}&password=${encodeURIComponent(password)}&remember=${remember}`
                        });

                        const data = await response.json();
                        if (data.success) {
                            if (data.requires_2fa) {
                                loginFields.classList.add('hidden');
                                titleSection.classList.add('hidden');
                                subtitleSection.classList.add('hidden');
                                googleSection.classList.add('hidden');
                                dividerSection.classList.add('hidden');
                                verificationFields.classList.remove('hidden');
                                submitButton.textContent = 'Verify Code';
                                is2FAMode = true;
                                toast.show('Verification code sent to your email', 'info');
                            } else {
                                window.location.href = data.redirect;
                            }
                        } else {
                            throw new Error(data.message || 'Invalid credentials');
                        }
                    }
                } catch (error) {
                    toast.show(error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                }
            });

            // Resend verification code
            document.getElementById('resendCode').addEventListener('click', async function () {
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'resend=true'
                    });

                    const data = await response.json();
                    if (data.success) {
                        toast.show('Verification code resent', 'success');
                    } else {
                        throw new Error(data.message || 'Failed to resend code');
                    }
                } catch (error) {
                    toast.show(error.message, 'error');
                }
            });
        });

        // Update Google sign in button click handler
        document.querySelector('a[href*="accounts.google.com"]').addEventListener('click', (e) => {
            e.preventDefault();
            handleGoogleLogin(e.currentTarget.href);
        });
    </script>
</body>

</html>
