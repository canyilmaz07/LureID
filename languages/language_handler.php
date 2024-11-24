<?php
// language_handler.php

require_once dirname(__FILE__) . "/../config/database.php";

class LanguageHandler {
    private static $translations = [];
    private static $currentLanguage = 'en';
    
    public static function init() {
        try {
            if(isset($_SESSION['user_id'])) {
                $config = require dirname(__FILE__) . "/../config/database.php";
                $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
                $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
                
                $stmt = $pdo->prepare("SELECT language FROM user_settings WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                self::$currentLanguage = $result ? $result['language'] : 'en';
            }
            
            $languageFile = dirname(__FILE__) . '/' . self::$currentLanguage . '.php';
            if(file_exists($languageFile)) {
                self::$translations = require $languageFile;
            }
        } catch(Exception $e) {
            error_log("Language initialization error: " . $e->getMessage());
            self::$currentLanguage = 'en';
        }
    }
    
    public static function translate($text) {
        return self::$translations[$text] ?? $text;
    }

    public static function getCurrentLanguage() {
        return self::$currentLanguage;
    }
}

// Auto-initialize when included
LanguageHandler::init();

// Global translation function
function __($text) {
    return LanguageHandler::translate($text);
}
?>