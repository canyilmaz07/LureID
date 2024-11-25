<?php
// registration.php
session_start();
$config = require_once '../../../config/database.php';
require_once '../../../languages/language_handler.php';

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
} catch (PDOException $e) {
    die("Bağlantı hatası: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Profil doluluk oranını kontrol et
$checkProfileQuery = "SELECT profile_completeness FROM user_extended_details WHERE user_id = ?";
$stmt = $db->prepare($checkProfileQuery);
$stmt->execute([$userId]);
$profileData = $stmt->fetch();
$profileCompleteness = $profileData['profile_completeness'] ?? 0;

// Mevcut freelancer kaydını kontrol et
$checkFreelancerQuery = "SELECT * FROM freelancers WHERE user_id = ?";
$stmt = $db->prepare($checkFreelancerQuery);
$stmt->execute([$userId]);
$existingFreelancer = $stmt->fetch();

if ($existingFreelancer) {
    switch($existingFreelancer['approval_status']) {
        case 'PENDING':
            header('Location: dashboard.php');
            exit;
        case 'APPROVED':
            header('Location: dashboard.php');
            exit;
    }
}

// Kullanıcı bilgilerini çek
$userQuery = "SELECT 
    u.*, 
    ued.*,
    COALESCE(JSON_EXTRACT(ued.skills_matrix, '$.technical_skills'), '[]') as technical_skills,
    COALESCE(JSON_EXTRACT(ued.education_history, '$[*]'), '[]') as education,
    COALESCE(JSON_EXTRACT(ued.work_experience, '$[*]'), '[]') as experience,
    COALESCE(JSON_EXTRACT(ued.professional_profile, '$.certifications'), '[]') as certifications,
    COALESCE(JSON_EXTRACT(ued.portfolio_showcase, '$[*]'), '[]') as portfolio
FROM users u 
LEFT JOIN user_extended_details ued ON u.user_id = ued.user_id 
WHERE u.user_id = ?";

$stmt = $db->prepare($userQuery);
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// JSON verileri decode et
$technicalSkills = json_decode($userData['technical_skills'], true) ?? [];
$education = json_decode($userData['education'], true) ?? [];
$experience = json_decode($userData['experience'], true) ?? [];
$certifications = json_decode($userData['certifications'], true) ?? [];
$portfolio = json_decode($userData['portfolio'], true) ?? [];

// Form gönderildiğinde INSERT sorgusu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($profileCompleteness < 20) {
        $_SESSION['error'] = "Profil doluluk oranınız %20'nin altında. Lütfen önce profilinizi güncelleyin.";
        header('Location: /profile-settings');
        exit;
    }

    // Form verilerini al ve JSON yapılarını oluştur
    $profileData = json_encode([
        'phone' => $_POST['phone'],
        'identity_number' => $_POST['identity_number'],
        'birth_year' => $_POST['birth_year'],
        'location' => [
            'country' => $_POST['country'],
            'city' => $_POST['city']
        ]
    ]);

    $professionalData = json_encode([
        'experience' => $_POST['experience'],
        'skills' => explode(',', $_POST['skills']),
        'education' => $_POST['education'],
        'certifications' => $_POST['certifications'],
        'portfolio' => $_POST['portfolio'],
        'references' => $_POST['references']
    ]);

    $financialData = json_encode([
        'account_holder' => $_POST['account_holder'],
        'bank_name' => $_POST['bank_name'],
        'iban' => $_POST['iban'],
        'tax_number' => $_POST['tax_number'],
        'daily_rate' => $_POST['daily_rate']
    ]);

    // Onay durumunu belirle
    $approvalStatus = $profileCompleteness >= 50 ? 'APPROVED' : 'PENDING';

    // Freelancer kaydını oluştur
    $insertQuery = "INSERT INTO freelancers (user_id, phone, identity_number, profile_data, 
                    professional_data, financial_data, approval_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($insertQuery);

    if (
        $stmt->execute([
            $userId,
            $_POST['phone'],
            $_POST['identity_number'],
            $profileData,
            $professionalData,
            $financialData,
            $approvalStatus
        ])
    ) {
        $_SESSION['success'] = $approvalStatus === 'APPROVED' ?
            "Freelancer kaydınız başarıyla oluşturuldu!" :
            "Başvurunuz alındı. Onay sürecinden sonra bilgilendirileceksiniz.";
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = "Bir hata oluştu. Lütfen tekrar deneyin.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Freelancer Registration') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js" defer></script>
</head>

<body class="bg-slate-100">
    <div class="container mx-auto px-4 py-8">
        <?php if ($profileCompleteness < 20): ?>
            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-100 border border-red-200" role="alert">
                <div class="font-medium"><?= __('Profile completion is too low!') ?></div>
                <p><?= __('Your profile must be at least 20% complete to register as a freelancer.') ?></p>
                <a href="/public/components/settings/settings.php" class="text-red-800 underline">
                    <?= __('Update Profile') ?>
                </a>
            </div>
        <?php elseif ($existingFreelancer): ?>
            <?php if ($existingFreelancer['approval_status'] === 'PENDING'): ?>
                <div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-100 border border-yellow-200">
                    <div class="font-medium"><?= __('Application Under Review') ?></div>
                    <p><?= __('Your freelancer application is being reviewed. We will get back to you soon.') ?></p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="max-w-4xl mx-auto bg-white rounded-xl shadow">
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 h-1">
                    <div class="bg-blue-600 h-1" style="width: <?= $profileCompleteness ?>%"></div>
                </div>

                <form method="POST" class="divide-y divide-gray-200">
                    <!-- Personal Information -->
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            <?= __('Personal Information') ?>
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <?= __('Full Name') ?>
                                </label>
                                <input type="text" value="<?= htmlspecialchars($userData['full_name']) ?>" readonly
                                    class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <?= __('Email') ?>
                                </label>
                                <input type="email" value="<?= htmlspecialchars($userData['email']) ?>" readonly
                                    class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <?= __('Phone Number') ?>
                                </label>
                                <input type="tel" name="phone" required pattern="[0-9]{10}" placeholder="5XX XXX XXXX"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    <?= __('Identity Number') ?>
                                </label>
                                <input type="text" name="identity_number" required pattern="[0-9]{11}"
                                    placeholder="XXXXXXXXXXX"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information Display -->
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-900">
                                <?= __('Professional Information') ?>
                            </h2>
                            <a href="/public/components/settings/settings.php"
                                class="text-blue-600 hover:text-blue-800 text-sm">
                                <?= __('Edit Profile') ?> →
                            </a>
                        </div>

                        <!-- Skills -->
                        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-md font-medium text-gray-900 mb-2">
                                <?= __('Technical Skills') ?>
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <?php if (!empty($technicalSkills)): ?>
                                    <?php foreach ($technicalSkills as $skill): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                            <?= htmlspecialchars($skill) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500 text-sm">
                                        <?= __('No skills added yet.') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            <?= __('Financial Information') ?>
                        </h2>
                        <div class="space-y-6">
                            <!-- Bank Account Details -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-md font-medium text-gray-900 mb-4">
                                    <?= __('Bank Account Details') ?>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Account Holder Name') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="account_holder" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Bank Name') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <select name="bank_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                            <option value=""><?= __('Select Bank') ?></option>
                                            <option value="Ziraat">Ziraat Bankası</option>
                                            <option value="Garanti">Garanti BBVA</option>
                                            <option value="Is">İş Bankası</option>
                                            <option value="Halkbank">Halkbank</option>
                                            <option value="Vakifbank">Vakıfbank</option>
                                            <option value="YapiKredi">Yapı Kredi</option>
                                            <option value="Akbank">Akbank</option>
                                            <option value="TEB">TEB</option>
                                            <option value="QNB">QNB Finansbank</option>
                                            <option value="DenizBank">DenizBank</option>
                                            <option value="HSBC">HSBC</option>
                                            <option value="ING">ING Bank</option>
                                            <option value="Odeabank">Odeabank</option>
                                        </select>
                                    </div>

                                    <div class="col-span-full">
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('IBAN Number') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 flex rounded-md shadow-sm">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                                TR
                                            </span>
                                            <input type="text" name="iban" required pattern="\d{2}(\s\d{4}){5}\s\d{2}"
                                                placeholder="XX XXXX XXXX XXXX XXXX XXXX XX"
                                                class="flex-1 block w-full px-3 py-2 border border-gray-300 rounded-none rounded-r-md focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?= __('Enter your IBAN number without TR prefix and spaces') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-md font-medium text-gray-900 mb-4">
                                    <?= __('Tax Information') ?>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Tax Number') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="tax_number" required pattern="[0-9]{10}"
                                            placeholder="XXXXXXXXXX" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?= __('Enter your 10-digit tax number') ?>
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Tax Office') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="tax_office" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Rate Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-md font-medium text-gray-900 mb-4">
                                    <?= __('Rate Information') ?>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Daily Rate') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input type="number" name="daily_rate" required min="0" step="0.01" class="block w-full px-3 py-2 border border-gray-300 rounded-md 
                                   focus:ring-blue-500 focus:border-blue-500 pl-7">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">₺</span>
                                            </div>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?= __('Enter your daily rate in Turkish Lira') ?>
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Preferred Payment Schedule') ?>
                                        </label>
                                        <select name="payment_schedule" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                            <option value="weekly"><?= __('Weekly') ?></option>
                                            <option value="biweekly"><?= __('Bi-weekly') ?></option>
                                            <option value="monthly"><?= __('Monthly') ?></option>
                                            <option value="project_based"><?= __('Project Based') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Methods -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-md font-medium text-gray-900 mb-4">
                                    <?= __('Additional Payment Methods') ?>
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('PayPal Email') ?> (<?= __('Optional') ?>)
                                        </label>
                                        <input type="email" name="paypal_email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            <?= __('Cryptocurrency Wallet') ?> (<?= __('Optional') ?>)
                                        </label>
                                        <input type="text" name="crypto_wallet" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="<?= __('Your BTC/ETH wallet address') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Agreement -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="terms_agreement" required
                                            class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label class="font-medium text-gray-700">
                                            <?= __('I agree to the payment terms and conditions') ?>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <p class="text-gray-500">
                                            <?= __('By checking this box, you agree to our payment processing terms and financial policies.') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="p-6 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">
                                <?= __('Profile Completion') ?>: <?= number_format($profileCompleteness, 2) ?>%
                            </span>
                            <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <?= __('Register') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Form doğrulama ve interaktif özellikler için gerekli JavaScript kodları
        document.addEventListener('DOMContentLoaded', function () {
            // Telefon formatı
            const phoneInput = document.querySelector('input[name="phone"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function (e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) value = value.slice(0, 10);
                    e.target.value = value;
                });
            }

            const ibanInput = document.querySelector('input[name="iban"]');
            if (ibanInput) {
                ibanInput.addEventListener('input', function (e) {
                    // Sadece rakamları al
                    let value = e.target.value.replace(/[^0-9]/g, '');

                    // 24 rakamla sınırla
                    value = value.slice(0, 24);

                    // Özel formatlama (XX XXXX XXXX XXXX XXXX XXXX XX)
                    if (value.length > 0) {
                        let formattedValue = '';

                        // İlk 2 rakam
                        formattedValue = value.slice(0, 2);

                        // Ortadaki 4'lü gruplar (20 rakam)
                        for (let i = 2; i < 22; i += 4) {
                            if (value.length > i) {
                                formattedValue += ' ' + value.slice(i, i + 4);
                            }
                        }

                        // Son 2 rakam
                        if (value.length > 22) {
                            formattedValue += ' ' + value.slice(22, 24);
                        }

                        e.target.value = formattedValue;
                    } else {
                        e.target.value = value;
                    }
                });

                // Sadece rakam girişine izin ver
                ibanInput.addEventListener('keypress', function (e) {
                    if (!/^[0-9]$/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Vergi numarası formatı
            const taxInput = document.querySelector('input[name="tax_number"]');
            if (taxInput) {
                taxInput.addEventListener('input', function (e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) value = value.slice(0, 10);
                    e.target.value = value;
                });
            }

            // Yetenekler için tag sistemi
            const skillsInput = document.querySelector('input[name="skills"]');
            if (skillsInput) {
                let tags = [];

                skillsInput.addEventListener('keydown', function (e) {
                    if (e.key === ',' || e.key === 'Enter') {
                        e.preventDefault();
                        let value = this.value.trim();
                        if (value && !tags.includes(value)) {
                            tags.push(value);
                            updateTags();
                        }
                        this.value = '';
                    }
                });

                function updateTags() {
                    const container = skillsInput.parentElement;
                    const existingTags = container.querySelector('.tags-container');

                    if (existingTags) {
                        existingTags.remove();
                    }

                    const tagsContainer = document.createElement('div');
                    tagsContainer.className = 'tags-container flex flex-wrap gap-2 mt-2';

                    tags.forEach((tag, index) => {
                        const tagElement = document.createElement('span');
                        tagElement.className = 'bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm flex items-center';
                        tagElement.innerHTML = `
                            ${tag}
                            <button type="button" class="ml-2 text-blue-600 hover:text-blue-800" data-index="${index}">×</button>
                        `;
                        tagsContainer.appendChild(tagElement);
                    });

                    container.appendChild(tagsContainer);

                    // Tag silme işlevselliği
                    tagsContainer.querySelectorAll('button').forEach(button => {
                        button.addEventListener('click', function () {
                            const index = parseInt(this.dataset.index);
                            tags.splice(index, 1);
                            updateTags();
                        });
                    });

                    // Hidden input güncelleme
                    skillsInput.value = tags.join(',');
                }
            }

            // Form gönderimi öncesi son kontroller
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Profil doluluk kontrolü
                    const completeness = <?= $profileCompleteness ?>;
                    if (completeness < 20) {
                        e.preventDefault();
                        alert('Profil doluluk oranınız çok düşük. Lütfen önce profilinizi güncelleyin.');
                        return;
                    }

                    // IBAN doğrulama
                    const iban = ibanInput.value.replace(/\s/g, '');

                    // 24 rakam kontrolü
                    if (iban.length !== 24) {
                        e.preventDefault();
                        alert('IBAN numarası 24 rakam olmalıdır');
                        return;
                    }

                    // Sadece rakam kontrolü
                    if (!/^\d{24}$/.test(iban)) {
                        e.preventDefault();
                        alert('IBAN sadece rakamlardan oluşmalıdır');
                        return;
                    }

                    // TC Kimlik kontrolü
                    const tcKimlik = document.querySelector('input[name="identity_number"]').value;
                    if (!/^[0-9]{11}$/.test(tcKimlik)) {
                        e.preventDefault();
                        alert('Lütfen geçerli bir TC Kimlik numarası giriniz.');
                        return;
                    }

                    // Telefon numarası kontrolü
                    const phone = document.querySelector('input[name="phone"]').value;
                    if (!/^[0-9]{10}$/.test(phone)) {
                        e.preventDefault();
                        alert('Lütfen geçerli bir telefon numarası giriniz.');
                        return;
                    }
                });
            }
        });
    </script>

    <style>
        /* Custom styling */
        .tags-container {
            margin-top: 0.5rem;
        }

        .tags-container span {
            display: inline-flex;
            align-items: center;
            margin: 0.25rem;
            padding: 0.25rem 0.75rem;
            background-color: #EBF5FF;
            color: #2563EB;
            border-radius: 9999px;
            font-size: 0.875rem;
        }

        .tags-container button {
            margin-left: 0.5rem;
            color: #2563EB;
            font-size: 1.25rem;
            line-height: 1;
            padding: 0 0.25rem;
        }

        .tags-container button:hover {
            color: #1E40AF;
        }

        /* Form validation styles */
        input:invalid {
            border-color: #EF4444;
        }

        input:invalid:focus {
            outline: none;
            ring: 1px #EF4444;
        }
    </style>
</body>

</html>