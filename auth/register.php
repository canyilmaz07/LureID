<?php
// register.php
session_start();
require_once '../vendor/autoload.php';
require_once '../config/logger.php';

$logger = new Logger();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Security
{
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    public static function generateToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function validatePassword($password)
    {
        return strlen($password) >= 8 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[a-z]/', $password) &&
            preg_match('/[0-9]/', $password);
    }

    public static function generateVerificationCode()
    {
        return sprintf("%04d", mt_rand(0, 9999));
    }

    public static function generateInviteCode()
    {
        return strtoupper(substr(uniqid() . bin2hex(random_bytes(8)), 0, 9));
    }
}

class Database
{
    private $conn;
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
        try {
            $dbConfig = require '../config/database.php';
            $this->conn = new PDO(
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

    private function debugLog($message)
    {
        // Mevcut logger'ı kullanarak debug loglarını kaydet
        $this->logger->log($message, 'DEBUG');

        // AJAX isteği ise hata logunu da ekle
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            error_log($message);
        }
    }

    public function checkEmailExists($email)
    {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logger->log("Email check failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Email check failed");
        }
    }

    public function checkUsernameExists($username)
    {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logger->log("Username check failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Username check failed");
        }
    }

    public function createTempUser($email, $fullName, $verificationCode)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO temp_users (email, full_name, verification_code)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                verification_code = ?, expires_at = CURRENT_TIMESTAMP + INTERVAL 1 HOUR
            ");
            $stmt->execute([$email, $fullName, $verificationCode, $verificationCode]);
            return true;
        } catch (PDOException $e) {
            $this->logger->log("Temp user creation failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to create temporary registration");
        }
    }

    public function verifyCode($email, $code)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM temp_users 
                WHERE email = ? AND verification_code = ? 
                AND expires_at > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$email, $code]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->logger->log("Code verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Verification failed");
        }
    }

    public function getTempUser($email)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM temp_users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Get temp user failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to get temporary user data");
        }
    }

    public function updateTempUser($email, $data)
    {
        try {
            $sets = [];
            $params = [];
            foreach ($data as $key => $value) {
                $sets[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $email;

            $stmt = $this->conn->prepare("
                UPDATE temp_users 
                SET " . implode(", ", $sets) . "
                WHERE email = ?
            ");
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->log("Temp user update failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to update temporary registration");
        }
    }

    public function getTempUserInviteCode($email)
    {
        try {
            $stmt = $this->conn->prepare("SELECT invite_code FROM temp_users WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['invite_code'] : null;
        } catch (PDOException $e) {
            $this->logger->log("Failed to get invite code: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to get invite code");
        }
    }

    public function checkReferralCodeExists($code)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM referral_sources 
                WHERE specific_source = ?
            ");
            $stmt->execute([$code]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logger->log("Referral code check failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Referral code check failed");
        }
    }

    private function generateUniqueUserId()
    {
        do {
            $userId = mt_rand(100000000, 999999999);
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
        } while ($stmt->fetchColumn() > 0);

        return $userId;
    }

    public function completeRegistration($email, $username, $password, $referralCode = null)
    {
        try {
            $this->conn->beginTransaction();
            $this->debugLog("Registration started for email: $email");

            // Get temp user data
            $tempUser = $this->getTempUser($email);
            if (!$tempUser) {
                $this->debugLog("Error: Temporary registration not found for email: $email");
                throw new Exception("Temporary registration not found");
            }
            $this->debugLog("Temp user found: " . json_encode($tempUser));

            // Generate unique user ID
            $userId = $this->generateUniqueUserId();
            $this->debugLog("Generated user ID: $userId for email: $email");

            // Insert into users table
            $stmt = $this->conn->prepare("
                INSERT INTO users (
                    user_id, username, email, password, 
                    full_name, is_verified
                ) VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $userId,
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $tempUser['full_name']
            ]);
            $this->debugLog("User inserted into users table - UserID: $userId, Username: $username");

            // Insert invitation code
            $stmt = $this->conn->prepare("
                INSERT INTO referral_sources (
                    user_id, source_type, specific_source, is_referral_signup
                ) VALUES (?, 'ORGANIC', ?, ?)
            ");
            $stmt->execute([
                $userId,
                $tempUser['invite_code'],
                $referralCode ? true : false
            ]);
            $this->debugLog("Referral source created for UserID: $userId");

            if ($referralCode) {
                $this->debugLog("Processing referral code: $referralCode for UserID: $userId");

                // Get referrer's user ID
                $stmt = $this->conn->prepare("
                    SELECT user_id FROM referral_sources 
                    WHERE specific_source = ?
                ");
                $stmt->execute([$referralCode]);
                $referrerId = $stmt->fetchColumn();
                $this->debugLog("Found referrer ID: $referrerId for referral code: $referralCode");

                if ($referrerId) {
                    // Create wallet for new user with referral bonus
                    $stmt = $this->conn->prepare("
                        INSERT INTO wallet (user_id, coins, last_transaction_date)
                        VALUES (?, 25, CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$userId]);
                    $this->debugLog("Created wallet with 25 coins for new user: $userId");

                    // Update referrer's wallet
                    $stmt = $this->conn->prepare("
                        UPDATE wallet 
                        SET coins = coins + 50,
                            last_transaction_date = CURRENT_TIMESTAMP
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$referrerId]);
                    $this->debugLog("Updated referrer's ($referrerId) wallet with +50 coins");

                    // Record invitation
                    $stmt = $this->conn->prepare("
                        INSERT INTO invitations (
                            inviter_id, invited_user_id, invitation_code
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$referrerId, $userId, $referralCode]);
                    $this->debugLog("Recorded invitation relationship - Inviter: $referrerId, Invited: $userId");
                }
            } else {
                $this->debugLog("Creating wallet with 0 coins for UserID: $userId (no referral)");
                $stmt = $this->conn->prepare("
                    INSERT INTO wallet (user_id, coins, last_transaction_date)
                    VALUES (?, 0, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$userId]);
            }

            // Delete temp user
            $stmt = $this->conn->prepare("DELETE FROM temp_users WHERE email = ?");
            $stmt->execute([$email]);
            $this->debugLog("Deleted temp user record for email: $email");

            $this->conn->commit();
            $this->debugLog("Registration completed successfully for UserID: $userId");
            return $userId;

        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->debugLog("Registration failed for email $email: " . $e->getMessage());
            throw new Exception("Failed to complete registration");
        }
    }


    public function generateUniqueInviteCode()
    {
        do {
            $code = strtoupper(substr(uniqid() . bin2hex(random_bytes(8)), 0, 9));
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM referral_sources 
                WHERE specific_source = ?
                UNION ALL
                SELECT COUNT(*) FROM temp_users 
                WHERE invite_code = ?
            ");
            $stmt->execute([$code, $code]);
            $exists = array_sum($stmt->fetchAll(PDO::FETCH_COLUMN)) > 0;
        } while ($exists);

        return $code;
    }
}

class EmailService
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

    public function sendVerificationCode($email, $fullName, $code)
    {
        try {
            $emailConfig = require '../config/email.php';

            $this->mailer->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $this->mailer->addAddress($email, $fullName);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Verify Your LUREID Account';
            $this->mailer->Body = $this->getEmailTemplate($fullName, $code);

            $result = $this->mailer->send();
            $this->logger->log("Verification email sent to: $email");
            return $result;

        } catch (Exception $e) {
            $this->logger->log("Email sending failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to send verification email");
        }
    }

    private function getEmailTemplate($fullName, $code)
    {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Welcome to LUREID!</h2>
                <p>Hello {$fullName},</p>
                <p>Your verification code is:</p>
                <div style='background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 5px; margin: 20px 0;'>
                    <strong>{$code}</strong>
                </div>
                <p>This code will expire in 15 minutes.</p>
                <p>Best regards,<br>LUREID Team</p>
            </div>";
    }
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();

    try {
        $database = new Database($logger);

        // Email check
        if (isset($_POST['check_email'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $exists = $database->checkEmailExists($email);

            echo json_encode(['success' => true, 'available' => !$exists]);
            exit;
        }

        // Username check
        if (isset($_POST['check_username'])) {
            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
            $exists = $database->checkUsernameExists($username);

            echo json_encode(['success' => true, 'available' => !$exists]);
            exit;
        }

        // Send verification code
        if (isset($_POST['send_verification'])) {
            if (!isset($_POST['csrf_token']) || !Security::validateToken($_POST['csrf_token'])) {
                throw new Exception("Invalid CSRF token");
            }

            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $fullName = filter_var($_POST['fullName'], FILTER_SANITIZE_STRING);
            $code = Security::generateVerificationCode();

            // Create temp user and save verification code
            $database->createTempUser($email, $fullName, $code);

            $emailService = new EmailService($logger);
            $emailService->sendVerificationCode($email, $fullName, $code);

            echo json_encode(['success' => true, 'message' => 'Verification code sent']);
            exit;
        }

        // Verify code
        if (isset($_POST['verify_code'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $code = filter_var($_POST['verificationCode'], FILTER_SANITIZE_STRING);

            if ($database->verifyCode($email, $code)) {
                $inviteCode = $database->generateUniqueInviteCode();
                $database->updateTempUser($email, ['invite_code' => $inviteCode]);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ]);
            }
            exit;
        }

        // Get temp user data
        if (isset($_POST['get_temp_user'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

            try {
                $inviteCode = $database->getTempUserInviteCode($email);

                if ($inviteCode) {
                    echo json_encode([
                        'success' => true,
                        'inviteCode' => $inviteCode
                    ]);
                } else {
                    throw new Exception("Invite code not found");
                }
            } catch (Exception $e) {
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
            $exists = $database->checkReferralCodeExists($code);

            echo json_encode(['success' => true, 'exists' => $exists]);
            exit;
        }

        // Complete registration
        if (isset($_POST['complete_registration'])) {
            if (!isset($_POST['csrf_token']) || !Security::validateToken($_POST['csrf_token'])) {
                throw new Exception("Invalid CSRF token");
            }

            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
            $password = $_POST['password']; // Will be hashed in completeRegistration
            $referralCode = isset($_POST['referralCode']) ?
                filter_var($_POST['referralCode'], FILTER_SANITIZE_STRING) : null;

            try {
                $userId = $database->completeRegistration($email, $username, $password, $referralCode);

                // Set session variables
                $_SESSION['user_id'] = $userId;
                $_SESSION['logged_in'] = true;

                echo json_encode([
                    'success' => true,
                    'message' => 'Registration completed successfully'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
        }

        // Update temp user
        if (isset($_POST['update_temp_user'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);

            try {
                $result = $database->updateTempUser($email, ['username' => $username]);

                if ($result) {
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

        // Resend verification code
        if (isset($_POST['resend_code'])) {
            try {
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                $fullName = filter_var($_POST['fullName'], FILTER_SANITIZE_STRING);
                $code = Security::generateVerificationCode();

                $database->updateTempUser($email, [
                    'verification_code' => $code,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
                ]);

                $emailService = new EmailService($logger);
                $emailService->sendVerificationCode($email, $fullName, $code);

                echo json_encode([
                    'success' => true,
                    'message' => 'Verification code resent successfully'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
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
    <title>Register - LUREID</title>
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

        .hero-quote {
            font-family: 'Bebas Neue', sans-serif;
        }

        .quote-mark {
            font-family: 'Playfair Display', serif;
        }

        .form-input {
            height: 60px !important;
        }

        .modal-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
        }

        .input-icon {
            top: 20px !important;
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

            #register-form-container {
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
        }

        /* 625px altındaki cihazlar için */
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

            #register-form-container {
                padding-top: 0 !important;
                max-width: 100% !important;
                margin-top: -80px !important;
            }

            .step-content {
                margin-top: 32px !important;
            }
        }

        /* Tablet & Small Laptop */
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

            #register-form-container {
                max-width: 380px !important;
                width: 100% !important;
                margin: 0 auto !important;
            }

            .form-title {
                font-size: 28px !important;
                margin-bottom: 12px !important;
            }
        }

        /* Desktop */
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

            #register-form-container {
                width: 100% !important;
                max-width: 400px !important;
                margin: 0 auto !important;
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
        ← Geri Dön
    </a>

    <!-- Main Layout -->
    <div class="flex min-h-screen">
        <!-- Content Section -->
        <section class="content-section flex flex-col justify-center items-center p-10 md:p-6 relative bg-[#f9f9f9]">
            <div id="register-form-container" class="max-w-[400px] md:max-w-full w-full md:-mt-20">
                <h1
                    class="form-title text-[2em] md:text-[24px] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue']">
                    LUREID'inizi Oluşturun
                </h1>
                <p class="subtitle text-[#6B7280] text-center mb-12 md:mb-8 md:text-[14px]">
                    Bugün topluluğumuza katılın!
                </p>

                <!-- Progress Bar -->
                <div class="w-full bg-[#E5E7EB] rounded-full h-1.5 mb-8">
                    <div id="progress-bar" class="bg-[#4F46E5] h-1.5 rounded-full transition-all duration-500"
                        style="width: 20%"></div>
                </div>

                <!-- Registration Form -->
                <form id="registerForm" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateToken(); ?>">

                    <!-- Step 1: Personal Info -->
                    <div id="step1" class="step-content space-y-4">
                        <div class="relative">
                            <img src="../sources/icons/bulk/user.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="user icon">
                            <input type="text" name="fullName" id="fullName"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Ad Soyad">
                        </div>
                        <div class="relative">
                            <img src="../sources/icons/bulk/sms.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="email icon">
                            <input type="email" name="email" id="email"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Email adresi">
                        </div>
                    </div>

                    <!-- Step 2: Verification -->
                    <div id="step2" class="step-content space-y-4 hidden">
                        <div class="relative">
                            <img src="../sources/icons/bulk/shield.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="shield icon">
                            <input type="text" name="verificationCode" id="verificationCode"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors text-center tracking-[0.5em]"
                                maxlength="4" placeholder="0000">
                        </div>
                        <p class="text-center text-[13px] text-[#6B7280]">
                            E-postanıza gönderilen 4 haneli kodu girin
                        </p>
                        <button type="button" id="resendCode"
                            class="w-full text-[#4F46E5] hover:text-[#0b0086] text-sm font-semibold transition-colors">
                            Kodu Yeniden Gönder
                        </button>
                    </div>

                    <!-- Step 3: Username -->
                    <div id="step3" class="step-content space-y-4 hidden">
                        <div class="relative">
                            <img src="../sources/icons/bulk/profile.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="at sign icon">
                            <input type="text" name="username" id="username"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Choose username">
                        </div>
                        <p class="text-[13px] text-[#6B7280]">
                            Username must be unique.
                        </p>
                    </div>

                    <!-- Step 4: Password -->
                    <div id="step4" class="step-content space-y-4 hidden">
                        <div class="relative">
                            <img src="../sources/icons/bulk/lock.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="lock icon">
                            <input type="password" name="password" id="password"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Create password">
                        </div>
                        <div class="relative">
                            <img src="../sources/icons/bulk/lock.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="lock icon">
                            <input type="password" name="confirmPassword" id="confirmPassword"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Confirm password">
                        </div>
                    </div>

                    <!-- Step 5: Invitation -->
                    <div id="step5" class="step-content space-y-6 hidden">
                        <div class="bg-[#F3F4F6] rounded-lg p-6">
                            <label class="block text-[13px] font-medium text-[#374151] mb-2">Your Invitation
                                Code</label>
                            <div id="generatedInviteCode"
                                class="bg-white h-[60px] md:h-[48px] flex items-center justify-center text-[16px] font-mono rounded-lg border border-[#E5E7EB]">
                            </div>
                            <p class="mt-2 text-[13px] text-[#6B7280]">
                                Save this code - you'll need it to invite others
                            </p>
                        </div>
                        <div class="relative">
                            <img src="../sources/icons/bulk/ticket.svg"
                                class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                                alt="ticket icon">
                            <input type="text" name="referralCode" id="referralCode"
                                class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                placeholder="Enter referral code (optional)">
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex gap-4">
                        <button type="button" id="prevBtn"
                            class="hidden flex-1 h-[60px] md:h-[52px] bg-white border border-[#E5E7EB] text-[#374151] rounded-lg text-sm font-semibold transition-all hover:bg-gray-50">
                            Önceki
                        </button>
                        <button type="button" id="nextBtn"
                            class="flex-1 h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg">
                            Sonraki
                        </button>
                    </div>
                </form>

                <!-- Bottom Links -->
                <div class="bottom-links text-center text-[#888] text-[13px] mt-6">
                    <p>
                        Zaten bir hesabınız var mı?
                        <a href="login.php" class="text-[#333] font-semibold hover:text-[#4F46E5] transition-colors">
                            Buradan Oturum Açın
                        </a>
                    </p>
                </div>
            </div>
        </section>

        <!-- Hero Section -->
        <section class="hero-section m-6 rounded-[30px] relative overflow-hidden shadow-lg">
            <div class="hero-slider h-full w-full">
                <!-- Slide 1 -->
                <div class="slide active" style="background-image: url('../sources/images/bg1.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            Join our community of developers and creators, where knowledge meets opportunity in the
                            digital age.
                        </p>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="slide" style="background-image: url('../sources/images/bg2.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            Turn your skills into passive income at LURE, where thousands of developers and employers
                            meet.
                        </p>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="slide" style="background-image: url('../sources/images/bg3.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            Build your future in tech with a community that supports your growth every step of the way.
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/CustomEase.min.js"></script>
    <script src="../sources/js/slider.js" defer></script>
    <script>
        // API işlemleri
        async function checkUsername(username) {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `check_username=true&username=${encodeURIComponent(username)}`
            });
            return response.json();
        }

        async function checkReferralCode(code) {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `check_referral=true&referralCode=${encodeURIComponent(code)}`
            });
            return response.json();
        }

        async function completeRegistration(email, username) {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `complete_registration=true&email=${encodeURIComponent(email)}&username=${encodeURIComponent(username)}`
            });
            return response.json();
        }

        function showToast(message, type) {
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

            toast.show(message, type);
        }

        // Enhanced JavaScript with security and validation
        const STEPS = {
            PERSONAL: 1,
            VERIFICATION: 2,
            USERNAME: 3,
            PASSWORD: 4,
            INVITATION: 5
        };

        function debugState() {
            console.log({
                currentStep,
                verificationAttempts,
                elementsVisible: {
                    step1: !document.getElementById('step1').classList.contains('hidden'),
                    step2: !document.getElementById('step2').classList.contains('hidden'),
                    step3: !document.getElementById('step3').classList.contains('hidden'),
                    step4: !document.getElementById('step4').classList.contains('hidden'),
                    step5: !document.getElementById('step5').classList.contains('hidden'),
                }
            });
        }

        function verifyUIState() {
            const visibleSteps = document.querySelectorAll('[id^="step"]:not(.hidden)');
            if (visibleSteps.length !== 1) {
                console.error('Invalid UI state: Multiple or no steps visible');
                console.log('Visible steps:', visibleSteps);
            }
            return visibleSteps.length === 1;
        }

        let currentStep = STEPS.PERSONAL;
        let verificationAttempts = 0;
        const MAX_VERIFICATION_ATTEMPTS = 3;

        // Enhanced toast notification system
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
            email: (email) => {
                const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return regex.test(email);
            },

            password: (password) => {
                return password.length >= 8 &&
                    /[A-Z]/.test(password) &&
                    /[a-z]/.test(password) &&
                    /[0-9]/.test(password);
            },

            username: (username) => {
                return /^[a-z][a-z0-9]{2,29}$/.test(username);
            }
        };

        const api = {
            async post(endpoint, data) {
                try {
                    // Mevcut URL'yi kullan
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(data)
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const result = await response.json();
                    console.log('API Response:', result); // Debug için log ekleyelim
                    return result;
                } catch (error) {
                    console.error('API Error:', error);
                    throw error;
                }
            }
        };

        let isLoading = false;

        // Step validation and progression
        async function validateCurrentStep() {
            if (isLoading) return false; // If already loading, prevent multiple clicks

            try {
                // Set loading state
                isLoading = true;
                const nextButton = document.getElementById('nextBtn');
                const originalText = nextButton.textContent;
                nextButton.disabled = true;
                nextButton.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline-block text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            İşleniyor...
        `;
                switch (currentStep) {
                    case STEPS.PERSONAL:
                        try {
                            const fullName = document.querySelector('[name="fullName"]').value.trim();
                            const email = document.querySelector('[name="email"]').value.trim();

                            console.log('Validating step 1:', { fullName, email });

                            if (!fullName) {
                                throw new Error('Please enter your full name');
                            }

                            if (!validate.email(email)) {
                                throw new Error('Please enter a valid email address');
                            }

                            // Check email availability
                            const emailCheck = await api.post('', {
                                check_email: true,
                                email: email
                            });

                            if (!emailCheck.available) {
                                throw new Error('E-posta zaten kayıtlı');
                            }

                            // Send verification code
                            const verificationResponse = await api.post('', {
                                send_verification: true,
                                email: email,
                                fullName: fullName,
                                csrf_token: document.querySelector('[name="csrf_token"]').value
                            });

                            if (!verificationResponse.success) {
                                throw new Error('Failed to send verification code');
                            }

                            toast.show('Verification code sent to your email', 'info');
                            return true;
                        } catch (error) {
                            console.error('Step 1 validation error:', error);
                            toast.show(error.message, 'error');
                            return false;
                        } finally {
                            // Reset loading state
                            isLoading = false;
                            nextButton.disabled = false;
                            nextButton.textContent = originalText;
                        }

                    case STEPS.VERIFICATION:
                        // Email bilgisini almayı unutmayalım
                        const email = document.querySelector('[name="email"]').value.trim();
                        const code = document.querySelector('[name="verificationCode"]').value.trim();

                        if (!code || code.length !== 4) {
                            throw new Error('Please enter the 4-digit verification code');
                        }

                        const verifyResponse = await api.post('', {  // URL'yi düzelttik
                            verify_code: true,
                            email: email,  // Email'i ekliyoruz
                            verificationCode: code
                        });

                        if (!verifyResponse.success) {
                            verificationAttempts++;
                            if (verificationAttempts >= MAX_VERIFICATION_ATTEMPTS) {
                                throw new Error('Too many verification attempts. Please try again later.');
                            }
                            throw new Error(verifyResponse.message || 'Invalid verification code');
                        }
                        break;

                    case STEPS.USERNAME:
                        const username = document.querySelector('[name="username"]').value.trim();

                        if (!username) {
                            throw new Error('Please enter a username');
                            return false;
                        }

                        if (!validate.username(username)) {
                            throw new Error('Username must start with a letter and contain only lowercase letters and numbers');
                            return false;
                        }

                        try {
                            const usernameCheck = await api.post('', {
                                check_username: true,
                                username: username
                            });

                            if (!usernameCheck.available) {
                                throw new Error('Username already taken');
                                return false;
                            }

                            showInputSuccess(document.querySelector('[name="username"]'));
                            return true;
                        } catch (error) {
                            throw new Error('Error checking username availability');
                            return false;
                        }

                    case STEPS.PASSWORD:
                        const password = document.querySelector('[name="password"]').value;
                        const confirmPassword = document.querySelector('[name="confirmPassword"]').value;

                        if (!validate.password(password)) {
                            throw new Error('Password must be at least 8 characters long and contain uppercase, lowercase, and numbers');
                        }

                        if (password !== confirmPassword) {
                            throw new Error('Passwords do not match');
                        }
                        break;

                    case STEPS.INVITATION:
                        try {
                            const email = document.querySelector('[name="email"]').value.trim();
                            const username = document.querySelector('[name="username"]').value.trim();
                            const password = document.querySelector('[name="password"]').value;
                            const referralCode = document.querySelector('[name="referralCode"]').value.trim();

                            // İlk olarak davet kodunu al (eğer daha önce alınmadıysa)
                            if (!document.getElementById('generatedInviteCode').textContent) {
                                const getTempUserData = await api.post('', {
                                    get_temp_user: true,
                                    email: email
                                });

                                if (getTempUserData.success) {
                                    document.getElementById('generatedInviteCode').textContent = getTempUserData.inviteCode;
                                } else {
                                    throw new Error('Failed to retrieve invite code');
                                }
                            }

                            // Referral kodu dolu ise kontrol et
                            if (referralCode) {
                                // Kendi kodunu kullanmaya çalışıyor mu?
                                if (referralCode === document.getElementById('generatedInviteCode').textContent) {
                                    throw new Error('You cannot use your own invite code');
                                }

                                // Referral kodu geçerli mi?
                                const referralCheck = await api.post('', {
                                    check_referral: true,
                                    referralCode: referralCode
                                });

                                if (!referralCheck.exists) {
                                    throw new Error('Invalid referral code');
                                }
                            }

                            // Kayıt işlemini tamamla
                            const response = await api.post('', {
                                complete_registration: true,
                                email: email,
                                username: username,
                                password: password,
                                referralCode: referralCode,
                                csrf_token: document.querySelector('[name="csrf_token"]').value
                            });

                            if (!response.success) {
                                throw new Error(response.message || 'Registration failed');
                            }

                            toast.show('Registration successful! Redirecting...', 'success');
                            setTimeout(() => window.location.href = '../public/index.php', 2000);
                            return true;

                        } catch (error) {
                            toast.show(error.message, 'error');
                            return false;
                        }
                        break;
                }

                return true;
            } catch (error) {
                toast.show(error.message, 'error');
                return false;
            } finally {
                // Always reset loading state
                isLoading = false;
                const nextButton = document.getElementById('nextBtn');
                nextButton.disabled = false;
                nextButton.textContent = currentStep === STEPS.INVITATION ? 'Complete Registration' : 'Next';
            }
        }

        // Navigation controls
        document.getElementById('nextBtn').addEventListener('click', async () => {
            console.log('Next button clicked, current step:', currentStep);
            debugState(); // Önceki durum

            const result = await validateCurrentStep();
            console.log('Validation result:', result);

            if (result === true) {
                if (currentStep < STEPS.INVITATION) {
                    currentStep++;
                    console.log('Moving to step:', currentStep);
                    await updateUI(); // async/await eklendi
                    debugState(); // Sonraki durum
                }
            }
        });

        document.getElementById('prevBtn').addEventListener('click', () => {
            if (currentStep > STEPS.PERSONAL) {
                currentStep--;
                updateUI();
            }
        });

        // UI updates
        function updateUI() {
            console.log('Updating UI for step:', currentStep);
            debugState(); // Debug için eklendi

            // Update progress bar
            const progress = (currentStep / Object.keys(STEPS).length) * 100;
            document.getElementById('progress-bar').style.width = `${progress}%`;

            // Hide all steps
            document.querySelectorAll('[id^="step"]').forEach(step => {
                step.classList.add('hidden');
            });

            // Show current step
            const currentStepElement = document.getElementById(`step${currentStep}`);
            if (currentStepElement) {
                currentStepElement.classList.remove('hidden');
                console.log(`Showing step${currentStep}`); // Debug için eklendi
            } else {
                console.error(`Step element not found: step${currentStep}`);
            }

            // Update navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            prevBtn.classList.toggle('hidden', currentStep === STEPS.PERSONAL);
            nextBtn.textContent = currentStep === STEPS.INVITATION ? 'Complete Registration' : 'Next';

            if (currentStep === STEPS.USERNAME) {
                setupUsernameValidation();
            }

            // Handle special cases
            if (currentStep === STEPS.INVITATION) {
                // Immediately fetch and display the invite code
                (async () => {
                    try {
                        const email = document.querySelector('[name="email"]').value.trim();
                        const getTempUserData = await api.post('', {
                            get_temp_user: true,
                            email: email
                        });

                        if (getTempUserData.success) {
                            document.getElementById('generatedInviteCode').textContent = getTempUserData.inviteCode;
                        } else {
                            toast.show('Error retrieving invite code', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        toast.show('Failed to load invite code', 'error');
                    }
                })();
                setupInvitationCodeValidation();
            }

            // Verify UI state
            if (!verifyUIState()) {
                console.error('UI update failed, attempting to recover...');
                document.querySelectorAll('[id^="step"]').forEach(step => {
                    step.classList.add('hidden');
                });
                document.getElementById(`step${currentStep}`).classList.remove('hidden');
            }

            debugState(); // Debug için eklendi
        }

        // Resend verification code
        document.getElementById('resendCode').addEventListener('click', async () => {
            try {
                const email = document.querySelector('[name="email"]').value;
                const fullName = document.querySelector('[name="fullName"]').value;

                const response = await api.post('register.php', {
                    send_verification: true,
                    email: email,
                    fullName: fullName,
                    csrf_token: document.querySelector('[name="csrf_token"]').value
                });

                if (response.success) {
                    toast.show('Verification code resent', 'success');
                    verificationAttempts = 0;
                } else {
                    throw new Error(response.message || 'Failed to resend code');
                }
            } catch (error) {
                toast.show(error.message, 'error');
            }
        });

        // Real-time validation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', async function () {
                const fieldName = this.getAttribute('name');
                const value = this.value.trim();

                switch (fieldName) {
                    case 'email':
                        if (value && !validate.email(value)) {
                            showInputError(this, 'Please enter a valid email address');
                        } else if (value) {
                            try {
                                const response = await api.post('register.php', {
                                    check_email: true,
                                    email: value
                                });
                                if (response.available) {
                                    showInputSuccess(this);
                                } else {
                                    showInputError(this, 'Email already registered');
                                }
                            } catch (error) {
                                showInputError(this, 'Error checking email availability');
                            }
                        }
                        break;

                    case 'username':
                        const usernameStatus = this.parentElement.querySelector('.error-message') || (() => {
                            const status = document.createElement('p');
                            status.className = 'mt-2 text-sm error-message';
                            this.parentElement.appendChild(status);
                            return status;
                        })();

                        if (!value) {
                            this.classList.remove('border-red-500', 'border-green-500', 'bg-red-50', 'bg-green-50');
                            this.classList.add('border-gray-300');
                            usernameStatus.textContent = '';
                            document.getElementById('nextBtn').disabled = true;
                            return;
                        }

                        if (!validate.username(value)) {
                            showInputError(this, 'Username must start with a letter and contain only lowercase letters and numbers');
                            document.getElementById('nextBtn').disabled = true;
                            return;
                        }

                        // Username formatı doğruysa, kullanılabilirlik kontrolü yap
                        try {
                            const response = await api.post('register.php', {
                                check_username: true,
                                username: value
                            });

                            if (response.available) {
                                showInputSuccess(this);
                                usernameStatus.className = 'mt-2 text-sm text-green-600 error-message';
                                usernameStatus.textContent = 'Username is available';
                                document.getElementById('nextBtn').disabled = false;
                            } else {
                                showInputError(this, 'Username is already taken');
                                document.getElementById('nextBtn').disabled = true;
                            }
                        } catch (error) {
                            showInputError(this, 'Error checking username availability');
                            document.getElementById('nextBtn').disabled = true;
                        }
                        break;

                    case 'password':
                        if (value) {
                            const isValid = validate.password(value);
                            if (!isValid) {
                                showInputError(this, 'Password must be at least 8 characters with uppercase, lowercase and numbers');
                            } else {
                                showInputSuccess(this);
                            }
                        }
                        break;

                    case 'confirmPassword':
                        if (value) {
                            const password = document.querySelector('[name="password"]').value;
                            if (value !== password) {
                                showInputError(this, 'Passwords do not match');
                            } else {
                                showInputSuccess(this);
                            }
                        }
                        break;
                }
            });
        });

        // Invitation code input styles
        function setupInvitationCodeValidation() {
            const referralInput = document.querySelector('[name="referralCode"]');
            const inviteCodeDisplay = document.getElementById('generatedInviteCode');

            // Generated invite code stillerini güncelle
            inviteCodeDisplay.classList.add('p-4', 'bg-gray-50', 'border', 'border-gray-300', 'rounded-lg', 'font-mono', 'text-lg', 'text-center');

            // URL'den ref parametresini al
            const urlParams = new URLSearchParams(window.location.search);
            const refCode = urlParams.get('ref');

            // Eğer URL'de ref parametresi varsa ve referralInput mevcutsa
            if (refCode && referralInput) {
                // Input'a değeri set et
                referralInput.value = refCode;

                // Validasyon için input event'ini tetikle
                const event = new Event('input', {
                    bubbles: true,
                    cancelable: true,
                });
                referralInput.dispatchEvent(event);
            }

            // Referral code input için real-time validasyon
            referralInput.addEventListener('input', async function () {
                const value = this.value.trim();

                // Eğer boşsa normal stile çevir
                if (!value) {
                    this.classList.remove('border-red-500', 'border-green-500', 'bg-red-50', 'bg-green-50');
                    this.classList.add('border-gray-300');
                    return;
                }

                // Kendi kodunu kullanmaya çalışıyor mu?
                const currentInviteCode = inviteCodeDisplay.textContent;
                if (currentInviteCode && value === currentInviteCode) {
                    showInputError(this, 'You cannot use your own invite code');
                    return;
                }

                try {
                    const response = await api.post('', {
                        check_referral: true,
                        referralCode: value
                    });

                    if (response.exists) {
                        showInputSuccess(this);
                    } else {
                        showInputError(this, 'Invalid referral code');
                    }
                } catch (error) {
                    showInputError(this, 'Error checking referral code');
                }
            });
        }

        // Initialize the form
        document.addEventListener('DOMContentLoaded', () => {
            currentStep = STEPS.PERSONAL; // Reset current step
            updateUI();
            console.log('Form initialized');
            debugState();
        });

        // Flowbite validations
        function showInputError(input, message) {
            input.classList.remove('border-gray-300', 'focus:border-blue-500');
            input.classList.add('border-red-500', 'focus:border-red-500', 'bg-red-50');

            // Error message
            let errorDiv = input.parentElement.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('p');
                errorDiv.className = 'mt-2 text-sm text-red-600 error-message';
                input.parentElement.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }

        function showInputSuccess(input) {
            input.classList.remove('border-red-500', 'focus:border-red-500', 'bg-red-50');
            input.classList.add('border-green-500', 'focus:border-green-500', 'bg-green-50');

            // Remove error message if exists
            const errorDiv = input.parentElement.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
    </script>
</body>

</html>