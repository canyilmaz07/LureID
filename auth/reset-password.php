<?php
//reset-password.php
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
}

// Email servisi için yeni sınıf ekleyelim (PasswordReset sınıfından sonra)
class PasswordChangeEmailService
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

    public function sendPasswordChangeNotification($email, $username)
    {
        try {
            $this->mailer->addAddress($email, $username);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Changed Successfully - LUREID';
            $this->mailer->Body = $this->getEmailTemplate($username);

            $result = $this->mailer->send();
            $this->logger->log("Password change notification sent to: $email");
            return $result;
        } catch (Exception $e) {
            $this->logger->log("Failed to send password change notification: " . $e->getMessage(), 'ERROR');
            throw new Exception("Failed to send notification email");
        }
    }

    private function getEmailTemplate($username)
    {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Password Changed Successfully</h2>
                <p>Hello {$username},</p>
                <p>Your password has been successfully changed.</p>
                <p>If you did not make this change, please contact our support team immediately.</p>
                <p>Best regards,<br>LUREID Team</p>
            </div>";
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
            $this->logger->log("Token verification failed: " . $e->getMessage(), 'ERROR');
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

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();

    try {
        $security = new Security();
        $passwordReset = new PasswordReset($logger);

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

            // Şifreyi güncelle
            $passwordReset->updatePassword($userData['user_id'], $password);

            // Bildirim emaili gönder
            $emailService = new PasswordChangeEmailService($logger);
            $emailService->sendPasswordChangeNotification($userData['email'], $userData['username']);

            // Session'ı ayarla (otomatik login için)
            $_SESSION['user_id'] = $userData['user_id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['logged_in'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Password has been successfully reset',
                'redirect' => '../public/index.php'
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

// Get token from URL
$token = $_GET['token'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Reset Password - LUREID</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@400..900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        *,
        html,
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* iPhone 14 Pro Max & similar devices */
        @media screen and (max-width: 428px) {
            #reset-form-container {
                padding-top: 0 !important;
                max-width: 100% !important;
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
        }

        /* Desktop */
        @media screen and (min-width: 1095px) {
            #reset-form-container {
                width: 100% !important;
                max-width: 400px !important;
                margin: 0 auto !important;
            }
        }
    </style>
</head>

<body class="bg-[#f9f9f9]">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 space-y-2 z-50"></div>

    <!-- Back Navigation -->
    <a href="index" class="absolute top-10 left-10 text-gray-800 hover:text-gray-600 font-semibold text-sm">
        ← Get back
    </a>

    <!-- Main Container -->
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div id="reset-form-container" class="w-full max-w-[400px] space-y-8 bg-white p-8 rounded-[20px] shadow-lg">
            <!-- Header -->
            <div class="text-center">
                <h1
                    class="form-title text-[2em] md:text-[24px] font-bold text-[#111827] uppercase tracking-wider font-['Bebas_Neue']">
                    Reset Your Password
                </h1>
                <p class="subtitle text-[#6B7280] text-sm md:text-[14px] mt-2">
                    Enter your new password below
                </p>
            </div>

            <!-- Reset Form -->
            <form id="resetForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateToken(); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="space-y-4">
                    <div class="relative">
                        <img src="../sources/icons/bulk/lock.svg"
                            class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                            alt="lock icon">
                        <input type="password" name="password" id="password" required
                            class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                            placeholder="New password">
                        <div class="password-requirements mt-2 text-sm space-y-1"></div>
                    </div>

                    <div class="relative">
                        <img src="../sources/icons/bulk/lock.svg"
                            class="input-icon absolute left-[25px] top-[22px] md:top-[16px]" width="20" height="20"
                            alt="lock icon">
                        <input type="password" name="confirmPassword" id="confirmPassword" required
                            class="form-input w-full h-[60px] md:h-[48px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] md:text-[14px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                            placeholder="Confirm password">
                    </div>
                </div>

                <button type="submit"
                    class="form-button w-full h-[60px] md:h-[52px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg flex items-center justify-center gap-3">
                    <span>Reset Password</span>
                </button>
            </form>

            <div class="text-center text-[#888] text-[13px]">
                <p>
                    Remember your password?
                    <a href="login.php" class="text-[#333] font-semibold hover:text-[#4F46E5] transition-colors">
                        Sign in here
                    </a>
                </p>
            </div>
        </div>
    </div>

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
            password: (password) => {
                return password.length >= 8 &&
                    /[A-Z]/.test(password) &&
                    /[a-z]/.test(password) &&
                    /[0-9]/.test(password);
            }
        };

        document.addEventListener('DOMContentLoaded', function () {
            const resetForm = document.getElementById('resetForm');
            const submitButton = resetForm.querySelector('button[type="submit"]');
            const token = document.querySelector('[name="token"]').value;

            // Token kontrolü
            if (!token) {
                toast.show('Invalid reset link', 'error');
                setTimeout(() => window.location.href = 'forgot-password.php', 2000);
                return;
            }

            // Token geçerliliğini kontrol et
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    verify_token: true,
                    token: token
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        toast.show('Invalid or expired reset link', 'error');
                        setTimeout(() => window.location.href = 'forgot-password.php', 2000);
                    }
                });

            // Form submit handler with loading animation
            resetForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const originalContent = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline-block text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                `;

                try {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    if (!validate.password(password)) {
                        throw new Error('Password must be at least 8 characters with uppercase, lowercase and numbers');
                    }

                    if (password !== confirmPassword) {
                        throw new Error('Passwords do not match');
                    }

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            reset_password: true,
                            token: token,
                            password: password,
                            csrf_token: document.querySelector('[name="csrf_token"]').value
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        toast.show('Password has been reset successfully', 'success');
                        setTimeout(() => window.location.href = data.redirect, 2000);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    toast.show(error.message, 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalContent;
                }
            });

            // Real-time password validation with updated styles
            document.getElementById('password').addEventListener('input', function () {
                const requirementsDiv = document.querySelector('.password-requirements');
                const criteria = [
                    { label: 'Length (min. 8 characters)', test: this.value.length >= 8 },
                    { label: 'Uppercase letter', test: /[A-Z]/.test(this.value) },
                    { label: 'Lowercase letter', test: /[a-z]/.test(this.value) },
                    { label: 'Number', test: /[0-9]/.test(this.value) }
                ];

                requirementsDiv.innerHTML = criteria
                    .map(({ label, test }) => `
                        <div class="flex items-center space-x-2">
                            <span class="${test ? 'text-[#4F46E5]' : 'text-red-600'}">
                                ${test ? '✓' : '✗'}
                            </span>
                            <span class="${test ? 'text-[#4F46E5]' : 'text-red-600'} text-[13px]">
                                ${label}
                            </span>
                        </div>
                    `)
                    .join('');
            });
        });
    </script>
</body>

</html>