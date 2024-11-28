<?php
// public/components/freelancer/active_jobs.php
session_start();
$config = require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Database connection
try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    die("Connection error: " . $e->getMessage());
}

$userId = $_SESSION['user_id'];

// Get freelancer_id
$freelancerQuery = "SELECT freelancer_id FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($freelancerQuery);
$stmt->execute([$userId]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$freelancerData) {
    header('Location: /public/index.php');
    exit;
}

$freelancerId = $freelancerData['freelancer_id'];

// Fetch active jobs
$jobsQuery = "
    SELECT 
        j.*,
        u.username as client_username,
        u.full_name as client_name,
        g.title as gig_title,
        g.price
    FROM jobs j
    JOIN users u ON j.client_id = u.user_id
    JOIN gigs g ON j.gig_id = g.gig_id
    WHERE j.freelancer_id = ? 
    ORDER BY j.created_at DESC
";
$stmt = $db->prepare($jobsQuery);
$stmt->execute([$freelancerId]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Jobs - Freelancer Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
    <?php include 'components/freelancer_header.php'; ?>

    <div class="p-4 sm:ml-64 pt-20">
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-900">Active Jobs</h2>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-600">No active jobs found.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($jobs as $job): ?>
                        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($job['gig_title']) ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client: <?= htmlspecialchars($job['client_name']) ?> (@<?= htmlspecialchars($job['client_username']) ?>)</p>
                                </div>
                                <div class="flex items-center">
                                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                                        <?php 
                                        switch($job['status']) {
                                            case 'PENDING':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'IN_PROGRESS':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'UNDER_REVIEW':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'REVISION_REQUESTED':
                                                echo 'bg-orange-100 text-orange-800';
                                                break;
                                            case 'COMPLETED':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'CANCELLED':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'DISPUTED':
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>">
                                        <?= str_replace('_', ' ', $job['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Budget</p>
                                    <p class="font-semibold">₺<?= number_format($job['budget'], 2) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Deadline</p>
                                    <p class="font-semibold"><?= date('M j, Y', strtotime($job['delivery_deadline'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Revisions Left</p>
                                    <p class="font-semibold"><?= $job['max_revisions'] - ($job['revision_count'] ?? 0) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Created</p>
                                    <p class="font-semibold"><?= date('M j, Y', strtotime($job['created_at'])) ?></p>
                                </div>
                            </div>
                            <?php if ($job['status'] !== 'COMPLETED' && $job['status'] !== 'CANCELLED'): ?>
                                <div class="mt-4 flex gap-2">
                                    <?php if ($job['status'] === 'PENDING'): ?>
                                        <button class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                            Accept & Start
                                        </button>
                                    <?php elseif ($job['status'] === 'IN_PROGRESS'): ?>
                                        <button class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                            Submit for Review
                                        </button>
                                    <?php endif; ?>
                                    <button class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                        Contact Client
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>
</body>
</html>