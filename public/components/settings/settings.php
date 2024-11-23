<?php
// settings.php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/login.php');
    exit;
}

// Database connection
$dbConfig = require '../../../config/database.php';
$db = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['options']
);

// Kullanıcının mevcut ayarlarını çek
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userSettings = $stmt->fetch(PDO::FETCH_ASSOC);

// Desteklenen diller
$languages = [
    'tr' => 'Türkçe',
    'en' => 'English',
    'de' => 'Deutsch',
    'fr' => 'Français',
    'es' => 'Español',
    'ru' => 'Русский'
];

// Saat dilimleri listesi
$timezones = DateTimeZone::listIdentifiers();

// Bölgeler listesi
$regions = [
    'TR' => 'Türkiye',
    'US' => 'United States',
    'GB' => 'United Kingdom',
    'DE' => 'Germany',
    'FR' => 'France',
    'ES' => 'Spain',
    'IT' => 'Italy',
    'RU' => 'Russia'
];

// Eğer kullanıcının ayarları yoksa varsayılan değerleri kullan
if (!$userSettings) {
    $userSettings = [
        'language' => 'tr',
        'timezone' => 'Europe/Istanbul',
        'region' => 'TR',
        'date_format' => 'DD.MM.YYYY',
        'time_format' => '24h'
    ];

    // Varsayılan ayarları veritabanına ekle
    $stmt = $db->prepare("
        INSERT INTO user_settings 
            (user_id, language, timezone, region, date_format, time_format) 
        VALUES 
            (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $userSettings['language'],
        $userSettings['timezone'],
        $userSettings['region'],
        $userSettings['date_format'],
        $userSettings['time_format']
    ]);
}

// Get active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID - Settings</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto:wght@400;500;600&family=Open+Sans:wght@400;500;600&family=Montserrat:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap"
        rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <a href="../../index.php" class="flex items-center text-gray-600 hover:text-gray-900">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Homepage
                    </a>
                    <h1 class="text-xl font-semibold">Account Settings</h1>
                    <div></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex gap-6">
                <!-- Sidebar -->
                <div class="w-64 flex-shrink-0">
                    <div class="bg-white rounded-lg shadow">
                        <nav class="space-y-1">
                            <a href="?tab=profile"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'profile' ? 'bg-gray-50' : ''; ?>">
                                Profile Settings
                            </a>
                            <a href="?tab=security"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'security' ? 'bg-gray-50' : ''; ?>">
                                Security Center
                            </a>
                            <a href="?tab=notifications"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'notifications' ? 'bg-gray-50' : ''; ?>">
                                Notification Settings
                            </a>
                            <a href="?tab=payment"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'payment' ? 'bg-gray-50' : ''; ?>">
                                Payment & Financial Transactions
                            </a>
                            <a href="?tab=privacy"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'privacy' ? 'bg-gray-50' : ''; ?>">
                                Privacy Settings
                            </a>
                            <a href="?tab=account"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'account' ? 'bg-gray-50' : ''; ?>">
                                Account and Data
                            </a>
                            <a href="?tab=language"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'language' ? 'bg-gray-50' : ''; ?>">
                                Language and Region
                            </a>
                            <a href="?tab=appearance"
                                class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo $activeTab === 'appearance' ? 'bg-gray-50' : ''; ?>">
                                Appearance and Theme
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1">
                    <div class="bg-white rounded-lg shadow p-6">
                        <?php
                        switch ($activeTab) {
                            case 'profile':
                                // Get user's profile and cover photo URLs
                                $stmt = $db->prepare("
                                    SELECT profile_photo_url, cover_photo_url 
                                    FROM user_extended_details 
                                    WHERE user_id = ?
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                                $photos = $stmt->fetch(PDO::FETCH_ASSOC);

                                $profilePhotoUrl = $photos['profile_photo_url'] ?? 'undefined';
                                $coverPhotoUrl = $photos['cover_photo_url'] ?? 'undefined';

                                $profilePhotoFullUrl = $profilePhotoUrl !== 'undefined' ? '/public/' . $profilePhotoUrl : '/public/profile/avatars/default.jpg';
                                $coverPhotoFullUrl = $coverPhotoUrl !== 'undefined' ? '/public/' . $coverPhotoUrl : '/public/profile/covers/default.jpg';
                                ?>
                                <div class="space-y-6">
                                    <!-- Cover Photo Section -->
                                    <div class="mb-8">
                                        <h3 class="text-lg font-medium mb-4">Kapak Fotoğrafı</h3>
                                        <form id="coverPhotoForm" class="space-y-4">
                                            <div class="relative">
                                                <div class="aspect-[3/1] bg-gray-100 rounded-lg overflow-hidden">
                                                    <img src="<?php echo htmlspecialchars($coverPhotoFullUrl); ?>"
                                                        alt="Cover Photo" id="previewCoverImage"
                                                        class="w-full h-full object-cover">
                                                </div>
                                                <div class="absolute bottom-4 right-4 flex gap-2">
                                                    <input type="file" id="coverPhotoInput" name="coverPhoto"
                                                        accept="image/jpeg,image/jpg,image/png" class="hidden">
                                                    <label for="coverPhotoInput"
                                                        class="bg-white text-gray-700 px-4 py-2 rounded shadow hover:bg-gray-50 cursor-pointer">
                                                        Change Cover
                                                    </label>
                                                    <?php if ($coverPhotoUrl !== 'undefined'): ?>
                                                        <button type="button" id="removeCoverPhoto"
                                                            class="bg-red-500 text-white px-4 py-2 rounded shadow hover:bg-red-600">
                                                            Remove
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Profil ve Kapak Fotoğrafları -->
                                    <div>
                                        <h3 class="text-lg font-medium mb-4">Profil Fotoğrafı</h3>
                                        <form id="profilePhotoForm" class="space-y-4">
                                            <div class="flex items-center space-x-4">
                                                <div class="relative">
                                                    <img src="<?php echo htmlspecialchars($profilePhotoFullUrl); ?>"
                                                        alt="Profile Photo" id="previewProfileImage"
                                                        class="w-24 h-24 rounded-full object-cover border-2 border-gray-200">
                                                    <input type="file" id="profilePhotoInput" name="profilePhoto"
                                                        accept="image/jpeg,image/jpg,image/png" class="hidden">
                                                    <label for="profilePhotoInput"
                                                        class="absolute bottom-0 right-0 bg-white rounded-full p-1.5 shadow cursor-pointer">
                                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                    </label>
                                                </div>
                                                <div class="space-y-2">
                                                    <?php if ($profilePhotoUrl !== 'undefined'): ?>
                                                        <button type="button" id="removeProfilePhoto"
                                                            class="text-red-600 hover:text-red-700">
                                                            Remove current photo
                                                        </button>
                                                    <?php endif; ?>
                                                    <p class="text-sm text-gray-500">Maximum file size: 20MB. JPG, JPEG or
                                                        PNG only.</p>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Kişisel Bilgiler -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Kişisel Bilgiler</h3>
                                    <?php
                                    // Basic info verilerini çek
                                    $stmt = $db->prepare("SELECT basic_info FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $basicInfo = json_decode($stmt->fetchColumn(), true) ?? [];
                                    ?>
                                    <form id="basicInfoForm" class="space-y-6">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                                                <input type="text" name="full_name"
                                                    value="<?php echo htmlspecialchars($basicInfo['full_name'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Yaş</label>
                                                <input type="number" name="age" min="13" max="100"
                                                    value="<?php echo htmlspecialchars($basicInfo['age'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div class="col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Biyografi</label>
                                                <textarea name="biography" rows="3"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php
                                                    echo htmlspecialchars($basicInfo['biography'] ?? '');
                                                    ?></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Şehir</label>
                                                <input type="text" name="city"
                                                    value="<?php echo htmlspecialchars($basicInfo['location']['city'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Ülke</label>
                                                <input type="text" name="country"
                                                    value="<?php echo htmlspecialchars($basicInfo['location']['country'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Kişisel E-posta</label>
                                                <input type="email" name="email"
                                                    value="<?php echo htmlspecialchars($basicInfo['contact']['email'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Website</label>
                                                <input type="url" name="website"
                                                    value="<?php echo htmlspecialchars($basicInfo['contact']['website'] ?? ''); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <div class="col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Diller (virgülle
                                                    ayırın)</label>
                                                <input type="text" name="languages"
                                                    value="<?php echo htmlspecialchars(implode(', ', $basicInfo['languages'] ?? [])); ?>"
                                                    placeholder="Türkçe, İngilizce, Almanca"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Eğitim Bilgileri -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Eğitim Bilgileri</h3>
                                    <?php
                                    // Education history verilerini çek
                                    $stmt = $db->prepare("SELECT education_history FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $educationHistory = json_decode($stmt->fetchColumn(), true) ?? [];
                                    ?>
                                    <div id="educationList" class="space-y-6">
                                        <?php foreach ($educationHistory as $index => $education): ?>
                                            <div class="education-entry bg-gray-50 p-4 rounded-lg">
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Okul Seviyesi</label>
                                                        <select name="education[<?php echo $index; ?>][level]"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            <option value="high_school" <?php echo ($education['level'] ?? '') === 'high_school' ? 'selected' : ''; ?>>Lise</option>
                                                            <option value="university" <?php echo ($education['level'] ?? '') === 'university' ? 'selected' : ''; ?>>Üniversite</option>
                                                            <option value="second_university" <?php echo ($education['level'] ?? '') === 'second_university' ? 'selected' : ''; ?>>İkinci Üniversite
                                                            </option>
                                                            <option value="masters" <?php echo ($education['level'] ?? '') === 'masters' ? 'selected' : ''; ?>>Yüksek Lisans</option>
                                                            <option value="phd" <?php echo ($education['level'] ?? '') === 'phd' ? 'selected' : ''; ?>>Doktora</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Kurum Adı</label>
                                                        <input type="text" name="education[<?php echo $index; ?>][institution]"
                                                            value="<?php echo htmlspecialchars($education['institution'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Bölüm/Alan</label>
                                                        <input type="text" name="education[<?php echo $index; ?>][degree]"
                                                            value="<?php echo htmlspecialchars($education['degree'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Not
                                                            Ortalaması</label>
                                                        <input type="number" step="0.01" min="0" max="4"
                                                            name="education[<?php echo $index; ?>][gpa]"
                                                            value="<?php echo htmlspecialchars($education['gpa'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Başlangıç
                                                            Tarihi</label>
                                                        <input type="month" name="education[<?php echo $index; ?>][start_date]"
                                                            value="<?php echo htmlspecialchars($education['start_date'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                                                        <input type="month" name="education[<?php echo $index; ?>][end_date]"
                                                            value="<?php echo htmlspecialchars($education['end_date'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                </div>
                                                <button type="button"
                                                    class="remove-education mt-4 text-red-600 hover:text-red-800">Eğitimi
                                                    Kaldır</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" id="addEducation"
                                        class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                        Yeni Eğitim Ekle
                                    </button>
                                </div>

                                <!-- İş Deneyimi -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">İş Deneyimi</h3>
                                    <?php
                                    // Work experience verilerini çek
                                    $stmt = $db->prepare("SELECT work_experience FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $workExperience = json_decode($stmt->fetchColumn(), true) ?? [];
                                    ?>
                                    <div id="workExperienceList" class="space-y-6">
                                        <?php foreach ($workExperience as $index => $work): ?>
                                            <div class="work-entry bg-gray-50 p-4 rounded-lg">
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Şirket Adı</label>
                                                        <input type="text" name="work[<?php echo $index; ?>][company]"
                                                            value="<?php echo htmlspecialchars($work['company'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Pozisyon</label>
                                                        <input type="text" name="work[<?php echo $index; ?>][position]"
                                                            value="<?php echo htmlspecialchars($work['position'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Başlangıç
                                                            Tarihi</label>
                                                        <input type="month" name="work[<?php echo $index; ?>][start_date]"
                                                            value="<?php echo htmlspecialchars($work['start_date'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                                                        <input type="month" name="work[<?php echo $index; ?>][end_date]"
                                                            value="<?php echo htmlspecialchars($work['end_date'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <div class="mt-1">
                                                            <label class="inline-flex items-center">
                                                                <input type="checkbox" class="current-job-checkbox form-checkbox"
                                                                    <?php echo empty($work['end_date']) ? 'checked' : ''; ?>
                                                                    data-index="<?php echo $index; ?>"
                                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                <span class="ml-2 text-sm text-gray-600">Şu an burada
                                                                    çalışıyorum</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">İş Tanımı</label>
                                                        <textarea name="work[<?php echo $index; ?>][description]" rows="3"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php
                                                            echo htmlspecialchars($work['description'] ?? '');
                                                            ?></textarea>
                                                    </div>
                                                </div>
                                                <button type="button" class="remove-work mt-4 text-red-600 hover:text-red-800">İş
                                                    Deneyimini Kaldır</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" id="addWork"
                                        class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                        Yeni İş Deneyimi Ekle
                                    </button>
                                </div>

                                <!-- Yetenekler -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Yetenekler</h3>
                                    <?php
                                    // Skills matrix verilerini çek
                                    $stmt = $db->prepare("SELECT skills_matrix FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $skillsMatrix = json_decode($stmt->fetchColumn(), true) ?? [
                                        'technical_skills' => [],
                                        'soft_skills' => [],
                                        'tools' => []
                                    ];
                                    ?>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <!-- Technical Skills -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <h4 class="text-md font-medium">Teknik Yetenekler</h4>
                                                <span class="text-sm text-gray-500" id="technical-count">
                                                    <?php echo count($skillsMatrix['technical_skills']); ?>/5
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-3">
                                                Programlama dilleri, frameworkler ve teknolojiler
                                                <br>
                                                <span class="text-xs italic">Örn: PHP, Python, JavaScript, React</span>
                                            </p>
                                            <div class="space-y-2" id="technical-skills-list">
                                                <?php foreach ($skillsMatrix['technical_skills'] as $skill): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input type="text" value="<?php echo htmlspecialchars($skill); ?>"
                                                            class="technical-skill flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            readonly>
                                                        <button type="button" class="remove-skill text-red-600 hover:text-red-800">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($skillsMatrix['technical_skills']) < 5): ?>
                                                <button type="button"
                                                    class="add-skill-btn mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="technical" data-list="technical-skills-list"
                                                    data-count="technical-count">
                                                    + Teknik Yetenek Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Soft Skills -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <h4 class="text-md font-medium">Kişisel Yetenekler</h4>
                                                <span class="text-sm text-gray-500" id="soft-count">
                                                    <?php echo count($skillsMatrix['soft_skills']); ?>/5
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-3">
                                                İletişim ve kişisel gelişim becerileri
                                                <br>
                                                <span class="text-xs italic">Örn: Liderlik, İletişim, Problem Çözme</span>
                                            </p>
                                            <div class="space-y-2" id="soft-skills-list">
                                                <?php foreach ($skillsMatrix['soft_skills'] as $skill): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input type="text" value="<?php echo htmlspecialchars($skill); ?>"
                                                            class="soft-skill flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            readonly>
                                                        <button type="button" class="remove-skill text-red-600 hover:text-red-800">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($skillsMatrix['soft_skills']) < 5): ?>
                                                <button type="button"
                                                    class="add-skill-btn mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="soft" data-list="soft-skills-list" data-count="soft-count">
                                                    + Kişisel Yetenek Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Tools -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <h4 class="text-md font-medium">Araçlar</h4>
                                                <span class="text-sm text-gray-500" id="tools-count">
                                                    <?php echo count($skillsMatrix['tools']); ?>/5
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-3">
                                                Kullandığınız yazılım ve araçlar
                                                <br>
                                                <span class="text-xs italic">Örn: Git, Docker, AWS, MySQL</span>
                                            </p>
                                            <div class="space-y-2" id="tools-list">
                                                <?php foreach ($skillsMatrix['tools'] as $skill): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input type="text" value="<?php echo htmlspecialchars($skill); ?>"
                                                            class="tool-skill flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            readonly>
                                                        <button type="button" class="remove-skill text-red-600 hover:text-red-800">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($skillsMatrix['tools']) < 5): ?>
                                                <button type="button"
                                                    class="add-skill-btn mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="tools" data-list="tools-list" data-count="tools-count">
                                                    + Araç Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Yetenek ekleme modalları -->
                                <div id="addSkillModal"
                                    class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden flex items-center justify-center">
                                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                                        <h3 class="text-lg font-medium mb-4" id="modal-title">Yetenek Ekle</h3>

                                        <div class="space-y-4">
                                            <input type="text" id="skillInput"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                placeholder="Yetenek adını giriniz">

                                            <div class="text-sm text-gray-600" id="categoryDescription"></div>

                                            <div class="flex justify-end space-x-3">
                                                <button type="button" id="cancelSkill"
                                                    class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                                    İptal
                                                </button>
                                                <button type="button" id="saveSkill"
                                                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                                    Ekle
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Portföy Projeleri -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Portföy Projeleri</h3>
                                    <?php
                                    $stmt = $db->prepare("SELECT portfolio_showcase FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $portfolioItems = json_decode($stmt->fetchColumn(), true) ?? [];
                                    ?>

                                    <div id="portfolioList" class="space-y-4">
                                        <?php foreach ($portfolioItems as $index => $item): ?>
                                            <div class="portfolio-entry bg-gray-50 p-4 rounded-lg">
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Proje Başlığı</label>
                                                        <input type="text" name="portfolio[<?php echo $index; ?>][title]"
                                                            value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Proje
                                                            Açıklaması</label>
                                                        <textarea name="portfolio[<?php echo $index; ?>][description]" rows="2"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php
                                                            echo htmlspecialchars($item['description'] ?? '');
                                                            ?></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Proje URL</label>
                                                        <input type="url" name="portfolio[<?php echo $index; ?>][url]"
                                                            value="<?php echo htmlspecialchars($item['url'] ?? ''); ?>"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    </div>
                                                </div>
                                                <button type="button"
                                                    class="remove-portfolio mt-4 text-red-600 hover:text-red-800">Projeyi
                                                    Kaldır</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($portfolioItems) < 3): ?>
                                        <button type="button" id="addPortfolio"
                                            class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                            Yeni Proje Ekle (<?php echo count($portfolioItems); ?>/3)
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Profesyonel Profil -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Profesyonel Profil</h3>
                                    <?php
                                    $stmt = $db->prepare("SELECT professional_profile FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $profProfile = json_decode($stmt->fetchColumn(), true) ?? [
                                        'summary' => '',
                                        'expertise_areas' => [],
                                        'certifications' => []
                                    ];
                                    ?>

                                    <div class="space-y-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Profesyonel Özet</label>
                                            <textarea id="profSummary" rows="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php
                                                echo htmlspecialchars($profProfile['summary'] ?? '');
                                                ?></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Uzmanlık Alanları (Maksimum
                                                2)</label>
                                            <div id="expertiseList" class="mt-2 space-y-2">
                                                <?php foreach (($profProfile['expertise_areas'] ?? []) as $expertise): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input type="text" value="<?php echo htmlspecialchars($expertise); ?>"
                                                            class="expertise-area flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            readonly>
                                                        <button type="button"
                                                            class="remove-expertise text-red-600 hover:text-red-800">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($profProfile['expertise_areas'] ?? []) < 2): ?>
                                                <button type="button" id="addExpertise"
                                                    class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                                    + Uzmanlık Alanı Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Sertifikalar (Maksimum
                                                2)</label>
                                            <div id="certificationList" class="mt-2 space-y-2">
                                                <?php foreach (($profProfile['certifications'] ?? []) as $cert): ?>
                                                    <div class="flex items-center gap-2">
                                                        <input type="text" value="<?php echo htmlspecialchars($cert); ?>"
                                                            class="certification flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            readonly>
                                                        <button type="button"
                                                            class="remove-certification text-red-600 hover:text-red-800">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($profProfile['certifications'] ?? []) < 2): ?>
                                                <button type="button" id="addCertification"
                                                    class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                                    + Sertifika Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sosyal Ağlar ve Bağlantılar -->
                                <div class="mt-8 border-t pt-8">
                                    <h3 class="text-lg font-medium mb-4">Sosyal Ağlar ve Bağlantılar</h3>
                                    <?php
                                    $stmt = $db->prepare("SELECT network_links FROM user_extended_details WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $networkLinks = json_decode($stmt->fetchColumn(), true) ?? [
                                        'professional' => [],
                                        'social' => [],
                                        'portfolio_sites' => []
                                    ];

                                    // Predefined platform configurations
                                    $platformConfigs = [
                                        'professional' => [
                                            'linkedin' => ['base' => 'https://linkedin.com/in/', 'prefix' => ''],
                                            'github' => ['base' => 'https://github.com/', 'prefix' => ''],
                                            'stackoverflow' => ['base' => 'https://stackoverflow.com/users/', 'prefix' => ''],
                                            'medium' => ['base' => 'https://medium.com/@', 'prefix' => '']
                                        ],
                                        'social' => [
                                            'twitter' => ['base' => 'https://twitter.com/', 'prefix' => ''],
                                            'instagram' => ['base' => 'https://instagram.com/', 'prefix' => ''],
                                            'youtube' => ['base' => 'https://youtube.com/', 'prefix' => '@'],
                                            'facebook' => ['base' => 'https://facebook.com/', 'prefix' => '']
                                        ],
                                        'portfolio_sites' => []  // Custom URLs allowed
                                    ];
                                    ?>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <!-- Professional Networks -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h4 class="text-md font-medium mb-2">Profesyonel Ağlar</h4>
                                            <div id="professionalLinksList" class="space-y-3">
                                                <?php foreach ($networkLinks['professional'] as $platform => $username): ?>
                                                    <div class="network-link-entry">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700"><?php echo ucfirst($platform); ?></label>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <div
                                                                class="flex-1 flex items-center bg-white rounded-md border border-gray-300">
                                                                <span
                                                                    class="px-3 py-2 text-gray-500 bg-gray-50 border-r border-gray-300 rounded-l-md">
                                                                    <?php echo $platformConfigs['professional'][$platform]['base']; ?>
                                                                </span>
                                                                <input type="text"
                                                                    value="<?php echo htmlspecialchars($username); ?>"
                                                                    class="flex-1 p-2 block w-full rounded-r-md border-0 focus:ring-2 focus:ring-blue-500"
                                                                    data-platform="<?php echo $platform; ?>"
                                                                    data-category="professional">
                                                            </div>
                                                            <button type="button"
                                                                class="remove-network-link text-red-600 hover:text-red-800">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($networkLinks['professional']) < 10): ?>
                                                <button type="button"
                                                    class="add-network-link mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="professional">
                                                    + Profesyonel Ağ Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Social Networks -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h4 class="text-md font-medium mb-2">Sosyal Medya</h4>
                                            <div id="socialLinksList" class="space-y-3">
                                                <?php foreach ($networkLinks['social'] as $platform => $username): ?>
                                                    <div class="network-link-entry">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700"><?php echo ucfirst($platform); ?></label>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <div
                                                                class="flex-1 flex items-center bg-white rounded-md border border-gray-300">
                                                                <span
                                                                    class="px-3 py-2 text-gray-500 bg-gray-50 border-r border-gray-300 rounded-l-md">
                                                                    <?php echo $platformConfigs['social'][$platform]['base']; ?>
                                                                </span>
                                                                <input type="text"
                                                                    value="<?php echo htmlspecialchars($username); ?>"
                                                                    class="flex-1 p-2 block w-full rounded-r-md border-0 focus:ring-2 focus:ring-blue-500"
                                                                    data-platform="<?php echo $platform; ?>" data-category="social">
                                                            </div>
                                                            <button type="button"
                                                                class="remove-network-link text-red-600 hover:text-red-800">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($networkLinks['social']) < 10): ?>
                                                <button type="button"
                                                    class="add-network-link mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="social">
                                                    + Sosyal Medya Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Portfolio Sites -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h4 class="text-md font-medium mb-2">Diğer Siteler</h4>
                                            <div id="portfolioSitesList" class="space-y-3">
                                                <?php foreach ($networkLinks['portfolio_sites'] as $name => $url): ?>
                                                    <div class="network-link-entry">
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <input type="text" placeholder="Site Adı"
                                                                value="<?php echo htmlspecialchars($name); ?>"
                                                                class="flex-1 p-2 rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500"
                                                                data-category="portfolio_sites">
                                                            <input type="url" placeholder="URL"
                                                                value="<?php echo htmlspecialchars($url); ?>"
                                                                class="flex-1 p-2 rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500">
                                                            <button type="button"
                                                                class="remove-network-link text-red-600 hover:text-red-800">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($networkLinks['portfolio_sites']) < 10): ?>
                                                <button type="button"
                                                    class="add-network-link mt-3 text-sm text-blue-600 hover:text-blue-800"
                                                    data-category="portfolio_sites">
                                                    + Site Ekle
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Başarılar ve Ödüller -->
                                    <div class="mt-8 border-t pt-8">
                                        <h3 class="text-lg font-medium mb-4">Başarılar ve Ödüller</h3>
                                        <?php
                                        $stmt = $db->prepare("SELECT achievements FROM user_extended_details WHERE user_id = ?");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $achievements = json_decode($stmt->fetchColumn(), true) ?? [];
                                        ?>

                                        <div id="achievementsList" class="space-y-4">
                                            <?php foreach ($achievements as $index => $achievement): ?>
                                                <div class="achievement-entry bg-gray-50 p-4 rounded-lg">
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700">Başlık</label>
                                                            <input type="text" name="achievement[<?php echo $index; ?>][title]"
                                                                value="<?php echo htmlspecialchars($achievement['title'] ?? ''); ?>"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        </div>
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700">Veren
                                                                Kurum</label>
                                                            <input type="text" name="achievement[<?php echo $index; ?>][issuer]"
                                                                value="<?php echo htmlspecialchars($achievement['issuer'] ?? ''); ?>"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        </div>
                                                        <div>
                                                            <label class="block text-sm font-medium text-gray-700">Tarih</label>
                                                            <input type="month" name="achievement[<?php echo $index; ?>][date]"
                                                                value="<?php echo htmlspecialchars($achievement['date'] ?? ''); ?>"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        </div>
                                                        <div class="col-span-2">
                                                            <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                                                            <textarea name="achievement[<?php echo $index; ?>][description]"
                                                                rows="2"
                                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php
                                                                echo htmlspecialchars($achievement['description'] ?? '');
                                                                ?></textarea>
                                                        </div>
                                                    </div>
                                                    <button type="button"
                                                        class="remove-achievement mt-4 text-red-600 hover:text-red-800">Başarıyı
                                                        Kaldır</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($achievements) < 3): ?>
                                            <button type="button" id="addAchievement"
                                                class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                                Yeni Başarı Ekle (<?php echo count($achievements); ?>/3)
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Platform Selection Modal -->
                                    <div id="platformModal"
                                        class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden flex items-center justify-center">
                                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                                            <h3 class="text-lg font-medium mb-4">Platform Seçin</h3>
                                            <div class="space-y-2" id="platformList">
                                                <!-- Platforms will be dynamically added here -->
                                            </div>
                                            <div class="mt-4 flex justify-end space-x-3">
                                                <button type="button" id="cancelPlatform"
                                                    class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                                    İptal
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="saveChangesBar"
                                        class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg transform translate-y-full transition-transform duration-300 z-50">
                                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                                            <div class="flex items-center justify-between">
                                                <div class="text-sm text-gray-600">
                                                    Kaydedilmemiş değişiklikleriniz var
                                                </div>
                                                <button type="button" id="saveAllChanges"
                                                    class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                                                    Tüm Değişiklikleri Kaydet
                                                </button>
                                            </div>
                                        </div>
                                    </div>



                                    <script>
                                        $(document).ready(function () {
                                            let formChanged = false;
                                            let currentNetworkCategory = '';
                                            let currentCategory = '';
                                            let currentListId = '';
                                            let currentCountId = '';
                                            let platformListHtml = '';

                                            $('#saveChangesBar').addClass('translate-y-full');

                                            $('input, textarea, select').on('change', function () {
                                                formChanged = true;
                                                showSaveBar();
                                                // Değişiklik olduğunda Save butonunu aktif et
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            function showSaveBar() {
                                                $('#saveChangesBar').removeClass('translate-y-full');
                                            }

                                            function hideSaveBar() {
                                                $('#saveChangesBar').addClass('translate-y-full');
                                            }

                                            // Profile Photo Handling
                                            $('#profilePhotoInput').change(function () {
                                                handleImageUpload(this, '#previewProfileImage', 'updateProfilePhoto', '/public/profile/avatars/default.jpg');
                                            });

                                            // Cover Photo Handling
                                            $('#coverPhotoInput').change(function () {
                                                handleImageUpload(this, '#previewCoverImage', 'updateCoverPhoto', '/public/profile/covers/default.jpg');
                                            });

                                            // Remove Profile Photo
                                            $('#removeProfilePhoto').click(function () {
                                                if (confirm('Are you sure you want to remove your profile photo?')) {
                                                    $.post('update_profile.php', {
                                                        action: 'removeProfilePhoto'
                                                    }).done(function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            $('#previewProfileImage').attr('src', '/public/profile/avatars/default.jpg');
                                                            $('#removeProfilePhoto').hide();
                                                        } else {
                                                            alert(response.message || 'Error removing profile photo');
                                                        }
                                                    });
                                                }
                                            });

                                            // Remove Cover Photo
                                            $('#removeCoverPhoto').click(function () {
                                                if (confirm('Are you sure you want to remove your cover photo?')) {
                                                    $.post('update_profile.php', {
                                                        action: 'removeCoverPhoto'
                                                    }).done(function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            $('#previewCoverImage').attr('src', '/public/profile/covers/default.jpg');
                                                            $(this).hide();
                                                        } else {
                                                            alert(response.message || 'Error removing cover photo');
                                                        }
                                                    });
                                                }
                                            });

                                            function handleImageUpload(input, previewSelector, action, defaultImage) {
                                                const file = input.files[0];
                                                if (file) {
                                                    if (file.size > 20 * 1024 * 1024) {  // 20MB
                                                        alert('File size must be less than 20MB');
                                                        input.value = '';
                                                        return;
                                                    }

                                                    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                                                        alert('Only JPG, JPEG & PNG files are allowed');
                                                        input.value = '';
                                                        return;
                                                    }

                                                    const reader = new FileReader();
                                                    reader.onload = function (e) {
                                                        $(previewSelector).attr('src', e.target.result);
                                                    };
                                                    reader.readAsDataURL(file);

                                                    const formData = new FormData();
                                                    formData.append('action', action);
                                                    formData.append(action === 'updateProfilePhoto' ? 'profilePhoto' : 'coverPhoto', file);

                                                    $.ajax({
                                                        url: 'update_profile.php',
                                                        type: 'POST',
                                                        data: formData,
                                                        processData: false,
                                                        contentType: false,
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                $(previewSelector).attr('src', '/public/' + response.path);
                                                                const removeButton = action === 'updateProfilePhoto' ?
                                                                    '#removeProfilePhoto' : '#removeCoverPhoto';
                                                                $(removeButton).show();
                                                            } else {
                                                                $(previewSelector).attr('src', defaultImage);
                                                                alert(response.message || 'Error updating photo');
                                                            }
                                                        },
                                                        error: function () {
                                                            $(previewSelector).attr('src', defaultImage);
                                                            alert('Error updating photo');
                                                        }
                                                    });
                                                }
                                            }

                                            // Eğitim ekleme
                                            $('#addEducation').click(function () {
                                                const index = $('.education-entry').length;
                                                const template = `
                                                                                                                                                                                                                                                                                                    <div class="education-entry bg-gray-50 p-4 rounded-lg">
                                                                                                                                                                                                                                                                                                        <div class="grid grid-cols-2 gap-4">
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Okul Seviyesi</label>
                                                                                                                                                                                                                                                                                                                <select name="education[${index}][level]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                    <option value="high_school">Lise</option>
                                                                                                                                                                                                                                                                                                                    <option value="university">Üniversite</option>
                                                                                                                                                                                                                                                                                                                    <option value="second_university">İkinci Üniversite</option>
                                                                                                                                                                                                                                                                                                                    <option value="masters">Yüksek Lisans</option>
                                                                                                                                                                                                                                                                                                                    <option value="phd">Doktora</option>
                                                                                                                                                                                                                                                                                                                </select>
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Kurum Adı</label>
                                                                                                                                                                                                                                                                                                                <input type="text" name="education[${index}][institution]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Bölüm/Alan</label>
                                                                                                                                                                                                                                                                                                                <input type="text" name="education[${index}][degree]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Not Ortalaması</label>
                                                                                                                                                                                                                                                                                                                <input type="number" step="0.01" min="0" max="4" name="education[${index}][gpa]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                                                                                                                                                                                                                                                                                                                <input type="month" name="education[${index}][start_date]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <div>
                                                                                                                                                                                                                                                                                                                <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                                                                                                                                                                                                                                                                                                                <input type="month" name="education[${index}][end_date]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                                                                                                        <button type="button" class="remove-education mt-4 text-red-600 hover:text-red-800">Eğitimi Kaldır</button>
                                                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                                                `;
                                                $('#educationList').append(template);
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            // Eğitim kaldırma
                                            $(document).on('click', '.remove-education', function () {
                                                $(this).closest('.education-entry').remove();
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            $('#addWork').click(function () {
                                                const index = $('.work-entry').length;
                                                const template = `
                                                                                                                                                                                                                                                                                                        <div class="work-entry bg-gray-50 p-4 rounded-lg">
                                                                                                                                                                                                                                                                                                            <div class="grid grid-cols-2 gap-4">
                                                                                                                                                                                                                                                                                                                <div>
                                                                                                                                                                                                                                                                                                                    <label class="block text-sm font-medium text-gray-700">Şirket Adı</label>
                                                                                                                                                                                                                                                                                                                    <input type="text" name="work[${index}][company]"
                                                                                                                                                                                                                                                                                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                                                <div>
                                                                                                                                                                                                                                                                                                                    <label class="block text-sm font-medium text-gray-700">Pozisyon</label>
                                                                                                                                                                                                                                                                                                                    <input type="text" name="work[${index}][position]"
                                                                                                                                                                                                                                                                                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                                                <div>
                                                                                                                                                                                                                                                                                                                    <label class="block text-sm font-medium text-gray-700">Başlangıç Tarihi</label>
                                                                                                                                                                                                                                                                                                                    <input type="month" name="work[${index}][start_date]"
                                                                                                                                                                                                                                                                                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                                                <div>
                                                                                                                                                                                                                                                                                                                    <label class="block text-sm font-medium text-gray-700">Bitiş Tarihi</label>
                                                                                                                                                                                                                                                                                                                    <input type="month" name="work[${index}][end_date]"
                                                                                                                                                                                                                                                                                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                    <div class="mt-1">
                                                                                                                                                                                                                                                                                                                        <label class="inline-flex items-center">
                                                                                                                                                                                                                                                                                                                            <input type="checkbox" class="current-job-checkbox form-checkbox"
                                                                                                                                                                                                                                                                                                                                data-index="${index}"
                                                                                                                                                                                                                                                                                                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                                                                                                                                                            <span class="ml-2 text-sm text-gray-600">Şu an burada çalışıyorum</span>
                                                                                                                                                                                                                                                                                                                        </label>
                                                                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                                                <div class="col-span-2">
                                                                                                                                                                                                                                                                                                                    <label class="block text-sm font-medium text-gray-700">İş Tanımı</label>
                                                                                                                                                                                                                                                                                                                    <textarea name="work[${index}][description]" rows="3"
                                                                                                                                                                                                                                                                                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                                                                                                            <button type="button" class="remove-work mt-4 text-red-600 hover:text-red-800">İş Deneyimini Kaldır</button>
                                                                                                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                                                                                                    `;
                                                $('#workExperienceList').append(template);
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            // Remove work experience entry
                                            $(document).on('click', '.remove-work', function () {
                                                $(this).closest('.work-entry').remove();
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            // Handle current job checkbox
                                            $(document).on('change', '.current-job-checkbox', function () {
                                                const index = $(this).data('index');
                                                const endDateInput = $(`input[name="work[${index}][end_date]"]`);

                                                if (this.checked) {
                                                    endDateInput.val('').prop('disabled', true);
                                                } else {
                                                    endDateInput.prop('disabled', false);
                                                }
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });



                                            const categoryDescriptions = {
                                                technical: 'Programlama dilleri, frameworkler ve teknik yeteneklerinizi ekleyin.<br>Örn: PHP, Python, JavaScript, React, Node.js',
                                                soft: 'Kişisel ve profesyonel gelişim yeteneklerinizi ekleyin.<br>Örn: Liderlik, İletişim, Problem Çözme, Takım Çalışması',
                                                tools: 'Kullandığınız yazılım araçlarını ve platformları ekleyin.<br>Örn: Git, Docker, AWS, MySQL, Photoshop'
                                            };

                                            // Open modal when add button is clicked
                                            $('.add-skill-btn').click(function () {
                                                currentCategory = $(this).data('category');
                                                currentListId = $(this).data('list');
                                                currentCountId = $(this).data('count');

                                                $('#categoryDescription').html(categoryDescriptions[currentCategory]);
                                                $('#addSkillModal').removeClass('hidden');
                                                $('#skillInput').val('').focus();
                                            });

                                            // Close modal
                                            $('#cancelSkill').click(function () {
                                                $('#addSkillModal').addClass('hidden');
                                            });

                                            // Save skill
                                            $('#saveSkill').click(function () {
                                                const skill = $('#skillInput').val().trim();
                                                if (!skill) return;

                                                const skillHtml = `
                                                                                                                                                                                                                                    <div class="flex items-center gap-2">
                                                                                                                                                                                                                                        <input type="text" value="${skill}" 
                                                                                                                                                                                                                                            class="${currentCategory}-skill flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                                                                                                                                                                                            readonly>
                                                                                                                                                                                                                                        <button type="button" class="remove-skill text-red-600 hover:text-red-800">
                                                                                                                                                                                                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                                                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                                                                                                                                                                                            </svg>
                                                                                                                                                                                                                                        </button>
                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                `;

                                                $(`#${currentListId}`).append(skillHtml);

                                                // Update count
                                                const currentCount = $(`#${currentListId} .flex`).length;
                                                $(`#${currentCountId}`).text(`${currentCount}/5`);

                                                // Hide add button if max reached
                                                if (currentCount >= 5) {
                                                    $(`[data-list="${currentListId}"]`).hide();
                                                }

                                                $('#addSkillModal').addClass('hidden');
                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            // Remove skill
                                            $(document).on('click', '.remove-skill', function () {
                                                const skillContainer = $(this).closest('.bg-gray-50');
                                                const addButton = skillContainer.find('.add-skill-btn');
                                                const countElement = skillContainer.find('[id$="-count"]');

                                                $(this).closest('.flex').remove();

                                                // Update count
                                                const currentCount = skillContainer.find('.flex').length;
                                                countElement.text(`${currentCount}/5`);

                                                // Show add button if below max
                                                if (currentCount < 5) {
                                                    addButton.show();
                                                }

                                                formChanged = true;
                                                $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                            });

                                            $('#addAchievement').click(function () {
                                                const index = $('.achievement-entry').length;
                                                if (index >= 3) return;

                                                const template = `
                                                                                                                                                                            <div class="achievement-entry bg-gray-50 p-4 rounded-lg">
                                                                                                                                                                                <div class="grid grid-cols-2 gap-4">
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Başlık</label>
                                                                                                                                                                                        <input type="text" name="achievement[${index}][title]"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                    </div>
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Veren Kurum</label>
                                                                                                                                                                                        <input type="text" name="achievement[${index}][issuer]"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                    </div>
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Tarih</label>
                                                                                                                                                                                        <input type="month" name="achievement[${index}][date]"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                    </div>
                                                                                                                                                                                    <div class="col-span-2">
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Açıklama</label>
                                                                                                                                                                                        <textarea name="achievement[${index}][description]" rows="2"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                                                                                                                                                                    </div>
                                                                                                                                                                                </div>
                                                                                                                                                                                <button type="button" class="remove-achievement mt-4 text-red-600 hover:text-red-800">Başarıyı Kaldır</button>
                                                                                                                                                                            </div>
                                                                                                                                                                        `;

                                                $('#achievementsList').append(template);
                                                updateAchievementCount();
                                            });

                                            $('.add-network-link').click(function () {
                                                currentNetworkCategory = $(this).data('category');
                                                platformListHtml = ''; // Her tıklamada listeyi sıfırla

                                                if (currentNetworkCategory === 'portfolio_sites') {
                                                    addPortfolioSite();
                                                    return;
                                                }

                                                const platforms = platformConfigs[currentNetworkCategory];
                                                for (const platform in platforms) {
                                                    if (!$(`.network-link-entry[data-platform="${platform}"]`).length) {
                                                        platformListHtml += `
                                                                                                                                <button type="button" 
                                                                                                                                    class="w-full text-left px-4 py-2 hover:bg-gray-100 rounded-md platform-option"
                                                                                                                                    data-platform="${platform}">
                                                                                                                                    ${platform.charAt(0).toUpperCase() + platform.slice(1)}
                                                                                                                                </button>
                                                                                                                            `;
                                                    }
                                                }

                                                $('#platformList').html(platformListHtml);
                                                $('#platformModal').removeClass('hidden');
                                            });

                                            $('#addCertification').click(function () {
                                                if ($('#certificationList .certification').length >= 2) return;

                                                const template = `
                                                                                                                                                                            <div class="flex items-center gap-2">
                                                                                                                                                                                <input type="text" class="certification flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                <button type="button" class="remove-certification text-red-600 hover:text-red-800">
                                                                                                                                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                                                                                                                                    </svg>
                                                                                                                                                                                </button>
                                                                                                                                                                            </div>
                                                                                                                                                                        `;

                                                $('#certificationList').append(template);
                                                updateCertificationCount();
                                            });

                                            $('#addExpertise').click(function () {
                                                if ($('#expertiseList .expertise-area').length >= 2) return;

                                                const template = `
                                                                                                                                                                            <div class="flex items-center gap-2">
                                                                                                                                                                                <input type="text" class="expertise-area flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                <button type="button" class="remove-expertise text-red-600 hover:text-red-800">
                                                                                                                                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                                                                                                                                    </svg>
                                                                                                                                                                                </button>
                                                                                                                                                                            </div>
                                                                                                                                                                        `;

                                                $('#expertiseList').append(template);
                                                updateExpertiseCount();
                                            });

                                            $('#addPortfolio').click(function () {
                                                const index = $('.portfolio-entry').length;
                                                if (index >= 3) return;

                                                const template = `
                                                                                                                                                                            <div class="portfolio-entry bg-gray-50 p-4 rounded-lg">
                                                                                                                                                                                <div class="grid grid-cols-1 gap-4">
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Proje Başlığı</label>
                                                                                                                                                                                        <input type="text" name="portfolio[${index}][title]"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                    </div>
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Proje Açıklaması</label>
                                                                                                                                                                                        <textarea name="portfolio[${index}][description]" rows="2"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                                                                                                                                                                    </div>
                                                                                                                                                                                    <div>
                                                                                                                                                                                        <label class="block text-sm font-medium text-gray-700">Proje URL</label>
                                                                                                                                                                                        <input type="url" name="portfolio[${index}][url]"
                                                                                                                                                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                                                                                                                    </div>
                                                                                                                                                                                </div>
                                                                                                                                                                                <button type="button" class="remove-portfolio mt-4 text-red-600 hover:text-red-800">Projeyi Kaldır</button>
                                                                                                                                                                            </div>
                                                                                                                                                                        `;

                                                $('#portfolioList').append(template);
                                                updatePortfolioCount();
                                            });

                                            $(document).on('click', '.remove-portfolio', function () {
                                                $(this).closest('.portfolio-entry').remove();
                                                updatePortfolioCount();
                                            });

                                            function updatePortfolioCount() {
                                                const count = $('.portfolio-entry').length;
                                                $('#addPortfolio').text(`Yeni Proje Ekle (${count}/3)`);
                                                if (count >= 3) {
                                                    $('#addPortfolio').hide();
                                                } else {
                                                    $('#addPortfolio').show();
                                                }
                                            }

                                            $(document).on('click', '.remove-expertise', function () {
                                                $(this).closest('.flex').remove();
                                                updateExpertiseCount();
                                            });

                                            function updateExpertiseCount() {
                                                const count = $('#expertiseList .expertise-area').length;
                                                if (count >= 2) {
                                                    $('#addExpertise').hide();
                                                } else {
                                                    $('#addExpertise').show();
                                                }
                                            }

                                            $(document).on('click', '.remove-certification', function () {
                                                $(this).closest('.flex').remove();
                                                updateCertificationCount();
                                            });

                                            function updateCertificationCount() {
                                                const count = $('#certificationList .certification').length;
                                                if (count >= 2) {
                                                    $('#addCertification').hide();
                                                } else {
                                                    $('#addCertification').show();
                                                }
                                            }

                                            // Network links handling
                                            const platformConfigs = {
                                                professional: {
                                                    linkedin: { base: 'https://linkedin.com/in/', prefix: '' },
                                                    github: { base: 'https://github.com/', prefix: '' },
                                                    stackoverflow: { base: 'https://stackoverflow.com/users/', prefix: '' },
                                                    medium: { base: 'https://medium.com/@', prefix: '' },
                                                    devto: { base: 'https://dev.to/', prefix: '' },
                                                    gitlab: { base: 'https://gitlab.com/', prefix: '' },
                                                    bitbucket: { base: 'https://bitbucket.org/', prefix: '' },
                                                    codepen: { base: 'https://codepen.io/', prefix: '' },
                                                    dribbble: { base: 'https://dribbble.com/', prefix: '' },
                                                    behance: { base: 'https://behance.net/', prefix: '' }
                                                },
                                                social: {
                                                    twitter: { base: 'https://twitter.com/', prefix: '' },
                                                    instagram: { base: 'https://instagram.com/', prefix: '' },
                                                    youtube: { base: 'https://youtube.com/', prefix: '@' },
                                                    facebook: { base: 'https://facebook.com/', prefix: '' },
                                                    tiktok: { base: 'https://tiktok.com/@', prefix: '' },
                                                    pinterest: { base: 'https://pinterest.com/', prefix: '' },
                                                    reddit: { base: 'https://reddit.com/user/', prefix: '' },
                                                    twitch: { base: 'https://twitch.tv/', prefix: '' },
                                                    spotify: { base: 'https://open.spotify.com/user/', prefix: '' },
                                                    discord: { base: 'https://discord.gg/', prefix: '' }
                                                }
                                            };

                                            $('#cancelPlatform').click(function () {
                                                $('#platformModal').addClass('hidden');
                                            });

                                            $(document).on('click', '.platform-option', function () {
                                                const platform = $(this).data('platform');
                                                const config = platformConfigs[currentNetworkCategory][platform];

                                                const template = `
                                                                                                                                                                            <div class="network-link-entry" data-platform="${platform}">
                                                                                                            <label class="block text-sm font-medium text-gray-700">${platform.charAt(0).toUpperCase() + platform.slice(1)}</label>
                                                                                                            <div class="flex items-center gap-2 mt-1">
                                                                                                                <div class="flex-1 flex items-center bg-white rounded-md border border-gray-300">
                                                                                                                    <span class="px-3 py-2 text-gray-500 bg-gray-50 border-r border-gray-300 rounded-l-md whitespace-nowrap">
                                                                                                                        ${config.base}
                                                                                                                    </span>
                                                                                                                    <input type="text" 
                                                                                                                        class="w-full p-2 block rounded-r-md border-0 focus:ring-2 focus:ring-blue-500 min-w-[200px]"
                                                                                                                        data-platform="${platform}"
                                                                                                                        data-category="${currentNetworkCategory}">
                                                                                                                </div>
                                                                                                                <button type="button" class="remove-network-link text-red-600 hover:text-red-800 shrink-0">
                                                                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                                                                    </svg>
                                                                                                                </button>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                                                                                        `;

                                                $(`#${currentNetworkCategory}LinksList`).append(template);
                                                $('#platformModal').addClass('hidden');

                                                updateNetworkLinksCount(currentNetworkCategory);
                                            });

                                            function addPortfolioSite() {
                                                const template = `
                                                                                                                                                                            <div class="network-link-entry">
                                                                                                                                                                                <div class="flex items-center gap-2 mt-1">
                                                                                                                                                                                    <input type="text" 
                                                                                                                                                                                        placeholder="Site Adı"
                                                                                                                                                                                        class="flex-1 p-2 rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500"
                                                                                                                                                                                        data-category="portfolio_sites">
                                                                                                                                                                                    <input type="url" 
                                                                                                                                                                                        placeholder="URL"
                                                                                                                                                                                        class="flex-1 p-2 rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500">
                                                                                                                                                                                    <button type="button" class="remove-network-link text-red-600 hover:text-red-800">
                                                                                                                                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                                                                                                                                        </svg>
                                                                                                                                                                                    </button>
                                                                                                                                                                                </div>
                                                                                                                                                                            </div>
                                                                                                                                                                        `;

                                                $('#portfolioSitesList').append(template);
                                                updateNetworkLinksCount('portfolio_sites');
                                            }

                                            $(document).on('click', '.remove-network-link', function () {
                                                const category = $(this).closest('.network-link-entry').find('input').data('category');
                                                $(this).closest('.network-link-entry').remove();
                                                updateNetworkLinksCount(category);
                                            });

                                            function updateNetworkLinksCount(category) {
                                                const count = $(`#${category}LinksList .network-link-entry`).length;
                                                const addButton = $(`.add-network-link[data-category="${category}"]`);

                                                if (count >= 10) {
                                                    addButton.hide();
                                                } else {
                                                    addButton.show();
                                                }
                                            }

                                            $(document).on('click', '.remove-achievement', function () {
                                                $(this).closest('.achievement-entry').remove();
                                                updateAchievementCount();
                                            });

                                            function updateAchievementCount() {
                                                const count = $('.achievement-entry').length;
                                                $('#addAchievement').text(`Yeni Başarı Ekle (${count}/3)`);
                                                if (count >= 3) {
                                                    $('#addAchievement').hide();
                                                } else {
                                                    $('#addAchievement').show();
                                                }
                                            }

                                            // Tüm değişiklikleri kaydetme
                                            $('#saveAllChanges').click(function () {
                                                if (!formChanged) return;
                                                // Basic info verilerini topla
                                                const basicInfo = {
                                                    full_name: $('[name="full_name"]').val(),
                                                    age: parseInt($('[name="age"]').val()),
                                                    biography: $('[name="biography"]').val(),
                                                    location: {
                                                        city: $('[name="city"]').val(),
                                                        country: $('[name="country"]').val()
                                                    },
                                                    contact: {
                                                        email: $('[name="email"]').val(),
                                                        website: $('[name="website"]').val()
                                                    },
                                                    languages: $('[name="languages"]').val()
                                                        .split(',')
                                                        .map(lang => lang.trim())
                                                        .filter(lang => lang !== '')
                                                };

                                                // Eğitim verilerini topla
                                                const education = [];
                                                $('.education-entry').each(function (index) {
                                                    education.push({
                                                        level: $(`[name="education[${index}][level]"]`).val(),
                                                        institution: $(`[name="education[${index}][institution]"]`).val(),
                                                        degree: $(`[name="education[${index}][degree]"]`).val(),
                                                        gpa: parseFloat($(`[name="education[${index}][gpa]"]`).val()),
                                                        start_date: $(`[name="education[${index}][start_date]"]`).val(),
                                                        end_date: $(`[name="education[${index}][end_date]"]`).val()
                                                    });
                                                });

                                                // İş deneyimlerini topla
                                                const workExperience = [];
                                                $('.work-entry').each(function (index) {
                                                    const endDate = $(`[name="work[${index}][end_date]"]`).val();
                                                    const isCurrentJob = $(this).find('.current-job-checkbox').is(':checked');

                                                    workExperience.push({
                                                        company: $(`[name="work[${index}][company]"]`).val(),
                                                        position: $(`[name="work[${index}][position]"]`).val(),
                                                        start_date: $(`[name="work[${index}][start_date]"]`).val(),
                                                        end_date: isCurrentJob ? null : endDate,
                                                        description: $(`[name="work[${index}][description]"]`).val()
                                                    });
                                                });

                                                const skillsMatrix = {
                                                    technical_skills: [],
                                                    soft_skills: [],
                                                    tools: []
                                                };

                                                // Get technical skills
                                                $('#technical-skills-list input').each(function () {
                                                    skillsMatrix.technical_skills.push($(this).val());
                                                });

                                                // Get soft skills
                                                $('#soft-skills-list input').each(function () {
                                                    skillsMatrix.soft_skills.push($(this).val());
                                                });

                                                // Get tools
                                                $('#tools-list input').each(function () {
                                                    skillsMatrix.tools.push($(this).val());
                                                });

                                                const portfolioShowcase = [];
                                                $('.portfolio-entry').each(function (index) {
                                                    portfolioShowcase.push({
                                                        title: $(`[name="portfolio[${index}][title]"]`).val(),
                                                        description: $(`[name="portfolio[${index}][description]"]`).val(),
                                                        url: $(`[name="portfolio[${index}][url]"]`).val()
                                                    });
                                                });

                                                // Professional profile data
                                                const professionalProfile = {
                                                    summary: $('#profSummary').val(),
                                                    expertise_areas: [],
                                                    certifications: []
                                                };

                                                $('.expertise-area').each(function () {
                                                    professionalProfile.expertise_areas.push($(this).val());
                                                });

                                                $('.certification').each(function () {
                                                    professionalProfile.certifications.push($(this).val());
                                                });

                                                // Network links data
                                                const networkLinks = {
                                                    professional: {},
                                                    social: {},
                                                    portfolio_sites: {}
                                                };

                                                // Professional and social networks
                                                ['professional', 'social'].forEach(category => {
                                                    $(`#${category}LinksList .network-link-entry`).each(function () {
                                                        const input = $(this).find('input');
                                                        const platform = input.data('platform');
                                                        const username = input.val();
                                                        if (platform && username) {
                                                            networkLinks[category][platform] = username;
                                                        }
                                                    });
                                                });

                                                // Portfolio sites
                                                $('#portfolioSitesList .network-link-entry').each(function () {
                                                    const inputs = $(this).find('input');
                                                    const name = inputs.eq(0).val();
                                                    const url = inputs.eq(1).val();
                                                    if (name && url) {
                                                        networkLinks.portfolio_sites[name] = url;
                                                    }
                                                });

                                                // Achievements data
                                                const achievements = [];
                                                $('.achievement-entry').each(function (index) {
                                                    achievements.push({
                                                        title: $(`[name="achievement[${index}][title]"]`).val(),
                                                        issuer: $(`[name="achievement[${index}][issuer]"]`).val(),
                                                        date: $(`[name="achievement[${index}][date]"]`).val(),
                                                        description: $(`[name="achievement[${index}][description]"]`).val()
                                                    });
                                                });

                                                // Tüm verileri gönder
                                                $.ajax({
                                                    url: 'update_profile.php',
                                                    type: 'POST',
                                                    data: {
                                                        action: 'updateAllProfileInfo',
                                                        basicInfo: JSON.stringify(basicInfo),
                                                        educationHistory: JSON.stringify(education),
                                                        workExperience: JSON.stringify(workExperience),
                                                        skillsMatrix: JSON.stringify(skillsMatrix),
                                                        portfolioShowcase: JSON.stringify(portfolioShowcase),
                                                        professionalProfile: JSON.stringify(professionalProfile),
                                                        networkLinks: JSON.stringify(networkLinks),
                                                        achievements: JSON.stringify(achievements)
                                                    },
                                                    success: function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            alert('Tüm bilgileriniz başarıyla güncellendi');
                                                            formChanged = false;
                                                            hideSaveBar();
                                                        } else {
                                                            alert('Bilgileriniz güncellenirken bir hata oluştu');
                                                        }
                                                    },
                                                    error: function () {
                                                        alert('Bir hata oluştu');
                                                    }
                                                });
                                            });

                                            $('#saveAllChanges').removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                                        });

                                        $(document).ready(function () {
                                            if (!formChanged) {
                                                hideSaveBar();
                                            }
                                        });
                                    </script>
                                    <?php
                                    break;
                            case 'security':
                                // Mevcut kullanıcı bilgilerini çek
                                $stmt = $db->prepare("SELECT username, email, two_factor_auth FROM users WHERE user_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                    <h2 class="text-xl font-semibold mb-6">Güvenlik Merkezi</h2>

                                    <div class="space-y-8">
                                        <!-- Hesap Bilgileri -->
                                        <div class="bg-white p-6 rounded-lg shadow space-y-6">
                                            <h3 class="text-lg font-medium">Hesap Bilgileri</h3>
                                            <form id="accountInfoForm" class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Kullanıcı Adı</label>
                                                    <input type="text" name="username"
                                                        value="<?php echo htmlspecialchars($userData['username']); ?>"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">E-posta
                                                        Adresi</label>
                                                    <input type="email" name="email"
                                                        value="<?php echo htmlspecialchars($userData['email']); ?>"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Şifre Değiştirme -->
                                        <div class="bg-white p-6 rounded-lg shadow space-y-6">
                                            <h3 class="text-lg font-medium">Şifre Değiştirme</h3>
                                            <form id="passwordChangeForm" class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Mevcut Şifre</label>
                                                    <input type="password" name="current_password"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Yeni Şifre</label>
                                                    <input type="password" name="new_password"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Yeni Şifre
                                                        Tekrar</label>
                                                    <input type="password" name="new_password_confirmation"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                </div>
                                            </form>
                                        </div>

                                        <!-- İki Faktörlü Doğrulama -->
                                        <div class="bg-white p-6 rounded-lg shadow space-y-6">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h3 class="text-lg font-medium">İki Faktörlü Doğrulama</h3>
                                                    <p class="text-sm text-gray-500">Hesabınızı daha güvenli hale getirmek için
                                                        iki faktörlü doğrulamayı etkinleştirin.</p>
                                                </div>
                                                <div class="flex items-center">
                                                    <label class="relative inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" id="twoFactorToggle" class="sr-only peer" <?php echo $userData['two_factor_auth'] ? 'checked' : ''; ?>>
                                                        <div
                                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Kaydet Butonu -->
                                        <div class="flex justify-end">
                                            <button type="button" id="saveSecurityChanges"
                                                class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                Değişiklikleri Kaydet
                                            </button>
                                        </div>
                                    </div>

                                    <script>
                                        $(document).ready(function () {
                                            // Form değişikliklerini takip et
                                            let formChanged = false;
                                            let usernameTimer, emailTimer;
                                            let originalUsername = $('[name="username"]').val();
                                            let originalEmail = $('[name="email"]').val();

                                            $('input').on('change', function () {
                                                formChanged = true;
                                            });

                                            $('[name="username"]').after('<div class="validation-feedback username-feedback mt-1 text-sm"></div>');
                                            $('[name="email"]').after('<div class="validation-feedback email-feedback mt-1 text-sm"></div>');

                                            // İki faktörlü doğrulama toggle
                                            $('#twoFactorToggle').on('change', function () {
                                                formChanged = true;
                                            });

                                            $('[name="username"]').on('input', function () {
                                                const username = $(this).val().trim();
                                                const feedbackElement = $('.username-feedback');

                                                // Orijinal değerse kontrol etme
                                                if (username === originalUsername) {
                                                    feedbackElement.empty();
                                                    return;
                                                }

                                                // Minimum uzunluk kontrolü
                                                if (username.length < 3) {
                                                    feedbackElement.html('<span class="text-yellow-600">Kullanıcı adı en az 3 karakter olmalıdır</span>');
                                                    return;
                                                }

                                                // Geçerli karakter kontrolü
                                                if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                                                    feedbackElement.html('<span class="text-red-600">Sadece harf, rakam ve alt çizgi kullanılabilir</span>');
                                                    return;
                                                }

                                                // Önceki zamanlayıcıyı temizle
                                                clearTimeout(usernameTimer);

                                                // Loading göster
                                                feedbackElement.html('<span class="text-blue-600">Kontrol ediliyor...</span>');

                                                // Yeni zamanlayıcı başlat
                                                usernameTimer = setTimeout(() => {
                                                    $.ajax({
                                                        url: 'check_availability.php',
                                                        type: 'POST',
                                                        data: { type: 'username', value: username },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.available) {
                                                                feedbackElement.html('<span class="text-green-600">✓ Bu kullanıcı adı kullanılabilir</span>');
                                                            } else {
                                                                feedbackElement.html('<span class="text-red-600">✗ Bu kullanıcı adı zaten kullanımda</span>');
                                                            }
                                                        },
                                                        error: function () {
                                                            feedbackElement.html('<span class="text-red-600">Bir hata oluştu</span>');
                                                        }
                                                    });
                                                }, 500);
                                            });

                                            // Email değişikliklerini kontrol et
                                            $('[name="email"]').on('input', function () {
                                                const email = $(this).val().trim();
                                                const feedbackElement = $('.email-feedback');

                                                // Orijinal değerse kontrol etme
                                                if (email === originalEmail) {
                                                    feedbackElement.empty();
                                                    return;
                                                }

                                                // Email format kontrolü
                                                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                                                    feedbackElement.html('<span class="text-red-600">Geçerli bir email adresi giriniz</span>');
                                                    return;
                                                }

                                                // Önceki zamanlayıcıyı temizle
                                                clearTimeout(emailTimer);

                                                // Loading göster
                                                feedbackElement.html('<span class="text-blue-600">Kontrol ediliyor...</span>');

                                                // Yeni zamanlayıcı başlat
                                                emailTimer = setTimeout(() => {
                                                    $.ajax({
                                                        url: 'check_availability.php',
                                                        type: 'POST',
                                                        data: { type: 'email', value: email },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.available) {
                                                                feedbackElement.html('<span class="text-green-600">✓ Bu email kullanılabilir</span>');
                                                            } else {
                                                                feedbackElement.html('<span class="text-red-600">✗ Bu email zaten kullanımda</span>');
                                                            }
                                                        },
                                                        error: function () {
                                                            feedbackElement.html('<span class="text-red-600">Bir hata oluştu</span>');
                                                        }
                                                    });
                                                }, 500);
                                            });

                                            // Değişiklikleri kaydet
                                            $('#saveSecurityChanges').click(function () {
                                                if (!formChanged) return;

                                                // Şifre kontrolü
                                                const newPassword = $('[name="new_password"]').val();
                                                const confirmPassword = $('[name="new_password_confirmation"]').val();

                                                if (newPassword && newPassword !== confirmPassword) {
                                                    alert('Yeni şifreler eşleşmiyor!');
                                                    return;
                                                }

                                                // Form verilerini topla
                                                const formData = {
                                                    username: $('[name="username"]').val(),
                                                    email: $('[name="email"]').val(),
                                                    current_password: $('[name="current_password"]').val(),
                                                    new_password: newPassword,
                                                    two_factor_auth: $('#twoFactorToggle').is(':checked') ? 1 : 0
                                                };

                                                $('#accountInfoForm').on('submit', function (e) {
                                                    e.preventDefault();
                                                    const hasError = $('.validation-feedback').text().includes('✗');
                                                    if (hasError) {
                                                        alert('Lütfen geçerli değerler giriniz');
                                                        return false;
                                                    }
                                                });

                                                // API'ye gönder
                                                $.ajax({
                                                    url: 'update_account.php',
                                                    type: 'POST',
                                                    data: formData,
                                                    success: function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            alert('Güvenlik ayarlarınız başarıyla güncellendi');
                                                            formChanged = false;
                                                            // Şifre alanlarını temizle
                                                            $('[name="current_password"]').val('');
                                                            $('[name="new_password"]').val('');
                                                            $('[name="new_password_confirmation"]').val('');
                                                        } else {
                                                            alert(response.message || 'Bir hata oluştu');
                                                        }
                                                    },
                                                    error: function () {
                                                        alert('Bir hata oluştu');
                                                    }
                                                });
                                            });
                                        });
                                    </script>
                                    <?php
                                    break;
                            case 'notifications':
                                echo '<h2 class="text-xl font-semibold mb-4">Bildirim Ayarları</h2>';
                                echo '<p>E-posta, web ve mobil bildirim tercihlerinizi buradan özelleştirebilirsiniz.</p>';
                                break;
                            case 'payment':
                                echo '<h2 class="text-xl font-semibold mb-4">Ödeme & Finansal İşlemler</h2>';
                                echo '<p>Banka hesapları, ödeme yöntemleri ve finansal işlemlerinizi buradan yönetebilirsiniz.</p>';
                                break;
                            case 'privacy':
                                echo '<h2 class="text-xl font-semibold mb-4">Gizlilik Ayarları</h2>';
                                echo '<p>Profil görünürlüğü ve gizlilik tercihlerinizi buradan düzenleyebilirsiniz.</p>';
                                break;
                            case 'account':
                                // Önce kullanıcının Google ile giriş yapıp yapmadığını ve şifresi olup olmadığını kontrol edelim
                                $stmt = $db->prepare("SELECT google_id, password FROM users WHERE user_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                                $isGoogleUser = !empty($userData['google_id']);
                                $hasPassword = !empty($userData['password']);

                                // Giriş denemelerini çek
                                $stmt = $db->prepare("
                                        SELECT * FROM login_attempts 
                                        WHERE user_id = ? AND verified = 0 
                                        ORDER BY attempt_time DESC"
                                );
                                $stmt->execute([$_SESSION['user_id']]);
                                $unverifiedAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Onaylanmış giriş denemelerini çek
                                $stmt = $db->prepare("
                                        SELECT * FROM login_attempts 
                                        WHERE user_id = ? AND verified = 1 
                                        ORDER BY attempt_time DESC"
                                );
                                $stmt->execute([$_SESSION['user_id']]);
                                $verifiedAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                    <h2 class="text-xl font-semibold mb-4">Hesap ve Veriler</h2>
                                    <p class="mb-6">Hesap verilerinizi görüntüleyebilir ve yönetebilirsiniz.</p>

                                    <!-- Giriş Denemeleri -->
                                    <div class="space-y-6">
                                        <!-- Onaylanmamış Giriş Denemeleri -->
                                        <div class="bg-white p-6 rounded-lg shadow">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-medium">Onaylanmamış Giriş Denemeleri</h3>
                                                <?php if (count($unverifiedAttempts) > 0): ?>
                                                    <button id="deleteAllUnverified"
                                                        class="text-red-600 hover:text-red-800 text-sm">
                                                        Tümünü Sil
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead>
                                                        <tr class="bg-gray-50">
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Tarih</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                IP</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Konum</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Tarayıcı</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php if (count($unverifiedAttempts) > 0): ?>
                                                            <?php foreach ($unverifiedAttempts as $attempt): ?>
                                                                <tr>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo date('d.m.Y H:i:s', strtotime($attempt['attempt_time'])); ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['ip_address']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['city'] . ', ' . $attempt['country']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['browser'] . ' ' . $attempt['browser_version']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <button
                                                                            class="verify-attempt text-green-600 hover:text-green-800 mr-3"
                                                                            data-id="<?php echo $attempt['attempt_id']; ?>">
                                                                            Bu Bendim
                                                                        </button>
                                                                        <button class="delete-attempt text-red-600 hover:text-red-800"
                                                                            data-id="<?php echo $attempt['attempt_id']; ?>">
                                                                            Sil
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                                    Onaylanmamış giriş denemesi bulunmuyor
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Onaylanmış Giriş Denemeleri -->
                                        <div class="bg-white p-6 rounded-lg shadow">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-medium">Onaylanmış Giriş Denemeleri</h3>
                                                <?php if (count($verifiedAttempts) > 0): ?>
                                                    <button id="deleteAllVerified" class="text-red-600 hover:text-red-800 text-sm">
                                                        Tümünü Sil
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead>
                                                        <tr class="bg-gray-50">
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Tarih</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                IP</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Konum</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                Tarayıcı</th>
                                                            <th
                                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                                İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php if (count($verifiedAttempts) > 0): ?>
                                                            <?php foreach ($verifiedAttempts as $attempt): ?>
                                                                <tr>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo date('d.m.Y H:i:s', strtotime($attempt['attempt_time'])); ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['ip_address']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['city'] . ', ' . $attempt['country']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <?php echo $attempt['browser'] . ' ' . $attempt['browser_version']; ?>
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                        <button
                                                                            class="delete-verified-attempt text-red-600 hover:text-red-800"
                                                                            data-id="<?php echo $attempt['attempt_id']; ?>">
                                                                            Sil
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                                    Onaylanmış giriş denemesi bulunmuyor
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <?php if ($isGoogleUser && !$hasPassword): ?>
                                            <!-- Google kullanıcısı için şifre oluşturma bölümü -->
                                            <div class="bg-white p-6 rounded-lg shadow mb-6">
                                                <h3 class="text-lg font-medium text-blue-600 mb-4">Şifre Oluşturma</h3>
                                                <p class="text-sm text-gray-600 mb-4">
                                                    Hesabınızı Google ile oluşturdunuz ve henüz bir şifre belirlemediniz.
                                                    Hesabınızı silebilmek için önce bir şifre oluşturmanız gerekmektedir.
                                                </p>
                                                <form id="createPasswordForm" class="space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Yeni Şifre</label>
                                                        <input type="password" name="new_password" id="newPassword"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            En az 8 karakter, 1 büyük harf, 1 küçük harf içermelidir.
                                                        </div>
                                                        <div id="passwordValidation" class="mt-2 text-xs space-y-1">
                                                            <div id="lengthCheck" class="text-red-600">✗ En az 8 karakter</div>
                                                            <div id="uppercaseCheck" class="text-red-600">✗ En az 1 büyük harf</div>
                                                            <div id="lowercaseCheck" class="text-red-600">✗ En az 1 küçük harf</div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Şifre Tekrar</label>
                                                        <input type="password" name="confirm_password" id="confirmPassword"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <div id="passwordMatch" class="text-xs mt-1"></div>
                                                    </div>
                                                    <button type="submit" id="createPasswordBtn"
                                                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        Şifre Oluştur
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Hesap Silme -->
                                        <div class="bg-white p-6 rounded-lg shadow">
                                            <h3 class="text-lg font-medium text-red-600 mb-4">Tehlikeli Bölge</h3>
                                            <p class="text-sm text-gray-600 mb-4">
                                                Hesabınızı silmek geri alınamaz bir işlemdir. Tüm verileriniz kalıcı olarak
                                                silinecektir.
                                            </p>
                                            <button id="deleteAccountBtn"
                                                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 <?php echo ($isGoogleUser && !$hasPassword) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                                <?php echo ($isGoogleUser && !$hasPassword) ? 'disabled' : ''; ?>>
                                                Hesabı Sil
                                            </button>
                                            <?php if ($isGoogleUser && !$hasPassword): ?>
                                                <p class="mt-2 text-sm text-red-600">
                                                    Hesabınızı silebilmek için önce bir şifre oluşturmanız gerekmektedir.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Hesap Silme Modalı -->
                                    <div id="deleteAccountModal"
                                        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
                                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                            <div class="mt-3 text-center">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900">Hesap Silme</h3>
                                                <div class="mt-2 px-7 py-3">
                                                    <input type="password" id="deleteAccountPassword"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                                        placeholder="Şifrenizi girin">
                                                </div>
                                                <div class="items-center px-4 py-3">
                                                    <button id="confirmDeleteAccount"
                                                        class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                        Hesabı Sil
                                                    </button>
                                                    <button id="cancelDeleteAccount"
                                                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                                        İptal
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Son Uyarı Modalı -->
                                    <div id="finalWarningModal"
                                        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
                                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                            <div class="mt-3 text-center">
                                                <div
                                                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </div>
                                                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Son Uyarı!</h3>
                                                <div class="mt-2 px-7 py-3">
                                                    <p class="text-sm text-gray-500">
                                                        Hesabınızın tüm verileri kalıcı olarak silinecektir. Mevcut bakiyeniz ve
                                                        diğer tüm verileriniz geri getirilemeyecektir.
                                                    </p>
                                                </div>
                                                <div class="items-center px-4 py-3">
                                                    <button id="finalConfirmDelete"
                                                        class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                        Evet, Hesabımı Sil
                                                    </button>
                                                    <button id="finalCancelDelete"
                                                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                                        Vazgeç
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                        // Şifre validasyonu için regex patterns
                                        const patterns = {
                                            length: /.{8,}/,
                                            uppercase: /[A-Z]/,
                                            lowercase: /[a-z]/
                                        };

                                        let validPassword = false;
                                        let passwordsMatch = false;

                                        function updateValidationUI(input) {
                                            const password = input.value;

                                            // Her bir kriteri kontrol et ve UI'ı güncelle
                                            for (const [key, pattern] of Object.entries(patterns)) {
                                                const element = document.getElementById(`${key}Check`);
                                                const isValid = pattern.test(password);
                                                element.className = isValid ? 'text-green-600' : 'text-red-600';
                                                element.innerHTML = `${isValid ? '✓' : '✗'} ${element.innerHTML.split(' ').slice(1).join(' ')}`;
                                            }

                                            // Tüm kriterler geçildi mi kontrol et
                                            validPassword = Object.values(patterns).every(pattern => pattern.test(password));
                                            updateSubmitButton();
                                        }

                                        function checkPasswordMatch() {
                                            const password = document.getElementById('newPassword').value;
                                            const confirm = document.getElementById('confirmPassword').value;
                                            const matchDiv = document.getElementById('passwordMatch');

                                            if (confirm) {
                                                passwordsMatch = password === confirm;
                                                matchDiv.className = `text-xs mt-1 ${passwordsMatch ? 'text-green-600' : 'text-red-600'}`;
                                                matchDiv.textContent = passwordsMatch ? '✓ Şifreler eşleşiyor' : '✗ Şifreler eşleşmiyor';
                                            } else {
                                                passwordsMatch = false;
                                                matchDiv.textContent = '';
                                            }

                                            updateSubmitButton();
                                        }

                                        function updateSubmitButton() {
                                            const submitBtn = document.getElementById('createPasswordBtn');
                                            submitBtn.disabled = !(validPassword && passwordsMatch);
                                        }

                                        $(document).ready(function () {
                                            if (document.getElementById('newPassword')) {
                                                $('#newPassword').on('input', function () {
                                                    updateValidationUI(this);
                                                });

                                                $('#confirmPassword').on('input', function () {
                                                    checkPasswordMatch();
                                                });

                                                $('#createPasswordForm').on('submit', function (e) {
                                                    e.preventDefault();

                                                    const password = $('#newPassword').val();

                                                    $.ajax({
                                                        url: 'update_critical.php',
                                                        type: 'POST',
                                                        data: {
                                                            action: 'create_password',
                                                            password: password
                                                        },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                alert('Şifre başarıyla oluşturuldu. Artık hesabınızı silebilirsiniz.');
                                                                location.reload();
                                                            } else {
                                                                alert(response.message || 'Bir hata oluştu');
                                                            }
                                                        },
                                                        error: function () {
                                                            alert('Bir hata oluştu');
                                                        }
                                                    });
                                                });
                                            }
                                        });

                                        $(document).ready(function () {
                                            let accountPassword = '';

                                            // Tekil giriş denemesi doğrulama
                                            $('.verify-attempt').click(function () {
                                                const attemptId = $(this).data('id');
                                                const row = $(this).closest('tr');

                                                $.ajax({
                                                    url: 'update_critical.php',
                                                    type: 'POST',
                                                    data: {
                                                        action: 'verify_attempt',
                                                        attempt_id: attemptId
                                                    },
                                                    success: function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            row.fadeOut(400, function () {
                                                                location.reload();
                                                            });
                                                        } else {
                                                            alert('Bir hata oluştu');
                                                        }
                                                    }
                                                });
                                            });

                                            // Tekil giriş denemesi silme
                                            $('.delete-attempt, .delete-verified-attempt').click(function () {
                                                const attemptId = $(this).data('id');
                                                const row = $(this).closest('tr');

                                                if (confirm('Bu giriş denemesini silmek istediğinizden emin misiniz?')) {
                                                    $.ajax({
                                                        url: 'update_critical.php',
                                                        type: 'POST',
                                                        data: {
                                                            action: 'delete_attempt',
                                                            attempt_id: attemptId
                                                        },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                row.fadeOut();
                                                            } else {
                                                                alert('Bir hata oluştu');
                                                            }
                                                        }
                                                    });
                                                }
                                            });

                                            // Tüm onaylanmamış denemeleri silme
                                            $('#deleteAllUnverified').click(function () {
                                                if (confirm('Tüm onaylanmamış giriş denemelerini silmek istediğinizden emin misiniz?')) {
                                                    $.ajax({
                                                        url: 'update_critical.php',
                                                        type: 'POST',
                                                        data: {
                                                            action: 'delete_all_unverified'
                                                        },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                location.reload();
                                                            } else {
                                                                alert('Bir hata oluştu');
                                                            }
                                                        }
                                                    });
                                                }
                                            });

                                            // Tüm onaylanmış denemeleri silme
                                            $('#deleteAllVerified').click(function () {
                                                if (confirm('Tüm onaylanmış giriş denemelerini silmek istediğinizden emin misiniz?')) {
                                                    $.ajax({
                                                        url: 'update_critical.php',
                                                        type: 'POST',
                                                        data: {
                                                            action: 'delete_all_verified'
                                                        },
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                location.reload();
                                                            } else {
                                                                alert('Bir hata oluştu');
                                                            }
                                                        }
                                                    });
                                                }
                                            });

                                            // Hesap silme modalı kontrolü
                                            $('#deleteAccountBtn').click(function () {
                                                $('#deleteAccountModal').removeClass('hidden');
                                            });

                                            $('#cancelDeleteAccount').click(function () {
                                                $('#deleteAccountModal').addClass('hidden');
                                                $('#deleteAccountPassword').val('');
                                            });

                                            // İlk modal onayı ve şifre kontrolü
                                            $('#confirmDeleteAccount').click(function () {
                                                const password = $('#deleteAccountPassword').val();

                                                if (!password) {
                                                    alert('Lütfen şifrenizi girin');
                                                    return;
                                                }

                                                // Şifreyi global değişkene kaydet
                                                accountPassword = password;

                                                $.ajax({
                                                    url: 'update_critical.php',
                                                    type: 'POST',
                                                    data: {
                                                        action: 'verify_password',
                                                        password: password
                                                    },
                                                    success: function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            // Şifre doğruysa ilk modalı kapat ve son uyarı modalını göster
                                                            $('#deleteAccountModal').addClass('hidden');
                                                            $('#finalWarningModal').removeClass('hidden');
                                                        } else {
                                                            alert('Şifre yanlış');
                                                        }
                                                    }
                                                });
                                            });


                                            // Son uyarı modalı kontrolleri
                                            $('#cancelDeleteAccount, #finalCancelDelete').click(function () {
                                                accountPassword = '';
                                                $('#deleteAccountPassword').val('');
                                                $('#deleteAccountModal, #finalWarningModal').addClass('hidden');
                                            });

                                            // Hesap silme işlemi
                                            $('#finalConfirmDelete').click(function () {
                                                $.ajax({
                                                    url: 'update_critical.php',
                                                    type: 'POST',
                                                    data: {
                                                        action: 'delete_account',
                                                        password: accountPassword // Kaydedilmiş şifreyi kullan
                                                    },
                                                    success: function (response) {
                                                        response = JSON.parse(response);
                                                        if (response.success) {
                                                            window.location.href = '/auth/logout.php';
                                                        } else {
                                                            alert(response.message || 'Bir hata oluştu');
                                                        }
                                                    },
                                                    error: function (xhr, status, error) {
                                                        console.error('Error:', error);
                                                        alert('Bir hata oluştu: ' + error);
                                                    }
                                                });
                                            });
                                        });
                                    </script>
                                    <?php
                                    break;
                            case 'language':
                                ?>
                                    <div class="space-y-6">
                                        <div class="bg-white p-6 rounded-lg shadow">
                                            <h2 class="text-xl font-semibold mb-6">Dil ve Bölge Ayarları</h2>

                                            <!-- Dil Seçimi -->
                                            <div class="mb-8">
                                                <h3 class="text-lg font-medium mb-4">Arayüz Dili</h3>
                                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                                    <?php foreach ($languages as $code => $name): ?>
                                                        <label
                                                            class="relative flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo ($userSettings['language'] === $code) ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                                            <div class="flex items-center">
                                                                <input type="radio" name="language" value="<?php echo $code; ?>"
                                                                    class="hidden" <?php echo ($userSettings['language'] === $code) ? 'checked' : ''; ?>>
                                                                <span class="text-sm font-medium"><?php echo $name; ?></span>
                                                            </div>
                                                            <?php if ($userSettings['language'] === $code): ?>
                                                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Saat Dilimi -->
                                            <div class="mb-8">
                                                <h3 class="text-lg font-medium mb-4">Saat Dilimi</h3>
                                                <div class="max-w-xl">
                                                    <select name="timezone"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <?php foreach ($timezones as $tz): ?>
                                                            <option value="<?php echo $tz; ?>" <?php echo ($userSettings['timezone'] === $tz) ? 'selected' : ''; ?>>
                                                                <?php echo $tz; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <p class="mt-2 text-sm text-gray-500">Yerel saatiniz: <span
                                                            id="localTime"></span></p>
                                                </div>
                                            </div>

                                            <!-- Bölge Seçimi -->
                                            <div class="mb-8">
                                                <h3 class="text-lg font-medium mb-4">Bölge</h3>
                                                <div class="max-w-xl">
                                                    <select name="region"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                        <?php foreach ($regions as $code => $name): ?>
                                                            <option value="<?php echo $code; ?>" <?php echo ($userSettings['region'] === $code) ? 'selected' : ''; ?>>
                                                                <?php echo $name; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Tarih ve Saat Formatı -->
                                            <div class="mb-8">
                                                <h3 class="text-lg font-medium mb-4">Tarih ve Saat Formatı</h3>
                                                <div class="space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Tarih
                                                            Formatı</label>
                                                        <select name="date_format"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            <option value="DD.MM.YYYY" <?php echo ($userSettings['date_format'] === 'DD.MM.YYYY') ? 'selected' : ''; ?>>DD.MM.YYYY</option>
                                                            <option value="MM/DD/YYYY" <?php echo ($userSettings['date_format'] === 'MM/DD/YYYY') ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                            <option value="YYYY-MM-DD" <?php echo ($userSettings['date_format'] === 'YYYY-MM-DD') ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Saat
                                                            Formatı</label>
                                                        <div class="mt-1 space-x-4">
                                                            <label class="inline-flex items-center">
                                                                <input type="radio" name="time_format" value="24h" <?php echo ($userSettings['time_format'] === '24h') ? 'checked' : ''; ?>
                                                                    class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                                                <span class="ml-2 text-sm text-gray-700">24 saat</span>
                                                            </label>
                                                            <label class="inline-flex items-center">
                                                                <input type="radio" name="time_format" value="12h" <?php echo ($userSettings['time_format'] === '12h') ? 'checked' : ''; ?>
                                                                    class="form-radio h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                                                <span class="ml-2 text-sm text-gray-700">12 saat (AM/PM)</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Kaydet Butonu -->
                                                <div class="flex justify-end">
                                                    <button type="button" id="saveRegionSettings"
                                                        class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                                                        Değişiklikleri Kaydet
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                            $(document).ready(function () {
                                                // Yerel saati güncelleme fonksiyonu
                                                function updateLocalTime() {
                                                    const timezone = $('select[name="timezone"]').val();
                                                    const now = new Date();
                                                    const options = {
                                                        timeZone: timezone,
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                        second: '2-digit',
                                                        hour12: $('input[name="time_format"]:checked').val() === '12h'
                                                    };
                                                    $('#localTime').text(now.toLocaleTimeString(undefined, options));
                                                }

                                                // Her saniye saati güncelle
                                                setInterval(updateLocalTime, 1000);
                                                updateLocalTime();

                                                // Ayarları kaydet
                                                $('#saveRegionSettings').click(function () {
                                                    const settings = {
                                                        language: $('input[name="language"]:checked').val(),
                                                        timezone: $('select[name="timezone"]').val(),
                                                        region: $('select[name="region"]').val(),
                                                        date_format: $('select[name="date_format"]').val(),
                                                        time_format: $('input[name="time_format"]:checked').val()
                                                    };

                                                    $.ajax({
                                                        url: 'update_region.php',
                                                        type: 'POST',
                                                        data: settings,
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                alert('Ayarlarınız başarıyla güncellendi');
                                                                if (settings.language !== '<?php echo $userSettings['language']; ?>') {
                                                                    // Dil değiştiyse sayfayı yenile
                                                                    location.reload();
                                                                }
                                                            } else {
                                                                alert(response.message || 'Bir hata oluştu');
                                                            }
                                                        },
                                                        error: function () {
                                                            alert('Bir hata oluştu');
                                                        }
                                                    });
                                                });

                                                // Dil seçimi
                                                $('label input[name="language"]').change(function () {
                                                    $('label').removeClass('border-blue-500 bg-blue-50').addClass('border-gray-200');
                                                    $('label svg').remove();

                                                    const selectedLabel = $(this).closest('label');
                                                    selectedLabel.removeClass('border-gray-200').addClass('border-blue-500 bg-blue-50');
                                                    selectedLabel.append(`
                                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        `);
                                                });
                                            });
                                        </script>
                                        <?php
                                        break;
                            case 'appearance':
                                // Kullanıcı ayarlarını çek
                                $stmt = $db->prepare("SELECT theme, font_family FROM user_settings WHERE user_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $appearanceSettings = $stmt->fetch(PDO::FETCH_ASSOC);

                                // Eğer ayarlar yoksa varsayılan değerleri kullan
                                if (!$appearanceSettings) {
                                    $appearanceSettings = [
                                        'theme' => 'light',
                                        'font_family' => 'Inter'
                                    ];
                                }

                                // Kullanılabilir fontlar
                                $availableFonts = [
                                    'Inter' => 'Inter',
                                    'Roboto' => 'Roboto',
                                    'Open Sans' => 'Open Sans',
                                    'Montserrat' => 'Montserrat',
                                    'Poppins' => 'Poppins',
                                ];
                                ?>
                                        <div class="space-y-6">
                                            <div class="bg-white p-6 rounded-lg shadow">
                                                <h2 class="text-xl font-semibold mb-6">Görünüm ve Tema</h2>

                                                <!-- Tema Seçimi -->
                                                <div class="mb-8">
                                                    <h3 class="text-lg font-medium mb-4">Tema</h3>
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <!-- Aydınlık Tema -->
                                                        <label
                                                            class="relative flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $appearanceSettings['theme'] === 'light' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                                            <div class="flex items-center">
                                                                <input type="radio" name="theme" value="light" class="hidden"
                                                                    <?php echo $appearanceSettings['theme'] === 'light' ? 'checked' : ''; ?>>
                                                                <div class="flex items-center space-x-3">
                                                                    <div
                                                                        class="w-10 h-10 bg-white border rounded-lg shadow-sm flex items-center justify-center">
                                                                        <svg class="w-6 h-6 text-yellow-500" fill="currentColor"
                                                                            viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd"
                                                                                d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                                                                                clip-rule="evenodd" />
                                                                        </svg>
                                                                    </div>
                                                                    <div>
                                                                        <div class="font-medium">Aydınlık Tema</div>
                                                                        <div class="text-sm text-gray-500">Beyaz arka plan</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if ($appearanceSettings['theme'] === 'light'): ?>
                                                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>

                                                            <?php endif; ?>
                                                        </label>

                                                        <!-- Karanlık Tema -->
                                                        <label
                                                            class="relative flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $appearanceSettings['theme'] === 'dark' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                                            <div class="flex items-center">
                                                                <input type="radio" name="theme" value="dark" class="hidden"
                                                                    <?php echo $appearanceSettings['theme'] === 'dark' ? 'checked' : ''; ?>>
                                                                <div class="flex items-center space-x-3">
                                                                    <div
                                                                        class="w-10 h-10 bg-gray-900 border border-gray-700 rounded-lg shadow-sm flex items-center justify-center">
                                                                        <svg class="w-6 h-6 text-gray-300" fill="currentColor"
                                                                            viewBox="0 0 20 20">
                                                                            <path
                                                                                d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                                                                        </svg>
                                                                    </div>
                                                                    <div>
                                                                        <div class="font-medium">Karanlık Tema</div>
                                                                        <div class="text-sm text-gray-500">Koyu arka plan</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if ($appearanceSettings['theme'] === 'dark'): ?>
                                                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>

                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                </div>

                                                <!-- Font Seçimi -->
                                                <div class="mb-8">
                                                    <h3 class="text-lg font-medium mb-4">Yazı Tipi</h3>
                                                    <div class="max-w-xl">
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <?php foreach ($availableFonts as $fontKey => $fontName): ?>
                                                                <label
                                                                    class="relative flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $appearanceSettings['font_family'] === $fontKey ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                                                    <div class="flex items-center">
                                                                        <input type="radio" name="font_family"
                                                                            value="<?php echo $fontKey; ?>" class="hidden" <?php echo $appearanceSettings['font_family'] === $fontKey ? 'checked' : ''; ?>>
                                                                        <div class="flex flex-col">
                                                                            <span class="font-medium"
                                                                                style="font-family: <?php echo $fontKey; ?>">
                                                                                <?php echo $fontName; ?>
                                                                            </span>
                                                                            <span class="text-sm text-gray-500" style=" font-family:
                                                                    <?php echo $fontKey; ?>">AaBbCcDdEeFf</span>
                                                                        </div>
                                                                    </div>
                                                                    <?php if ($appearanceSettings['font_family'] === $fontKey): ?>
                                                                        <svg class="w-5 h-5 text-blue-500" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                        </svg>

                                                                    <?php endif; ?>
                                                                </label>

                                                            <?php endforeach; ?>
                                                        </div>
                                                        <p class="mt-2 text-sm text-gray-500">
                                                            Seçtiğiniz yazı tipi tüm arayüzde kullanılacaktır.
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Kaydet Butonu -->
                                                <div class="flex justify-end">
                                                    <button type="button" id="saveAppearanceSettings"
                                                        class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                                                        Değişiklikleri Kaydet
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                            $(document).ready(function () {
                                                // Tema değişikliğini canlı olarak göster
                                                $('input[name="theme"]').change(function () {
                                                    const theme = $(this).val();

                                                    // Tüm tema labellerinin stilini sıfırla
                                                    $('input[name="theme"]').closest('label').removeClass('border-blue-500 bg-blue-50').addClass('border-gray-200');

                                                    // Seçilen temanın labelini güncelle
                                                    $(this).closest('label').removeClass('border-gray-200').addClass('border-blue-500 bg-blue-50');

                                                    // Tik işaretlerini kaldır
                                                    $('input[name="theme"]').closest('label').find('svg').remove();

                                                    // Seçilen temaya tik işareti ekle
                                                    $(this).closest('label').append(`
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                `);

                                                    // Tema değişikliği için gerekli işlemler
                                                    $('body').toggleClass('dark-mode', theme === 'dark');
                                                });

                                                // Font değişikliğini canlı olarak göster
                                                $('input[name="font_family"]').change(function () {
                                                    const font = $(this).val();

                                                    // Tüm font labellerinin stilini sıfırla
                                                    $('input[name="font_family"]').closest('label').removeClass('border-blue-500 bg-blue-50').addClass('border-gray-200');

                                                    // Seçilen fontun labelini güncelle
                                                    $(this).closest('label').removeClass('border-gray-200').addClass('border-blue-500 bg-blue-50');

                                                    // Tik işaretlerini kaldır
                                                    $('input[name="font_family"]').closest('label').find('svg').remove();

                                                    // Seçilen fonta tik işareti ekle
                                                    $(this).closest('label').append(`
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                `);

                                                    // Font değişikliği için gerekli işlemler
                                                    $('body').css('font-family', font);
                                                });

                                                // Ayarları kaydet
                                                $('#saveAppearanceSettings').click(function () {
                                                    const settings = {
                                                        theme: $('input[name="theme"]:checked').val(),
                                                        font_family: $('input[name="font_family"]:checked').val()
                                                    };

                                                    $.ajax({
                                                        url: 'update_appearance.php',
                                                        type: 'POST',
                                                        data: settings,
                                                        success: function (response) {
                                                            response = JSON.parse(response);
                                                            if (response.success) {
                                                                alert('Görünüm ayarlarınız başarıyla güncellendi');
                                                            } else {
                                                                alert(response.message || 'Bir hata oluştu');
                                                            }
                                                        },
                                                        error: function () {
                                                            alert('Bir hata oluştu');
                                                        }
                                                    });
                                                });
                                            });
                                        </script>
                                        <?php
                                        break;
                            default:
                                echo '<h2 class="text-xl font-semibold mb-4">Oops!</h2>';
                                echo '<p>Sanırım yanlış yere geldiniz, lütfen bir sekme seçin!</p>';
                        }
                        ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</body>

</html>