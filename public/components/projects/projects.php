<?php
// components/projects/projects.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

try {
    $dbConfig = require '../../../config/database.php';

    // Database bağlantısını test et
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL sorgusunu çalıştırmadan önce debug edelim
    $user_id = $_SESSION['user_id'];
    $sql = "
        SELECT *
        FROM projects
        WHERE (owner_id = :user_id 
        OR JSON_CONTAINS(collaborators, :user_id_json, '$'))
        AND status = 'active'
        ORDER BY created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'user_id_json' => json_encode($user_id)
    ]);

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Detaylı hata mesajı göster
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUREID - Projeler</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/sources/css/main.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .projects-container {
            width: 95%;
            margin: 40px auto;
            padding: 0 10px;
            margin-top: 100px;
        }

        .projects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .new-project-btn {
            background: #c3ff00;
            color: #000;
            padding: 8px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .new-project-btn span {
            transform: translateY(0);
            transition: transform 0.3s ease;
            padding-left: 22px;
        }

        .new-project-btn img {
            position: absolute;
            left: 16px;
        }

        .new-project-btn::after {
            content: "Yeni Proje";
            position: absolute;
            left: 38px;
            bottom: -100%;
            transition: bottom 0.3s ease;
        }

        .new-project-btn:hover {
            background: #9ec700;
        }

        .new-project-btn:hover span {
            transform: translateY(-150%);
        }

        .new-project-btn:hover::after {
            bottom: 8px;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .project-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 56px;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .project-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
        }

        .project-visibility {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .project-description {
            color: #4b5563;
            margin-bottom: 15px;
            font-size: 0.875rem;
        }

        .project-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .project-tag {
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            color: #4b5563;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .project-collaborators {
            display: flex;
            align-items: center;
            gap: -8px;
        }

        .collaborator-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid white;
            margin-left: -8px;
        }

        .owner-avatar {
            margin-left: 0;
            border: 5px solid white;
            z-index: 5;
            width: 32px;
            height: 32px;
        }

        .collaborator-count {
            margin-left: 8px;
            background: #f3f4f6;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: #6b7280;
        }

        .project-goto {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px 20px;
            background: #c3ff00;
            border-radius: 0 0 15px 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            color: #000;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .project-goto:hover {
            background: #9ec700;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-modal {
            cursor: pointer;
            padding: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            background-color: white;
        }

        .tags-input {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 5px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            min-height: 42px;
        }

        .tag {
            background: #e5e7eb;
            padding: 2px 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tag-remove {
            cursor: pointer;
            color: #6b7280;
        }

        .tags-input input {
            border: none;
            outline: none;
            padding: 5px;
            flex: 1;
            min-width: 60px;
        }

        .submit-btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            width: 100%;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .empty-state img {
            width: 200px;
            margin: 0 auto 20px;
        }
    </style>
</head>

<body>
    <?php include '../menu.php'; ?>

    <div class="projects-container">
        <div class="projects-header">
            <h1 class="text-2xl font-semibold">Projelerim</h1>
            <button class="new-project-btn" onclick="openNewProjectModal()">
                <img src="/sources/icons/bulk/add.svg" alt="add" class="w-4 h-4">
                <span>Yeni Proje</span>
            </button>
        </div>

        <div class="projects-grid">
            <?php if (empty($projects)): ?>
                <div class="empty-state col-span-full">
                    <img src="empty.svg" alt="No projects">
                    <h3 class="text-xl font-semibold mb-2">Henüz projeniz yok</h3>
                    <p class="mb-4">Yeni bir proje oluşturarak başlayın!</p>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <?php
                    // Define visibility icons at the beginning of the loop
                    $visibilityIcons = [
                        'public' => 'global',
                        'private' => 'lock',
                        'followers' => 'user-minus',
                        'connections' => 'people'
                    ];
                    $icon = $visibilityIcons[$project['visibility']];
                    ?>
                    <div class="project-card">
                        <div class="project-header">
                            <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                            <div class="flex items-center gap-2">
                                <span class="project-visibility">
                                    <img src="/sources/icons/bulk/<?php echo $icon; ?>.svg"
                                        alt="<?php echo $project['visibility']; ?>" class="w-5 h-5">
                                </span>
                                <?php if ($project['owner_id'] == $_SESSION['user_id']): ?>
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = !open" type="button" class="p-1 hover:bg-gray-100 rounded-full">
                                            <img src="/sources/icons/bulk/more.svg" alt="more" class="w-5 h-5">
                                        </button>
                                        <div x-show="open" @click.away="open = false" style="display: none;"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10">
                                            <button onclick="showProjectSettings('<?php echo $project['project_id']; ?>')"
                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2 text-[12px]">
                                                <img src="/sources/icons/bulk/user-plus.svg" alt="" class="w-4 h-4">
                                                Kullanıcı Ekle
                                            </button>
                                            <button onclick="showProjectSettings('<?php echo $project['project_id']; ?>')"
                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2 text-[12px]">
                                                <img src="/sources/icons/bulk/setting-2.svg" alt="" class="w-4 h-4">
                                                Ayarlar
                                            </button>
                                            <button onclick="deleteProject('<?php echo $project['project_id']; ?>')"
                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 text-red-600 flex items-center gap-2 text-[12px]">
                                                <img src="/sources/icons/bulk/trash.svg" alt="" class="w-4 h-4">
                                                Projeyi Sil
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                        <div class="project-tags">
                            <?php
                            $tags = json_decode($project['tags'], true);
                            if ($tags) {
                                foreach ($tags as $tag) {
                                    echo '<span class="project-tag">' . htmlspecialchars($tag) . '</span>';
                                }
                            }
                            ?>
                        </div>
                        <div class="project-meta">
                            <div class="project-collaborators">
                                <?php
                                // Owner avatar
                                $ownerStmt = $db->prepare("
                SELECT profile_photo_url 
                FROM user_extended_details 
                WHERE user_id = ?
            ");
                                $ownerStmt->execute([$project['owner_id']]);
                                $ownerAvatar = $ownerStmt->fetchColumn();
                                echo '<img src="/public/' . htmlspecialchars($ownerAvatar) . '" 
                      alt="Owner" 
                      class="collaborator-avatar owner-avatar" 
                      title="Proje Sahibi">';

                                // Collaborator avatars
                                $collaborators = json_decode($project['collaborators'], true) ?? [];
                                $maxDisplay = 3;
                                $count = 0;

                                if ($collaborators) {
                                    foreach ($collaborators as $collaborator) {
                                        if ($count < $maxDisplay) {
                                            $avatarStmt = $db->prepare("
                            SELECT profile_photo_url 
                            FROM user_extended_details 
                            WHERE user_id = ?
                        ");
                                            $avatarStmt->execute([$collaborator]);
                                            $avatar = $avatarStmt->fetchColumn();

                                            echo '<img src="/public/' . htmlspecialchars($avatar) . '" 
                                  alt="Collaborator" 
                                  class="collaborator-avatar">';
                                        }
                                        $count++;
                                    }

                                    if (count($collaborators) > $maxDisplay) {
                                        echo '<span class="collaborator-count">+' .
                                            (count($collaborators) - $maxDisplay) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                            <span><?php
                            $date = new DateTime($project['created_at']);
                            echo $date->format('d.m.Y');
                            ?></span>
                        </div>
                        <div class="project-goto"
                            onclick="window.location.href='editor.php?id=<?php echo $project['project_id']; ?>'">
                            <img src="/sources/icons/bulk/arrow-right.svg" alt="" class="w-4 h-4">
                            <span>Projeye Git</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['project_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['project_error']; ?></span>
        </div>
        <?php unset($_SESSION['project_error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['project_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['project_success']; ?></span>
        </div>
        <?php unset($_SESSION['project_success']); ?>
    <?php endif; ?>

    <!-- New Project Modal -->
    <div id="newProjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-semibold">Yeni Proje Oluştur</h2>
                <span class="close-modal" onclick="closeNewProjectModal()">&times;</span>
            </div>
            <form id="newProjectForm" onsubmit="submitNewProject(event)">
                <div class="form-group">
                    <label class="form-label" for="projectTitle">Proje Adı</label>
                    <input type="text" id="projectTitle" name="title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="projectDescription">Proje Açıklaması</label>
                    <textarea id="projectDescription" name="description" class="form-input form-textarea"
                        required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Etiketler</label>
                    <div class="tags-input" id="tagsInput">
                        <input type="text" placeholder="Enter tuşu ile etiket ekleyin">
                    </div>
                    <input type="hidden" name="tags" id="tagsHidden">
                </div>

                <div class="form-group">
                    <label class="form-label" for="projectVisibility">Gizlilik</label>
                    <select id="projectVisibility" name="visibility" class="form-select">
                        <option value="public">Herkese Açık</option>
                        <option value="private">Gizli</option>
                        <option value="followers">Sadece Takip Edilenler</option>
                        <option value="connections">Takipçi ve Takip Edilenler</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Proje Oluştur</button>
            </form>
        </div>
    </div>

    <div id="genericModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-semibold" id="modalTitle"></h2>
                <span class="close-modal" onclick="closeGenericModal()">&times;</span>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        // Modal functions
        function openNewProjectModal() {
            document.getElementById('newProjectModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeNewProjectModal() {
            document.getElementById('newProjectModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Tags handling
        const tagsInput = document.querySelector('#tagsInput input');
        const tagsHidden = document.getElementById('tagsHidden');
        let tags = [];

        function updateTags() {
            // Update hidden input
            tagsHidden.value = JSON.stringify(tags);

            // Update visual representation
            const tagElements = tags.map(tag => `
                <div class="tag">
                    <span>${tag}</span>
                    <span class="tag-remove" onclick="removeTag('${tag}')">&times;</span>
                </div>
            `).join('');

            const input = '<input type="text" placeholder="Enter tuşu ile etiket ekleyin">';
            document.getElementById('tagsInput').innerHTML = tagElements + input;

            // Reattach event listener to new input
            document.querySelector('#tagsInput input').addEventListener('keydown', handleTagInput);
        }

        function removeTag(tag) {
            tags = tags.filter(t => t !== tag);
            updateTags();
        }

        function handleTagInput(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const tag = e.target.value.trim();
                if (tag && !tags.includes(tag)) {
                    tags.push(tag);
                    updateTags();
                }
                e.target.value = '';
            }
        }

        tagsInput.addEventListener('keydown', handleTagInput);

        async function submitNewProject(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('api/create-project.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Bir hata oluştu');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Bir hata oluştu');
            }
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('newProjectModal');
            if (event.target === modal) {
                closeNewProjectModal();
            }
        }

        function generateInviteLink(projectId) {
            fetch('api/generate-invite-link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ project_id: projectId })
            })
                .then(response => response.text())
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON:', text);
                        throw new Error('Invalid JSON response');
                    }
                })
                .then(data => {
                    if (data.success) {
                        const inviteLink = window.location.origin + '/public/components/projects/join.php?code=' + data.invite_code;
                        showModal('Davet Linki', `
                <p class="mb-4">Bu linki paylaşarak kullanıcıları projenize davet edebilirsiniz:</p>
                <div class="flex gap-2">
                    <input type="text" value="${inviteLink}" 
                           class="form-input flex-1" id="inviteLinkInput" readonly>
                    <button onclick="copyInviteLink()" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                        <img src="/sources/icons/bulk/copy.svg" alt="copy" class="w-5 h-5">
                    </button>
                </div>
            `);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu');
                });
        }

        function deleteProject(projectId) {
            if (confirm('Bu projeyi silmek istediğinize emin misiniz?')) {
                fetch('api/delete-project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                    .then(response => response.text())  // Önce text olarak al
                    .then(text => {
                        try {
                            return JSON.parse(text);  // Sonra JSON'a çevir
                        } catch (e) {
                            console.error('Invalid JSON:', text);
                            throw new Error('Invalid JSON response');
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Bir hata oluştu');
                    });
            }
        }

        function showProjectSettings(projectId) {
            fetch('api/get-project-details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ project_id: projectId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showModal('Proje Yönetimi', `
                <div class="project-management-tabs">
                    <div class="tabs-header mb-4">
                        <button onclick="switchTab('invite', this)" class="tab-btn active">Davet Et</button>
                        <button onclick="switchTab('members', this)" class="tab-btn">Katılımcılar</button>
                        <button onclick="switchTab('settings', this)" class="tab-btn">Ayarlar</button>
                    </div>
                    
                    <div id="invite-tab" class="tab-content active">
                        <p class="mb-4">Bu linki paylaşarak kullanıcıları projenize davet edebilirsiniz:</p>
                        <div class="flex gap-2 mb-4">
                            <input type="text" value="${window.location.origin}/public/components/projects/join.php?code=${data.invite_code}" 
                                   class="form-input flex-1" id="inviteLinkInput" readonly>
                            <button onclick="copyInviteLink()" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                                <img src="/sources/icons/bulk/copy.svg" alt="copy" class="w-5 h-5">
                            </button>
                        </div>
                    </div>
                    
                    <div id="members-tab" class="tab-content hidden">
                        <div class="members-list">
                            ${generateMembersList(data.members)}
                        </div>
                    </div>
                    
                    <div id="settings-tab" class="tab-content hidden">
                        <form onsubmit="updateProject(event, ${projectId})" class="space-y-4">
                            <div class="form-group">
                                <label class="form-label">Proje Adı</label>
                                <input type="text" name="title" class="form-input" 
                                       value="${data.project.title}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Açıklama</label>
                                <textarea name="description" class="form-input" 
                                          required>${data.project.description}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gizlilik</label>
                                <select name="visibility" class="form-select">
                                    <option value="public" ${data.project.visibility === 'public' ? 'selected' : ''}>Herkese Açık</option>
                                    <option value="private" ${data.project.visibility === 'private' ? 'selected' : ''}>Gizli</option>
                                    <option value="followers" ${data.project.visibility === 'followers' ? 'selected' : ''}>Sadece Takip Edilenler</option>
                                    <option value="connections" ${data.project.visibility === 'connections' ? 'selected' : ''}>Takipçi ve Takip Edilenler</option>
                                </select>
                            </div>
                            <button type="submit" class="submit-btn">Güncelle</button>
                        </form>
                    </div>
                </div>
            `);
                    }
                });
        }

        function generateMembersList(members) {
            return members.map(member => `
        <div class="member-item flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
            <div class="flex items-center gap-3">
                <img src="/public/${member.avatar}" alt="" class="w-8 h-8 rounded-full">
                <div>
                    <div class="font-medium">${member.name}</div>
                    <div class="text-sm text-gray-500">${member.role}</div>
                </div>
            </div>
            ${member.role !== 'owner' ? `
                <button onclick="removeMember(${member.user_id}, ${member.project_id})" 
                        class="text-red-600 hover:text-red-800">
                    <img src="/sources/icons/bulk/user-minus.svg" alt="" class="w-5 h-5">
                </button>
            ` : ''}
        </div>
    `).join('');
        }

        function switchTab(tabId, button) {
            // Tüm tab içeriklerini gizle
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            // Tüm tab butonlarından active sınıfını kaldır
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Seçilen tabı göster ve butonunu aktif yap
            document.getElementById(`${tabId}-tab`).classList.remove('hidden');
            button.classList.add('active');
        }

        function removeMember(userId, projectId) {
            if (confirm('Bu kullanıcıyı projeden çıkarmak istediğinize emin misiniz?')) {
                fetch('api/remove-member.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        project_id: projectId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showProjectSettings(projectId); // Modalı yenile
                        }
                    });
            }
        }

        function updateProject(e, projectId) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('api/update-project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    title: formData.get('title'),
                    description: formData.get('description'),
                    visibility: formData.get('visibility')
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
        }

        function copyInviteLink() {
            const input = document.getElementById('inviteLinkInput');
            input.select();
            document.execCommand('copy');
            alert('Link kopyalandı!');
        }

        function showModal(title, content) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('genericModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeGenericModal() {
            document.getElementById('genericModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    </script>
</body>

</html>