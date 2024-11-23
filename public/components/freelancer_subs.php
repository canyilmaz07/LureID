<?php
// freelancer_subs.php
session_start();
require_once '../../config/database.php';
require_once '../../config/logger.php';

// Initialize logger
$logger = new Logger();

if (!isset($_SESSION['user_id'])) {
    $logger->log('Unauthorized access attempt', 'WARNING');
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

// Experience formatting functions
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

function calculateExperienceDays($createdAt)
{
    $registrationDate = new DateTime($createdAt);
    $now = new DateTime();
    return $registrationDate->diff($now)->days;
}

// Media handling functions
function validateMedia($file, $type)
{
    $maxSize = ($type === 'video') ? 30 * 1024 * 1024 : 20 * 1024 * 1024;
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $allowedVideoTypes = ['video/mp4', 'video/webm'];

    if ($file['size'] > $maxSize) {
        throw new Exception(($type === 'video' ? "Video" : "Image") . " file size must be less than " . ($maxSize / 1024 / 1024) . "MB");
    }

    if ($type === 'image' && !in_array($file['type'], $allowedImageTypes)) {
        throw new Exception("Only JPG, JPEG and PNG images are allowed");
    }

    if ($type === 'video' && !in_array($file['type'], $allowedVideoTypes)) {
        throw new Exception("Only MP4 and WebM videos are allowed");
    }

    return true;
}

function handleFileUpload($file, $type)
{
    $uploadDir = "../../public/uploads/" . ($type === 'video' ? 'videos/' : 'photos/');

    // Dizin yoksa oluştur
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid() . '_' . time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Failed to upload file");
    }

    // Burayı değiştirdik: public/ ekledik başına
    return "public/" . str_replace("../../public/", "", $uploadDir . $fileName);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'register':
                $accountHolder = $_SESSION['user_data']['full_name'];  // users tablosundan gelen full_name'i kullan
                $bankName = $_POST['bank_name'];
                $iban = $_POST['iban'];
                $taxNumber = $_POST['tax_number'];
                $dailyRate = floatval($_POST['daily_rate']);

                $db->beginTransaction();
                try {
                    // Check if user is already registered as freelancer
                    $stmt = $db->prepare("SELECT COUNT(*) FROM freelancers WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('You are already registered as a freelancer');
                    }

                    // Get user's registration date for experience calculation
                    $stmt = $db->prepare("SELECT created_at FROM users WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                    $experienceDays = calculateExperienceDays($userData['created_at']);

                    // Insert freelancer record
                    $stmt = $db->prepare("
                        INSERT INTO freelancers (
                            user_id, account_holder, bank_name, iban,
                            tax_number, daily_rate, experience_time,
                            availability_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'AVAILABLE')
                    ");

                    $stmt->execute([
                        $_SESSION['user_id'],
                        $accountHolder,
                        $bankName,
                        $iban,
                        $taxNumber,
                        $dailyRate,
                        $experienceDays
                    ]);

                    $db->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Successfully registered as freelancer'
                    ]);
                } catch (Exception $e) {
                    $db->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;

            case 'check_status':
                $stmt = $db->prepare("SELECT * FROM freelancers WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'isFreelancer' => $freelancer !== false,
                    'data' => $freelancer
                ]);
                break;
            case 'create_work':
                $db->beginTransaction();

                // Check if user is a freelancer
                $stmt = $db->prepare("SELECT freelancer_id FROM freelancers WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $freelancerId = $stmt->fetchColumn();

                if (!$freelancerId) {
                    throw new Exception("User is not a freelancer");
                }

                // Validate inputs
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $requirements = trim($_POST['requirements'] ?? '');
                $dailyRate = floatval($_POST['daily_rate'] ?? 0);
                $fixedPrice = floatval($_POST['fixed_price'] ?? 0);
                $tags = json_decode($_POST['tags'] ?? '[]', true);
                $visibility = isset($_POST['visibility']) ? 1 : 0;

                if (empty($title) || empty($description) || empty($category)) {
                    throw new Exception("Required fields are missing");
                }

                if (count($tags) > 5) {
                    throw new Exception("Maximum 5 tags allowed");
                }

                // Insert work
                $stmt = $db->prepare("
                    INSERT INTO works (
                        freelancer_id, title, description, category,
                        requirements, daily_rate, fixed_price, tags,
                        visibility
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $freelancerId,
                    $title,
                    $description,
                    $category,
                    $requirements,
                    $dailyRate,
                    $fixedPrice,
                    json_encode($tags),
                    $visibility
                ]);

                $workId = $db->lastInsertId();

                // Handle media uploads
                $mediaPaths = [];
                $imageCount = 0;

                // Handle images
                if (isset($_FILES['images'])) {
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($imageCount >= 5)
                            break;

                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'type' => $_FILES['images']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'size' => $_FILES['images']['size'][$key]
                        ];

                        if (validateMedia($file, 'image')) {
                            $path = handleFileUpload($file, 'image');
                            $mediaPaths['image_' . $imageCount] = $path;
                            $imageCount++;
                        }
                    }
                }

                // Handle video
                if (isset($_FILES['video']) && $_FILES['video']['size'] > 0) {
                    $file = $_FILES['video'];
                    try {
                        if (validateMedia($file, 'video')) {
                            $path = handleFileUpload($file, 'video');
                            $mediaPaths['video'] = $path;
                        }
                    } catch (Exception $e) {
                        // Video yükleme başarısız olursa işlemi durdurmayalım, sadece log tutalım
                        $logger->log("Video upload failed: " . $e->getMessage(), 'WARNING');
                    }
                }

                // Insert media paths
                if (!empty($mediaPaths)) {
                    $stmt = $db->prepare("
                        INSERT INTO works_media (work_id, media_paths)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$workId, json_encode($mediaPaths)]);
                }

                $db->commit();
                $logger->log("Work created successfully: ID $workId", 'INFO');
                echo json_encode(['success' => true, 'message' => 'Work created successfully']);
                break;

            case 'update_visibility':
                $workId = intval($_POST['work_id'] ?? 0);
                $visibility = intval($_POST['visibility'] ?? 0);

                $stmt = $db->prepare("
                    UPDATE works w
                    JOIN freelancers f ON w.freelancer_id = f.freelancer_id
                    SET w.visibility = ?
                    WHERE w.work_id = ? AND f.user_id = ?
                ");

                $result = $stmt->execute([$visibility, $workId, $_SESSION['user_id']]);
                if ($result) {
                    $logger->log("Work visibility updated: ID $workId", 'INFO');
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception("Failed to update visibility");
                }
                break;

            case 'delete_work':
                $workId = intval($_POST['work_id'] ?? 0);

                $db->beginTransaction();

                // Get media paths before deletion
                $stmt = $db->prepare("
                        SELECT wm.media_paths
                        FROM works w
                        JOIN works_media wm ON w.work_id = wm.work_id
                        JOIN freelancers f ON w.freelancer_id = f.freelancer_id
                        WHERE w.work_id = ? AND f.user_id = ?
                    ");
                $stmt->execute([$workId, $_SESSION['user_id']]);
                $mediaData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($mediaData) {
                    $mediaPaths = json_decode($mediaData['media_paths'], true);
                    foreach ($mediaPaths as $path) {
                        // public/ önekini kaldırıp, doğru yolu oluştur
                        $fullPath = "../../" . $path;
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                }

                // Delete work (cascade will handle media)
                $stmt = $db->prepare("
                    DELETE w FROM works w
                    JOIN freelancers f ON w.freelancer_id = f.freelancer_id
                    WHERE w.work_id = ? AND f.user_id = ?
                ");

                $result = $stmt->execute([$workId, $_SESSION['user_id']]);

                if ($result) {
                    $db->commit();
                    $logger->log("Work deleted: ID $workId", 'INFO');
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception("Failed to delete work");
                }
                break;

            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $logger->log("Error: " . $e->getMessage(), 'ERROR');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Freelancer kontrolü ve kullanıcı verilerini çekme
$stmt = $db->prepare("
    SELECT 
        f.*,
        u.created_at,
        u.full_name,
        DATEDIFF(CURRENT_DATE, u.created_at) as total_days
    FROM users u 
    LEFT JOIN freelancers f ON f.user_id = u.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Freelancer değilse experience_time'ı hesapla
if (!$userData['freelancer_id']) {
    $experienceDays = $userData['total_days'];
} else {
    // Freelancer ise mevcut experience_time'ı kullan
    $experienceDays = $userData['experience_time'];
}

// Eski veriyi korumak için freelancer bilgisini ayrı değişkende tutalım
$freelancer = $userData['freelancer_id'] ? $userData : null;

// Get user's works if freelancer
$works = [];
if ($freelancer) {
    $stmt = $db->prepare("
        SELECT w.*, wm.media_paths
        FROM works w
        LEFT JOIN works_media wm ON w.work_id = wm.work_id
        WHERE w.freelancer_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$freelancer['freelancer_id']]);
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="space-y-6">
    <?php if (!$freelancer): ?>
        <!-- Registration Form -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium mb-4">Become a Freelancer</h3>
            <form id="freelancerForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Account Holder Name</label>
                    <input type="text" name="account_holder"
                        value="<?php echo htmlspecialchars($_SESSION['user_data']['full_name']); ?>"
                        class="mt-1 block w-full rounded border p-2 bg-gray-100" disabled>
                </div>

                <div>
                    <label class="block text-sm font-medium">Bank Name</label>
                    <input type="text" name="bank_name" required class="mt-1 block w-full rounded border p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">IBAN</label>
                    <input type="text" name="iban" required class="mt-1 block w-full rounded border p-2"
                        pattern="TR\d{2}(\s\d{4}){5}\s\d{2}" maxlength="32"
                        oninput="this.value = this.value.replace(/[^0-9TR]/g, '').toUpperCase();"
                        placeholder="TR12 1234 1234 1234 1234 1234 12">
                </div>

                <div>
                    <label class="block text-sm font-medium">Tax Number</label>
                    <input type="text" name="tax_number" required class="mt-1 block w-full rounded border p-2"
                        pattern="[0-9]{10}" maxlength="10"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);">
                </div>

                <div>
                    <label class="block text-sm font-medium">Daily Rate (₺)</label>
                    <input type="number" name="daily_rate" required min="0" step="0.01"
                        class="mt-1 block w-full rounded border p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">Experience</label>
                    <div class="mt-1 p-2 bg-gray-100 rounded border">
                        <?php
                        $experienceDays = calculateExperienceDays($userData['created_at']);
                        echo formatExperienceTime($experienceDays);
                        echo " (Member since " . date('F j, Y', strtotime($userData['created_at'])) . ")";
                        ?>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Register as Freelancer
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- Freelancer Dashboard -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-medium mb-4">Freelancer Dashboard</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Account Holder</p>
                        <p class="font-medium"><?php echo htmlspecialchars($freelancer['account_holder']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Daily Rate</p>
                        <p class="font-medium">₺<?php echo number_format($freelancer['daily_rate'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Experience</p>
                        <p class="font-medium"><?php echo formatExperienceTime($freelancer['experience_time']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Status</p>
                        <p class="font-medium"><?php echo $freelancer['availability_status']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Works Section -->
        <div class="bg-white rounded-lg shadow">
            <h3 class="font-bold text-lg p-6 border-b">Active Works</h3>
            <div class="p-6">
                <?php
                // Get active jobs for the freelancer
                $stmt = $db->prepare("
            SELECT j.*, u.username as client_username, u.full_name as client_name,
                   w.title as work_title
            FROM jobs j
            JOIN users u ON j.user_id = u.user_id
            JOIN works w ON j.title = w.title
            JOIN freelancers f ON j.freelancer_id = f.freelancer_id
            WHERE f.user_id = :user_id 
            AND j.status IN ('IN_PROGRESS', 'DELIVERED')
            ORDER BY j.created_at DESC
        ");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $activeJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($activeJobs)): ?>
                    <p class="text-gray-500 text-center">No active works found.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($activeJobs as $job): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium"><?php echo htmlspecialchars($job['work_title']); ?></h4>
                                        <p class="text-sm text-gray-600">Client:
                                            <?php echo htmlspecialchars($job['client_name']); ?>
                                            (@<?php echo htmlspecialchars($job['client_username']); ?>)
                                        </p>
                                        <p class="text-sm text-gray-600">Budget: ₺<?php echo number_format($job['budget'], 2); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">Status: <span
                                                class="font-medium <?php echo $job['status'] === 'DELIVERED' ? 'text-yellow-600' : 'text-blue-600'; ?>">
                                                <?php echo $job['status']; ?></span></p>
                                    </div>
                                    <?php if ($job['status'] === 'IN_PROGRESS'): ?>
                                        <button onclick="deliverWork(<?php echo $job['job_id']; ?>)"
                                            class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                            Deliver Work
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function deliverWork(jobId) {
                if (!confirm('Are you sure you want to deliver this work?')) {
                    return;
                }

                $.post('components/job_actions.php', {
                    action: 'deliver',
                    job_id: jobId
                }).done(function (response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'An error occurred');
                    }
                }).fail(function () {
                    alert('Network error occurred');
                });
            }
        </script>

        <!-- Works Management Section -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="showTab('createWork')"
                        class="tab-btn w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm" data-tab="createWork">
                        Create New Work
                    </button>
                    <button onclick="showTab('worksList')"
                        class="tab-btn w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm" data-tab="worksList">
                        My Works
                    </button>
                </nav>
            </div>

            <!-- Create Work Form -->
            <div id="createWork" class="tab-content p-6 space-y-4">
                <form id="workForm" class="space-y-4" enctype="multipart/form-data">
                    <div>
                        <label class="block text-sm font-medium">Title</label>
                        <input type="text" name="title" required class="mt-1 block w-full rounded border p-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Description</label>
                        <textarea name="description" required class="mt-1 block w-full rounded border p-2"
                            rows="4"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Category</label>
                        <input type="text" name="category" required class="mt-1 block w-full rounded border p-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Requirements</label>
                        <textarea name="requirements" required class="mt-1 block w-full rounded border p-2"
                            rows="3"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium">Daily Rate (₺)</label>
                            <input type="number" name="daily_rate" min="0" step="0.01"
                                class="mt-1 block w-full rounded border p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Fixed Price (₺)</label>
                            <input type="number" name="fixed_price" min="0" step="0.01"
                                class="mt-1 block w-full rounded border p-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Tags (comma separated, max 5)</label>
                        <input type="text" name="tags" class="mt-1 block w-full rounded border p-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Images (max 5, max 20MB each)</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="mt-1 block w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Video (optional, max 30MB)</label>
                        <input type="file" name="video" accept="video/*" class="mt-1 block w-full">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="visibility" id="visibility" class="mr-2" checked>
                        <label for="visibility" class="text-sm">Make this work public</label>
                    </div>

                    <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Create Work
                    </button>
                </form>
            </div>

            <!-- Works List -->
            <div id="worksList" class="tab-content p-6 hidden">
                <?php
                // Get user's works
                $stmt = $db->prepare("
                        SELECT w.*, wm.media_paths
                        FROM works w
                        LEFT JOIN works_media wm ON w.work_id = wm.work_id
                        WHERE w.freelancer_id = ?
                        ORDER BY w.created_at DESC
                    ");
                $stmt->execute([$freelancer['freelancer_id']]);
                $works = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($works)):
                    ?>
                    <div class="text-center py-8 text-gray-500">
                        You haven't created any works yet.
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($works as $work): ?>
                            <div class="border rounded p-4 space-y-3" id="work-<?php echo $work['work_id']; ?>">
                                <div class="flex justify-between items-start">
                                    <h4 class="text-lg font-medium"><?php echo htmlspecialchars($work['title']); ?></h4>
                                    <div class="space-x-2">
                                        <button
                                            onclick="toggleVisibility(<?php echo $work['work_id']; ?>, <?php echo $work['visibility']; ?>)"
                                            class="px-3 py-1 rounded text-sm <?php echo $work['visibility'] ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'; ?>">
                                            <?php echo $work['visibility'] ? 'Public' : 'Private'; ?>
                                        </button>
                                        <button onclick="deleteWork(<?php echo $work['work_id']; ?>)"
                                            class="px-3 py-1 bg-red-500 text-white rounded text-sm">
                                            Delete
                                        </button>
                                    </div>
                                </div>

                                <!-- Work details -->
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-600">Category</p>
                                        <p><?php echo htmlspecialchars($work['category']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">Pricing</p>
                                        <p>
                                            <?php if ($work['daily_rate']): ?>
                                                Daily: ₺<?php echo number_format($work['daily_rate'], 2); ?><br>
                                            <?php endif; ?>
                                            <?php if ($work['fixed_price']): ?>
                                                Fixed: ₺<?php echo number_format($work['fixed_price'], 2); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <?php if (!empty($work['media_paths'])): ?>
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <?php
                                        $mediaPaths = json_decode($work['media_paths'], true);
                                        foreach ($mediaPaths as $type => $path):
                                            if (strpos($type, 'image_') !== false):
                                                ?>
                                                <div class="relative pt-[56.25%]">
                                                    <img src="/<?php echo htmlspecialchars($path); ?>" alt="Work image"
                                                        class="absolute inset-0 w-full h-full object-cover rounded">
                                                </div>
                                                <?php
                                            endif;
                                        endforeach;

                                        if (isset($mediaPaths['video'])):
                                            ?>
                                            <div class="relative pt-[56.25%]">
                                                <video src="/<?php echo htmlspecialchars($mediaPaths['video']); ?>" controls
                                                    class="absolute inset-0 w-full h-full rounded">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
    function showTab(tabId) {
        // Tüm tab içeriklerini gizle
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Tüm tab butonlarından active class'ı kaldır
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Seçili tab'ı göster
        document.getElementById(tabId).classList.remove('hidden');

        // Seçili tab butonunu active yap
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('border-blue-500', 'text-blue-600');
        document.querySelector(`[data-tab="${tabId}"]`).classList.remove('border-transparent', 'text-gray-500');
    }

    $(document).ready(function () {

        const hasFreelancerDashboard = document.getElementById('createWork') !== null;

        function showTab(tabId) {
            // Freelancer dashboard varsa tab işlemlerini yap
            if (hasFreelancerDashboard) {
                // Tüm tab içeriklerini gizle
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });

                // Tüm tab butonlarından active class'ı kaldır
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('border-blue-500', 'text-blue-600');
                    btn.classList.add('border-transparent', 'text-gray-500');
                });

                // Seçili tab'ı göster
                document.getElementById(tabId).classList.remove('hidden');

                // Seçili tab butonunu active yap
                document.querySelector(`[data-tab="${tabId}"]`).classList.add('border-blue-500', 'text-blue-600');
                document.querySelector(`[data-tab="${tabId}"]`).classList.remove('border-transparent', 'text-gray-500');
            }
        }

        // Sayfa yüklendiğinde, freelancer dashboard varsa varsayılan tab'ı göster
        if (hasFreelancerDashboard) {
            showTab('createWork');
        }

        window.showTab = showTab;

        $('#freelancerForm').submit(function (e) {
            e.preventDefault();

            // IBAN doğrulama
            const iban = $('input[name="iban"]').val().replace(/\s/g, '');
            if (iban.length !== 26) {
                alert('Please enter a valid Turkish IBAN (26 characters)');
                return false;
            }

            if (!iban.startsWith('TR')) {
                alert('IBAN must start with TR');
                return false;
            }

            // Tax number kontrolü (10 haneli olmalı)
            const taxNumber = $('input[name="tax_number"]').val().trim();
            if (taxNumber.length !== 10 || !/^\d{10}$/.test(taxNumber)) {
                alert('Tax number must be exactly 10 digits');
                return false;
            }

            // Daily rate kontrolü
            const dailyRate = parseFloat($('input[name="daily_rate"]').val());
            if (isNaN(dailyRate) || dailyRate <= 0) {
                alert('Please enter a valid daily rate');
                return false;
            }

            // Bank name kontrolü
            const bankName = $('input[name="bank_name"]').val().trim();
            if (bankName.length < 2) {
                alert('Please enter a valid bank name');
                return false;
            }

            // Form verilerini manuel olarak objede topluyoruz
            const formData = {
                action: 'register',
                bank_name: bankName,
                iban: iban,
                tax_number: taxNumber,
                daily_rate: dailyRate
            };

            const submitButton = $(this).find('button[type="submit"]');
            const originalButtonText = submitButton.html();
            submitButton.html('<span class="spinner">Processing...</span>').prop('disabled', true);

            $.ajax({
                url: 'components/freelancer_subs.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                            submitButton.html(originalButtonText).prop('disabled', false);
                        }
                    } catch (e) {
                        alert('An error occurred while processing the response');
                        submitButton.html(originalButtonText).prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    alert('An error occurred while submitting the form: ' + error);
                    submitButton.html(originalButtonText).prop('disabled', false);
                }
            });
        });

        // Input maskeleri
        $('input[name="tax_number"]').on('keypress input', function (e) {
            // Sadece sayılara izin ver
            if (!/^\d*$/.test(e.key) && e.type === 'keypress') {
                e.preventDefault();
                return false;
            }

            // 10 karakterle sınırla
            if (this.value.length >= 10 && e.type === 'keypress') {
                e.preventDefault();
                return false;
            }

            // Input event için temizleme
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        $('input[name="bank_name"]').on('input', function () {
            let value = this.value.replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, ''); // Türkçe karakterleri de ekledik
            this.value = value;
        });

        $('input[name="daily_rate"]').on('input', function () {
            let value = this.value.replace(/[^\d.]/g, '');
            const dots = value.match(/\./g);
            if (dots && dots.length > 1) {
                value = value.slice(0, value.lastIndexOf('.'));
            }
            this.value = value;
        });

        // Format IBAN input
        $('input[name="iban"]').on('keypress input', function (e) {
            if (e.type === 'keypress' && !/^[0-9TR]$/i.test(e.key)) {
                e.preventDefault();
                return false;
            }

            let value = this.value.replace(/[^0-9TR]/g, '').toUpperCase();

            // TR ile başlamasını sağla
            if (!value.startsWith('TR')) {
                if (value.length > 0) {
                    value = 'TR' + value;
                }
            }

            // 26 karakterle sınırla (TR dahil)
            value = value.slice(0, 26);

            // IBAN formatlaması
            if (value.length > 2) {
                let formattedValue = '';
                const numbers = value.slice(2); // TR'den sonraki sayıları al

                // İlk 2 rakam (TR'den hemen sonra)
                formattedValue = value.slice(0, 2) + numbers.slice(0, 2);

                // Ortadaki 20 rakam (4'erli gruplar)
                for (let i = 2; i < 22; i += 4) {
                    if (numbers.length > i) {
                        formattedValue += ' ' + numbers.slice(i, i + 4);
                    }
                }

                // Son 2 rakam (ayrı grup)
                if (numbers.length > 22) {
                    formattedValue += ' ' + numbers.slice(22, 24);
                }

                value = formattedValue;
            }

            this.value = value;
        });

        // Format daily rate to 2 decimal places on blur
        $('input[name="daily_rate"]').on('blur', function () {
            const amount = parseFloat($(this).val());
            if (!isNaN(amount)) {
                $(this).val(amount.toFixed(2));
            }
        });

        $('#workForm').submit(function (e) {
            e.preventDefault();

            // Form validation
            const title = $('input[name="title"]').val().trim();
            const description = $('textarea[name="description"]').val().trim();
            const category = $('input[name="category"]').val().trim();
            const requirements = $('textarea[name="requirements"]').val().trim();
            const dailyRate = parseFloat($('input[name="daily_rate"]').val()) || 0;
            const fixedPrice = parseFloat($('input[name="fixed_price"]').val()) || 0;
            const tagsInput = $('input[name="tags"]').val().trim();
            const tags = tagsInput ? tagsInput.split(',').map(tag => tag.trim()) : [];

            // Basic validation
            if (!title || !description || !category || !requirements) {
                alert('Please fill in all required fields');
                return false;
            }

            if (tags.length > 5) {
                alert('Maximum 5 tags allowed');
                return false;
            }

            if (dailyRate < 0 || fixedPrice < 0) {
                alert('Prices cannot be negative');
                return false;
            }

            // Media validation
            const imageFiles = $('input[name="images[]"]')[0].files;
            const videoFile = $('input[name="video"]')[0].files[0];

            if (imageFiles.length > 5) {
                alert('Maximum 5 images allowed');
                return false;
            }

            // Create FormData object
            const formData = new FormData(this);
            formData.append('action', 'create_work');
            formData.append('tags', JSON.stringify(tags));

            // Show loading state
            const submitButton = $(this).find('button[type="submit"]');
            const originalButtonText = submitButton.html();
            submitButton.html('<span class="spinner">Processing...</span>').prop('disabled', true);

            // Submit form
            $.ajax({
                url: 'components/freelancer_subs.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                            submitButton.html(originalButtonText).prop('disabled', false);
                        }
                    } catch (e) {
                        alert('An error occurred while processing the response');
                        submitButton.html(originalButtonText).prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    alert('An error occurred while submitting the form: ' + error);
                    submitButton.html(originalButtonText).prop('disabled', false);
                }
            });
        });
    });

    // Toggle work visibility
    function toggleVisibility(workId, currentVisibility) {
        if (!confirm('Are you sure you want to change the visibility of this work?')) {
            return;
        }

        $.ajax({
            url: 'components/freelancer_subs.php',
            type: 'POST',
            data: {
                action: 'update_visibility',
                work_id: workId,
                visibility: currentVisibility ? 0 : 1
            },
            success: function (response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to update visibility');
                    }
                } catch (e) {
                    alert('An error occurred while processing the response');
                }
            },
            error: function (xhr, status, error) {
                alert('An error occurred: ' + error);
            }
        });
    }

    // Delete work
    function deleteWork(workId) {
        if (!confirm('Are you sure you want to delete this work? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: 'components/freelancer_subs.php',
            type: 'POST',
            data: {
                action: 'delete_work',
                work_id: workId
            },
            success: function (response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        $(`#work-${workId}`).fadeOut(400, function () {
                            $(this).remove();
                        });
                    } else {
                        alert('Failed to delete work');
                    }
                } catch (e) {
                    alert('An error occurred while processing the response');
                }
            },
            error: function (xhr, status, error) {
                alert('An error occurred: ' + error);
            }
        });
    }
</script>