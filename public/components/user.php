<?php
//user.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

// Database connection
$dbConfig = require '../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

$stmt = $db->prepare("SELECT profile_photo_url FROM user_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profilePhotoUrl = $stmt->fetchColumn();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'updateProfilePhoto':
            if (!isset($_FILES['profilePhoto'])) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }

            $file = $_FILES['profilePhoto'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
                exit;
            }

            if (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
                echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG & PNG files are allowed']);
                exit;
            }

            $uploadDir = '../../public/profile/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = $_SESSION['user_id'] . '.jpg';
            $uploadPath = $uploadDir . $fileName;

            // Convert any image to JPG and save
            $image = null;
            switch ($file['type']) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
            }

            if ($image) {
                imagejpeg($image, $uploadPath, 90);
                imagedestroy($image);

                $stmt = $db->prepare("UPDATE user_profiles SET profile_photo_url = ? WHERE user_id = ?");
                $stmt->execute(['profile/avatars/' . $fileName, $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'url' => 'public/profile/avatars/' . $fileName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to process image']);
            }
            break;

        case 'updateBasicInfo':
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?, full_name = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $_POST['fullName'],
                $_SESSION['user_id']
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'updatePassword':
            $currentPass = $_POST['currentPassword'];
            $newPass = $_POST['newPassword'];

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentHashedPass = $stmt->fetchColumn();

            if (!password_verify($currentPass, $currentHashedPass)) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                exit;
            }

            // Update password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'toggleTwoFactor':
            $enabled = $_POST['enabled'] === 'true';
            $stmt = $db->prepare("UPDATE users SET two_factor_auth = ? WHERE user_id = ?");
            $stmt->execute([$enabled ? 1 : 0, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'updatePersonal':
            $age = (int) $_POST['age'];
            if ($age < 13 || $age > 85) {
                echo json_encode(['success' => false, 'message' => 'Age must be between 13 and 85']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE user_profiles 
                SET age = ?, biography = ?, city = ?, country = ?, website = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $age,
                $_POST['biography'],
                $_POST['city'],
                $_POST['country'],
                $_POST['website'],
                $_SESSION['user_id']
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'updateSocialLinks':
            $links = json_decode($_POST['links'], true);
            $stmt = $db->prepare("UPDATE social_links SET social_links = ? WHERE user_id = ?");
            $stmt->execute([json_encode($links), $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'updateSkills':
            $skills = json_decode($_POST['skills'], true);

            if (count($skills) > 5) {
                echo json_encode(['success' => false, 'message' => 'Maximum 5 skills allowed']);
                exit;
            }

            // Her skill için level kontrolü
            foreach ($skills as $skill) {
                if (
                    empty($skill['name']) ||
                    empty($skill['level']) ||
                    $skill['level'] < 1 ||
                    $skill['level'] > 5
                ) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Skill levels must be between 1 and 5'
                    ]);
                    exit;
                }
            }

            // Önce kullanıcının skills kaydı var mı kontrol et
            $checkSkillsStmt = $db->prepare("SELECT COUNT(*) FROM skills WHERE user_id = ?");
            $checkSkillsStmt->execute([$_SESSION['user_id']]);

            if ($checkSkillsStmt->fetchColumn() == 0) {
                // Kayıt yoksa yeni kayıt oluştur
                $stmt = $db->prepare("INSERT INTO skills (user_id, skills) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], json_encode($skills)]);
            } else {
                // Kayıt varsa güncelle
                $stmt = $db->prepare("UPDATE skills SET skills = ? WHERE user_id = ?");
                $stmt->execute([json_encode($skills), $_SESSION['user_id']]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'verifyLogin':
            $stmt = $db->prepare("
                UPDATE login_attempts 
                SET verified = 1 
                WHERE attempt_id = ? AND user_id = ?
            ");
            $stmt->execute([$_POST['attemptId'], $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'deleteLoginAttempt':
            $stmt = $db->prepare("
                DELETE FROM login_attempts 
                WHERE attempt_id = ? AND user_id = ?
            ");
            $stmt->execute([$_POST['attemptId'], $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'deleteAllLoginAttempts':
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'verifyPasswordForDeletion':
            $password = $_POST['password'];
            $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentHashedPass = $stmt->fetchColumn();

            if (password_verify($password, $currentHashedPass)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            }
            break;

        case 'deleteAccount':
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $db->commit();
                session_destroy();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Load content based on tab
$tab = $_GET['tab'] ?? 'security';
$userData = $_SESSION['user_data'];

// Common data fetching
switch ($tab) {
    case 'security':
        // Security tab content
        ?>
        <div>
            <h3 class="text-lg font-medium mb-4">Profile Photo</h3>
            <form id="profilePhotoForm" class="space-y-4">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <img src="<?php echo htmlspecialchars($profilePhotoUrl); ?>" alt="Profile Photo" id="previewImage"
                            class="w-24 h-24 rounded-full object-cover border-2 border-gray-200">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium mb-2">Change Profile Photo</label>
                        <input type="file" id="profilePhotoInput" name="profilePhoto" accept="image/jpeg,image/jpg,image/png"
                            class="block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-full file:border-0
                              file:text-sm file:font-semibold
                              file:bg-blue-50 file:text-blue-700
                              hover:file:bg-blue-100">
                        <button type="submit" id="saveProfilePhoto"
                            class="hidden bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Save Photo
                        </button>
                        <p class="mt-1 text-sm text-gray-500">Maximum file size: 5MB. JPG, JPEG or PNG only.</p>
                    </div>
                </div>
            </form>
        </div>

        <script>
            $(document).ready(function () {
                // Dosya seçildiğinde önizleme göster
                $('#profilePhotoInput').change(function () {
                    const file = this.files[0];
                    if (file) {
                        // Dosya boyutu kontrolü
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size must be less than 5MB');
                            this.value = '';
                            return;
                        }

                        // Dosya tipi kontrolü
                        if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                            alert('Only JPG, JPEG & PNG files are allowed');
                            this.value = '';
                            return;
                        }

                        // Önizleme göster
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            $('#previewImage').attr('src', e.target.result);
                        };
                        reader.readAsDataURL(file);

                        // Save butonunu göster
                        $('#saveProfilePhoto').removeClass('hidden');
                    }
                });

                // Form submit edildiğinde
                $('#profilePhotoForm').submit(function (e) {
                    e.preventDefault();

                    const formData = new FormData();
                    const file = $('#profilePhotoInput')[0].files[0];

                    if (!file) {
                        alert('Please select a file first');
                        return;
                    }

                    formData.append('action', 'updateProfilePhoto');
                    formData.append('profilePhoto', file);

                    $.ajax({
                        url: 'components/user.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        beforeSend: function () {
                            $('#saveProfilePhoto').prop('disabled', true).text('Uploading...');
                        },
                        success: function (response) {
                            response = JSON.parse(response);
                            if (response.success) {
                                alert('Profile photo updated successfully');
                                // Form'u resetle ve save butonunu gizle
                                $('#profilePhotoForm')[0].reset();
                                $('#saveProfilePhoto').addClass('hidden');
                            } else {
                                alert(response.message || 'Error updating profile photo');
                            }
                        },
                        error: function () {
                            alert('Error updating profile photo');
                        },
                        complete: function () {
                            $('#saveProfilePhoto').prop('disabled', false).text('Save Photo');
                        }
                    });
                });
            });
        </script>

        <!-- Basic Info -->
        <div>
            <h3 class="text-lg font-medium mb-4">Basic Information</h3>
            <form id="basicInfoForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>"
                        class="mt-1 block w-full rounded border p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>"
                        class="mt-1 block w-full rounded border p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium">Full Name</label>
                    <input type="text" name="fullName" value="<?php echo htmlspecialchars($userData['full_name']); ?>"
                        class="mt-1 block w-full rounded border p-2">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Password Change -->
        <div class="border-b pb-6">
            <h3 class="text-lg font-medium mb-4">Change Password</h3>
            <form id="passwordForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Current Password</label>
                    <input type="password" name="currentPassword" required class="mt-1 block w-full rounded border p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium">New Password</label>
                    <input type="password" name="newPassword" required minlength="8"
                        class="mt-1 block w-full rounded border p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium">Confirm New Password</label>
                    <input type="password" name="confirmPassword" required class="mt-1 block w-full rounded border p-2">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Update Password
                </button>
            </form>
        </div>

        <!-- Two Factor Auth -->
        <div class="border-b pb-6">
            <h3 class="text-lg font-medium mb-4">Two-Factor Authentication</h3>
            <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                <div>
                    <p class="font-medium">Status:
                        <span class="<?php echo $userData['two_factor_auth'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $userData['two_factor_auth'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                    <p class="text-sm text-gray-600">Add an extra layer of security to your account</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" class="sr-only peer" id="twoFactorToggle" <?php echo $userData['two_factor_auth'] ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                                    peer-checked:after:translate-x-full peer-checked:after:border-white 
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                                    after:bg-white after:border-gray-300 after:border after:rounded-full 
                                    after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>


        </div>

        <script>
            $(document).ready(function () {
                // Two Factor Toggle
                $('#twoFactorToggle').change(function () {
                    const enabled = $(this).is(':checked');
                    const statusSpan = $(this).closest('.bg-gray-50').find('span');

                    $.post('components/user.php', {
                        action: 'toggleTwoFactor',
                        enabled: enabled
                    }).done(function (response) {
                        if (response.success) {
                            const statusText = enabled ? 'Enabled' : 'Disabled';
                            const statusColor = enabled ? 'text-green-600' : 'text-red-600';
                            statusSpan.removeClass('text-green-600 text-red-600')
                                .addClass(statusColor)
                                .text(statusText);

                            $('#settingsSection').hide();
                            $('#mainDashboard').show();
                        }
                    });
                });

                // Security tab içindeki script kısmına ekle
                $('#basicInfoForm').submit(function (e) {
                    e.preventDefault();
                    $.post('components/user.php', {
                        action: 'updateBasicInfo',
                        username: $('[name="username"]').val(),
                        email: $('[name="email"]').val(),
                        fullName: $('[name="fullName"]').val()
                    }).done(function (response) {
                        if (response.success) {
                            alert('Basic information updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // Password Form
                $('#passwordForm').submit(function (e) {
                    e.preventDefault();
                    const newPass = $('[name="newPassword"]').val();
                    const confirmPass = $('[name="confirmPassword"]').val();

                    if (newPass !== confirmPass) {
                        alert('New passwords do not match');
                        return;
                    }

                    $.post('components/user.php', {
                        action: 'updatePassword',
                        currentPassword: $('[name="currentPassword"]').val(),
                        newPassword: newPass
                    }).done(function (response) {
                        if (response.success) {
                            alert('Password updated successfully');
                            $('#passwordForm')[0].reset();
                        } else {
                            alert(response.message);
                        }
                    });
                });
            });
        </script>
        <?php
        break;

    case 'personal':
        ?>
        <div class="space-y-6">
            <h3 class="text-lg font-medium">Personal Information</h3>
            <form id="personalForm" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Age</label>
                        <input type="number" name="age" value="<?php echo htmlspecialchars($userData['age'] ?? ''); ?>" min="13"
                            max="85" required class="mt-1 block w-full rounded border p-2">
                        <p class="mt-1 text-sm text-gray-500">Must be between 13 and 85 years old</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>"
                            class="mt-1 block w-full rounded border p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Country</label>
                        <input type="text" name="country" value="<?php echo htmlspecialchars($userData['country'] ?? ''); ?>"
                            class="mt-1 block w-full rounded border p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Website</label>
                        <input type="url" name="website" value="<?php echo htmlspecialchars($userData['website'] ?? ''); ?>"
                            class="mt-1 block w-full rounded border p-2">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium">Biography</label>
                    <textarea name="biography" class="mt-1 block w-full rounded border p-2 h-24"><?php
                    echo htmlspecialchars($userData['biography'] ?? '');
                    ?></textarea>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Save Changes
                </button>
            </form>
        </div>

        <script>
            $(document).ready(function () {
                $('#personalForm').submit(function (e) {
                    e.preventDefault();
                    const age = parseInt($('[name="age"]').val());

                    if (age < 13 || age > 85) {
                        alert('Age must be between 13 and 85');
                        return;
                    }

                    $.post('components/user.php', {
                        action: 'updatePersonal',
                        age: age,
                        city: $('[name="city"]').val(),
                        country: $('[name="country"]').val(),
                        website: $('[name="website"]').val(),
                        biography: $('[name="biography"]').val()
                    }).done(function (response) {
                        if (response.success) {
                            alert('Personal information updated');
                        } else {
                            alert(response.message);
                        }
                    });
                });
            });
        </script>
        <?php
        break;

    case 'social':
        $stmt = $db->prepare("SELECT social_links FROM social_links WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $links = json_decode($stmt->fetchColumn() ?: '[]', true);

        // Platform bilgileri (domain ve formatlarıyla)
        $availablePlatforms = [
            'youtube' => [
                'label' => 'YouTube',
                'baseUrl' => 'https://youtube.com/',
                'placeholder' => 'channel/username'
            ],
            'facebook' => [
                'label' => 'Facebook',
                'baseUrl' => 'https://facebook.com/',
                'placeholder' => 'username'
            ],
            'instagram' => [
                'label' => 'Instagram',
                'baseUrl' => 'https://instagram.com/',
                'placeholder' => 'username'
            ],
            'dribbble' => [
                'label' => 'Dribbble',
                'baseUrl' => 'https://dribbble.com/',
                'placeholder' => 'username'
            ],
            'discord' => [
                'label' => 'Discord',
                'baseUrl' => 'https://discord.com/users/',
                'placeholder' => 'userId'
            ],
            'behance' => [
                'label' => 'Behance',
                'baseUrl' => 'https://behance.net/',
                'placeholder' => 'username'
            ],
            'pinterest' => [
                'label' => 'Pinterest',
                'baseUrl' => 'https://pinterest.com/',
                'placeholder' => 'username'
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'baseUrl' => 'https://linkedin.com/in/',
                'placeholder' => 'username'
            ],
            'twitter' => [
                'label' => 'X',
                'baseUrl' => 'https://x.com/',
                'placeholder' => 'username'
            ],
            'github' => [
                'label' => 'GitHub',
                'baseUrl' => 'https://github.com/',
                'placeholder' => 'username'
            ],
            'wordpress' => [
                'label' => 'WordPress',
                'baseUrl' => 'https://profiles.wordpress.org/',
                'placeholder' => 'username'
            ],
            'twitch' => [
                'label' => 'Twitch',
                'baseUrl' => 'https://twitch.tv/',
                'placeholder' => 'username'
            ],
            'kick' => [
                'label' => 'Kick',
                'baseUrl' => 'https://kick.com/',
                'placeholder' => 'username'
            ],
            'medium' => [
                'label' => 'Medium',
                'baseUrl' => 'https://medium.com/@',
                'placeholder' => 'username'
            ],
            'etsy' => [
                'label' => 'Etsy',
                'baseUrl' => 'https://etsy.com/shop/',
                'placeholder' => 'shopname'
            ]
        ];
        ?>
        <div class="space-y-6">
            <h3 class="text-lg font-medium">Social Links</h3>
            <form id="socialForm" class="space-y-4">
                <div id="linksList" class="space-y-4">
                    <?php foreach ($links as $link) { ?>
                        <div class="flex gap-4 items-center social-entry">
                            <select name="platform" class="mt-1 block w-48 rounded border p-2" required>
                                <?php foreach ($availablePlatforms as $value => $platform) { ?>
                                    <option value="<?php echo $value; ?>" <?php echo $link['platform'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $platform['label']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="flex-1 relative">
                                <div
                                    class="absolute left-0 top-0 h-full flex items-center px-3 text-gray-500 border-r select-none bg-gray-50">
                                    <span class="whitespace-nowrap baseUrl"></span>
                                </div>
                                <input type="text" name="username" value="<?php
                                $platform = $link['platform'];
                                $fullUrl = $link['url'];
                                $baseUrl = $availablePlatforms[$platform]['baseUrl'];
                                echo htmlspecialchars(str_replace($baseUrl, '', $fullUrl));
                                ?>"
                                    class="mt-1 block w-full rounded border p-2 pl-[calc(1.5rem+var(--base-url-width))]" required>
                            </div>
                            <button type="button" class="removeLink bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                Remove
                            </button>
                        </div>
                    <?php } ?>
                </div>
                <div class="flex gap-4">
                    <button type="button" id="addLink" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Add Link
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Save Changes
                    </button>
                </div>
                <p class="text-sm text-gray-500">Add your social media profiles</p>
            </form>
        </div>

        <script>
            $(document).ready(function () {
                const platforms = <?php echo json_encode($availablePlatforms); ?>;

                function createPlatformOptions(selectedPlatform = '') {
                    return Object.entries(platforms)
                        .map(([value, platform]) => `
                                                                                                                                                                                                                                                                <option value="${value}" ${selectedPlatform === value ? 'selected' : ''}>
                                                                                                                                                                                                                                                                    ${platform.label}
                                                                                                                                                                                                                                                                </option>
                                                                                                                                                                                                                                                            `).join('');
                }

                // Profile Photo Handling
                $('[name="profilePhoto"]').change(function () {
                    const file = this.files[0];
                    if (file) {
                        // Dosya boyutu kontrolü
                        if (file.size > 5 * 1024 * 1024) {
                            showToast('File size must be less than 5MB', 'error');
                            this.value = '';
                            return;
                        }

                        // Dosya tipi kontrolü
                        if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                            showToast('Only JPG, JPEG & PNG files are allowed', 'error');
                            this.value = '';
                            return;
                        }

                        // Resmi önizle
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            const $previewImage = $('#previewImage');
                            const $progress = $('#uploadProgress');

                            // Önizleme resmini güncelle
                            $previewImage.attr('src', e.target.result);

                            // Upload progress göster
                            $progress.removeClass('hidden').html('<span>Uploading...</span>');

                            // Form verisi oluştur
                            const formData = new FormData();
                            formData.append('action', 'updateProfilePhoto');
                            formData.append('profilePhoto', file);

                            // Upload işlemi
                            $.ajax({
                                url: 'components/user.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function (response) {
                                    response = JSON.parse(response);
                                    if (response.success) {
                                        // Progress'i gizle
                                        $progress.addClass('hidden');
                                        // Toast mesajı göster
                                        showToast('Profile photo updated successfully');
                                    } else {
                                        // Hata durumunda eski resmi geri yükle
                                        $previewImage.attr('src', '<?php echo $profilePhotoUrl; ?>');
                                        $progress.addClass('hidden');
                                        showToast(response.message || 'Error updating profile photo', 'error');
                                    }
                                },
                                error: function () {
                                    // Hata durumunda eski resmi geri yükle
                                    $previewImage.attr('src', '<?php echo $profilePhotoUrl; ?>');
                                    $progress.addClass('hidden');
                                    showToast('Error updating profile photo', 'error');
                                },
                                xhr: function () {
                                    const xhr = new window.XMLHttpRequest();
                                    xhr.upload.addEventListener('progress', function (e) {
                                        if (e.lengthComputable) {
                                            const percent = Math.round((e.loaded / e.total) * 100);
                                            $progress.html(`<span>${percent}%</span>`);
                                        }
                                    }, false);
                                    return xhr;
                                }
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });

                function updateBaseUrl(select) {
                    const container = $(select).closest('.social-entry');
                    const platform = $(select).val();
                    const baseUrlSpan = container.find('.baseUrl');
                    const input = container.find('input[name="username"]');

                    baseUrlSpan.text(platforms[platform].baseUrl);
                    input.attr('placeholder', platforms[platform].placeholder);

                    // Dinamik padding hesapla
                    setTimeout(() => {
                        const width = baseUrlSpan.width();
                        input.css('paddingLeft', `calc(${width}px + 2rem)`);
                        container.find('.baseUrl').parent().css('width', `${width + 24}px`);
                    }, 0);
                }

                $('#addLink').click(function () {
                    const entry = $(`
                                                                                                       <div class="flex gap-4 items-center social-entry">
                                                                                                           <select name="platform" class="mt-1 block w-48 rounded border p-2" required>
                                                                                                               ${createPlatformOptions()}
                                                                                                           </select>
                                                                                                           <div class="flex-1 relative">
                                                                                                               <div class="absolute left-0 top-0 h-full flex items-center px-3 text-gray-500 border-r select-none bg-gray-50">
                                                                                                                   <span class="whitespace-nowrap baseUrl"></span>
                                                                                                               </div>
                                                                                                               <input type="text" name="username" class="mt-1 block w-full rounded border p-2" required>
                                                                                                           </div>
                                                                                                           <button type="button" class="removeLink bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                                                                                               Remove
                                                                                                           </button>
                                                                                                       </div>
                                                                                                   `);

                    $('#linksList').append(entry);
                    updateBaseUrl(entry.find('select'));
                });

                $(document).on('click', '.removeLink', function () {
                    $(this).closest('.social-entry').remove();
                });

                $(document).on('change', 'select[name="platform"]', function () {
                    updateBaseUrl(this);
                });

                // İlk yüklemede tüm mevcut alanları güncelle
                $('select[name="platform"]').each(function () {
                    updateBaseUrl(this);
                });

                $('#socialForm').submit(function (e) {
                    e.preventDefault();
                    const links = [];
                    const usedPlatforms = new Set();

                    let hasError = false;
                    $('.social-entry').each(function () {
                        const platform = $(this).find('select[name="platform"]').val();
                        const username = $(this).find('input[name="username"]').val().trim();

                        if (usedPlatforms.has(platform)) {
                            alert(`${platforms[platform].label} is already added`);
                            hasError = true;
                            return false;
                        }

                        if (username) {
                            usedPlatforms.add(platform);
                            links.push({
                                platform,
                                url: platforms[platform].baseUrl + username
                            });
                        }
                    });

                    if (hasError) return;

                    $.post('components/user.php', {
                        action: 'updateSocialLinks',
                        links: JSON.stringify(links)
                    }).done(function (response) {
                        if (response.success) {
                            alert('Social links updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });
            });
        </script>
        <?php
        break;

    case 'skills':
        // Skills verilerini çek
        $stmt = $db->prepare("SELECT skills FROM skills WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $skillsData = json_decode($stmt->fetchColumn() ?: '[]', true);
        ?>
        <div class="space-y-6">
            <h3 class="text-lg font-medium">Skills</h3>
            <form id="skillsForm" class="space-y-4">
                <div id="skillsList" class="space-y-4">
                    <?php
                    // Eğer skillsData boş değilse mevcut yetenekleri listele
                    if (!empty($skillsData)) {
                        foreach ($skillsData as $skill) { ?>
                            <div class="flex gap-4 items-center skill-entry">
                                <input type="text" name="skillName" value="<?php echo htmlspecialchars($skill['name']); ?>"
                                    class="mt-1 block w-full rounded border p-2" placeholder="Skill name" required>
                                <input type="number" name="skillLevel" value="<?php echo htmlspecialchars($skill['level']); ?>"
                                    class="mt-1 block w-24 rounded border p-2" placeholder="Level" min="1" max="5" required>
                                <button type="button" class="removeSkill bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                    Remove
                                </button>
                            </div>
                        <?php }
                    } ?>
                </div>
                <div class="flex gap-4">
                    <button type="button" id="addSkill" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Add Skill
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Save Changes
                    </button>
                </div>
                <p class="text-sm text-gray-500">Maximum 5 skills allowed. Level must be between 1 and 5.</p>
            </form>
        </div>

        <script>
            $(document).ready(function () {
                const maxSkills = 5;

                function updateAddButton() {
                    const skillCount = $('.skill-entry').length;
                    $('#addSkill').prop('disabled', skillCount >= maxSkills)
                        .toggleClass('opacity-50', skillCount >= maxSkills);
                }

                $('#addSkill').click(function () {
                    if ($('.skill-entry').length >= maxSkills) {
                        alert('Maximum 5 skills allowed');
                        return;
                    }

                    $('#skillsList').append(`
                                                                                                                <div class="flex gap-4 items-center skill-entry">
                                                                                                                    <input type="text" name="skillName" class="mt-1 block w-full rounded border p-2"
                                                                                                                        placeholder="Skill name" required>
                                                                                                                        <input type="number" name="skillLevel" class="mt-1 block w-24 rounded border p-2"
                                                                                                                            placeholder="Level" min="1" max="5" required>
                                                                                                                            <button type="button" class="removeSkill bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                                                                                                                Remove
                                                                                                                            </button>
                                                                                                                        </div>
                                                                                                                        `);
                    updateAddButton();
                });

                $(document).on('click', '.removeSkill', function () {
                    $(this).closest('.skill-entry').remove();
                    updateAddButton();
                });

                $('#skillsForm').submit(function (e) {
                    e.preventDefault();
                    const skills = [];

                    $('.skill-entry').each(function () {
                        const name = $(this).find('[name="skillName"]').val().trim();
                        const level = parseInt($(this).find('[name="skillLevel"]').val());

                        if (!name || !level) {
                            alert('All skill fields must be filled');
                            return false;
                        }

                        if (level < 1 || level > 5) {
                            alert('Skill level must be between 1 and 5');
                            return false;
                        }

                        skills.push({ name, level });
                    });

                    $.post('components/user.php', {
                        action: 'updateSkills',
                        skills: JSON.stringify(skills)
                    }).done(function (response) {
                        if (response.success) {
                            alert('Skills updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // İlk yüklemede buton durumunu güncelle
                updateAddButton();
            });
        </script>
        <script>
            $(document).ready(function () {
                // Profile Photo Handling
                $('[name="profilePhoto"]').change(function () {
                    const file = this.files[0];
                    if (file) {
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size must be less than 5MB');
                            this.value = '';
                            return;
                        }

                        const formData = new FormData();
                        formData.append('action', 'updateProfilePhoto');
                        formData.append('profilePhoto', file);

                        $.ajax({
                            url: 'components/user.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function (response) {
                                response = JSON.parse(response);
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.message);
                                }
                            }
                        });
                    }
                });

                // Two Factor Toggle
                $('#twoFactorToggle').change(function () {
                    const enabled = $(this).is(':checked');
                    const statusSpan = $(this).closest('.bg-gray-50').find('span');

                    $.post('components/user.php', {
                        action: 'toggleTwoFactor',
                        enabled: enabled
                    }).done(function (response) {
                        if (response.success) {
                            const statusText = enabled ? 'Enabled' : 'Disabled';
                            const statusColor = enabled ? 'text-green-600' : 'text-red-600';
                            statusSpan.removeClass('text-green-600 text-red-600')
                                .addClass(statusColor)
                                .text(statusText);
                        }
                    });
                });

                // Basic Information Form
                $('#basicInfoForm').submit(function (e) {
                    e.preventDefault();
                    $.post('components/user.php', {
                        action: 'updateBasicInfo',
                        username: $('[name="username"]').val(),
                        email: $('[name="email"]').val(),
                        fullName: $('[name="fullName"]').val()
                    }).done(function (response) {
                        if (response.success) {
                            alert('Basic information updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // Password Form
                $('#passwordForm').submit(function (e) {
                    e.preventDefault();
                    const newPass = $('[name="newPassword"]').val();
                    const confirmPass = $('[name="confirmPassword"]').val();

                    if (newPass !== confirmPass) {
                        alert('New passwords do not match');
                        return;
                    }

                    $.post('components/user.php', {
                        action: 'updatePassword',
                        currentPassword: $('[name="currentPassword"]').val(),
                        newPassword: newPass
                    }).done(function (response) {
                        if (response.success) {
                            alert('Password updated successfully');
                            $('#passwordForm')[0].reset();
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // Personal Information Form
                $('#personalForm').submit(function (e) {
                    e.preventDefault();
                    const age = parseInt($('[name="age"]').val());

                    if (age < 13 || age > 85) {
                        alert('Age must be between 13 and 85');
                        return;
                    }

                    $.post('components/user.php', {
                        action: 'updatePersonal',
                        age: age,
                        city: $('[name="city"]').val(),
                        country: $('[name="country"]').val(),
                        website: $('[name="website"]').val(),
                        biography: $('[name="biography"]').val()
                    }).done(function (response) {
                        if (response.success) {
                            alert('Personal information updated');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // Social Links Form
                const platforms = <?php echo json_encode($availablePlatforms); ?>;

                function createPlatformOptions(selectedPlatform = '') {
                    return Object.entries(platforms)
                        .map(([value, platform]) => `
                                                                                                                                    <option value="${value}" ${selectedPlatform === value ? 'selected' : ''}>
                                                                                                                                        ${platform.label}
                                                                                                                                    </option>
                                                                                                                                    `).join('');
                }

                function updateBaseUrl(select) {
                    const container = $(select).closest('.social-entry');
                    const platform = $(select).val();
                    const baseUrlSpan = container.find('.baseUrl');
                    const input = container.find('input[name="username"]');

                    baseUrlSpan.text(platforms[platform].baseUrl);
                    input.attr('placeholder', platforms[platform].placeholder);

                    setTimeout(() => {
                        const width = baseUrlSpan.width();
                        input.css('paddingLeft', `calc(${width}px + 2rem)`);
                        container.find('.baseUrl').parent().css('width', `${width + 24}px`);
                    }, 0);
                }

                $('#addLink').click(function () {
                    const entry = $(`
                                                                                                                                    <div class="flex gap-4 items-center social-entry">
                                                                                                                                        <select name="platform" class="mt-1 block w-48 rounded border p-2" required>
                                                                                                                                            ${createPlatformOptions()}
                                                                                                                                        </select>
                                                                                                                                        <div class="flex-1 relative">
                                                                                                                                            <div class="absolute left-0 top-0 h-full flex items-center px-3 text-gray-500 border-r select-none bg-gray-50">
                                                                                                                                                <span class="whitespace-nowrap baseUrl"></span>
                                                                                                                                            </div>
                                                                                                                                            <input type="text" name="username" class="mt-1 block w-full rounded border p-2" required>
                                                                                                                                        </div>
                                                                                                                                        <button type="button" class="removeLink bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                                                                                                                            Remove
                                                                                                                                        </button>
                                                                                                                                    </div>
                                                                                                                                    `);

                    $('#linksList').append(entry);
                    updateBaseUrl(entry.find('select'));
                });

                $(document).on('click', '.removeLink', function () {
                    $(this).closest('.social-entry').remove();
                });

                $(document).on('change', 'select[name="platform"]', function () {
                    updateBaseUrl(this);
                });

                $('select[name="platform"]').each(function () {
                    updateBaseUrl(this);
                });

                $('#socialForm').submit(function (e) {
                    e.preventDefault();
                    const links = [];
                    const usedPlatforms = new Set();

                    let hasError = false;
                    $('.social-entry').each(function () {
                        const platform = $(this).find('select[name="platform"]').val();
                        const username = $(this).find('input[name="username"]').val().trim();

                        if (usedPlatforms.has(platform)) {
                            alert(`${platforms[platform].label} is already added`);
                            hasError = true;
                            return false;
                        }

                        if (username) {
                            usedPlatforms.add(platform);
                            links.push({
                                platform,
                                url: platforms[platform].baseUrl + username
                            });
                        }
                    });

                    if (hasError) return;

                    $.post('components/user.php', {
                        action: 'updateSocialLinks',
                        links: JSON.stringify(links)
                    }).done(function (response) {
                        if (response.success) {
                            alert('Social links updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                // Skills Form
                const maxSkills = 5;

                function updateAddButton() {
                    const skillCount = $('.skill-entry').length;
                    $('#addSkill').prop('disabled', skillCount >= maxSkills)
                        .toggleClass('opacity-50', skillCount >= maxSkills);
                }

                $('#addSkill').click(function () {
                    if ($('.skill-entry').length >= maxSkills) {
                        alert('Maximum 5 skills allowed');
                        return;
                    }

                    $('#skillsList').append(`
                                                                                                                                    <div class="flex gap-4 items-center skill-entry">
                                                                                                                                        <input type="text" name="skillName" class="mt-1 block w-full rounded border p-2"
                                                                                                                                            placeholder="Skill name" required>
                                                                                                                                            <input type="number" name="skillLevel" class="mt-1 block w-24 rounded border p-2"
                                                                                                                                                placeholder="Level" min="1" max="5" required>
                                                                                                                                                <button type="button" class="removeSkill bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">
                                                                                                                                                    Remove
                                                                                                                                                </button>
                                                                                                                                            </div>
                                                                                                                                                                                                `);
                    updateAddButton();
                });

                $(document).on('click', '.removeSkill', function () {
                    $(this).closest('.skill-entry').remove();
                    updateAddButton();
                });

                $('#skillsForm').submit(function (e) {
                    e.preventDefault();
                    const skills = [];

                    $('.skill-entry').each(function () {
                        const name = $(this).find('[name="skillName"]').val().trim();
                        const level = parseInt($(this).find('[name="skillLevel"]').val());

                        if (!name || !level) {
                            alert('All skill fields must be filled');
                            return false;
                        }

                        if (level < 1 || level > 5) {
                            alert('Skill level must be between 1 and 5');
                            return false;
                        }

                        skills.push({ name, level });
                    });

                    $.post('components/user.php', {
                        action: 'updateSkills',
                        skills: JSON.stringify(skills)
                    }).done(function (response) {
                        if (response.success) {
                            alert('Skills updated successfully');
                        } else {
                            alert(response.message);
                        }
                    });
                });

                updateAddButton();

                // Login History
                $('.verifyLogin').click(function () {
                    const button = $(this);
                    const attemptId = button.data('id');

                    $.post('components/user.php', {
                        action: 'verifyLogin',
                        attemptId: attemptId
                    }).done(function (response) {
                        if (response.success) {
                            button.replaceWith('<span class="text-green-600">Verified</span>');
                        }
                    });
                });

                $('.deleteAttempt').click(function () {
                    const button = $(this);
                    const container = button.closest('.flex.justify-between.items-center');

                    if (confirm('Delete this login attempt?')) {
                        $.post('components/user.php', {
                            action: 'deleteLoginAttempt',
                            attemptId: button.data('id')
                        }).done(function (response) {
                            if (response.success) {
                                container.slideUp(300, function () {
                                    $(this).remove();
                                });
                            }
                        });
                    }
                });

                $('#clearAllAttempts').click(function () {
                    if (confirm('Are you sure you want to delete all login history? This cannot be undone.')) {
                        $.post('components/user.php', {
                            action: 'deleteAllLoginAttempts'
                        }).done(function (response) {
                            if (response.success) {
                                $('.flex.justify-between.items-center').slideUp(300, function () {
                                    $(this).remove();
                                });
                            }
                        });
                    }
                });

                // Account Deletion
                $('#initiateDelete').click(function () {
                    $('#deleteModal').removeClass('hidden');
                    $('#deletePassword').val('');
                    $('#finalConfirmation').addClass('hidden');
                    $('#verifyPassword').show();
                });

                $('#cancelDelete').click(function () {
                    $('#deleteModal').addClass('hidden');
                });

                $('#deleteForm').submit(function (e) {
                    e.preventDefault();

                    $.post('components/user.php', {
                        action: 'verifyPasswordForDeletion',
                        password: $('#deletePassword').val()
                    }).done(function (response) {
                        if (response.success) {
                            $('#finalConfirmation').removeClass('hidden');
                            $('#verifyPassword').hide();
                        } else {
                            alert('Incorrect password');
                        }
                    });
                });

                $('#confirmDelete').click(function () {
                    if (confirm('This is your last chance to cancel. Are you absolutely sure?')) {
                        $.post('components/user.php', {
                            action: 'deleteAccount'
                        }).done(function (response) {
                            if (response.success) {
                                window.location.href = 'views/auth/login.php';
                            } else {
                                alert('Failed to delete account: ' + response.error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
        break;

    case 'invitations':
        // Kullanıcının davet kodunu al
        $stmt = $db->prepare("SELECT specific_source FROM referral_sources WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $inviteCode = $stmt->fetchColumn();

        // Davetiye ile kayıt olduğu kodu ve sahibini al
        $stmt = $db->prepare("
                SELECT i.*, u.username as inviter_username 
                FROM invitations i 
                LEFT JOIN users u ON i.inviter_id = u.user_id 
                WHERE i.invited_user_id = ?
            ");
        $stmt->execute([$_SESSION['user_id']]);
        $receivedInvitation = $stmt->fetch(PDO::FETCH_ASSOC);

        // Bu kullanıcının davet ettiği kişileri al
        $stmt = $db->prepare("
                SELECT i.*, u.username as invited_username 
                FROM invitations i 
                LEFT JOIN users u ON i.invited_user_id = u.user_id 
                WHERE i.inviter_id = ?
            ");
        $stmt->execute([$_SESSION['user_id']]);
        $sentInvitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="space-y-6">
            <h3 class="text-lg font-medium">Invitations</h3>

            <!-- Your Invite Code -->
            <!-- Invitations sekmesindeki invite code bölümünü güncelleyelim -->
            <div class="mt-8 mb-8 border-b pb-6">
                <h3 class="text-lg font-medium mb-4">Invite Code</h3>
                <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                    <div>
                        <?php
                        // Get user's invite code
                        $stmt = $db->prepare("SELECT specific_source FROM referral_sources WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $inviteCode = $stmt->fetchColumn();

                        // Tam URL'i oluştur
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                        $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . "/views/auth/register.php?ref=" . $inviteCode;
                        ?>
                        <p class="font-medium">Your invite code:
                            <span class="font-mono bg-white px-3 py-1 rounded border ml-2" id="inviteCode">
                                <?php echo htmlspecialchars($inviteCode); ?>
                            </span>
                        </p>
                        <p class="text-sm text-gray-600 mt-2">Share this code to invite others. New users get 25 coins, you get
                            50 coins.</p>
                    </div>
                    <div class="flex gap-2">
                        <!-- Gizli input -->
                        <input type="text" id="inviteLinkInput" value="<?php echo htmlspecialchars($fullUrl); ?>"
                            style="position: absolute; left: -9999px;">
                        <button type="button" id="copyInviteLink"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center gap-2">
                            <span>Copy Invite Link</span>
                        </button>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function () {
                    $('#copyInviteLink').on('click', function () {
                        const inputElement = document.getElementById('inviteLinkInput');

                        try {
                            // Input'u görünür yap (ama ekranda değil)
                            inputElement.style.position = 'fixed';
                            inputElement.style.opacity = 0;

                            // Input'u seç ve kopyala
                            inputElement.select();
                            inputElement.setSelectionRange(0, 99999);
                            document.execCommand('copy');

                            // Input'u tekrar gizle
                            inputElement.style.position = 'absolute';
                            inputElement.style.left = '-9999px';

                            // Buton görünümünü güncelle
                            const button = $(this);
                            const originalContent = button.html();

                            button.text('Copied!')
                                .removeClass('bg-blue-500 hover:bg-blue-600')
                                .addClass('bg-green-500');

                            setTimeout(() => {
                                button.html(originalContent)
                                    .removeClass('bg-green-500')
                                    .addClass('bg-blue-500 hover:bg-blue-600');
                            }, 2000);

                            // Opsiyonel: Konsola URL'i yazdır (test için)
                            console.log('Copied URL:', inputElement.value);

                        } catch (err) {
                            console.error('Copy failed:', err);
                            alert('Failed to copy invite link. Please try again.');
                        }
                    });
                });
            </script>

            <!-- If user was invited -->
            <?php if ($receivedInvitation): ?>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium mb-2">You were invited by</h4>
                    <p class="text-sm">
                        <?php echo htmlspecialchars($receivedInvitation['inviter_username']); ?>
                        (Code: <?php echo htmlspecialchars($receivedInvitation['invitation_code']); ?>)
                    </p>
                </div>
            <?php endif; ?>

            <!-- Users invited by you -->
            <?php if ($sentInvitations): ?>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium mb-2">Users you've invited</h4>
                    <div class="space-y-2">
                        <?php foreach ($sentInvitations as $invite): ?>
                            <div class="bg-white p-3 rounded border">
                                <p class="font-medium"><?php echo htmlspecialchars($invite['invited_username']); ?></p>
                                <p class="text-sm text-gray-600">
                                    Joined: <?php echo date('F j, Y', strtotime($invite['used_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        break;

    case 'history':
        // Fetch login history
        $stmt = $db->prepare("
            SELECT * FROM login_attempts 
            WHERE user_id = ? 
            ORDER BY attempt_time DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium">Account History</h3>
                <button id="clearAllAttempts" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Clear All History
                </button>
            </div>
            <div class="space-y-4">
                <?php foreach ($loginHistory as $login) { ?>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded">
                        <div>
                            <p class="font-medium"><?php echo $login['status']; ?> login attempt</p>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($login['browser']); ?> on <?php echo htmlspecialchars($login['os']); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($login['city']); ?>, <?php echo htmlspecialchars($login['country']); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?php echo date('F j, Y g:i a', strtotime($login['attempt_time'])); ?>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <?php if (!$login['verified']) { ?>
                                <button class="verifyLogin bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
                                    data-id="<?php echo $login['attempt_id']; ?>">
                                    This was me
                                </button>
                            <?php } else { ?>
                                <span class="text-green-600">Verified</span>
                            <?php } ?>
                            <button class="deleteAttempt bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600"
                                data-id="<?php echo $login['attempt_id']; ?>">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <script>
            $(document).ready(function () {
                $('.verifyLogin').click(function () {
                    const button = $(this);
                    const attemptId = button.data('id');

                    $.post('components/user.php', {
                        action: 'verifyLogin',
                        attemptId: attemptId
                    }).done(function (response) {
                        if (response.success) {
                            button.replaceWith('<span class="text-green-600">Verified</span>');
                        }
                    });
                });

                $('.deleteAttempt').click(function () {
                    const button = $(this);
                    const container = button.closest('.flex.justify-between.items-center');

                    if (confirm('Delete this login attempt?')) {
                        $.post('components/user.php', {
                            action: 'deleteLoginAttempt',
                            attemptId: button.data('id')
                        }).done(function (response) {
                            if (response.success) {
                                container.slideUp(300, function () {
                                    $(this).remove();
                                });
                            }
                        });
                    }
                });

                $('#clearAllAttempts').click(function () {
                    if (confirm('Are you sure you want to delete all login history? This cannot be undone.')) {
                        $.post('components/user.php', {
                            action: 'deleteAllLoginAttempts'
                        }).done(function (response) {
                            if (response.success) {
                                $('.flex.justify-between.items-center').slideUp(300, function () {
                                    $(this).remove();
                                });
                            }
                        });
                    }
                });
            });
        </script>
        <?php
        break;

    case 'account':
        ?>
        <div class="space-y-6">
            <h3 class="text-lg font-medium">Account Details</h3>
            <p>Account created: <?php echo date('F j, Y', strtotime($userData['created_at'])); ?></p>

            <div class="border-t pt-6">
                <h4 class="text-lg font-medium text-red-600 mb-4">Danger Zone</h4>
                <button id="initiateDelete" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Delete Account
                </button>
            </div>

            <!-- Delete Account Modal -->
            <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-medium mb-4">Delete Account</h3>
                    <p class="text-gray-600 mb-4">Please enter your password to confirm account deletion:</p>

                    <form id="deleteForm" class="space-y-4">
                        <input type="password" id="deletePassword" class="block w-full rounded border p-2"
                            placeholder="Enter your password" required>

                        <div class="hidden" id="finalConfirmation">
                            <div class="bg-red-50 border border-red-200 rounded p-4 mb-4">
                                <p class="text-red-700">Warning: This action cannot be undone. Your account and all associated
                                    data will be permanently deleted.</p>
                            </div>
                            <button type="button" id="confirmDelete"
                                class="w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                                Yes, Delete My Account
                            </button>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" id="verifyPassword"
                                class="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Verify Password
                            </button>
                            <button type="button" id="cancelDelete"
                                class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function () {
                $('#initiateDelete').click(function () {
                    $('#deleteModal').removeClass('hidden');
                    $('#deletePassword').val('');
                    $('#finalConfirmation').addClass('hidden');
                    $('#verifyPassword').show();
                });

                $('#cancelDelete').click(function () {
                    $('#deleteModal').addClass('hidden');
                });

                $('#deleteForm').submit(function (e) {
                    e.preventDefault();

                    $.post('components/user.php', {
                        action: 'verifyPasswordForDeletion',
                        password: $('#deletePassword').val()
                    }).done(function (response) {
                        if (response.success) {
                            $('#finalConfirmation').removeClass('hidden');
                            $('#verifyPassword').hide();
                        } else {
                            alert('Incorrect password');
                        }
                    });
                });

                $('#confirmDelete').click(function () {
                    if (confirm('This is your last chance to cancel. Are you absolutely sure?')) {
                        $.post('components/user.php', {
                            action: 'deleteAccount'
                        }).done(function (response) {
                            if (response.success) {
                                window.location.href = 'views/auth/login.php';
                            } else {
                                alert('Failed to delete account: ' + response.error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
        break;
}
?>