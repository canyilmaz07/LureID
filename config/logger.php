<?php
class Logger 
{     
    private $logFile;     
    private $defaultPath = '../../config/logs/app.log';     
    private $logDir = '../../config/logs';      

    public function __construct($logFile = null)     
    {         
        $this->logFile = $logFile ?? $this->defaultPath;          
        
        // logs dizinini oluştur         
        if (!file_exists($this->logDir)) {             
            mkdir($this->logDir, 0755, true);         
        }          
        
        // logs dizininin yazılabilir olduğundan emin ol         
        if (!is_writable($this->logDir)) {             
            throw new Exception("Logs directory is not writable");         
        }          
        
        // Log dosyası yoksa oluştur         
        if (!file_exists($this->logFile)) {             
            touch($this->logFile);             
            chmod($this->logFile, 0644);         
        }     
    }      

    public function log($message, $level = 'INFO')     
    {         
        $timestamp = date('Y-m-d H:i:s');         
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;          
        
        // Log dosyasının yazılabilir olduğunu kontrol et         
        if (!is_writable($this->logFile)) {             
            throw new Exception("Log file is not writable: {$this->logFile}");         
        }          
        
        // Log mesajını dosyaya yaz         
        if (file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {             
            throw new Exception("Failed to write to log file");         
        }          
        
        // Dosya boyutu 10MB'ı geçerse rotasyon yap         
        if (filesize($this->logFile) > 10 * 1024 * 1024) {             
            $this->rotateLogFile();         
        }     
    }      

    private function rotateLogFile()     
    {         
        $timestamp = date('Y-m-d_H-i-s');         
        $backupFile = str_replace('.log', "_{$timestamp}.log", $this->logFile);                  
        rename($this->logFile, $backupFile);         
        touch($this->logFile);         
        chmod($this->logFile, 0644);     
    } 
}