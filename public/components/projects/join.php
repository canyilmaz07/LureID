<?php
// join.php
session_start();
require_once '../../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_GET['code'])) {
    header('Location: /public/components/projects/projects.php');
    exit;
}

try {
    // Veritabanı bağlantısı
    $dbConfig = require '../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $code = $_GET['code'];
    
    // Proje detaylarını ve davet geçerliliğini kontrol et
    $stmt = $db->prepare("
        SELECT * FROM projects 
        WHERE invite_code = :code 
        AND invite_expires_at > NOW()
        AND status = 'active'
    ");
    
    $stmt->execute(['code' => $code]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project) {
        // Kendisinin projesi mi kontrol et
        if ($project['owner_id'] == $_SESSION['user_id']) {
            $_SESSION['project_error'] = "Kendi projenize katılamazsınız.";
            header('Location: /public/components/projects/projects.php');
            exit;
        }

        // Zaten collaborator mı kontrol et
        $collaborators = json_decode($project['collaborators'], true) ?? [];
        if (in_array($_SESSION['user_id'], $collaborators)) {
            $_SESSION['project_error'] = "Bu projeye zaten katılmışsınız.";
            header('Location: /public/components/projects/projects.php');
            exit;
        }

        // Kullanıcıyı collaborators'a ekle
        $collaborators[] = $_SESSION['user_id'];
        
        $stmt = $db->prepare("
            UPDATE projects 
            SET collaborators = :collaborators 
            WHERE project_id = :project_id
        ");
        
        $stmt->execute([
            'collaborators' => json_encode($collaborators),
            'project_id' => $project['project_id']
        ]);
        
        // Başarılı katılım mesajını ayarla
        $_SESSION['project_success'] = "{$project['title']} projesine başarıyla katıldınız!";
    } else {
        $_SESSION['project_error'] = "Geçersiz veya süresi dolmuş davet linki.";
    }
    
    header('Location: /public/components/projects/projects.php');
    
} catch (Exception $e) {
    $_SESSION['project_error'] = "Bir hata oluştu: " . $e->getMessage();
    header('Location: /public/components/projects/projects.php');
}
?>