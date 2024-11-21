<?php
require_once '../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$colorPairs = [
    ["#FDEB71", "#F8D800"],
    ["#ABDCFF", "#0396FF"],
    ["#FEB692", "#EA5455"],
    ["#CE9FFC", "#7367F0"],
    ["#90F7EC", "#32CCBC"],
    ["#FFF6B7", "#F6416C"],
    ["#81FBB8", "#28C76F"],
    ["#E2B0FF", "#9F44D3"],
    ["#F97794", "#623AA2"],
    ["#FCCF31", "#F55555"],
    ["#F761A1", "#8C1BAB"],
    ["#43CBFF", "#9708CC"],
    ["#5EFCE8", "#736EFE"],
    ["#FAD7A1", "#E96D71"],
    ["#F0E68C", "#98FB98"]
];

function getInitials($fullName) {
    $names = explode(' ', trim($fullName));
    if(count($names) == 1) {
        return strtoupper(substr($names[0], 0, 1) . substr($names[0], -1));
    } else if(count($names) == 2) {
        return strtoupper(substr($names[0], 0, 1) . substr($names[1], 0, 1));
    } else {
        return strtoupper(substr($names[0], 0, 1) . substr($names[count($names)-1], 0, 1));
    }
}

function getRandomColorPair() {
    global $colorPairs;
    return $colorPairs[array_rand($colorPairs)];
}

function calculateFontSize($text, $width, $height) {
    // Önceden %60'tı, şimdi %90 yapıyoruz (%60 * 1.5 = %90)
    $targetSize = min($width, $height) * 0.9;
    
    // Her karakter için azaltma oranını da biraz azaltalım
    return $targetSize / (strlen($text) * 1.1);  // 1.2'den 1.1'e düşürdük
}

if(isset($_POST['check_avatar'])) {
    try {
        $dbConfig = require '../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );

        $stmt = $db->prepare("SELECT profile_photo_url FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_POST['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $needsAvatar = ($result['profile_photo_url'] === 'undefined');
        echo json_encode(['needsAvatar' => $needsAvatar, 'debug' => $result]);
        exit;
    } catch(PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

if(isset($_POST['create_avatar'])) {
    try {
        $avatarDir = __DIR__ . '/../../public/profile/avatars';
        if (!file_exists($avatarDir)) {
            if (!mkdir($avatarDir, 0777, true)) {
                throw new Exception("Directory creation failed: " . $avatarDir);
            }
        }

        if (!is_writable($avatarDir)) {
            throw new Exception("Directory not writable: " . $avatarDir);
        }

        $width = 400;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);
        
        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        // Rastgele renk çifti seç
        list($color1, $color2) = getRandomColorPair();
        
        // HEX'ten RGB'ye dönüştür
        list($r1, $g1, $b1) = sscanf($color1, "#%02x%02x%02x");
        list($r2, $g2, $b2) = sscanf($color2, "#%02x%02x%02x");
        
        // Gradient oluştur
        for($i = 0; $i < $height; $i++) {
            $ratio = $i / $height;
            $r = $r1 * (1 - $ratio) + $r2 * $ratio;
            $g = $g1 * (1 - $ratio) + $g2 * $ratio;
            $b = $b1 * (1 - $ratio) + $b2 * $ratio;
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $i, $width, $i, $color);
        }
        
        // İnitialleri ekle
        $initials = getInitials($_POST['full_name']);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Font dosyasının yolunu belirle
        $fontPath = __DIR__ . '/../../public/components/fonts/Anton.ttf';
        if (!file_exists($fontPath)) {
            throw new Exception("Font file not found: " . $fontPath);
        }
        
        // Font boyutunu hesapla
        $fontSize = calculateFontSize($initials, $width, $height);
        
        // Text boyutlarını hesapla
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $initials);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        
        // Text pozisyonunu merkeze al
        $x = ($width - $textWidth) / 2;
        $y = ($height + $textHeight) / 2;
        
        // Text'i ekle
        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $y,
            $white,
            $fontPath,
            $initials
        );
        
        $filename = $avatarDir . '/' . $_POST['user_id'] . '.jpg';
        imagejpeg($image, $filename, 90);
        imagedestroy($image);
        
        // Veritabanını güncelle
        $dbConfig = require '../../config/database.php';
        $db = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );
        
        $stmt = $db->prepare("UPDATE user_profiles SET profile_photo_url = ? WHERE user_id = ?");
        $avatarPath = 'profile/avatars/' . $_POST['user_id'] . '.jpg';
        
        if (!$stmt->execute([$avatarPath, $_POST['user_id']])) {
            throw new Exception("Database update failed");
        }

        echo json_encode([
            'success' => true, 
            'path' => $avatarPath,
            'colors_used' => ['color1' => $color1, 'color2' => $color2],
            'debug' => [
                'directory' => $avatarDir,
                'filename' => $filename,
                'avatar_path' => $avatarPath,
                'font_size' => $fontSize,
                'text_dimensions' => [
                    'width' => $textWidth,
                    'height' => $textHeight
                ]
            ]
        ]);
        exit;
    } catch(Exception $e) {
        error_log("Avatar Creation Error: " . $e->getMessage());
        echo json_encode([
            'error' => $e->getMessage(),
            'debug' => [
                'post_data' => $_POST,
                'directory' => $avatarDir ?? null
            ]
        ]);
        exit;
    }
}
?>