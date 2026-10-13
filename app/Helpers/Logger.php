<?php
/**
 * Logger — simple file-based logging
 */

class Logger {
    private static $logDir;

    public static function init() {
        self::$logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    public static function log($level, $message, $context = []) {
        if (!self::$logDir) self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        $file = self::$logDir . '/' . date('Y-m-d') . '.log';
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }

    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }

    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }

    public static function debug($message, $context = []) {
        if (Config::get('APP_DEBUG', false)) {
            self::log('DEBUG', $message, $context);
        }
    }
}
