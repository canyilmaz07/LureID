<?php
// gig.php
session_start();
require_once '../../config/database.php';

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
    error_log("Error: " . $e->getMessage());
    exit('Database connection failed');
}

// Get gig ID from URL
$gigId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$gigId) {
    header('Location: /');
    exit;
}

// Purchase process
if (isset($_POST['purchase']) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Check gig and user information
    $checkStmt = $db->prepare("
        SELECT 
            g.*, 
            f.freelancer_id,
            f.user_id as freelancer_user_id,
            g.price as fixed_price, 
            wa.balance 
        FROM gigs g 
        JOIN freelancers f ON g.freelancer_id = f.freelancer_id 
        JOIN users u ON f.user_id = u.user_id
        JOIN wallet wa ON wa.user_id = :user_id
        WHERE g.gig_id = :gig_id
    ");
    $checkStmt->execute([':gig_id' => $gigId, ':user_id' => $userId]);
    $purchaseCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Check for errors
    $error = null;
    if ($purchaseCheck['freelancer_user_id'] == $userId) {
        $error = "You cannot purchase your own gig!";
    } elseif ($purchaseCheck['balance'] < $purchaseCheck['fixed_price']) {
        $error = "Insufficient balance!";
    }

    if (!$error) {
        try {
            $db->beginTransaction();

            // Calculate delivery deadline based on delivery_time
            $deliveryDeadline = date('Y-m-d H:i:s', strtotime("+{$purchaseCheck['delivery_time']} days"));

            // Create job record
            $jobStmt = $db->prepare("
                INSERT INTO jobs (
                    gig_id,
                    client_id,
                    freelancer_id,
                    title,
                    description,
                    requirements,
                    category,
                    subcategory,
                    budget,
                    status,
                    delivery_deadline,
                    max_revisions,
                    milestones_data
                ) VALUES (
                    :gig_id,
                    :client_id,
                    :freelancer_id,
                    :title,
                    :description,
                    :requirements,
                    :category,
                    :subcategory,
                    :budget,
                    'PENDING',
                    :delivery_deadline,
                    :max_revisions,
                    :milestones_data
                )
            ");

            $jobStmt->execute([
                ':gig_id' => $gigId,
                ':client_id' => $userId,
                ':freelancer_id' => $purchaseCheck['freelancer_id'],
                ':title' => $purchaseCheck['title'],
                ':description' => $purchaseCheck['description'],
                ':requirements' => $purchaseCheck['requirements'],
                ':category' => $purchaseCheck['category'],
                ':subcategory' => $purchaseCheck['subcategory'],
                ':budget' => $purchaseCheck['price'],
                ':delivery_deadline' => $deliveryDeadline,
                ':max_revisions' => $purchaseCheck['revision_count'],
                ':milestones_data' => $purchaseCheck['milestones_data']
            ]);

            // Update wallet
            $walletStmt = $db->prepare("
                UPDATE wallet 
                SET balance = balance - :amount,
                    last_transaction_date = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");

            $walletStmt->execute([
                ':amount' => $purchaseCheck['fixed_price'],
                ':user_id' => $userId
            ]);

            // Create transaction record
            $transactionStmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_id,
                    sender_id,
                    receiver_id,
                    amount,
                    transaction_type,
                    status,
                    description,
                    created_at
                ) VALUES (
                    :transaction_id,
                    :sender_id,
                    :receiver_id,
                    :amount,
                    'PAYMENT',
                    'PENDING',
                    :description,
                    CURRENT_TIMESTAMP
                )
            ");

            $transactionId = mt_rand(10000000000, 99999999999);

            $transactionStmt->execute([
                ':transaction_id' => $transactionId,
                ':sender_id' => $userId,
                ':receiver_id' => $purchaseCheck['freelancer_user_id'],
                ':amount' => $purchaseCheck['fixed_price'],
                ':description' => 'Payment for gig: ' . $purchaseCheck['title']
            ]);

            // Update the job with the transaction ID
            $updateJobStmt = $db->prepare("
                UPDATE jobs 
                SET transaction_id = :transaction_id 
                WHERE job_id = LAST_INSERT_ID()
            ");
            $updateJobStmt->execute([':transaction_id' => $transactionId]);

            $db->commit();
            $success = "Purchase successful! Your job has been created.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "An error occurred during purchase. Please try again.";
            error_log("Purchase Error: " . $e->getMessage());
        }
    }
}

// Get gig details
$stmt = $db->prepare("
    SELECT 
        g.*,
        f.user_id as freelancer_user_id,
        f.financial_data,
        f.profile_data,
        u.username,
        u.full_name,
        ued.profile_photo_url,
        ued.basic_info,
        DATEDIFF(CURRENT_TIMESTAMP, f.created_at) as experience_days,
        CASE 
            WHEN :logged_in_user IS NOT NULL THEN (
                SELECT balance 
                FROM wallet 
                WHERE user_id = :logged_in_user_balance
                ORDER BY wallet_id DESC
                LIMIT 1
            )
            ELSE 0
        END as user_balance
    FROM gigs g
    LEFT JOIN freelancers f ON g.freelancer_id = f.freelancer_id
    LEFT JOIN users u ON f.user_id = u.user_id
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    WHERE g.gig_id = :gig_id AND g.status IN ('APPROVED', 'ACTIVE')
");

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$stmt->bindParam(':gig_id', $gigId);
$stmt->bindParam(':logged_in_user', $userId);
$stmt->bindParam(':logged_in_user_balance', $userId);
$stmt->execute();
$gig = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gig) {
    header('Location: /');
    exit;
}

// Parse JSON data
$basicInfo = json_decode($gig['basic_info'], true) ?? [];
$financialData = json_decode($gig['financial_data'], true) ?? [];
$profileData = json_decode($gig['profile_data'], true) ?? [];
$mediaData = json_decode($gig['media_data'], true) ?? [];

function formatExperienceTime($days)
{
    if ($days < 1) {
        return "Just started";
    }

    $years = floor($days / 365);
    $months = floor(($days % 365) / 30);
    $weeks = floor(($days % 30) / 7);
    $remainingDays = $days % 7;

    $experience = [];

    if ($years > 0) {
        $experience[] = $years . ' ' . ($years == 1 ? 'year' : 'years');
    }
    if ($months > 0) {
        $experience[] = $months . ' ' . ($months == 1 ? 'month' : 'months');
    }
    if ($weeks > 0) {
        $experience[] = $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks');
    }
    if ($remainingDays > 0) {
        $experience[] = $remainingDays . ' ' . ($remainingDays == 1 ? 'day' : 'days');
    }

    return implode(', ', $experience);
}

// Get other gigs by the same freelancer
$stmt = $db->prepare("
    SELECT g.*, g.media_data
    FROM gigs g
    WHERE g.freelancer_id = ? 
    AND g.gig_id != ? 
    AND g.status IN ('APPROVED', 'ACTIVE')
    ORDER BY g.created_at DESC
    LIMIT 3
");
$stmt->execute([$gig['freelancer_id'], $gigId]);
$otherGigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($gig['title']); ?> - Gig Details</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <div class="flex items-center gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/public/index.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Dashboard</a>
                    <a href="/auth/logout.php" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Display error/success messages if they exist -->
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-8">
            <!-- Ana İçerik -->
            <div class="col-span-2">
                <!-- İlan Başlığı ve Fiyatlandırma -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($gig['title']); ?></h1>
                    <div class="flex gap-4 text-lg mb-4">
                        <?php if ($gig['pricing_type'] !== 'ONE_TIME'): ?>
                            <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded">
                                <?php echo ucfirst(strtolower($gig['pricing_type'])); ?> Rate:
                                ₺<?php echo number_format($gig['price'], 2); ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-green-100 text-green-800 px-4 py-2 rounded">
                                Fixed Price: ₺<?php echo number_format($gig['price'], 2); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Purchase Button -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" class="mt-4">
                            <button type="submit" name="purchase"
                                class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed"
                                <?php echo ($_SESSION['user_id'] === $gig['freelancer_user_id'] || $gig['user_balance'] < $gig['price']) ? 'disabled' : ''; ?>>
                                <?php
                                if ($_SESSION['user_id'] === $gig['freelancer_user_id']) {
                                    echo "You can't purchase your own gig";
                                } elseif ($gig['user_balance'] < $gig['price']) {
                                    echo "Insufficient balance - Need ₺" . number_format($gig['price'], 2);
                                } else {
                                    echo "Purchase for ₺" . number_format($gig['price'], 2);
                                }
                                ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Etiketler -->
                    <?php if (isset($gig['tags'])): ?>
                        <div class="flex gap-2 mb-4">
                            <?php foreach (json_decode($gig['tags'], true) as $tag): ?>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded text-sm">
                                    <?php echo htmlspecialchars($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Medya Galerisi -->
                <?php if (!empty($gig['media_data'])): ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-8">
                        <h2 class="text-xl font-bold mb-4">Gallery</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <?php
                            $mediaData = json_decode($gig['media_data'], true);
                            if (isset($mediaData['images'])):
                                foreach ($mediaData['images'] as $image):
                                    ?>
                                    <div class="relative pt-[56.25%]">
                                        <img src="/public/components/freelancer/<?php echo htmlspecialchars($image); ?>" alt="Gig image"
                                            class="absolute inset-0 w-full h-full object-cover rounded">
                                    </div>
                                    <?php
                                endforeach;
                            endif;

                            if (isset($mediaData['video'])):
                                ?>
                                <div class="relative pt-[56.25%] col-span-2">
                                    <video src="/public/components/freelancer/<?php echo htmlspecialchars($mediaData['video']); ?>" controls
                                        class="absolute inset-0 w-full h-full rounded">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Açıklama ve Gereksinimler -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold mb-2">Description</h2>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($gig['description'])); ?>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold mb-2">Requirements</h2>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($gig['requirements'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yan Panel -->
            <div class="space-y-8">
                <!-- Freelancer Bilgileri -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="/public/<?php echo $gig['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>"
                            alt="<?php echo htmlspecialchars($gig['username']); ?>"
                            class="w-16 h-16 rounded-full object-cover">
                        <div>
                            <h3 class="font-bold text-lg">
                                <a href="/<?php echo htmlspecialchars($gig['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($basicInfo['full_name'] ?? $gig['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600">@<?php echo htmlspecialchars($gig['username']); ?></p>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        <?php if (isset($basicInfo['location'])): ?>
                            <p>📍 <?php
                            echo htmlspecialchars(
                                implode(', ', array_filter([
                                    $basicInfo['location']['city'] ?? '',
                                    $basicInfo['location']['country'] ?? ''
                                ]))
                            );
                            ?></p>
                        <?php endif; ?>

                        <p>⭐ <?php echo formatExperienceTime($gig['experience_days']); ?> experience</p>
                        <p>💰 Base rate: ₺<?php echo number_format($financialData['daily_rate'] ?? 0, 2); ?>/day</p>

                        <?php if (isset($basicInfo['biography'])): ?>
                            <p class="text-gray-700 mt-3"><?php echo htmlspecialchars($basicInfo['biography']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($basicInfo['contact']) && isset($basicInfo['contact']['email'])): ?>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $gig['freelancer_user_id']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($basicInfo['contact']['email']); ?>"
                                class="block w-full bg-blue-500 text-white text-center px-4 py-2 rounded hover:bg-blue-600">
                                Contact Freelancer
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Professional Info Section -->
                <?php
                $professionalData = json_decode($gig['professional_data'] ?? '{}', true);
                if (!empty($professionalData)):
                    ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-bold text-lg mb-4">Professional Profile</h3>
                        <?php if (isset($professionalData['skills']) && !empty($professionalData['skills'])): ?>
                            <div class="mb-4">
                                <h4 class="font-medium text-gray-700 mb-2">Expertise</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($professionalData['skills'] as $skill): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($professionalData['experience'])): ?>
                            <p class="text-gray-600 text-sm">
                                <?php echo htmlspecialchars($professionalData['experience']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Diğer Gig'ler -->
                <?php if (!empty($otherGigs)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-bold text-lg mb-4">Other Gigs by <?php echo htmlspecialchars($gig['username']); ?>
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($otherGigs as $otherGig): ?>
                                <a href="/public/views/gig.php?id=<?php echo $otherGig['gig_id']; ?>"
                                    class="block hover:bg-gray-50 p-3 rounded">
                                    <h4 class="font-medium"><?php echo htmlspecialchars($otherGig['title']); ?></h4>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?php echo htmlspecialchars($otherGig['description']); ?>
                                    </p>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <?php echo date('M j, Y', strtotime($otherGig['created_at'])); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

</html>