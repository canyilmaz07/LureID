<?php
session_start();
require_once '../../config/database.php';
require_once '../../languages/language_handler.php';

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

// Get username from URL
$username = isset($_GET['username']) ? $_GET['username'] : null;

if (!$username) {
    header('Location: /');
    exit;
}

// Get profile information with extended details
$stmt = $db->prepare("
    SELECT 
        u.*,
        ued.*,
        w.balance,
        w.coins,
        f.following,
        f.followers
    FROM users u
    LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id
    LEFT JOIN wallet w ON u.user_id = w.user_id
    LEFT JOIN follows f ON u.user_id = f.user_id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header('Location: /');
    exit;
}

// Parse JSON fields
$basicInfo = json_decode($profile['basic_info'], true) ?? [];
$educationHistory = json_decode($profile['education_history'], true) ?? [];
$workExperience = json_decode($profile['work_experience'], true) ?? [];
$skillsMatrix = json_decode($profile['skills_matrix'], true) ?? [];
$portfolioShowcase = json_decode($profile['portfolio_showcase'], true) ?? [];
$professionalProfile = json_decode($profile['professional_profile'], true) ?? [];
$networkLinks = json_decode($profile['network_links'], true) ?? [];
$achievements = json_decode($profile['achievements'], true) ?? [];
$communityEngagement = json_decode($profile['community_engagement'], true) ?? [];
$performanceMetrics = json_decode($profile['performance_metrics'], true) ?? [];

// Check if user is freelancer
$isFreelancer = false;
$freelancerData = null;
$works = [];

$stmt = $db->prepare("SELECT * FROM freelancers WHERE user_id = ?");
$stmt->execute([$profile['user_id']]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($freelancerData) {
    $isFreelancer = true;

    // Get works
    $stmt = $db->prepare("
        SELECT w.*, wm.media_paths
        FROM works w
        LEFT JOIN works_media wm ON w.work_id = wm.work_id
        WHERE w.freelancer_id = ? AND w.visibility = 1
        ORDER BY w.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$freelancerData['freelancer_id']]);
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check avatar
$avatarPath = $profile['profile_photo_url'] ?? 'profile/avatars/default.jpg';
$avatarUrl = '/public/' . $avatarPath;

// Check if own profile
$isOwnProfile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile['user_id'];

// Parse followers/following
$following = json_decode($profile['following'] ?? '[]', true);
$followers = json_decode($profile['followers'] ?? '[]', true);

$followersCount = count($followers);
$followingCount = count($following);

// Check if current user is following
$isFollowing = false;
if (isset($_SESSION['user_id'])) {
    $isFollowing = in_array($_SESSION['user_id'], $followers);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile['username']); ?> - <?= __('Profile') ?></title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .toast-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.5rem;
            z-index: 50;
            display: none;
        }

        .toast-success {
            background-color: #34D399;
            color: white;
        }

        .toast-error {
            background-color: #EF4444;
            color: white;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div id="toast" class="toast-alert"></div>

    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" id="searchUsers"
                        class="w-64 px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                        placeholder="<?= __('Search users...') ?>">
                    <div id="searchResults"
                        class="hidden absolute w-full mt-1 bg-white border rounded-lg shadow-lg z-50"></div>
                </div>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center gap-4">
                    <a href="/public/index.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        <?= __('Dashboard') ?>
                    </a>
                    <a href="/views/auth/logout.php" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        <?= __('Logout') ?>
                    </a>
                </div>
            <?php else: ?>
                <a href="/views/auth/login.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    <?= __('Login') ?>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <!-- Profile Header -->
            <div class="flex items-start gap-8 mb-8">
                <img src="<?php echo $avatarUrl; ?>"
                    alt="<?php echo htmlspecialchars($profile['username']); ?>'s <?= __('Profile Photo') ?>"
                    class="w-32 h-32 rounded-full object-cover">

                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($profile['username']); ?></h2>
                        <?php if ($isOwnProfile): ?>
                            <a href="/public/components/settings/settings.php"
                                class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 inline-block">
                                <?= __('Edit Profile') ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Follow Stats -->
                    <div class="flex gap-6 mb-4">
                        <div class="followersCount cursor-pointer">
                            <span class="font-bold"><?php echo $followersCount; ?></span> <?= __('followers') ?>
                        </div>
                        <div class="followingCount cursor-pointer">
                            <span class="font-bold"><?php echo $followingCount; ?></span> <?= __('following') ?>
                        </div>
                        <?php if (!$isOwnProfile && isset($_SESSION['user_id'])): ?>
                            <button id="followButton" data-user-id="<?php echo $profile['user_id']; ?>"
                                class="px-4 py-2 rounded <?php echo $isFollowing ? 'bg-gray-200 hover:bg-gray-300' : 'bg-blue-500 text-white hover:bg-blue-600'; ?>">
                                <?php echo $isFollowing ? __('Unfollow') : __('Follow'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($isOwnProfile): ?>
                            <div>
                                <span class="font-bold">₺<?php echo number_format($profile['balance'], 2); ?></span>
                                <?= __('balance') ?>
                            </div>
                            <div>
                                <span class="font-bold"><?php echo $profile['coins']; ?></span> <?= __('coins') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Basic Info -->
                    <div class="mb-4">
                        <h3 class="font-bold">
                            <?php echo htmlspecialchars($basicInfo['full_name'] ?? $profile['full_name']); ?>
                        </h3>
                        <div class="flex items-center gap-2 text-gray-600 mt-1 mb-2">
                            <?php if (isset($basicInfo['age'])): ?>
                                <span><?php echo htmlspecialchars($basicInfo['age']); ?>     <?= __('years old') ?></span>
                                <?php if (isset($basicInfo['location'])): ?>
                                    <span>•</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (isset($basicInfo['location'])): ?>
                                <span><?php echo htmlspecialchars($basicInfo['location']['city'] ?? ''); ?></span>
                                <?php if (isset($basicInfo['location']['country'])): ?>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($basicInfo['location']['country']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($basicInfo['biography'])): ?>
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($basicInfo['biography'])); ?></p>
                        <?php endif; ?>
                        <?php if (isset($basicInfo['contact']['website'])): ?>
                            <a href="<?php echo htmlspecialchars($basicInfo['contact']['website']); ?>" target="_blank"
                                class="text-blue-500 hover:underline">
                                <?php echo htmlspecialchars($basicInfo['contact']['website']); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Professional Status -->
                    <?php if ($isFreelancer): ?>
                        <div class="flex items-center gap-2 mb-4">
                            <span
                                class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm"><?= __('Freelancer') ?></span>
                            <span class="mx-2 text-gray-400">•</span>
                            <span
                                class="text-gray-600">₺<?php echo number_format($freelancerData['daily_rate'], 2); ?>/<?= __('day') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skills Section -->
            <?php if (!empty($skillsMatrix)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Skills') ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if (!empty($skillsMatrix['technical_skills'])): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold mb-2"><?= __('Technical Skills') ?></h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($skillsMatrix['technical_skills'] as $skill): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($skillsMatrix['soft_skills'])): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold mb-2"><?= __('Soft Skills') ?></h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($skillsMatrix['soft_skills'] as $skill): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($skillsMatrix['tools'])): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold mb-2"><?= __('Tools') ?></h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($skillsMatrix['tools'] as $tool): ?>
                                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-sm">
                                            <?php echo htmlspecialchars($tool); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Work Experience Section -->
            <?php if (!empty($workExperience)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Work Experience') ?></h3>
                    <div class="space-y-4">
                        <?php foreach ($workExperience as $work): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold"><?= htmlspecialchars($work['position']); ?></h4>
                                <div class="text-gray-600">
                                    <?= htmlspecialchars($work['company']); ?> •
                                    <?= htmlspecialchars($work['start_date']); ?> -
                                    <?= $work['end_date'] ? htmlspecialchars($work['end_date']) : __('Present'); ?>
                                </div>
                                <?php if (isset($work['description'])): ?>
                                    <p class="mt-2 text-gray-700"><?= htmlspecialchars($work['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Education History Section -->
            <?php if (!empty($educationHistory)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Education') ?></h3>
                    <div class="space-y-4">
                        <?php foreach ($educationHistory as $education): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold"><?= htmlspecialchars($education['institution']); ?></h4>
                                <div class="text-gray-600">
                                    <?= htmlspecialchars($education['degree']); ?> •
                                    <?= htmlspecialchars($education['start_date']); ?> -
                                    <?= $education['end_date'] ? htmlspecialchars($education['end_date']) : __('Present'); ?>
                                </div>
                                <?php if (isset($education['gpa'])): ?>
                                    <div class="mt-1 text-gray-600">
                                        <?= __('GPA') ?>: <?= htmlspecialchars($education['gpa']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Portfolio Showcase Section -->
            <?php if (!empty($portfolioShowcase)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Portfolio') ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($portfolioShowcase as $portfolio): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold"><?= htmlspecialchars($portfolio['title']); ?></h4>
                                <p class="text-gray-600 mt-1"><?= htmlspecialchars($portfolio['description']); ?></p>
                                <?php if (isset($portfolio['url'])): ?>
                                    <a href="<?= htmlspecialchars($portfolio['url']); ?>" target="_blank"
                                        class="text-blue-500 hover:underline mt-2 inline-block">
                                        <?= __('View Project') ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Professional Profile Section -->
            <?php if (!empty($professionalProfile)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Professional Profile') ?></h3>
                    <div class="bg-gray-50 p-4 rounded">
                        <?php if (isset($professionalProfile['summary'])): ?>
                            <p class="mb-4"><?= htmlspecialchars($professionalProfile['summary']); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($professionalProfile['expertise_areas'])): ?>
                            <div class="mb-4">
                                <h4 class="font-semibold mb-2"><?= __('Areas of Expertise') ?></h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($professionalProfile['expertise_areas'] as $area): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                            <?= htmlspecialchars($area); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($professionalProfile['certifications'])): ?>
                            <div>
                                <h4 class="font-semibold mb-2"><?= __('Certifications') ?></h4>
                                <ul class="list-disc list-inside space-y-1">
                                    <?php foreach ($professionalProfile['certifications'] as $cert): ?>
                                        <li class="text-gray-700"><?= htmlspecialchars($cert); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Network Links Section -->
            <?php if (!empty($networkLinks)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Network Links') ?></h3>
                    <div class="flex flex-wrap gap-4">
                        <?php if (!empty($networkLinks['professional'])): ?>
                            <?php foreach ($networkLinks['professional'] as $platform => $username): ?>
                                <a href="https://<?= htmlspecialchars($platform); ?>.com/<?= htmlspecialchars($username); ?>"
                                    target="_blank" class="flex items-center gap-2 px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                                    <span class="capitalize"><?= htmlspecialchars($platform); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($networkLinks['social'])): ?>
                            <?php foreach ($networkLinks['social'] as $platform => $username): ?>
                                <a href="https://<?= htmlspecialchars($platform); ?>.com/<?= htmlspecialchars($username); ?>"
                                    target="_blank" class="flex items-center gap-2 px-4 py-2 bg-blue-100 rounded hover:bg-blue-200">
                                    <span class="capitalize"><?= htmlspecialchars($platform); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Achievements Section -->
            <?php if (!empty($achievements)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Achievements') ?></h3>
                    <div class="space-y-4">
                        <?php foreach ($achievements as $achievement): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <h4 class="font-semibold"><?= htmlspecialchars($achievement['title']); ?></h4>
                                <div class="text-gray-600">
                                    <?= htmlspecialchars($achievement['issuer']); ?> •
                                    <?= htmlspecialchars($achievement['date']); ?>
                                </div>
                                <?php if (isset($achievement['description'])): ?>
                                    <p class="mt-2 text-gray-700"><?= htmlspecialchars($achievement['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Works Section -->
            <?php if ($isFreelancer && !empty($works)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4"><?= __('Works') ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($works as $work): ?>
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <?php
                                $mediaPaths = json_decode($work['media_paths'], true);
                                $firstImage = null;
                                if ($mediaPaths) {
                                    foreach ($mediaPaths as $type => $path) {
                                        if (strpos($type, 'image_') !== false) {
                                            $firstImage = $path;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <?php if ($firstImage): ?>
                                    <div class="relative pt-[56.25%]">
                                        <img src="/<?php echo htmlspecialchars($firstImage); ?>"
                                            alt="<?php echo htmlspecialchars($work['title']); ?>"
                                            class="absolute inset-0 w-full h-full object-cover">
                                    </div>
                                <?php endif; ?>
                                <div class="p-4">
                                    <h4 class="font-semibold mb-2"><?php echo htmlspecialchars($work['title']); ?></h4>
                                    <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                        <?php echo htmlspecialchars($work['description']); ?>
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($work['created_at'])); ?>
                                        </div>
                                        <a href="/public/views/work.php?id=<?php echo $work['work_id']; ?>"
                                            class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                            View Details
                                        </a>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <?php if ($work['daily_rate']): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                                ₺<?php echo number_format($work['daily_rate'], 2); ?>/day
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($work['fixed_price']): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                                ₺<?php echo number_format($work['fixed_price'], 2); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($works) >= 6): ?>
                        <div class="text-center mt-4">
                            <a href="/public/views/works.php?user=<?php echo htmlspecialchars($profile['username']); ?>"
                                class="inline-block px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                                View All Works
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Followers Modal -->
            <div id="followersModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                <div
                    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg w-full max-w-md">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="text-xl font-bold">Followers</h3>
                        <button class="closeModal text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-content max-h-96 overflow-y-auto">
                        <div id="followersList"></div>
                    </div>
                </div>
            </div>

            <!-- Following Modal -->
            <div id="followingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                <div
                    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg w-full max-w-md">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="text-xl font-bold">Following</h3>
                        <button class="closeModal text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-content max-h-96 overflow-y-auto">
                        <div id="followingList"></div>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function () {
                    // Follow/Unfollow functionality
                    $('#followButton').click(function () {
                        const userId = $(this).data('user-id');
                        const $button = $(this);

                        $.post('/public/components/follow_user.php', { user_id: userId }, function (response) {
                            if (response.success) {
                                if (response.action === 'followed') {
                                    $button.removeClass('bg-blue-500 text-white hover:bg-blue-600')
                                        .addClass('bg-gray-200 hover:bg-gray-300')
                                        .text('Unfollow');
                                } else {
                                    $button.removeClass('bg-gray-200 hover:bg-gray-300')
                                        .addClass('bg-blue-500 text-white hover:bg-blue-600')
                                        .text('Follow');
                                }
                                // Takipçi sayısını güncelle
                                location.reload();
                            }
                        });
                    });

                    // Followers Modal
                    $('.followersCount').click(function () {
                        const userId = <?php echo $profile['user_id']; ?>;
                        $.get('/public/components/get_follows.php', {
                            user_id: userId,
                            type: 'followers'
                        }, function (data) {
                            let html = '';
                            data.forEach(user => {
                                html += `
                <div class="flex items-center justify-between p-3 hover:bg-gray-50">
                    <a href="/${user.username}" class="flex items-center gap-3">
                        <img src="/public/${user.profile_photo_url}" 
                             alt="${user.username}" 
                             class="w-10 h-10 rounded-full">
                        <div>
                            <div class="font-semibold">${user.username}</div>
                            <div class="text-sm text-gray-500">${user.full_name}</div>
                        </div>
                    </a>
                    ${user.can_follow ? `
                        <button class="modalFollowButton px-4 py-2 rounded ${user.is_following ? 'bg-gray-200 hover:bg-gray-300' : 'bg-blue-500 text-white hover:bg-blue-600'}"
                                data-user-id="${user.user_id}">
                            ${user.is_following ? 'Unfollow' : 'Follow'}
                        </button>
                    ` : ''}
                </div>
            `;
                            });
                            $('#followersList').html(html);
                            $('#followersModal').removeClass('hidden');
                        });
                    });

                    // Following Modal
                    $('.followingCount').click(function () {
                        const userId = <?php echo $profile['user_id']; ?>;
                        $.get('/public/components/get_follows.php', {
                            user_id: userId,
                            type: 'following'
                        }, function (data) {
                            let html = '';
                            data.forEach(user => {
                                html += `
                <div class="flex items-center justify-between p-3 hover:bg-gray-50">
                    <a href="/${user.username}" class="flex items-center gap-3">
                        <img src="/public/${user.profile_photo_url}" 
                             alt="${user.username}" 
                             class="w-10 h-10 rounded-full">
                        <div>
                            <div class="font-semibold">${user.username}</div>
                            <div class="text-sm text-gray-500">${user.full_name}</div>
                        </div>
                    </a>
                    ${user.can_follow ? `
                        <button class="modalFollowButton px-4 py-2 rounded ${user.is_following ? 'bg-gray-200 hover:bg-gray-300' : 'bg-blue-500 text-white hover:bg-blue-600'}"
                                data-user-id="${user.user_id}">
                            ${user.is_following ? 'Unfollow' : 'Follow'}
                        </button>
                    ` : ''}
                </div>
            `;
                            });
                            $('#followingList').html(html);
                            $('#followingModal').removeClass('hidden');
                        });
                    });

                    // Modal içindeki takip butonları
                    $(document).on('click', '.modalFollowButton', function (e) {
                        e.stopPropagation(); // Modal kapanmasını engellemek için
                        const userId = $(this).data('user-id');
                        const $button = $(this);
                        const modalId = $button.closest('[id$="Modal"]').attr('id');
                        const isOwnProfile = <?php echo $isOwnProfile ? 'true' : 'false'; ?>; // Kendi profili mi kontrol et

                        $.post('/public/components/follow_user.php', { user_id: userId }, function (response) {
                            if (response.success) {
                                // Buton görünümünü güncelle
                                if (response.action === 'followed') {
                                    $button.removeClass('bg-blue-500 text-white hover:bg-blue-600')
                                        .addClass('bg-gray-200 hover:bg-gray-300')
                                        .text('Unfollow');

                                    // Eğer kendi profilindeyse following sayısını arttır
                                    if (isOwnProfile) {
                                        const currentCount = parseInt($('.followingCount .font-bold').text());
                                        $('.followingCount .font-bold').text(currentCount + 1);
                                    }
                                } else {
                                    $button.removeClass('bg-gray-200 hover:bg-gray-300')
                                        .addClass('bg-blue-500 text-white hover:bg-blue-600')
                                        .text('Follow');

                                    // Eğer kendi profilindeyse following sayısını azalt
                                    if (isOwnProfile) {
                                        const currentCount = parseInt($('.followingCount .font-bold').text());
                                        $('.followingCount .font-bold').text(currentCount - 1);
                                    }
                                }

                                // Diğer kullanıcının profilindeyse takipçi sayısını güncelle
                                if (!isOwnProfile) {
                                    updateFollowCounts(<?php echo $profile['user_id']; ?>);
                                }

                                // Her iki modalı da güncelle
                                refreshModalContent('followersModal');
                                refreshModalContent('followingModal');
                            }
                        });
                    });

                    // Modal kapatma
                    $('.closeModal').click(function () {
                        $(this).closest('[id$="Modal"]').addClass('hidden');
                    });

                    // Arama fonksiyonalitesi
                    let searchTimeout;
                    $('#searchUsers').on('input', function () {
                        clearTimeout(searchTimeout);
                        const query = $(this).val();
                        const $results = $('#searchResults');

                        if (query.length < 2) {
                            $results.html('').addClass('hidden');
                            return;
                        }

                        searchTimeout = setTimeout(() => {
                            $.get('/public/components/search_users.php', { query: query }, function (data) {
                                if (data.length > 0) {
                                    let html = '';
                                    data.forEach(user => {
                                        html += `
                            <a href="/${user.username}" class="flex items-center gap-3 p-3 hover:bg-gray-50">
                                <img src="/public/${user.profile_photo_url}" 
                                     alt="${user.username}" 
                                     class="w-8 h-8 rounded-full">
                                <div>
                                    <div class="font-semibold">${user.username}</div>
                                    <div class="text-sm text-gray-500">${user.full_name}</div>
                                </div>
                            </a>
                        `;
                                    });
                                    $results.html(html).removeClass('hidden');
                                } else {
                                    $results.html('<div class="p-3 text-gray-500">No users found</div>').removeClass('hidden');
                                }
                            });
                        }, 300);
                    });

                    // Arama sonuçlarını kapat
                    $(document).click(function (e) {
                        if (!$(e.target).closest('#searchUsers, #searchResults').length) {
                            $('#searchResults').addClass('hidden');
                        }
                    });

                    // Takipçi sayılarını güncelle
                    function updateFollowCounts(userId) {
                        $.get('/public/components/get_follows.php', {
                            user_id: userId,
                            type: 'counts'
                        }, function (response) {
                            $('.followersCount .font-bold').text(response.followers);
                            $('.followingCount .font-bold').text(response.following);
                        });
                    }

                    // Modal içeriğini yenile
                    function refreshModalContent(modalId) {
                        const userId = <?php echo $profile['user_id']; ?>;
                        const type = modalId === 'followersModal' ? 'followers' : 'following';
                        const listId = modalId === 'followersModal' ? 'followersList' : 'followingList';

                        $.get('/public/components/get_follows.php', {
                            user_id: userId,
                            type: type
                        }, function (data) {
                            let html = '';
                            data.forEach(user => {
                                html += `
                <div class="flex items-center justify-between p-3 hover:bg-gray-50">
                    <a href="/${user.username}" class="flex items-center gap-3">
                        <img src="/public/${user.profile_photo_url}" 
                             alt="${user.username}" 
                             class="w-10 h-10 rounded-full">
                        <div>
                            <div class="font-semibold">${user.username}</div>
                            <div class="text-sm text-gray-500">${user.full_name}</div>
                        </div>
                    </a>
                    ${user.can_follow ? `
                        <button class="modalFollowButton px-4 py-2 rounded ${user.is_following ? 'bg-gray-200 hover:bg-gray-300' : 'bg-blue-500 text-white hover:bg-blue-600'}"
                                data-user-id="${user.user_id}">
                            ${user.is_following ? 'Unfollow' : 'Follow'}
                        </button>
                    ` : ''}
                </div>
            `;
                            });
                            $(`#${listId}`).html(html);
                        });
                    }
                });
            </script>
</body>

</html>