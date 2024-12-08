<?php
session_start();
$config = require_once '../../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$action = $_POST['action'] ?? '';
$jobId = $_POST['job_id'] ?? null;

if (!$jobId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Job ID is required']));
}

switch ($action) {
    case 'start_job':
        startJob($db, $jobId);
        break;
    case 'submit_delivery':
        handleDelivery($db, $jobId);
        break;
    case 'accept_delivery':
        acceptDelivery($db, $jobId);
        break;
    default:
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Invalid action']));
}

function startJob($db, $jobId)
{
    try {
        // Önce işin bu freelancer'a ait olduğunu kontrol edelim
        $stmt = $db->prepare("
            SELECT j.job_id 
            FROM jobs j 
            JOIN freelancers f ON j.freelancer_id = f.freelancer_id 
            WHERE j.job_id = ? AND f.user_id = ? AND j.status = 'PENDING'
        ");
        $stmt->execute([$jobId, $_SESSION['user_id']]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Unauthorized to start this job']));
        }

        // İşi başlat
        $updateStmt = $db->prepare("
            UPDATE jobs 
            SET status = 'IN_PROGRESS' 
            WHERE job_id = ? AND status = 'PENDING'
        ");

        $result = $updateStmt->execute([$jobId]);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Could not start job']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function handleDelivery($db, $jobId)
{
    $note = $_POST['note'] ?? '';
    $uploadedFiles = [];
    $uploadDir = '../../../uploads/deliverables/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle file uploads
    if (!empty($_FILES['files'])) {
        foreach ($_FILES['files']['name'] as $key => $name) {
            $tmpName = $_FILES['files']['tmp_name'][$key];
            $fileName = uniqid() . '_' . $name;
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedFiles[] = $fileName;
            }
        }
    }

    try {
        $db->beginTransaction();

        // Update job status
        $stmt = $db->prepare("
            UPDATE jobs 
            SET status = 'UNDER_REVIEW',
                deliverables_data = ?
            WHERE job_id = ? AND status = 'IN_PROGRESS'
        ");

        $deliverablesData = json_encode([
            'files' => $uploadedFiles,
            'note' => $note,
            'delivered_at' => date('Y-m-d H:i:s')
        ]);

        $result = $stmt->execute([$deliverablesData, $jobId]);

        if ($result) {
            $db->commit();
            echo json_encode(['success' => true]);
        } else {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Could not submit delivery']);
        }
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}

function acceptDelivery($db, $jobId)
{
    try {
        $db->beginTransaction();

        // Önce job'un bu kullanıcıya ait olduğunu kontrol edelim
        $stmt = $db->prepare("
            SELECT j.* 
            FROM jobs j
            WHERE j.job_id = ? AND j.client_id = ? AND j.status = 'UNDER_REVIEW'
        ");
        $stmt->execute([$jobId, $_SESSION['user_id']]);

        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Unauthorized']));
        }

        // İşi tamamlandı olarak işaretle
        $updateStmt = $db->prepare("
            UPDATE jobs 
            SET status = 'COMPLETED',
                completed_at = CURRENT_TIMESTAMP
            WHERE job_id = ?
        ");

        $result = $updateStmt->execute([$jobId]);

        if (!$result) {
            throw new Exception('Could not update job status');
        }

        // Review kaydı oluştur
        $reviewStmt = $db->prepare("
            INSERT INTO job_reviews (job_id, client_id, freelancer_id, rating, review_text)
            VALUES (?, ?, ?, ?, ?)
        ");

        $rating = $_POST['rating'] ?? null;
        $reviewText = $_POST['review'] ?? null;

        if ($rating) {
            $reviewStmt->execute([
                $jobId,
                $_SESSION['user_id'],
                $job['freelancer_id'],
                $rating,
                $reviewText
            ]);
        }

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>