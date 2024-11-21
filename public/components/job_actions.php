<?php
// job_actions.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
try {
    $dbConfig = require '../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? '';
$jobId = intval($_POST['job_id'] ?? 0);

switch ($action) {
    case 'deliver':
        try {
            // Verify the freelancer owns this job
            $stmt = $db->prepare("
                SELECT j.* 
                FROM jobs j
                JOIN freelancers f ON j.freelancer_id = f.freelancer_id
                WHERE j.job_id = ? AND f.user_id = ? AND j.status = 'IN_PROGRESS'
            ");
            $stmt->execute([$jobId, $_SESSION['user_id']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Invalid job or unauthorized');
            }

            // Update job status to delivered
            $stmt = $db->prepare("
                UPDATE jobs 
                SET status = 'DELIVERED', 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE job_id = ?
            ");
            
            if ($stmt->execute([$jobId])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Work delivered successfully'
                ]);
            } else {
                throw new Exception('Failed to update job status');
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'complete':
        try {
            $db->beginTransaction();

            // Get job details
            $stmt = $db->prepare("
                SELECT j.*, f.user_id as freelancer_user_id, t.transaction_id
                FROM jobs j
                JOIN freelancers f ON j.freelancer_id = f.freelancer_id
                JOIN transactions t ON (t.sender_id = j.user_id AND t.receiver_id = f.user_id)
                WHERE j.job_id = ? AND j.user_id = ? AND j.status = 'DELIVERED'
                AND t.status = 'PENDING' AND t.transaction_type = 'PAYMENT'
            ");
            $stmt->execute([$jobId, $_SESSION['user_id']]);
            $jobData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jobData) {
                throw new Exception('Invalid job or unauthorized');
            }

            // Update job status
            $stmt = $db->prepare("
                UPDATE jobs 
                SET status = 'COMPLETED', 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE job_id = ?
            ");
            $stmt->execute([$jobId]);

            // Update transaction status
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'COMPLETED' 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$jobData['transaction_id']]);

            // Add amount to freelancer's wallet
            $stmt = $db->prepare("
                UPDATE wallet 
                SET balance = balance + :amount,
                    updated_at = CURRENT_TIMESTAMP,
                    last_transaction_date = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':amount' => $jobData['budget'],
                ':user_id' => $jobData['freelancer_user_id']
            ]);

            $db->commit();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Work completed and payment released successfully'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}
?>