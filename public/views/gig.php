<?php
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

// Helper function for experience time
function formatExperienceTime($days)
{
    if ($days < 1) {
        return "Just started";
    }

    $years = floor($days / 365);
    $months = floor(($days % 365) / 30);
    $remaining_days = $days % 30;

    $experience = [];

    if ($years > 0) {
        $experience[] = $years . ' ' . ($years == 1 ? 'year' : 'years');
    }
    if ($months > 0) {
        $experience[] = $months . ' ' . ($months == 1 ? 'month' : 'months');
    }
    if ($remaining_days > 0 && count($experience) == 0) {
        $experience[] = $remaining_days . ' ' . ($remaining_days == 1 ? 'day' : 'days');
    }

    return implode(', ', $experience);
}

// Get comprehensive gig details with all related information
$stmt = $db->prepare("
    SELECT 
        g.*,
        f.user_id as freelancer_user_id,
        f.financial_data,
        f.profile_data,
        f.professional_data,
        f.created_at as freelancer_created_at,
        u.username,
        u.full_name,
        u.email,
        ued.profile_photo_url,
        ued.basic_info,
        ued.skills_matrix,
        DATEDIFF(CURRENT_TIMESTAMP, f.created_at) as experience_days,
        (SELECT COUNT(*) FROM jobs WHERE freelancer_id = f.freelancer_id AND status = 'COMPLETED') as completed_jobs,
        (SELECT AVG(client_rating) FROM jobs WHERE freelancer_id = f.freelancer_id AND client_rating IS NOT NULL) as avg_rating
    FROM gigs g
    JOIN freelancers f ON g.freelancer_id = f.freelancer_id
    JOIN users u ON f.user_id = u.user_id
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    WHERE g.gig_id = :gig_id AND g.status IN ('APPROVED', 'ACTIVE')
");

$stmt->execute([':gig_id' => $gigId]);
$gig = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gig) {
    header('Location: /');
    exit;
}

// Parse all JSON data
$basicInfo = json_decode($gig['basic_info'], true) ?? [];
$financialData = json_decode($gig['financial_data'], true) ?? [];
$profileData = json_decode($gig['profile_data'], true) ?? [];
$professionalData = json_decode($gig['professional_data'], true) ?? [];
$mediaData = json_decode($gig['media_data'], true) ?? [];
$skillsMatrix = json_decode($gig['skills_matrix'], true) ?? [];
$milestones = json_decode($gig['milestones_data'], true) ?? [];
$ndaData = json_decode($gig['nda_data'], true) ?? [];

// Get other active gigs by the same freelancer
$otherGigsStmt = $db->prepare("
    SELECT 
        g.*, 
        g.media_data,
        COUNT(j.job_id) as order_count,
        AVG(j.client_rating) as avg_rating
    FROM gigs g
    LEFT JOIN jobs j ON g.gig_id = j.gig_id
    WHERE g.freelancer_id = ? 
    AND g.gig_id != ? 
    AND g.status IN ('APPROVED', 'ACTIVE')
    GROUP BY g.gig_id
    ORDER BY order_count DESC, avg_rating DESC
    LIMIT 3
");
$otherGigsStmt->execute([$gig['freelancer_id'], $gigId]);
$otherGigs = $otherGigsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller's stats
$sellerStatsStmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT j.job_id) as total_orders,
        AVG(j.client_rating) as avg_rating,
        COUNT(DISTINCT CASE WHEN j.status = 'COMPLETED' THEN j.job_id END) as completed_orders,
        AVG(CASE WHEN j.status = 'COMPLETED' THEN TIMESTAMPDIFF(HOUR, j.created_at, j.completed_at) END) as avg_completion_time
    FROM jobs j
    WHERE j.freelancer_id = ?
");
$sellerStatsStmt->execute([$gig['freelancer_id']]);
$sellerStats = $sellerStatsStmt->fetch(PDO::FETCH_ASSOC);

$avgResponseTime = "2 hours";

// $responseStatsStmt = $db->prepare("
//     SELECT 
//         COUNT(*) as total_messages,
//         AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as avg_response_time
//     FROM messages m1
//     LEFT JOIN messages m2 ON m1.conversation_id = m2.conversation_id 
//     AND m2.created_at > m1.created_at
//     WHERE (m1.sender_id = ? OR m1.receiver_id = ?)
//     AND m2.id IS NOT NULL
//     GROUP BY m1.sender_id
// ");
// $responseStatsStmt->execute([$gig['freelancer_user_id'], $gig['freelancer_user_id']]);
// $responseStats = $responseStatsStmt->fetch(PDO::FETCH_ASSOC);

// // Format response time for display
// $avgResponseTime = "N/A";
// if ($responseStats && $responseStats['avg_response_time']) {
//     $minutes = $responseStats['avg_response_time'];
//     if ($minutes < 60) {
//         $avgResponseTime = round($minutes) . " minutes";
//     } else if ($minutes < 1440) { // Less than 24 hours
//         $avgResponseTime = round($minutes / 60) . " hours";
//     } else {
//         $avgResponseTime = round($minutes / 1440) . " days";
//     }
// }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($gig['title']); ?> - LureID</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gig-hero {
            background: linear-gradient(to bottom, rgba(249, 250, 251, 0.8), rgb(249, 250, 251));
        }

        .price-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 22px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .price-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.15);
        }

        .feature-tag {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .gallery-image {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            aspect-ratio: 16/9;
        }

        .gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gallery-image:hover img {
            transform: scale(1.05);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #E5E7EB;
        }

        .milestone-card {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #E5E7EB;
        }

        .milestone-number {
            width: 28px;
            height: 28px;
            background: #4F46E5;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .tag {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #F9FAFB;
            border-radius: 8px;
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            color: #4F46E5;
        }

        .gig-description {
            font-size: 1rem;
            line-height: 1.75;
            color: #374151;
        }

        .gig-description p {
            margin-bottom: 1rem;
        }

        .gig-description h1 {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #111827;
        }

        .gig-description h2 {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 0.75rem;
            color: #111827;
        }

        .gig-description h3 {
            font-size: 1.25em;
            font-weight: bold;
            margin-bottom: 0.75rem;
            color: #111827;
        }

        .gig-description ul,
        .gig-description ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .gig-description ul {
            list-style-type: disc;
        }

        .gig-description ol {
            list-style-type: decimal;
        }

        .gig-description li {
            margin-bottom: 0.5rem;
        }

        .gig-description strong,
        .gig-description b {
            font-weight: bold;
        }

        .gig-description em,
        .gig-description i {
            font-style: italic;
        }

        .gig-description u {
            text-decoration: underline;
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <div class="flex items-center gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/public/index.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Dashboard</a>
                    <a href="/auth/logout.php"
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">Logout</a>
                <?php else: ?>
                    <a href="/auth/login.php"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Gig Hero Section -->
        <div class="gig-hero rounded-2xl p-8 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Gig Info -->
                <div>
                    <nav class="flex mb-4 text-sm">
                        <a href="#"
                            class="text-gray-500 hover:text-gray-700"><?php echo htmlspecialchars($gig['category']); ?></a>
                        <span class="mx-2 text-gray-500">/</span>
                        <a href="#"
                            class="text-gray-500 hover:text-gray-700"><?php echo htmlspecialchars($gig['subcategory']); ?></a>
                    </nav>

                    <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($gig['title']); ?>
                    </h1>

                    <!-- Freelancer Info -->
                    <div class="flex items-center gap-4 mb-6">
                        <img src="/public/<?php echo $gig['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>"
                            alt="<?php echo htmlspecialchars($gig['username']); ?>"
                            class="w-12 h-12 rounded-full object-cover">
                        <div>
                            <h3 class="font-semibold text-lg">
                                <a href="/<?php echo htmlspecialchars($gig['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($basicInfo['full_name'] ?? $gig['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600 text-sm">Level 2 Seller |
                                <?php echo formatExperienceTime($gig['experience_days']); ?> experience
                            </p>
                        </div>
                    </div>

                    <!-- Main Image -->
                    <?php
                    $mediaData = json_decode($gig['media_data'], true);
                    $firstImage = null;
                    if ($mediaData && isset($mediaData['images']) && !empty($mediaData['images'])) {
                        $firstImage = $mediaData['images'][0];
                    }
                    ?>
                    <?php if ($firstImage): ?>
                        <div class="gallery-image mb-6">
                            <img src="/public/components/freelancer/<?php echo htmlspecialchars($firstImage); ?>"
                                alt="<?php echo htmlspecialchars($gig['title']); ?>">
                        </div>
                    <?php endif; ?>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="feature-tag bg-blue-50 text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?php echo $gig['delivery_time']; ?> Days Delivery
                        </div>
                        <div class="feature-tag bg-green-50 text-green-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <?php echo $gig['revision_count']; ?> Revisions
                        </div>
                        <?php if ($gig['pricing_type'] !== 'ONE_TIME'): ?>
                            <div class="feature-tag bg-purple-50 text-purple-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <?php echo ucfirst(strtolower($gig['pricing_type'])); ?> Plan
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Pricing Card -->
                <div>
                    <div class="price-card p-6 sticky top-8">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold">
                                ₺<?php echo number_format($gig['price'], 2); ?>
                                <?php if ($gig['pricing_type'] !== 'ONE_TIME'): ?>
                                    <span
                                        class="text-base font-normal text-gray-600">/<?php echo strtolower($gig['pricing_type']); ?></span>
                                <?php endif; ?>
                            </h3>
                        </div>

                        <div class="space-y-4 mb-6">
                            <?php
                            $deliverables = json_decode($gig['deliverables'], true);
                            if (!empty($deliverables)):
                                foreach ($deliverables as $deliverable):
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span><?php echo htmlspecialchars($deliverable); ?></span>
                                    </div>
                                    <?php
                                endforeach;
                            endif;
                            ?>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="/public/views/order.php?gig=<?php echo $gig['gig_id']; ?>"
                                class="block w-full bg-blue-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                                Continue (₺<?php echo number_format($gig['price'], 2); ?>)
                            </a>
                            <?php if ($_SESSION['user_id'] === $gig['freelancer_user_id']): ?>
                                <p class="text-sm text-gray-500 text-center mt-2">This is your own gig</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="/auth/login.php"
                                class="block w-full bg-gray-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition">
                                Login to Continue
                            </a>
                        <?php endif; ?>

                        <?php if (isset($basicInfo['contact']) && isset($basicInfo['contact']['email'])): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $gig['freelancer_user_id']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($basicInfo['contact']['email']); ?>"
                                    class="block w-full text-center px-6 py-3 mt-4 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                                    Contact Freelancer
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Description & Details -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Description Section -->
                <section class="bg-white rounded-2xl p-6">
                    <h2 class="section-title">About This Gig</h2>
                    <div class="prose max-w-none">
                        <?php
                        // HTML içeriğini güvenli bir şekilde temizle ve göster
                        $description = $gig['description'];

                        // Tehlikeli tagları ve attributeleri temizle
                        $allowed_tags = [
                            'p' => [],
                            'h1' => [],
                            'h2' => [],
                            'h3' => [],
                            'h4' => [],
                            'h5' => [],
                            'h6' => [],
                            'strong' => [],
                            'em' => [],
                            'u' => [],
                            'ol' => [],
                            'ul' => [],
                            'li' => [],
                            'span' => [],
                            'br' => [],
                            'b' => [],
                            'i' => [],
                        ];

                        // HTML Purifier kullanmak daha güvenli olur ama şimdilik basit bir temizleme yapalım
                        $description = strip_tags($description, '<p><h1><h2><h3><h4><h5><h6><strong><em><u><ol><ul><li><span><br><b><i>');

                        // CSS stillerini içeren bir wrapper div içinde göster
                        echo '<div class="gig-description">' . $description . '</div>';
                        ?>
                    </div>
                </section>

                <!-- Requirements Section -->
                <section class="bg-white rounded-2xl p-6">
                    <h2 class="section-title">Requirements</h2>
                    <div class="prose max-w-none">
                        <?php echo nl2br(htmlspecialchars($gig['requirements'])); ?>
                    </div>
                </section>

                <!-- Gallery Section -->
                <?php if (!empty($mediaData['images'])): ?>
                    <section class="bg-white rounded-2xl p-6">
                        <h2 class="section-title">Gallery</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($mediaData['images'] as $image): ?>
                                <div class="gallery-image">
                                    <img src="/public/components/freelancer/<?php echo htmlspecialchars($image); ?>"
                                        alt="Gig gallery image" onclick="openLightbox(this.src)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Video Section -->
                <?php if (isset($mediaData['video'])): ?>
                    <section class="bg-white rounded-2xl p-6">
                        <h2 class="section-title">Video Preview</h2>
                        <div class="relative pt-[56.25%] rounded-lg overflow-hidden">
                            <video src="/public/components/freelancer/<?php echo htmlspecialchars($mediaData['video']); ?>"
                                controls class="absolute inset-0 w-full h-full">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Milestones Section -->
                <?php
                $milestones = json_decode($gig['milestones_data'], true);
                if (!empty($milestones)):
                    ?>
                    <section class="bg-white rounded-2xl p-6">
                        <h2 class="section-title">Project Milestones</h2>
                        <div class="space-y-4">
                            <?php foreach ($milestones as $index => $milestone): ?>
                                <div class="milestone-card flex gap-4">
                                    <div class="milestone-number"><?php echo $index + 1; ?></div>
                                    <div>
                                        <h3 class="font-semibold text-lg mb-1">
                                            <?php echo htmlspecialchars($milestone['title']); ?>
                                        </h3>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($milestone['description']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- NDA Section -->
                <?php
                $ndaData = json_decode($gig['nda_data'], true);
                if (isset($ndaData['required']) && $ndaData['required']):
                    ?>
                    <section class="bg-white rounded-2xl p-6">
                        <h2 class="section-title">Non-Disclosure Agreement</h2>
                        <div class="flex items-start gap-4 bg-blue-50 p-4 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mt-1" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-blue-800 mb-2">NDA Protection</h3>
                                <p class="text-blue-600"><?php echo htmlspecialchars($ndaData['text']); ?></p>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <!-- Right Column: Seller Info & Related Gigs -->
            <div class="space-y-8">
                <!-- Seller Profile Card -->
                <section class="bg-white rounded-2xl p-6">
                    <h2 class="text-xl font-semibold mb-4">About the Seller</h2>
                    <div class="flex items-center gap-4 mb-6">
                        <img src="/public/<?php echo $gig['profile_photo_url'] ?: 'profile/avatars/default.jpg'; ?>"
                            alt="<?php echo htmlspecialchars($gig['username']); ?>"
                            class="w-16 h-16 rounded-full object-cover">
                        <div>
                            <h3 class="font-semibold text-lg">
                                <a href="/<?php echo htmlspecialchars($gig['username']); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($basicInfo['full_name'] ?? $gig['full_name']); ?>
                                </a>
                            </h3>
                            <p class="text-gray-600">@<?php echo htmlspecialchars($gig['username']); ?></p>
                        </div>
                    </div>

                    <div class="space-y-4 text-sm text-gray-600">
                        <?php if (isset($basicInfo['location'])): ?>
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span><?php
                                echo htmlspecialchars(
                                    implode(', ', array_filter([
                                        $basicInfo['location']['city'] ?? '',
                                        $basicInfo['location']['country'] ?? ''
                                    ]))
                                );
                                ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Member since <?php echo date('F Y', strtotime($gig['created_at'])); ?></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Average response time: 2 hours</span>
                        </div>
                    </div>

                    <?php if (isset($basicInfo['biography'])): ?>
                        <p class="mt-4 text-gray-700"><?php echo htmlspecialchars($basicInfo['biography']); ?></p>
                    <?php endif; ?>
                </section>

                <!-- Other Gigs Section -->
                <?php if (!empty($otherGigs)): ?>
                    <section class="bg-white rounded-2xl p-6">
                        <h2 class="text-xl font-semibold mb-4">More from <?php echo htmlspecialchars($gig['username']); ?>
                        </h2>
                        <div class="space-y-4">
                            <?php foreach ($otherGigs as $otherGig):
                                $otherMediaData = json_decode($otherGig['media_data'], true);
                                $otherFirstImage = !empty($otherMediaData['images']) ? $otherMediaData['images'][0] : null;
                                ?>
                                <a href="/public/views/gig.php?id=<?php echo $otherGig['gig_id']; ?>"
                                    class="block hover:bg-gray-50 rounded-lg transition">
                                    <div class="flex gap-4 p-3">
                                        <?php if ($otherFirstImage): ?>
                                            <div class="w-24 h-24 flex-shrink-0">
                                                <img src="/public/components/freelancer/<?php echo htmlspecialchars($otherFirstImage); ?>"
                                                    alt="<?php echo htmlspecialchars($otherGig['title']); ?>"
                                                    class="w-full h-full object-cover rounded-lg">
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($otherGig['title']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-500 mt-1">
                                                Starting at ₺<?php echo number_format($otherGig['price'], 2); ?>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50" onclick="closeLightbox()">
        <button class="absolute top-4 right-4 text-white text-4xl">&times;</button>
        <img id="lightbox-image"
            class="max-w-[90%] max-h-[90vh] absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightbox').classList.remove('hidden');
            document.getElementById('lightbox-image').src = src;
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.getElementById('lightbox-image').src = '';
            document.body.style.overflow = 'auto';
        }

        // Close lightbox with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>

</html>