<?php
// components/projects/api/ProjectUtils.php

class ProjectUtils {
    private $db;
    private $uploadsPath;

    public function __construct($db) {
        $this->db = $db;
        $this->uploadsPath = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/projects';
    }

    // 25 haneli benzersiz proje ID oluşturur
    public function generateProjectId() {
        $characters = '0123456789';
        $id = '';
        
        do {
            $id = '';
            for ($i = 0; $i < 25; $i++) {
                $id .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM projects WHERE project_id = ?");
            $stmt->execute([$id]);
        } while ($stmt->fetchColumn() > 0);
        
        return $id;
    }

    // Proje adını URL-friendly formata çevirir
    public function sanitizeProjectName($title) {
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $title); // Türkçe karakterleri dönüştür
        $clean = strtolower(trim($clean));
        $clean = preg_replace('/[^a-z0-9\-]/', '-', $clean);
        $clean = preg_replace('/-+/', '-', $clean);
        return trim($clean, '-');
    }

    // JSON dosya yolunu oluşturur
    public function createJsonPath($userId, $projectId, $projectName) {
        $sanitizedName = $this->sanitizeProjectName($projectName);
        return sprintf('%d.%s.%s.json', $userId, $projectId, $sanitizedName);
    }

    // Proje verilerini JSON olarak kaydeder
    public function saveProjectData($filePath, $projectData) {
        $fullPath = $this->uploadsPath . '/files/' . $filePath;
        $directory = dirname($fullPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        
        return file_put_contents($fullPath, json_encode($projectData, JSON_PRETTY_PRINT));
    }

    // Proje verilerini JSON'dan okur
    public function loadProjectData($filePath) {
        $fullPath = $this->uploadsPath . '/files/' . $filePath;
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        return json_decode(file_get_contents($fullPath), true);
    }

    // Önizleme görselini kaydeder
    public function savePreviewImage($base64Image, $projectId) {
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
        $imagePath = 'uploads/projects/previews/' . $projectId . '.png';
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $imagePath;
        
        file_put_contents($fullPath, $imageData);
        
        return $imagePath;
    }

    // Projeyi beğenenleri yönetir
    public function toggleLike($projectId, $userId) {
        $stmt = $this->db->prepare("SELECT likes_data FROM projects WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $likesData = json_decode($stmt->fetchColumn(), true) ?? [];
        
        $userIdStr = (string)$userId;
        $key = array_search($userIdStr, $likesData);
        
        if ($key !== false) {
            // Beğeniyi kaldır
            unset($likesData[$key]);
            $likesData = array_values($likesData); // Diziyi yeniden indeksle
        } else {
            // Beğeni ekle
            $likesData[] = $userIdStr;
        }
        
        $stmt = $this->db->prepare("UPDATE projects SET likes_data = ? WHERE project_id = ?");
        return $stmt->execute([json_encode($likesData), $projectId]);
    }

    // Proje önizleme verilerini alır
    public function getProjectPreview($projectId) {
        $stmt = $this->db->prepare("
            SELECT p.*, u.username, u.full_name, 
                   JSON_LENGTH(p.likes_data) as like_count,
                   IF(JSON_SEARCH(p.likes_data, 'one', ?) IS NOT NULL, 1, 0) as is_liked
            FROM projects p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.project_id = ?
        ");
        
        $stmt->execute([$_SESSION['user_id'], $projectId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>