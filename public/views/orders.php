<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    $dbConfig = require '../../config/database.php';
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Fetch all orders with related information
    $stmt = $db->prepare("
        SELECT 
            j.*,
            g.title as gig_title,
            g.price,
            g.pricing_type,
            g.delivery_time,
            g.revision_count,
            g.media_data,
            u.username as freelancer_username,
            u.full_name as freelancer_name,
            ued.profile_photo_url as freelancer_photo,
            t.status as payment_status,
            t.transaction_id
        FROM jobs j
        JOIN gigs g ON j.gig_id = g.gig_id
        JOIN freelancers f ON j.freelancer_id = f.freelancer_id
        JOIN users u ON f.user_id = u.user_id
        LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
        LEFT JOIN transactions t ON j.transaction_id = t.transaction_id
        WHERE j.client_id = ?
        ORDER BY j.created_at DESC
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    exit('Database error occurred');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - LureID</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <a href="/public/index.php" class="text-blue-600 hover:text-blue-700">Back to Dashboard</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold mb-8">My Orders</h1>

        <div class="space-y-6">
            <?php foreach ($orders as $order):
                $mediaData = json_decode($order['media_data'], true);
                $firstImage = !empty($mediaData['images']) ? $mediaData['images'][0] : null;
                ?>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-start gap-6">
                        <!-- Gig Image -->
                        <?php if ($firstImage): ?>
                            <div class="w-40 h-40 flex-shrink-0">
                                <img src="/public/components/freelancer/<?php echo htmlspecialchars($firstImage); ?>"
                                    alt="<?php echo htmlspecialchars($order['gig_title']); ?>"
                                    class="w-full h-full object-cover rounded-lg">
                            </div>
                        <?php endif; ?>

                        <div class="flex-1">
                            <!-- Order Status Badge -->
                            <div class="flex items-center justify-between mb-4">
                                <span class="<?php
                                echo match ($order['status']) {
                                    'PENDING' => 'bg-yellow-100 text-yellow-800',
                                    'IN_PROGRESS' => 'bg-blue-100 text-blue-800',
                                    'UNDER_REVIEW' => 'bg-purple-100 text-purple-800',
                                    'REVISION_REQUESTED' => 'bg-orange-100 text-orange-800',
                                    'COMPLETED' => 'bg-green-100 text-green-800',
                                    'CANCELLED' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                ?> px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo str_replace('_', ' ', $order['status']); ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    Order #<?php echo $order['transaction_id']; ?>
                                </span>
                            </div>

                            <!-- Gig Title -->
                            <h3 class="text-lg font-semibold mb-2">
                                <?php echo htmlspecialchars($order['gig_title']); ?>
                            </h3>

                            <!-- Freelancer Info -->
                            <div class="flex items-center gap-3 mb-4">
                                <img src="/public/<?php echo $order['freelancer_photo'] ?: 'profile/avatars/default.jpg'; ?>"
                                    alt="<?php echo htmlspecialchars($order['freelancer_username']); ?>"
                                    class="w-8 h-8 rounded-full">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($order['freelancer_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        @<?php echo htmlspecialchars($order['freelancer_username']); ?></div>
                                </div>
                            </div>

                            <!-- Order Details -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-500">Price</div>
                                    <div class="font-medium">₺<?php echo number_format($order['price'], 2); ?></div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Delivery Time</div>
                                    <div class="font-medium"><?php echo $order['delivery_time']; ?> days</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Order Date</div>
                                    <div class="font-medium"><?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Due Date</div>
                                    <div class="font-medium">
                                        <?php echo date('M j, Y', strtotime($order['delivery_deadline'])); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Timeline -->
                            <div class="mt-6 relative">
                                <div class="absolute left-0 top-0 h-full w-px bg-gray-200"></div>
                                <div class="space-y-6 relative">
                                    <!-- Payment Step -->
                                    <div class="flex items-center gap-4">
                                        <div class="w-4 h-4 rounded-full bg-green-500 border-2 border-white shadow"></div>
                                        <div>
                                            <div class="font-medium">Payment Completed</div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Started Step -->
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-4 h-4 rounded-full <?php echo $order['status'] !== 'PENDING' ? 'bg-green-500' : 'bg-gray-200'; ?> border-2 border-white shadow">
                                        </div>
                                        <div>
                                            <div class="font-medium">Order Started</div>
                                            <?php if ($order['status'] !== 'PENDING'): ?>
                                                <div class="text-sm text-gray-500">Work in progress</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Delivery Step -->
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-4 h-4 rounded-full <?php echo $order['status'] === 'COMPLETED' ? 'bg-green-500' : 'bg-gray-200'; ?> border-2 border-white shadow">
                                        </div>
                                        <div>
                                            <div class="font-medium">Delivery</div>
                                            <?php if ($order['status'] === 'COMPLETED'): ?>
                                                <div class="text-sm text-gray-500">Order completed successfully</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($order['status'] === 'UNDER_REVIEW' || $order['status'] === 'COMPLETED'): ?>
                                <div class="mt-4 border-t pt-4">
                                    <h4 class="font-medium mb-2">Delivery Details</h4>
                                    <?php
                                    $deliverables = json_decode($order['deliverables_data'], true);
                                    if ($deliverables):
                                        ?>
                                        <?php if (!empty($deliverables['note'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm text-gray-600">Note from freelancer:</p>
                                                <p class="text-sm"><?php echo htmlspecialchars($deliverables['note']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <p class="text-sm text-gray-600">Delivered files:</p>
                                            <div class="space-y-2">
                                                <?php foreach ($deliverables['files'] as $file): ?>
                                                    <a href="/public/uploads/deliverables/<?php echo urlencode($file); ?>" download
                                                        class="block text-sm text-blue-600 hover:text-blue-800">
                                                        <?php echo htmlspecialchars($file); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <?php if ($order['status'] === 'UNDER_REVIEW'): ?>
                                            <button onclick="acceptDelivery(<?php echo $order['job_id']; ?>)"
                                                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                                Accept Delivery
                                            </button>

                                            <div id="reviewModal" tabindex="-1" aria-hidden="true"
                                                class="fixed top-0 left-0 right-0 bottom-0 z-50 hidden w-full overflow-x-hidden overflow-y-auto flex items-center justify-center bg-black bg-opacity-50">
                                                <div class="relative w-full max-w-md mx-auto">
                                                    <div class="relative bg-white rounded-lg shadow">
                                                        <div class="flex items-start justify-between p-4 border-b rounded-t">
                                                            <h3 class="text-xl font-semibold text-gray-900">
                                                                Rate & Review
                                                            </h3>
                                                            <button type="button"
                                                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center"
                                                                onclick="closeReviewModal()">
                                                                <svg class="w-3 h-3" aria-hidden="true"
                                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                                                    <path stroke="currentColor" stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <form id="reviewForm">
                                                            <div class="p-6 space-y-6">
                                                                <div>
                                                                    <label
                                                                        class="block mb-2 text-sm font-medium text-gray-900">Rating</label>
                                                                    <div class="flex gap-2">
                                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                            <button type="button" class="rating-star p-1"
                                                                                data-rating="<?= $i ?>" onclick="setRating(<?= $i ?>)">
                                                                                <svg class="w-6 h-6 text-gray-300" fill="currentColor"
                                                                                    viewBox="0 0 20 20">
                                                                                    <path
                                                                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                                                </svg>
                                                                            </button>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                    <input type="hidden" name="rating" id="ratingInput">
                                                                </div>
                                                                <div>
                                                                    <label
                                                                        class="block mb-2 text-sm font-medium text-gray-900">Review</label>
                                                                    <textarea name="review" rows="4"
                                                                        class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="flex items-center p-6 space-x-2 border-t border-gray-200 rounded-b">
                                                                <button type="submit"
                                                                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Submit
                                                                    Review</button>
                                                                <button type="button"
                                                                    class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10"
                                                                    onclick="closeReviewModal()">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No orders yet</h3>
                    <p class="text-gray-500">When you purchase a gig, it will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let currentJobId = null;
        let selectedRating = 0;

        function setRating(rating) {
            selectedRating = rating;
            document.getElementById('ratingInput').value = rating;

            // Yıldızları güncelle
            document.querySelectorAll('.rating-star svg').forEach((star, index) => {
                star.classList.toggle('text-yellow-400', index < rating);
                star.classList.toggle('text-gray-300', index >= rating);
            });
        }

        function openReviewModal(jobId) {
            currentJobId = jobId;
            const modal = document.getElementById('reviewModal');
            modal.classList.remove('hidden');
        }

        function closeReviewModal() {
            const modal = document.getElementById('reviewModal');
            modal.classList.add('hidden');
            currentJobId = null;
            selectedRating = 0;
            setRating(0);
        }

        function acceptDelivery(jobId) {
            if (confirm('Are you sure you want to accept this delivery? This action cannot be undone.')) {
                openReviewModal(jobId);
            }
        }

        document.getElementById('reviewForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'accept_delivery');
            formData.append('job_id', currentJobId);

            fetch('/public/components/freelancer/api/job_actions.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'An error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        });
    </script>
</body>

</html>