<?php
// view.php
session_start();
require_once '../../config/database.php';

// Veritabanı bağlantısı
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

// URL'den kullanıcı adını al
$username = isset($_GET['username']) ? $_GET['username'] : null;

if (!$username) {
    header('Location: /');
    exit;
}

// Profil bilgilerini getir - Move this BEFORE accessing $profile
$stmt = $db->prepare("
    SELECT 
        u.*,
        up.*,
        w.balance,
        w.coins,
        s.skills,
        sl.social_links
    FROM users u
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    LEFT JOIN wallet w ON u.user_id = w.user_id
    LEFT JOIN skills s ON u.user_id = s.user_id
    LEFT JOIN social_links sl ON u.user_id = sl.user_id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header('Location: /');
    exit;
}

$isFreelancer = false;
$freelancerData = null;
$works = [];

// Now we can safely use $profile['user_id']
$stmt = $db->prepare("SELECT * FROM freelancers WHERE user_id = ?");
$stmt->execute([$profile['user_id']]);
$freelancerData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($freelancerData) {
    $isFreelancer = true;

    // İlanları getir
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

// Avatar yolu kontrolü
$avatarPath = 'profile/avatars/' . $profile['user_id'] . '.jpg';
$avatarFullPath = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $avatarPath;
$defaultAvatarPath = 'profile/avatars/default.jpg';

// Profil fotoğrafı var mı kontrol et
if (file_exists($avatarFullPath)) {
    $avatarUrl = '/public/' . $avatarPath;
} else {
    $avatarUrl = '/public/' . $defaultAvatarPath;
}

// Kullanıcının kendi profili mi kontrol et
$isOwnProfile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile['user_id'];

// Takipçi ve takip bilgilerini getir
$followsStmt = $db->prepare("SELECT following, followers FROM follows WHERE user_id = ?");
$followsStmt->execute([$profile['user_id']]);
$followsData = $followsStmt->fetch(PDO::FETCH_ASSOC);

// JSON'ları parse et
$following = json_decode($followsData['following'] ?? '[]', true);
$followers = json_decode($followsData['followers'] ?? '[]', true);

$followersCount = count($followers);
$followingCount = count($following);

// Takip durumunu kontrol et (eğer giriş yapmış bir kullanıcı varsa)
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
    <title><?php echo htmlspecialchars($profile['username']); ?> - Profile</title>
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
<div id="toast" class="toast-alert"></div>

<body class="bg-gray-100">
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">LUREID</h1>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" id="searchUsers"
                        class="w-64 px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                        placeholder="Search users...">
                    <div id="searchResults"
                        class="hidden absolute w-full mt-1 bg-white border rounded-lg shadow-lg z-50"></div>
                </div>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center gap-4">
                    <a href="/public/index.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Dashboard
                    </a>
                    <a href="/views/auth/logout.php" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="/public/views/auth/login.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Login
                </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow p-6">
            <!-- Profile Header -->
            <div class="flex items-start gap-8 mb-8">
                <img src="<?php echo $avatarUrl; ?>"
                    alt="<?php echo htmlspecialchars($profile['username']); ?>'s Profile Photo"
                    class="w-32 h-32 rounded-full object-cover">

                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($profile['username']); ?></h2>
                        <?php if ($isOwnProfile): ?>
                            <button id="editProfile" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                                Edit Profile
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-6 mb-4">
                        <div class="followersCount cursor-pointer">
                            <span class="font-bold"><?php echo $followersCount; ?></span> followers
                        </div>
                        <div class="followingCount cursor-pointer">
                            <span class="font-bold"><?php echo $followingCount; ?></span> following
                        </div>
                        <?php if (!$isOwnProfile && isset($_SESSION['user_id'])): ?>
                            <button id="followButton" data-user-id="<?php echo $profile['user_id']; ?>"
                                class="px-4 py-2 rounded <?php echo $isFollowing ? 'bg-gray-200 hover:bg-gray-300' : 'bg-blue-500 text-white hover:bg-blue-600'; ?>">
                                <?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($isOwnProfile): ?>
                            <div>
                                <span class="font-bold">₺<?php echo number_format($profile['balance'], 2); ?></span> balance
                            </div>
                            <div>
                                <span class="font-bold"><?php echo $profile['coins']; ?></span> coins
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <h3 class="font-bold"><?php echo htmlspecialchars($profile['full_name']); ?></h3>
                        <!-- Yaş, Şehir ve Ülke bilgileri -->
                        <div class="flex items-center gap-2 text-gray-600 mt-1 mb-2">
                            <?php if ($profile['age']): ?>
                                <span><?php echo htmlspecialchars($profile['age']); ?> years old</span>
                                <?php if ($profile['city'] || $profile['country']): ?>
                                    <span>•</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($profile['city']): ?>
                                <span><?php echo htmlspecialchars($profile['city']); ?></span>
                                <?php if ($profile['country']): ?>
                                    <span>•</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($profile['country']): ?>
                                <span><?php echo htmlspecialchars($profile['country']); ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- Biography ve Website -->
                        <p><?php echo nl2br(htmlspecialchars($profile['biography'])); ?></p>
                        <?php if ($profile['website']): ?>
                            <a href="<?php echo htmlspecialchars($profile['website']); ?>" target="_blank"
                                class="text-blue-500 hover:underline">
                                <?php echo htmlspecialchars($profile['website']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($isFreelancer): ?>
                            <div class="mt-2 flex items-center">
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">Freelancer</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span
                                    class="text-gray-600">₺<?php echo number_format($freelancerData['daily_rate'], 2); ?>/day</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <?php
            $skills = json_decode($profile['skills'] ?? '[]', true);
            if (!empty($skills)):
                ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4">Skills</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach ($skills as $skill): ?>
                            <div class="bg-gray-50 p-4 rounded">
                                <div class="font-semibold"><?php echo htmlspecialchars($skill['name']); ?></div>
                                <div class="w-full bg-gray-200 rounded h-2 mt-2">
                                    <div class="bg-blue-500 h-2 rounded" style="width: <?php echo ($skill['level'] * 20); ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Social Links -->
            <?php
            $socialLinks = json_decode($profile['social_links'], true);
            if (!empty($socialLinks)):
                ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4">Social Links</h3>
                    <div class="flex gap-4">
                        <?php foreach ($socialLinks as $link): ?>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"
                                class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                                <?php echo htmlspecialchars($link['platform']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <!-- İlanlar Bölümü - sosyal linklerden sonra -->
        <?php if ($isFreelancer && !empty($works)): ?>
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-4">Works</h3>
                <div class="grid grid-cols-2 gap-4">
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
                                        View
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
                                    <?php
                                    $tags = json_decode($work['tags'], true);
                                    if ($tags):
                                        foreach (array_slice($tags, 0, 2) as $tag):
                                            ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                                <?php echo htmlspecialchars($tag); ?>
                                            </span>
                                            <?php
                                        endforeach;
                                        if (count($tags) > 2):
                                            ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                                +<?php echo count($tags) - 2; ?>
                                            </span>
                                            <?php
                                        endif;
                                    endif;
                                    ?>
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
    </main>

    <!-- Followers/Following Modals -->
    <div id="followersModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Followers</h3>
                <button class="closeModal text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div id="followersList" class="max-h-96 overflow-y-auto">
                <!-- Followers will be loaded here -->
            </div>
        </div>
    </div>

    <div id="followingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Following</h3>
                <button class="closeModal text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div id="followingList" class="max-h-96 overflow-y-auto">
                <!-- Following users will be loaded here -->
            </div>
        </div>
    </div>

    <?php if ($isOwnProfile): ?>
        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
                <h3 class="text-xl font-bold mb-4">Edit Profile</h3>
                <form id="profileForm" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($profile['username']); ?>"
                                class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block mb-1">Full Name</label>
                            <input type="text" name="fullName"
                                value="<?php echo htmlspecialchars($profile['full_name']); ?>"
                                class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block mb-1">Age</label>
                            <input type="number" name="age" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>"
                                min="13" max="85" class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block mb-1">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>"
                                class="w-full border rounded p-2">
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1">Biography</label>
                        <textarea name="biography"
                            class="w-full border rounded p-2"><?php echo htmlspecialchars($profile['biography']); ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1">City</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($profile['city']); ?>"
                                class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block mb-1">Country</label>
                            <input type="text" name="country" value="<?php echo htmlspecialchars($profile['country']); ?>"
                                class="w-full border rounded p-2">
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1">Website</label>
                        <input type="url" name="website" value="<?php echo htmlspecialchars($profile['website']); ?>"
                            class="w-full border rounded p-2">
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" id="closeEditProfile" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        $(document).ready(function () {
            // Edit Profile Modal (sadece kendi profili)
            <?php if ($isOwnProfile): ?>
                $('#editProfile').click(function () {
                    $('#editProfileModal').removeClass('hidden');
                });

                $('#closeEditProfile').click(function () {
                    $('#editProfileModal').addClass('hidden');
                });

                function showToast(message, type = 'success') {
                    const $toast = $('#toast');
                    $toast.removeClass().addClass('toast-alert toast-' + type);
                    $toast.text(message);
                    $toast.fadeIn();
                    setTimeout(() => $toast.fadeOut(), 3000);
                }

                $('#profileForm').submit(function (e) {
                    e.preventDefault();
                    $.ajax({
                        url: '/public/components/update_profile.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                // Formdaki verileri al
                                const formData = {
                                    username: $('[name="username"]').val(),
                                    fullName: $('[name="fullName"]').val(),
                                    age: $('[name="age"]').val(),
                                    city: $('[name="city"]').val(),
                                    country: $('[name="country"]').val(),
                                    biography: $('[name="biography"]').val(),
                                    website: $('[name="website"]').val()
                                };

                                // Username ve full name güncelle
                                $('h2.text-2xl.font-bold').text(formData.username);
                                $('h3.font-bold').first().text(formData.fullName);

                                // Biography güncelle
                                const biographyContainer = $('.mb-4').find('p').first();
                                if (formData.biography) {
                                    biographyContainer.html(formData.biography.replace(/\n/g, '<br>'));
                                } else {
                                    biographyContainer.html('');
                                }

                                // Yaş, şehir ve ülke bölümünü güncelle
                                const locationContainer = $('.flex.items-center.gap-2.text-gray-600');
                                let locationHtml = '';

                                if (formData.age) {
                                    locationHtml += `<span>${formData.age} years old</span>`;
                                    if (formData.city || formData.country) locationHtml += '<span>•</span>';
                                }
                                if (formData.city) {
                                    locationHtml += `<span>${formData.city}</span>`;
                                    if (formData.country) locationHtml += '<span>•</span>';
                                }
                                if (formData.country) {
                                    locationHtml += `<span>${formData.country}</span>`;
                                }
                                locationContainer.html(locationHtml);

                                // Website güncelle
                                const websiteLink = $('.mb-4').find('a.text-blue-500');
                                if (formData.website) {
                                    if (websiteLink.length) {
                                        websiteLink.attr('href', formData.website).text(formData.website);
                                    } else {
                                        $('.mb-4').append(`
                                            <a href="${formData.website}" target="_blank" 
                                               class="text-blue-500 hover:underline">
                                                ${formData.website}
                                            </a>
                                        `);
                                    }
                                } else {
                                    websiteLink.remove();
                                }

                                // Modalı kapat
                                $('#editProfileModal').addClass('hidden');

                                // Toast mesajı göster
                                showToast('Profile updated successfully!');
                            } else {
                                showToast(response.message || 'Error updating profile', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            showToast('Error updating profile: ' + error, 'error');
                        }
                    });
                });
            <?php endif; ?>

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

            // Modal dışına tıklayınca kapatma
            $(document).click(function (e) {
                const $modal = $(e.target).closest('[id$="Modal"]');
                // Edit profile modalı değilse ve modal içeriğine tıklanmadıysa
                if ($modal.length && !$(e.target).closest('.modal-content').length && $modal.attr('id') !== 'editProfileModal') {
                    $modal.addClass('hidden');
                }
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