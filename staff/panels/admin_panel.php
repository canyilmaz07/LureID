<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Metinleri Çek</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .form-container {
            margin-bottom: 20px;
        }
        .result {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <form method="post">
            <label for="filepath">HTML Dosya Dizini:</label><br>
            <input type="text" name="filepath" id="filepath" style="width: 300px;" required>
            <button type="submit">Metinleri Getir</button>
        </form>
    </div>

    <?php
    function getTextFromHTML($filePath) {
        if (!file_exists($filePath)) {
            return "Dosya bulunamadı.";
        }

        $doc = new DOMDocument();
        @$doc->loadHTML(file_get_contents($filePath));
        $xpath = new DOMXPath($doc);
        $textNodes = $xpath->query("//text()[normalize-space()]");

        $texts = [];
        foreach ($textNodes as $textNode) {
            $texts[] = trim($textNode->nodeValue);
        }

        return $texts;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['filepath'])) {
        $filePath = $_POST['filepath'];
        $metinler = getTextFromHTML($filePath);

        echo '<div class="result">';
        if (is_array($metinler)) {
            echo '<h3>Metin İçerikleri:</h3>';
            echo '<ul>';
            foreach ($metinler as $metin) {
                echo '<li>' . htmlspecialchars($metin) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . htmlspecialchars($metinler) . '</p>';
        }
        echo '</div>';
    }
    ?>
</body>
</html>
