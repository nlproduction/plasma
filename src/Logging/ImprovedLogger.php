<?php

namespace Plasma\Logging;

/**
 * Improved Logger - More independent and flexible
 * 
 * Improvements over old Logger:
 * 1. Multiple handlers (Clockwork, File, Custom)
 * 2. No global state (instance-based)
 * 3. Callable handlers for integration with application loggers
 * 4. Configurable via DI
 * 5. Testable
 */
class ImprovedLogger
{
    private array $handlers = [];
    private bool $enabled = true;
    
    // Log levels
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Add a log handler
     * Handler signature: function(string $level, string $message, array $context): void
     */
    public function addHandler(callable $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }
    
    /**
     * Enable/disable logging
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
    
    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        foreach ($this->handlers as $handler) {
            try {
                $handler($level, $message, $context);
            } catch (\Throwable $e) {
                // Fail silently to not break application
                error_log("Logger handler failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Convenience methods
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log database query
     */
    public function query(string $sql, float $duration, array $context = []): void
    {
        $this->log(self::DEBUG, $sql, array_merge($context, [
            'duration' => $duration,
            'type' => 'query'
        ]));
    }
    
    // ========================================
    // Built-in Handlers
    // ========================================
    
    /**
     * Clockwork handler
     */
    public static function clockworkHandler(): callable
    {
        return function(string $level, string $message, array $context) {
            if (!function_exists('clock')) {
                return;
            }
            
            // If it's a database query
            if (isset($context['type']) && $context['type'] === 'query') {
                clock()->addDatabaseQuery(
                    $message,
                    [],
                    $context['duration'] ?? 0,
                    $context
                );
            } else {
                // Regular log
                clock()->log($level, $message, $context);
            }
        };
    }
    
    /**
     * File handler
     */
    public static function fileHandler(string $filepath): callable
    {
        return function(string $level, string $message, array $context) use ($filepath) {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? json_encode($context) : '';
            $logLine = "[{$timestamp}] [{$level}] {$message} {$contextStr}\n";
            
            file_put_contents($filepath, $logLine, FILE_APPEND);
        };
    }
    
    /**
     * Console handler (for CLI)
     */
    public static function consoleHandler(): callable
    {
        return function(string $level, string $message, array $context) {
            $colors = [
                self::ERROR => "\033[31m",   // Red
                self::WARNING => "\033[33m", // Yellow
                self::INFO => "\033[32m",    // Green
                self::DEBUG => "\033[36m",   // Cyan
            ];
            $reset = "\033[0m";
            
            $color = $colors[$level] ?? '';
            $timestamp = date('H:i:s');
            
            echo "{$color}[{$timestamp}] [{$level}]{$reset} {$message}\n";
        };
    }
    
    /**
     * Error log handler (PHP error_log)
     */
    public static function errorLogHandler(): callable
    {
        return function(string $level, string $message, array $context) {
            $contextStr = !empty($context) ? json_encode($context) : '';
            error_log("[{$level}] {$message} {$contextStr}");
        };
    }
}
