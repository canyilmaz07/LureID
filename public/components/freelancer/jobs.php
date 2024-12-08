<?php
// jobs.php
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

<body>
    <?php include 'components/menu.php'; ?>

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
                                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($job['gig_title']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">Client: <?= htmlspecialchars($job['client_name']) ?>
                                        (@<?= htmlspecialchars($job['client_username']) ?>)</p>
                                </div>
                                <div class="flex items-center">
                                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                                        <?php
                                        switch ($job['status']) {
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
                                        <button data-job-id="<?= $job['job_id'] ?>"
                                            class="accept-start-btn px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                            Accept & Start
                                        </button>
                                    <?php elseif ($job['status'] === 'IN_PROGRESS'): ?>
                                        <button data-modal-target="deliveryModal" data-modal-toggle="deliveryModal"
                                            data-job-id="<?= $job['job_id'] ?>" onclick="openDeliveryModal(<?= $job['job_id'] ?>)"
                                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
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

    <div id="deliveryModal" data-modal-target="deliveryModal" data-modal-backdrop="static" tabindex="-1"
        aria-hidden="true"
        class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-2xl max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-start justify-between p-4 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Submit Delivery
                    </h3>
                    <button type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center"
                        data-modal-hide="deliveryModal">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                    </button>
                </div>
                <form id="deliveryForm">
                    <div class="p-6 space-y-6">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Delivery Files</label>
                            <input type="file" name="files[]" multiple
                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none"
                                required>
                            <div class="mt-2">
                                <div id="uploadProgress" class="w-full bg-gray-200 rounded-full h-2.5 hidden">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <p id="uploadStatus" class="text-sm text-gray-500 mt-1"></p>
                            </div>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Note to Client
                                (Optional)</label>
                            <textarea name="note" rows="4"
                                class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center p-6 space-x-2 border-t border-gray-200 rounded-b">
                        <button type="submit"
                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Submit
                            Delivery</button>
                        <button type="button"
                            class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10"
                            data-modal-hide="deliveryModal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Accept & Start button click handler
            document.querySelectorAll('.accept-start-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const jobId = this.getAttribute('data-job-id');

                    if (!jobId) {
                        console.error('No job ID found');
                        return;
                    }

                    fetch('/public/components/freelancer/api/job_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=start_job&job_id=${jobId}`
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.error || 'Failed to start job. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while processing your request. Please try again.');
                        });
                });
            });

            // Initialize Modal
            const modal = new Modal(document.getElementById('deliveryModal'), {
                backdrop: 'static',
                closable: true,
            });

            // Delivery form submit handler
            const deliveryForm = document.getElementById('deliveryForm');
            const progressBar = document.querySelector('#uploadProgress div');
            const uploadStatus = document.getElementById('uploadStatus');

            if (deliveryForm) {
                deliveryForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('action', 'submit_delivery');
                    formData.append('job_id', deliveryForm.dataset.jobId);

                    document.getElementById('uploadProgress').classList.remove('hidden');

                    const xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            progressBar.style.width = percentComplete + '%';
                            uploadStatus.textContent = `Uploading: ${Math.round(percentComplete)}%`;
                        }
                    });

                    xhr.addEventListener('load', function () {
                        if (xhr.status === 200) {
                            location.reload();
                        } else {
                            uploadStatus.textContent = 'Upload failed. Please try again.';
                        }
                    });

                    xhr.addEventListener('error', function () {
                        uploadStatus.textContent = 'Upload failed. Please try again.';
                    });

                    xhr.open('POST', '/public/components/freelancer/api/job_actions.php', true);
                    xhr.send(formData);
                });
            }
        });

        // Delivery button click handler - Opens modal
        function openDeliveryModal(jobId) {
            if (!jobId) return;

            const form = document.getElementById('deliveryForm');
            if (form) {
                form.dataset.jobId = jobId;
            }
        }

        // Close modal handler
        document.querySelectorAll('[data-modal-hide="deliveryModal"]').forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('deliveryModal').classList.add('hidden');
            });
        });
    </script>
</body>

</html>