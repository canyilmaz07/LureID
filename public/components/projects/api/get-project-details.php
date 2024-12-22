<?php
// api/get-project-details.php
session_start();
require_once '../../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $dbConfig = require '../../../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $projectId = $data['project_id'];

    // Proje detaylarını al
    $stmt = $db->prepare("SELECT * FROM projects WHERE project_id = :project_id AND owner_id = :user_id");
    $stmt->execute([
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        // Üyeleri al
        $members = [];
        // Owner'ı ekle
        $ownerStmt = $db->prepare("
            SELECT u.user_id, u.full_name, ued.profile_photo_url
            FROM users u
            JOIN user_extended_details ued ON u.user_id = ued.user_id
            WHERE u.user_id = ?
        ");
        $ownerStmt->execute([$project['owner_id']]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        $members[] = [
            'user_id' => $owner['user_id'],
            'name' => $owner['full_name'],
            'avatar' => $owner['profile_photo_url'],
            'role' => 'owner',
            'project_id' => $projectId
        ];

        // Collaboratorları ekle
        $collaborators = json_decode($project['collaborators'], true) ?? [];
        if (!empty($collaborators)) {
            $collaboratorStmt = $db->prepare("
                SELECT u.user_id, u.full_name, ued.profile_photo_url
                FROM users u
                JOIN user_extended_details ued ON u.user_id = ued.user_id
                WHERE u.user_id = ?
            ");
            foreach ($collaborators as $collaborator) {
                $collaboratorStmt->execute([$collaborator]);
                $member = $collaboratorStmt->fetch(PDO::FETCH_ASSOC);
                if ($member) {
                    $members[] = [
                        'user_id' => $member['user_id'],
                        'name' => $member['full_name'],
                        'avatar' => $member['profile_photo_url'],
                        'role' => 'member',
                        'project_id' => $projectId
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'project' => $project,
            'members' => $members,
            'invite_code' => $project['invite_code']
        ]);
    } else {
        throw new Exception('Proje bulunamadı');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>