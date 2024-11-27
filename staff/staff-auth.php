<?php
session_start();

if (isset($_SESSION['staff_id']) && basename($_SERVER['PHP_SELF']) === 'staff-auth.php') {
    // Role göre yönlendirme
    switch($_SESSION['staff_role']) {
        case 'ADMIN':
            header('Location: admin/panel.php');
            break;
        case 'MODERATOR':
            header('Location: moderator/panel.php');
            break;
        case 'SUPPORT':
            header('Location: support/panel.php');
            break;
        default:
            header('Location: staff-auth.php');
    }
    exit;
}

require_once '../vendor/autoload.php';
require_once '../config/logger.php';

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

        return '';
    }

    public function getLocation()
    {
        try {
            if (empty($this->ip)) {
                throw new Exception('Could not detect public IP address');
            }

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

class StaffAuth {
    private $db;
    private $logger;

    public function __construct($logger) {
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

    private function getBrowserInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $browser = "Unknown";
        $version = "Unknown";
        $os = "Unknown";

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
        }

        if (preg_match('/' . $browser . '\/([0-9.]+)/i', $userAgent, $matches)) {
            $version = $matches[1];
        }

        if (preg_match('/Windows/i', $userAgent)) {
            $os = "Windows";
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = "Linux";
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = "Mac";
        }

        return ['browser' => $browser, 'version' => $version, 'os' => $os];
    }

    public function verifyLogin($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT staff_id, username, password, role 
                FROM staff 
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([$username]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            $locationFinder = new LocationFinder();
            $locationData = $locationFinder->getLocation();
            $browserInfo = $this->getBrowserInfo();

            if ($staff && password_verify($password, $staff['password'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO staff_login_attempts (
                        staff_id, ip_address, status, country, city, 
                        region, isp, timezone, browser, browser_version, os
                    ) VALUES (?, ?, 'SUCCESS', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $staff['staff_id'],
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

                $stmt = $this->db->prepare("
                    UPDATE staff SET last_login = CURRENT_TIMESTAMP 
                    WHERE staff_id = ?
                ");
                $stmt->execute([$staff['staff_id']]);

                $this->logger->log("Successful staff login: {$username}");
                return [
                    'success' => true,
                    'staff_id' => $staff['staff_id'],
                    'username' => $staff['username'],
                    'role' => $staff['role']
                ];
            }

            if ($staff) {
                $stmt = $this->db->prepare("
                    INSERT INTO staff_login_attempts (
                        staff_id, ip_address, status, country, city, 
                        region, isp, timezone, browser, browser_version, os
                    ) VALUES (?, ?, 'FAILED', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $staff['staff_id'],
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
            }

            $this->logger->log("Failed staff login attempt: {$username}");
            return ['success' => false];
        } catch (PDOException $e) {
            $this->logger->log("Staff login verification failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("Login verification failed");
        }
    }
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $logger = new Logger();
    $auth = new StaffAuth($logger);

    try {
        if (isset($_POST['login'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];

            $result = $auth->verifyLogin($username, $password);

            if ($result['success']) {
                $_SESSION['staff_id'] = $result['staff_id'];
                $_SESSION['staff_username'] = $result['username'];
                $_SESSION['staff_role'] = $result['role'];

                // Role'e göre yönlendirme URL'i
                $redirectUrl = '';
                switch($result['role']) {
                    case 'ADMIN':
                        $redirectUrl = 'admin/panel.php';
                        break;
                    case 'MODERATOR':
                        $redirectUrl = 'moderator/panel.php';
                        break;
                    case 'SUPPORT':
                        $redirectUrl = 'support/panel.php';
                        break;
                }

                echo json_encode([
                    'success' => true,
                    'redirect' => $redirectUrl
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid username or password'
                ]);
            }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - LUREID</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, html, body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-[#f9f9f9] min-h-screen flex items-center justify-center">
    <div id="toast-container" class="fixed bottom-5 right-5 space-y-2 z-50"></div>

    <div class="bg-white p-10 rounded-lg shadow-lg w-full max-w-md mx-4">
        <h1 class="text-[2em] font-bold text-[#111827] text-center uppercase tracking-wider font-['Bebas_Neue'] mb-3">
            Staff Login
        </h1>
        <p class="text-[#6B7280] text-center mb-8 text-sm">Staff members only</p>

        <form id="loginForm" class="space-y-4">
            <div class="relative flex items-center">
                <img src="../sources/icons/bulk/user.svg" class="absolute left-[25px]" width="20" height="20" alt="user icon">
                <input type="text" name="username" required 
                    class="w-full h-[60px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                    placeholder="Username">
            </div>

            <div class="relative flex items-center">
                <img src="../sources/icons/bulk/lock.svg" class="absolute left-[25px]" width="20" height="20" alt="lock icon">
                <input type="password" name="password" required 
                    class="w-full h-[60px] pl-[55px] pr-[25px] border border-[#E5E7EB] rounded-lg text-[13px] font-medium bg-[#f9f9f9] focus:outline-none focus:border-[#4F46E5] transition-colors"
                    placeholder="Password">
            </div>

            <button type="submit" 
                class="w-full h-[60px] bg-black text-white rounded-lg text-sm font-semibold transition-colors hover:bg-[#3f3f3f] shadow-lg">
                Sign In
            </button>
        </form>
    </div>

    <script>
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

        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                const username = $('input[name="username"]').val();
                const password = $('input[name="password"]').val();
                
                if (!username || !password) {
                    toast.show('Please fill in all fields', 'error');
                    return;
                }
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        login: true,
                        username: username,
                        password: password
                    },
                    success: function(response) {
                        if (response.success) {
                            toast.show('Login successful', 'success');
                            setTimeout(() => {
                                window.location.href = response.redirect;
                            }, 1000);
                        } else {
                            toast.show(response.message || 'Login failed', 'error');
                        }
                    },
                    error: function() {
                        toast.show('An error occurred during login', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>