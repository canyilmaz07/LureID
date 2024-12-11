<?php
// components/projects/create.php

session_start();
$db = require_once '../../../config/database.php';
require_once __DIR__ . '/api/ProjectUtils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$projectUtils = new ProjectUtils($db);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Proje Oluştur - LUREID</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .CodeMirror {
            height: 300px;
            border-radius: 0.5rem;
            font-size: 14px;
        }
        
        .preview-frame {
            background: white;
            border-radius: 0.5rem;
            width: 100%;
            height: 500px;
            border: none;
        }
        
        .tab-active {
            border-bottom: 2px solid #3B82F6;
            color: #3B82F6;
        }
    </style>
</head>
<body class="bg-gray-50">

    <div class="container mx-auto px-4 py-8 mt-20">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Yeni Proje Oluştur</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sol Panel: Kod Editörü -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <input type="text" 
                           id="projectTitle" 
                           placeholder="Proje Başlığı" 
                           class="w-full text-xl font-semibold mb-4 p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    
                    <textarea id="projectDescription" 
                              placeholder="Proje Açıklaması" 
                              class="w-full h-20 p-2 mb-4 border border-gray-200 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    
                    <div class="flex gap-2 mb-4">
                        <input type="text" 
                               id="projectTags" 
                               placeholder="Etiketler (virgülle ayırın)" 
                               class="flex-1 p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        
                        <select id="projectVisibility" 
                                class="p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="PUBLIC">Herkese Açık</option>
                            <option value="PRIVATE">Gizli</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <div class="flex gap-4 mb-4">
                        <button class="tab-button tab-active px-4 py-2" data-tab="html">HTML</button>
                        <button class="tab-button px-4 py-2" data-tab="css">CSS</button>
                        <button class="tab-button px-4 py-2" data-tab="js">JavaScript</button>
                    </div>

                    <div id="htmlEditor" class="editor-panel"></div>
                    <div id="cssEditor" class="editor-panel hidden"></div>
                    <div id="jsEditor" class="editor-panel hidden"></div>
                </div>
            </div>

            <!-- Sağ Panel: Önizleme -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Önizleme</h2>
                        <button onclick="refreshPreview()" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            Yenile
                        </button>
                    </div>
                    <div id="previewContainer">
                        <iframe id="previewFrame" class="preview-frame"></iframe>
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <button onclick="window.location.href='projects.php'" 
                            class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        İptal
                    </button>
                    <button onclick="saveProject()" 
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                        Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let htmlEditor, cssEditor, jsEditor;
        
        document.addEventListener('DOMContentLoaded', function() {
            // CodeMirror editörlerini başlat
            htmlEditor = CodeMirror(document.getElementById('htmlEditor'), {
                mode: 'xml',
                theme: 'monokai',
                lineNumbers: true,
                autoCloseTags: true,
                autoCloseBrackets: true,
                tabSize: 2,
                extraKeys: {"Ctrl-Space": "autocomplete"}
            });

            cssEditor = CodeMirror(document.getElementById('cssEditor'), {
                mode: 'css',
                theme: 'monokai',
                lineNumbers: true,
                autoCloseBrackets: true,
                tabSize: 2
            });

            jsEditor = CodeMirror(document.getElementById('jsEditor'), {
                mode: 'javascript',
                theme: 'monokai',
                lineNumbers: true,
                autoCloseBrackets: true,
                tabSize: 2
            });

            // Tab değiştirme işlemleri
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.tab-button').forEach(btn => {
                        btn.classList.remove('tab-active');
                    });
                    button.classList.add('tab-active');
                    
                    document.querySelectorAll('.editor-panel').forEach(panel => {
                        panel.classList.add('hidden');
                    });
                    document.getElementById(button.dataset.tab + 'Editor').classList.remove('hidden');
                });
            });

            // İlk önizlemeyi yükle
            refreshPreview();
        });

        function refreshPreview() {
            const preview = document.getElementById('previewFrame').contentWindow.document;
            preview.open();
            preview.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${cssEditor.getValue()}</style>
                </head>
                <body>
                    ${htmlEditor.getValue()}
                    <script>${jsEditor.getValue()}<\/script>
                </body>
                </html>
            `);
            preview.close();
        }

        function takeScreenshot() {
            return new Promise((resolve) => {
                const preview = document.getElementById('previewFrame');
                html2canvas(preview.contentDocument.body).then(canvas => {
                    resolve(canvas.toDataURL('image/png'));
                });
            });
        }

        async function saveProject() {
            try {
                const title = document.getElementById('projectTitle').value;
                if (!title) {
                    alert('Lütfen bir proje başlığı girin.');
                    return;
                }

                const projectData = {
                    title: title,
                    description: document.getElementById('projectDescription').value,
                    tags: document.getElementById('projectTags').value.split(',').map(tag => tag.trim()),
                    visibility: document.getElementById('projectVisibility').value,
                    projectData: {
                        html: htmlEditor.getValue(),
                        css: cssEditor.getValue(),
                        js: jsEditor.getValue()
                    },
                    previewImage: await takeScreenshot()
                };

                const response = await fetch('/public/components/projects/api/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(projectData)
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    window.location.href = 'projects.php';
                } else {
                    throw new Error(result.error || 'Bir hata oluştu');
                }
            } catch (error) {
                alert('Proje kaydedilirken bir hata oluştu: ' + error.message);
            }
        }

        // Otomatik önizleme güncellemesi
        let previewTimeout;
        [htmlEditor, cssEditor, jsEditor].forEach(editor => {
            if (editor) {
                editor.on('change', () => {
                    clearTimeout(previewTimeout);
                    previewTimeout = setTimeout(refreshPreview, 1000);
                });
            }
        });
    </script>
</body>
</html>