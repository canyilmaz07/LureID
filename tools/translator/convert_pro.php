<?php
// convert_pro.php
header('Content-Type: application/json');

class HTMLTranslator {
    private $options;
    private $translations = [];
    
    public function __construct($options = []) {
        $this->options = $options;
    }

    // PHP kodlarını koru
    private function preservePHP($html) {
        return preg_replace_callback('/<\?php.*?\?>/s', function($match) {
            return '<!--PHP_PRESERVE-->' . base64_encode($match[0]) . '<!--/PHP_PRESERVE-->';
        }, $html);
    }

    // PHP kodlarını geri getir
    private function restorePHP($html) {
        return preg_replace_callback('/<!--PHP_PRESERVE-->(.*?)<!--\/PHP_PRESERVE-->/s', function($match) {
            return base64_decode($match[1]);
        }, $html);
    }

    // HTML attribute'larını koru
    private function preserveAttributes($html) {
        return preg_replace_callback('/\s+([\w\-@:]+)\s*=\s*(["\'])(.*?)\2/s', function($match) {
            // Alpine.js direktifleri veya event handler'ları
            if (strpos($match[1], '@') === 0 || strpos($match[1], 'x-') === 0 || strpos($match[1], 'on') === 0) {
                return $match[0];
            }
            // Diğer HTML attributeleri
            return ' ' . $match[1] . '=' . $match[2] . $match[3] . $match[2];
        }, $html);
    }

    // Script ve style taglarını koru
    private function preserveScriptAndStyle($html) {
        // Script taglarını koru
        $html = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/si', function($match) {
            return '<!--SCRIPT_PRESERVE-->' . base64_encode($match[0]) . '<!--/SCRIPT_PRESERVE-->';
        }, $html);

        // Style taglarını koru
        $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/si', function($match) {
            return '<!--STYLE_PRESERVE-->' . base64_encode($match[0]) . '<!--/STYLE_PRESERVE-->';
        }, $html);

        return $html;
    }

    // Script ve style taglarını geri getir
    private function restoreScriptAndStyle($html) {
        // Script taglarını geri getir
        $html = preg_replace_callback('/<!--SCRIPT_PRESERVE-->(.*?)<!--\/SCRIPT_PRESERVE-->/s', function($match) {
            return base64_decode($match[1]);
        }, $html);

        // Style taglarını geri getir
        $html = preg_replace_callback('/<!--STYLE_PRESERVE-->(.*?)<!--\/STYLE_PRESERVE-->/s', function($match) {
            return base64_decode($match[1]);
        }, $html);

        return $html;
    }

    // Dinamik PHP ifadelerini düzelt
    private function handleDynamicContent($text) {
        // PHP echo ifadelerini bul
        if (strpos($text, '<?php echo') !== false || strpos($text, '<?=') !== false) {
            preg_match('/(.*?)(<?php echo|<?=)(.*?)\?>/s', $text, $matches);
            if (!empty($matches)) {
                $prefix = trim($matches[1]);
                $variable = trim($matches[3]);
                return ["<?= __('" . $prefix . " %s', [" . $variable . "]) ?>", true];
            }
        }
        return [$text, false];
    }

    public function convert($html) {
        // 1. Önce korunacak kısımları işaretle
        $html = $this->preservePHP($html);
        $html = $this->preserveScriptAndStyle($html);
        $html = $this->preserveAttributes($html);

        // 2. Çevrilebilir metinleri bul ve işle
        $html = preg_replace_callback('/>(.*?)</s', function($match) {
            $text = trim($match[1]);

            // Boş veya sayısal içeriği atla
            if (empty($text) || is_numeric($text)) {
                return '>' . $text . '<';
            }

            // Base64 kodlanmış içeriği atla
            if (strpos($text, '_PRESERVE-->') !== false) {
                return '>' . $text . '<';
            }

            // Dinamik içeriği kontrol et
            list($processedText, $isDynamic) = $this->handleDynamicContent($text);
            if ($isDynamic) {
                return '>' . $processedText . '<';
            }

            // Normal metni çeviri fonksiyonuna sar
            $this->translations[$text] = '';
            return "><?= __('" . addslashes($text) . "') ?><";
        }, $html);

        // 3. Korunan kısımları geri getir
        $html = $this->restoreScriptAndStyle($html);
        $html = $this->restorePHP($html);

        // 4. Dil dosyasını oluştur
        $langFile = "<?php\nreturn [\n";
        foreach($this->translations as $en => $tr) {
            if (!empty(trim($en))) {
                $langFile .= "    '" . addslashes($en) . "' => '',\n";
            }
        }
        $langFile .= "];\n?>";

        return [
            'convertedHtml' => $html,
            'langFile' => $langFile
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html'])) {
    $options = json_decode($_POST['options'], true);
    $translator = new HTMLTranslator($options);
    echo json_encode($translator->convert($_POST['html']));
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>