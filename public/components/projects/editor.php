<?php
// components/projects/editor.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Proje ID'sini URL'den alalım
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    header('Location: /public/components/projects/projects.php');
    exit;
}

try {
    // Database config dosyasını doğru yoldan yükleyelim
    $dbConfig = require_once(__DIR__ . '/../../../config/database.php');

    // PDO bağlantısını kuralım
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        $dbConfig['host'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );

    $db = new PDO(
        $dsn,
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Proje bilgilerini çekelim
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as owner_name
        FROM projects p
        JOIN users u ON p.owner_id = u.user_id
        WHERE p.project_id = :project_id
    ");
    $stmt->execute(['project_id' => $project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header('Location: /public/components/projects/projects.php');
        exit;
    }

    // Kullanıcının yetkisi var mı kontrol edelim
    $hasAccess = false;
    if ($project['owner_id'] == $_SESSION['user_id']) {
        $hasAccess = true;
        $isOwner = true;
    } else {
        $collaborators = json_decode($project['collaborators'], true);
        if (in_array($_SESSION['user_id'], $collaborators)) {
            $hasAccess = true;
            $isOwner = false;
        }
    }

    if (!$hasAccess) {
        header('Location: /components/projects/projects.php');
        exit;
    }

    // Proje dosyasının yolu
    $projectFileName = $project['file_path'] ?? md5($project_id . '_' . time()) . '.json';
    $projectFilePath = __DIR__ . "/../../../storage/projects/{$projectFileName}";

    // Eğer dosya varsa içeriğini okutalım
    $projectData = null;
    if (file_exists($projectFilePath)) {
        $projectData = json_decode(file_get_contents($projectFilePath), true);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lureid Code Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1e1e1e;
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .navbar {
            background-color: #2c2c2c;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3e3e3e;
            height: 50px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-size: 1.2rem;
            font-weight: 500;
            color: #fff;
            text-transform: lowercase;
            cursor: pointer;
            text-decoration: none;
        }

        .project-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .project-name {
            font-size: 16px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .project-name img {
            width: 14px;
            height: 14px;
            opacity: 0.9;
            cursor: pointer;
            filter: brightness(0) invert(1);
        }

        .project-author {
            font-size: 12px;
            color: #888;
        }

        .nav-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-controls button {
            background-color: #c3ff00;
            color: #000;
            border: none;
            padding: 8px 16px;
            height: 35px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .nav-controls button:hover {
            background-color: #b3e600;
        }

        .settings-button {
            background-color: #c3ff00 !important;
            border: none;
            color: #000;
            cursor: pointer;
            padding: 4px 8px;
            height: 35px !important;
            border-radius: 3px;
            transition: all 0.2s;
        }

        .settings-button:hover {
            background-color: #b3e600 !important;
        }

        .container {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 10px;
            gap: 10px;
            height: calc(100vh - 50px);
            overflow: hidden;
        }

        .editors-container {
            display: flex;
            gap: 10px;
            height: 45%;
            min-height: 200px;
        }

        .editor-container {
            flex: 1;
            background-color: #252526;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .editor-header {
            background-color: #333;
            padding: 8px 12px;
            font-size: 0.9rem;
            color: #ddd;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid #3e3e3e;
            height: 35px;
            font-family: 'Poppins', sans-serif;
        }

        .editor-header img {
            width: 16px;
            height: 16px;
            opacity: 0.9;
        }

        .html-icon {
            filter: brightness(0) saturate(100%) invert(35%) sepia(52%) saturate(2880%) hue-rotate(343deg) brightness(95%) contrast(92%);
        }

        .css-icon {
            filter: brightness(0) saturate(100%) invert(34%) sepia(40%) saturate(1117%) hue-rotate(177deg) brightness(93%) contrast(89%);
        }

        .js-icon {
            filter: brightness(0) saturate(100%) invert(88%) sepia(61%) saturate(1095%) hue-rotate(359deg) brightness(105%) contrast(94%);
        }

        .preview-icon {
            filter: brightness(0) invert(1);
        }

        .editor {
            height: calc(100% - 35px);
        }

        #preview-container {
            height: 55%;
            background-color: #252526;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        #preview {
            background-color: white;
            height: calc(100% - 35px);
            overflow: auto;
        }

        #preview iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        @media (max-width: 768px) {
            .editors-container {
                flex-direction: column;
                height: auto;
            }

            .editor-container {
                height: 200px;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.js"></script>
</head>

<body>
    <nav class="navbar">
        <div class="logo-section">
            <a href="/public/index.php" class="logo">lureid</a>
            <div class="project-info">
                <div class="project-name">
                    <span id="projectTitle"><?php echo htmlspecialchars($project['title']); ?></span>
                    <?php if ($isOwner): ?>
                        <img src="/sources/icons/bulk/edit-2.svg" alt="Düzenle" title="Proje adını düzenle"
                            onclick="editProjectName()">
                    <?php endif; ?>
                </div>
                <div class="project-author"><?php echo htmlspecialchars($project['owner_name']); ?></div>
            </div>
        </div>
        <div class="nav-controls">
            <button class="settings-button">
                <img src="/sources/icons/bulk/setting-2.svg" alt="Ayarlar" width="20" height="20">
            </button>
            <button id="saveButton" <?php echo $projectData ? 'disabled' : ''; ?>>
                <img src="/sources/icons/bulk/save-2.svg" alt="Kaydet" width="20" height="20">
                <?php echo $projectData ? 'Paylaşıldı' : 'Paylaş'; ?>
            </button>
        </div>
    </nav>

    <div class="container">
        <div class="editors-container">
            <div class="editor-container">
                <div class="editor-header">
                    <img src="/sources/icons/Logos/bold/html-5.svg" alt="HTML" class="html-icon">
                    HTML
                </div>
                <div id="htmlEditor" class="editor"></div>
            </div>
            <div class="editor-container">
                <div class="editor-header">
                    <img src="/sources/icons/bulk/brush-2.svg" alt="CSS" class="css-icon">
                    CSS
                </div>
                <div id="cssEditor" class="editor"></div>
            </div>
            <div class="editor-container">
                <div class="editor-header">
                    <img src="/sources/icons/Logos/bold/js.svg" alt="JavaScript" class="js-icon">
                    JavaScript
                </div>
                <div id="jsEditor" class="editor"></div>
            </div>
        </div>
        <div id="preview-container">
            <div class="editor-header">
                <img src="/sources/icons/bulk/monitor.svg" alt="Önizleme" class="preview-icon">
                Önizleme
            </div>
            <div id="preview"></div>
        </div>
    </div>

    <script>
        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
        require(['vs/editor/editor.main'], function () {
            const editorOptions = {
                theme: 'vs-dark',
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 14,
                lineHeight: 21,
                padding: { top: 10 },
                scrollBeyondLastLine: false,
                roundedSelection: false,
                renderLineHighlight: 'all',
                fontFamily: 'Consolas, "Courier New", monospace',
                fontLigatures: true
            };

            const htmlEditor = monaco.editor.create(document.getElementById('htmlEditor'), {
                ...editorOptions,
                value: '<!DOCTYPE html>\n<html>\n<head>\n\t<title>Önizleme</title>\n</head>\n<body>\n\t<h1>Merhaba Dünya!</h1>\n\t<p>Kod yazmaya başlayın!</p>\n</body>\n</html>',
                language: 'html'
            });

            const cssEditor = monaco.editor.create(document.getElementById('cssEditor'), {
                ...editorOptions,
                value: 'body {\n\tbackground-color: #f0f0f0;\n\tcolor: #333;\n\tfont-family: Arial, sans-serif;\n\tpadding: 20px;\n}\n\nh1 {\n\tcolor: #2196F3;\n}',
                language: 'css'
            });

            const jsEditor = monaco.editor.create(document.getElementById('jsEditor'), {
                ...editorOptions,
                value: '// JavaScript kodunuz buraya\nconsole.log("JavaScript\'ten merhaba!");\n\n// Örnek: Tıklama olayı\ndocument.querySelector("h1").addEventListener("click", () => {\n\talert("Merhaba!");\n});',
                language: 'javascript'
            });

            let isCodeChanged = false;

            htmlEditor.onDidChangeModelContent(() => {
                updatePreview();
                handleCodeChange();
            });
            cssEditor.onDidChangeModelContent(() => {
                updatePreview();
                handleCodeChange();
            });
            jsEditor.onDidChangeModelContent(() => {
                updatePreview();
                handleCodeChange();
            });


            let updateTimeout;
            function updatePreview() {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(() => {
                    const html = htmlEditor.getValue();
                    const css = cssEditor.getValue();
                    const js = jsEditor.getValue();

                    const previewFrame = document.createElement('iframe');
                    document.getElementById('preview').innerHTML = '';
                    document.getElementById('preview').appendChild(previewFrame);

                    const previewContent = `
            ${html}
            <style>${css}</style>
            <script>${js}<\/script>
        `;

                    previewFrame.contentDocument.open();
                    previewFrame.contentDocument.write(previewContent);
                    previewFrame.contentDocument.close();
                }, 300); // 300ms
            }

            <?php if ($projectData): ?>
                htmlEditor.setValue(<?php echo json_encode($projectData['html'] ?? ''); ?>);
                cssEditor.setValue(<?php echo json_encode($projectData['css'] ?? ''); ?>);
                jsEditor.setValue(<?php echo json_encode($projectData['js'] ?? ''); ?>);
            <?php endif; ?>

            function editProjectName() {
                const titleSpan = document.getElementById('projectTitle');
                const currentTitle = titleSpan.textContent;

                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentTitle;
                input.style.width = '200px';

                input.addEventListener('blur', async function () {
                    try {
                        const response = await fetch('api/update-project-name.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                project_id: <?php echo $project_id; ?>,
                                title: this.value
                            })
                        });

                        if (response.ok) {
                            titleSpan.textContent = this.value;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        titleSpan.textContent = currentTitle;
                    }
                });

                titleSpan.parentNode.replaceChild(input, titleSpan);
                input.focus();
            }

            // Kod değişikliğini yöneten fonksiyon
            function handleCodeChange() {
                if (!isCodeChanged) {
                    isCodeChanged = true;
                    const saveButton = document.getElementById('saveButton');
                    saveButton.disabled = false;
                    saveButton.innerHTML = `
            <img src="/sources/icons/bulk/save-2.svg" alt="Kaydet" width="20" height="20">
            Kaydet
        `;
                }
            }

            document.getElementById('saveButton').addEventListener('click', async function () {
                // Önce önizleme ekranını bir görüntü olarak alalım
                const preview = document.getElementById('preview');
                const iframe = preview.querySelector('iframe');

                // Önizleme içeriğini canvas'a çevirelim
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                // Canvas boyutlarını ayarlayalım
                canvas.width = iframe.offsetWidth;
                canvas.height = iframe.offsetHeight;

                try {
                    // html2canvas kullanarak iframe içeriğini yakalayalım
                    const screenshot = await html2canvas(iframe.contentDocument.body, {
                        width: iframe.offsetWidth,
                        height: iframe.offsetHeight,
                        scale: 1
                    });

                    // Canvas'a çizelim
                    context.drawImage(screenshot, 0, 0);

                    // Canvas'ı base64 formatında JPG'ye çevirelim
                    const imageData = canvas.toDataURL('image/jpeg', 0.8);

                    const projectData = {
                        html: htmlEditor.getValue(),
                        css: cssEditor.getValue(),
                        js: jsEditor.getValue(),
                        preview_image: imageData // Görüntü verisini ekleyelim
                    };

                    const response = await fetch('/public/components/projects/api/save-project.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            project_id: <?php echo $project_id; ?>,
                            data: projectData
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        isCodeChanged = false;
                        this.disabled = true;
                        this.innerHTML = `
                <img src="/sources/icons/bulk/save-2.svg" alt="Kaydet" width="20" height="20">
                Paylaşıldı
            `;
                        localStorage.setItem('project_<?php echo $project_id; ?>', JSON.stringify(projectData));
                    } else {
                        throw new Error(result.error || 'Kayıt sırasında bir hata oluştu');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Bir hata oluştu: ' + error.message);
                    this.disabled = false;
                }
            });

            window.addEventListener('load', () => {
                const savedCode = localStorage.getItem('project_<?php echo $project_id; ?>');
                if (savedCode) {
                    try {
                        const code = JSON.parse(savedCode);
                        htmlEditor.setValue(code.html || '');
                        cssEditor.setValue(code.css || '');
                        jsEditor.setValue(code.js || '');
                        isCodeChanged = false; // Başlangıçta değişiklik yok
                    } catch (e) {
                        console.error('Error loading saved code:', e);
                    }
                }

                <?php if ($projectData): ?>
                    // Veritabanından gelen kodu yükle
                    htmlEditor.setValue(<?php echo json_encode($projectData['html'] ?? ''); ?>);
                    cssEditor.setValue(<?php echo json_encode($projectData['css'] ?? ''); ?>);
                    jsEditor.setValue(<?php echo json_encode($projectData['js'] ?? ''); ?>);
                    isCodeChanged = false; // Başlangıçta değişiklik yok
                <?php endif; ?>

                // İlk yüklemede önizlemeyi güncelle
                updatePreview();
            });
        });
    </script>
</body>

</html>