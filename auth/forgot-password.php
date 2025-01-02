<?php
//forgot-password.php
session_start();
require_once '../vendor/autoload.php';
require_once '../config/logger.php';

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

    public static function generateResetToken()
    {
        return bin2hex(random_bytes(32));
    }
}

class PasswordReset
{
    private $db;
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
        try {
            $dbConfig = require '../config/database.php';
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";

            $this->db = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options']
            );
        } catch (PDOException $e) {
            $this->logger->log("Database connection failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Database connection failed");
        }
    }

    public function verifyEmail($emailOrUsername)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, username, email 
                FROM users 
                WHERE (email = ? OR username = ?) 
                AND is_verified = 1
            ");
            $stmt->execute([$emailOrUsername, $emailOrUsername]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Log for debugging
            $this->logger->log("Verify email/username result for '$emailOrUsername': " . json_encode($result));

            return $result;
        } catch (PDOException $e) {
            $this->logger->log("Email/username verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Email/username verification failed");
        }
    }

    public function createResetToken($userId, $token)
    {
        try {
            // Önce eski token'ları temizle
            $stmt = $this->db->prepare("DELETE FROM verification WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Yeni token ekle
            $stmt = $this->db->prepare("
                INSERT INTO verification (user_id, code, expires_at) 
                VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))
            ");
            return $stmt->execute([$userId, $token]);
        } catch (PDOException $e) {
            $this->logger->log("Reset token creation failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to create reset token");
        }
    }

    public function verifyResetToken($token)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT v.user_id, u.email, u.username
                FROM verification v
                JOIN users u ON u.user_id = v.user_id
                WHERE v.code = ? AND v.expires_at > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->log("Reset token verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Token verification failed");
        }
    }

    public function updatePassword($userId, $password)
    {
        try {
            $this->db->beginTransaction();

            // Şifreyi güncelle
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

            // Reset token'ı sil
            $stmt = $this->db->prepare("DELETE FROM verification WHERE user_id = ?");
            $stmt->execute([$userId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logger->log("Password update failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to update password");
        }
    }
}

class ResetEmailService
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

    public function sendResetLink($email, $username, $token)
    {
        try {
            $this->mailer->addAddress($email, $username);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Reset Your Password - LUREID';
            $this->mailer->Body = $this->getResetEmailTemplate($username, $token);

            $result = $this->mailer->send();
            $this->logger->log("Reset email sent to: $email");
            return $result;
        } catch (Exception $e) {
            $this->logger->log("Failed to send reset email: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to send reset email");
        }
    }

    private function getResetEmailTemplate($username, $token)
    {
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/views/auth/reset-password.php?token=" . $token;

        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Password Reset Request</h2>
            <p>Hello {$username},</p>
            <p>You have requested to reset your password. Click the button below to set a new password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' style='background-color: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;'>
                    Reset Password
                </a>
            </div>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this password reset, please ignore this email.</p>
            <p>Best regards,<br>LUREID Team</p>
        </div>";
    }
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();

    try {
        $security = new Security();
        $passwordReset = new PasswordReset($logger);

        // Request reset link
        if (isset($_POST['request_reset'])) {
            if (!$security->validateToken($_POST['csrf_token'])) {
                throw new Exception("Invalid CSRF token");
            }

            $emailOrUsername = $security->sanitizeInput($_POST['email']);
            $userData = $passwordReset->verifyEmail($emailOrUsername);

            if ($userData) {
                $resetToken = $security->generateResetToken();
                $passwordReset->createResetToken($userData['user_id'], $resetToken);

                $emailService = new ResetEmailService($logger);
                // Her zaman userData['email'] kullan, çünkü bu veritabanındaki email adresi
                $emailService->sendResetLink($userData['email'], $userData['username'], $resetToken);

                echo json_encode([
                    'success' => true,
                    'message' => 'Password reset instructions have been sent to your email',
                    'email' => $userData['email'] // Success screen için email'i gönder
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'If this email/username exists in our system, you will receive reset instructions'
                ]);
            }
            exit;
        }

        // Verify reset token
        if (isset($_POST['verify_token'])) {
            $token = $security->sanitizeInput($_POST['token']);
            $userData = $passwordReset->verifyResetToken($token);

            echo json_encode([
                'success' => (bool) $userData,
                'message' => $userData ? 'Valid reset token' : 'Invalid or expired reset token'
            ]);
            exit;
        }

        // Reset password
        if (isset($_POST['reset_password'])) {
            if (!$security->validateToken($_POST['csrf_token'])) {
                throw new Exception("Invalid CSRF token");
            }

            $token = $security->sanitizeInput($_POST['token']);
            $password = $_POST['password'];

            if (!$security->validatePassword($password)) {
                throw new Exception("Password must be at least 8 characters with uppercase, lowercase and numbers");
            }

            $userData = $passwordReset->verifyResetToken($token);
            if (!$userData) {
                throw new Exception("Invalid or expired reset token");
            }

            $passwordReset->updatePassword($userData['user_id'], $password);

            echo json_encode([
                'success' => true,
                'message' => 'Password has been successfully reset'
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
    <title>Forgot Password - LUREID</title>
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

            #reset-form-container {
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

            #reset-form-container {
                padding-top: 0 !important;
                max-width: 100% !important;
                margin-top: -80px !important;
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

            #reset-form-container {
                max-width: 380px !important;
                width: 100% !important;
                margin: 0 auto !important;
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

            #reset-form-container {
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
    <a href="login.php"
        class="nav-back absolute top-10 left-10 text-gray-800 hover:text-gray-600 font-semibold text-sm z-20">
        ← Geri Dön
    </a>

    <!-- Main Layout -->
    <div class="flex min-h-screen">
        <!-- Content Section -->
        <section class="content-section flex flex-col justify-center items-center p-10 md:p-6 relative bg-[#f9f9f9]">
            <div id="reset-form-container" class="max-w-[400px] md:max-w-full w-full md:-mt-20">
                <div id="initial-content">
                    <h1
                        class="form-title text-[2em] md:text-[24px] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue']">
                        Şifrenizi Sıfırlayın
                    </h1>
                    <p class="subtitle text-[#6B7280] text-center mb-12 md:mb-8 md:text-[14px]">
                    Sıfırlama talimatlarını almak için e-postanızı girin
                    </p>

                    <!-- Reset Form -->
                    <form id="resetForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateToken(); ?>">

                        <div id="requestFields">
                            <div class="relative">
                                <img src="../sources/icons/bulk/sms.svg"
                                    class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20"
                                    height="20" alt="email icon">
                                <input type="text" name="email" id="email" required
                                    class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                                    placeholder="E-posta adresi veya kullanıcı adı">
                            </div>
                        </div>

                        <button type="submit"
                            class="form-button w-full h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg flex items-center justify-center gap-3">
                            <span>Sıfırlama Bağlantısını Gönder</span>
                        </button>
                    </form>
                </div>

                <!-- Success Screen (Initially Hidden) -->
                <div id="success-content" class="hidden space-y-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </div>
                    <div class="text-center space-y-3">
                        <h3 class="text-xl font-semibold text-gray-900">E-postanızı kontrol edin</h3>
                        <p class="text-sm text-gray-500">
                        Şuraya bir şifre sıfırlama bağlantısı gönderdik: <br>
                            <span id="masked-email" class="font-medium"></span>
                        </p>
                    </div>
                    <button type="button" id="backButton"
                        class="text-sm font-medium text-[#4F46E5] hover:text-[#0b0086] transition-colors">
                        ← Başka bir e-posta veya kullanıcı adı deneyin
                    </button>
                </div>

                <!-- Bottom Links -->
                <div
                    class="bottom-links absolute bottom-10 md:fixed md:bottom-8 text-center text-[#888] text-[13px] w-full">
                    <p>
                    Şifrenizi hatırlıyor musunuz?
                        <a href="login.php" class="text-[#333] font-semibold hover:text-[#4F46E5] transition-colors">
                            Buradan oturum açın
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
                            SECURE YOUR JOURNEY WITH US. RESET YOUR PASSWORD AND GET BACK TO EXPLORING THE
                            WORLD OF OPPORTUNITIES AT LURE.
                        </p>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="slide" style="background-image: url('../sources/images/bg2.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            YOUR SECURITY IS OUR PRIORITY. WE'RE HERE TO HELP YOU REGAIN ACCESS TO
                            YOUR LURE ACCOUNT SAFELY AND SECURELY.
                        </p>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="slide" style="background-image: url('../sources/images/bg3.jpg')">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="quote-mark text-5xl text-white font-bold leading-none mr-1">"</span>
                        <p class="hero-quote text-4xl text-white font-bold uppercase leading-tight tracking-wider">
                            DON'T LET A FORGOTTEN PASSWORD HOLD YOU BACK. RESET IT NOW AND CONTINUE
                            YOUR JOURNEY WITH THE LURE COMMUNITY.
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
        // Form validation helper
        const validate = {
            emailOrUsername: (value) => {
                // Email regex pattern
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                // Username pattern (3-30 karakter, alfanumerik ve alt çizgi)
                const usernamePattern = /^[a-zA-Z0-9_]{3,30}$/;

                // Eğer @ işareti içeriyorsa email olarak kontrol et
                if (value.includes('@')) {
                    return emailPattern.test(value);
                }
                // @ işareti içermiyorsa kullanıcı adı olarak kontrol et
                return usernamePattern.test(value);
            },
            password: (password) => {
                return password.length >= 8 &&
                    /[A-Z]/.test(password) &&
                    /[a-z]/.test(password) &&
                    /[0-9]/.test(password);
            }
        };

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

        // API helper
        const api = {
            async post(endpoint, data) {
                try {
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

                    return await response.json();
                } catch (error) {
                    console.error('API Error:', error);
                    throw error;
                }
            }
        };

        // Input validation styles
        function showInputError(input, message) {
            input.classList.remove('border-gray-300', 'focus:border-blue-500');
            input.classList.add('border-red-500', 'focus:border-red-500', 'bg-red-50');

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

            const errorDiv = input.parentElement.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.remove();
            }
        }

        // Email maskeleme fonksiyonu
        function maskEmail(value) {
            if (value.includes('@')) {
                const [name, domain] = value.split('@');
                const maskedName = name.charAt(0) + '*'.repeat(name.length - 2) + name.charAt(name.length - 1);
                return `${maskedName}@${domain}`;
            }
            return value.charAt(0) + '*'.repeat(value.length - 2) + value.charAt(value.length - 1);
        }

        // Success screen gösterme fonksiyonu
        // Success screen gösterme fonksiyonu - Yeni versiyon
        function showSuccessScreen(value) {
            const maskedValue = maskEmail(value);
            const initialContent = document.getElementById('initial-content');
            const successContent = document.getElementById('success-content');
            const maskedEmailSpan = document.getElementById('masked-email');

            // Initial content'i gizle
            initialContent.classList.add('hidden');

            // Masked email'i set et ve success content'i göster
            maskedEmailSpan.textContent = maskedValue;
            successContent.classList.remove('hidden');

            // Back button handler
            document.getElementById('backButton').addEventListener('click', function () {
                successContent.classList.add('hidden');
                initialContent.classList.remove('hidden');
                document.getElementById('resetForm').reset();
            });
        }

        // Ana DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function () {
            const resetForm = document.getElementById('resetForm');
            const initialContent = document.getElementById('initial-content');
            const successContent = document.getElementById('success-content');
            const submitButton = resetForm.querySelector('button[type="submit"]');
            let isResetMode = false;

            // URL'den token kontrolü
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');

            if (token) {
                verifyToken(token);
            }

            async function verifyToken(token) {
                try {
                    const response = await api.post('', {
                        verify_token: true,
                        token: token
                    });

                    if (response.success) {
                        showPasswordForm();
                    } else {
                        toast.show('Invalid or expired reset link', 'error');
                        setTimeout(() => window.location.href = 'forgot-password.php', 2000);
                    }
                } catch (error) {
                    toast.show('Error verifying reset token', 'error');
                }
            }

            function showPasswordForm() {
                requestFields.classList.add('hidden');
                passwordFields.classList.remove('hidden');
                submitButton.textContent = 'Reset Password';
                isResetMode = true;
            }

            const backButton = document.getElementById('backButton');
            if (backButton) {
                backButton.addEventListener('click', function () {
                    successContent.classList.add('hidden');
                    initialContent.classList.remove('hidden');
                    resetForm.reset();
                });
            }

            resetForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitButton = this.querySelector('button[type="submit"]');

                // Processing state
                const originalButtonContent = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline-block text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Processing...
    `;

                try {
                    if (isResetMode) {
                        // Reset password işlemi
                        const password = document.getElementById('password').value;
                        const confirmPassword = document.getElementById('confirmPassword').value;

                        if (!validate.password(password)) {
                            throw new Error('Password must be at least 8 characters with uppercase, lowercase and numbers');
                        }

                        if (password !== confirmPassword) {
                            throw new Error('Passwords do not match');
                        }

                        const response = await api.post('', {
                            reset_password: true,
                            token: token,
                            password: password,
                            csrf_token: document.querySelector('[name="csrf_token"]').value
                        });

                        if (response.success) {
                            toast.show('Password has been reset successfully', 'success');
                            setTimeout(() => window.location.href = 'login.php', 2000);
                        } else {
                            throw new Error(response.message);
                        }
                    } else {
                        const emailOrUsername = document.getElementById('email').value.trim();

                        if (!validate.emailOrUsername(emailOrUsername)) {
                            throw new Error('Please enter a valid email address or username');
                        }

                        const response = await api.post('', {
                            request_reset: true,
                            email: emailOrUsername,
                            csrf_token: document.querySelector('[name="csrf_token"]').value
                        });

                        if (response.success) {
                            const emailToShow = response.email || emailOrUsername;
                            showSuccessScreen(emailToShow);
                        } else {
                            toast.show('If this email/username exists in our system, you will receive reset instructions', 'info');
                        }
                    }
                } catch (error) {
                    toast.show(error.message, 'error');
                } finally {
                    // Reset button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            });

            // Real-time input validation
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function () {
                    const fieldName = this.getAttribute('name');
                    const value = this.value.trim();

                    switch (fieldName) {
                        case 'email':
                            if (value && !validate.emailOrUsername(value)) {
                                showInputError(this, 'Please enter a valid email address or username');
                            } else {
                                showInputSuccess(this);
                            }
                            break;

                        case 'password':
                            if (value && !validate.password(value)) {
                                showInputError(this, 'Password must be at least 8 characters with uppercase, lowercase and numbers');
                            } else {
                                showInputSuccess(this);
                            }
                            break;

                        case 'confirmPassword':
                            const password = document.getElementById('password').value;
                            if (value && value !== password) {
                                showInputError(this, 'Passwords do not match');
                            } else if (value) {
                                showInputSuccess(this);
                            }
                            break;
                    }
                });
            });
        });
    </script>
</body>

</html>