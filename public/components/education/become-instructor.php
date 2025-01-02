<?php
// become-instructor.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    $dbConfig = require '../../../config/database.php';
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Kullanıcının zaten eğitmen olup olmadığını kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM instructors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        header('Location: /instructor/dashboard.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Form verilerini al
        $bio = $_POST['bio'] ?? '';
        $expertise = $_POST['expertise'] ?? [];
        $education = $_POST['education'] ?? '';
        $experience = $_POST['experience'] ?? '';

        // Expertise alanlarını JSON formatına çevir
        $expertiseJson = json_encode($expertise);

        // Eğitmen kaydını oluştur
        $stmt = $conn->prepare("
            INSERT INTO instructors (
                user_id, 
                bio, 
                expertise_areas, 
                education, 
                teaching_experience
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $bio,
            $expertiseJson,
            $education,
            $experience
        ]);

        header('Location: instructor/dashboard.php');
        exit;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitmen Ol - Eğitim Platformu</title>
</head>
<body>
    <main>
        <section class="become-instructor">
            <h1>Eğitmen Ol</h1>
            <p>Bilginizi paylaşın, öğrencilere ilham verin ve gelir elde edin.</p>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="bio">Hakkınızda</label>
                    <textarea name="bio" id="bio" required 
                        placeholder="Kendinizi ve öğretme tutkunuzu anlatın"></textarea>
                </div>

                <div class="form-group">
                    <label>Uzmanlık Alanları</label>
                    <div class="expertise-checkboxes">
                        <label>
                            <input type="checkbox" name="expertise[]" value="web_development">
                            Web Geliştirme
                        </label>
                        <label>
                            <input type="checkbox" name="expertise[]" value="mobile_development">
                            Mobil Uygulama Geliştirme
                        </label>
                        <label>
                            <input type="checkbox" name="expertise[]" value="data_science">
                            Veri Bilimi
                        </label>
                        <label>
                            <input type="checkbox" name="expertise[]" value="design">
                            Tasarım
                        </label>
                        <label>
                            <input type="checkbox" name="expertise[]" value="marketing">
                            Dijital Pazarlama
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="education">Eğitim Geçmişi</label>
                    <textarea name="education" id="education" required 
                        placeholder="Eğitim geçmişinizi ve aldığınız sertifikaları belirtin"></textarea>
                </div>

                <div class="form-group">
                    <label for="experience">Öğretmenlik Deneyimi</label>
                    <textarea name="experience" id="experience" required 
                        placeholder="Varsa önceki öğretmenlik deneyimlerinizi anlatın"></textarea>
                </div>

                <div class="form-group terms">
                    <label>
                        <input type="checkbox" required>
                        Eğitmen sözleşmesini okudum ve kabul ediyorum
                    </label>
                </div>

                <button type="submit">Eğitmen Olmak İstiyorum</button>
            </form>
        </section>
    </main>
</body>
</html>