<?php

namespace Plasma\Logging;

/**
 * Logger class that sends the data from PHP to browser console.
 * Ported from MapSVG with improvements - now fully independent
 * 
 * Requires: itsgoingd/clockwork package (optional)
 * @see https://github.com/itsgoingd/clockwork
 */
class Logger
{
  /** @var \Clockwork\Support\Vanilla\Clockwork|null */
  private static $clockwork = null;

  private static bool $logToFile = false;
  private static bool $isFinalized = false;
  private static bool $enabled = true;

  // Define message types as constants
  const ERROR = 'ERROR';
  const WARNING = 'WARNING';
  const INFO = 'INFO';
  const DEBUG = 'DEBUG';

  /**
   * Check if Clockwork is enabled
   */
  public static function clockworkEnabled(): bool
  {
    return self::$clockwork !== null;
  }

  /**
   * Enable/disable logger
   */
  public static function setEnabled(bool $enabled): void
  {
    self::$enabled = $enabled;
  }

  /**
   * Initialize the Clockwork library
   * 
   * @param array $params ['logToClockwork' => bool, 'logToFile' => bool, 'clockworkConfig' => array]
   */
  public static function init(array $params = []): void
  {
    if (!self::$enabled) {
      return;
    }

    // Initialize Clockwork
    if (!empty($params["logToClockwork"])) {
      try {
        if (class_exists('\Clockwork\Support\Vanilla\Clockwork')) {
          /** @phpstan-ignore-next-line */
          self::$clockwork = \Clockwork\Support\Vanilla\Clockwork::init($params['clockworkConfig'] ?? []);
        }
      } catch (\Exception $e) {
        error_log("Failed to initialize Clockwork: " . $e->getMessage());
        self::$clockwork = null;
      }
    }

    // Enable file logging
    if (!empty($params["logToFile"])) {
      self::$logToFile = true;
    }
  }

  /**
   * Save the logged data for Clockwork. The data becomes accessible by an API URL
   */
  public static function finish(): void
  {
    if (!self::$isFinalized && self::clockworkEnabled()) {
      try {
        self::$clockwork->requestProcessed();
      } catch (\Exception $e) {
        error_log("Clockwork finish failed: " . $e->getMessage());
      }
      self::$isFinalized = true;
    }
  }

  /**
   * Send Clockwork headers
   */
  public static function sendHeaders(): void
  {
    if (self::clockworkEnabled()) {
      try {
        self::$clockwork->sendHeaders();
      } catch (\Exception $e) {
        error_log("Clockwork sendHeaders failed: " . $e->getMessage());
      }
    }
  }

  /**
   * Return metadata for Clockwork
   */
  public static function getMetaData($request)
  {
    if (self::clockworkEnabled()) {
      try {
        return self::$clockwork->returnMetadata($request);
      } catch (\Exception $e) {
        error_log("Clockwork getMetaData failed: " . $e->getMessage());
        return null;
      }
    }
    return null;
  }

  /**
   * Add an error log to Clockwork and/or file
   * 
   * @param mixed $data Data to log
   * @param string|null $label Optional label (legacy parameter, not used)
   */
  public static function error($data, $label = null): void
  {
    if (!self::$enabled) {
      return;
    }

    $message = self::formatMessage(self::ERROR, $data);

    if (self::clockworkEnabled() && function_exists('clock')) {
      try {
        /** @phpstan-ignore-next-line */
        clock($message);
      } catch (\Exception $e) {
        error_log("Clockwork error logging failed: " . $e->getMessage());
      }
    }

    if (self::$logToFile) {
      error_log($message);
    }
  }

  /**
   * Add an info log to Clockwork and/or file
   * 
   * @param mixed $data Data to log
   * @param string|null $label Optional label (legacy parameter, not used)
   */
  public static function info($data, $label = null): void
  {
    if (!self::$enabled) {
      return;
    }

    $message = self::formatMessage(self::INFO, $data);

    if (self::clockworkEnabled() && function_exists('clock')) {
      try {
        /** @phpstan-ignore-next-line */
        clock($message);
      } catch (\Exception $e) {
        error_log("Clockwork info logging failed: " . $e->getMessage());
      }
    }

    if (self::$logToFile) {
      error_log($message);
    }
  }

  /**
   * Add a database query with timing to Clockwork logs
   * 
   * @param string $query SQL query
   * @param float $time Start time (microtime(true))
   */
  public static function addDatabaseQuery(string $query, float $time): void
  {
    if (!self::$enabled || !self::clockworkEnabled() || !function_exists('clock')) {
      return;
    }

    try {
      $duration = (microtime(true) - $time) * 1000; // Convert to milliseconds
      /** @phpstan-ignore-next-line */
      clock()->addDatabaseQuery($query, [], $duration);
    } catch (\Exception $e) {
      error_log("Clockwork addDatabaseQuery failed: " . $e->getMessage());
    }
  }

  /**
   * Add a warning log to Clockwork and/or file
   * 
   * @param mixed $data Data to log
   * @param string|null $label Optional label (legacy parameter, not used)
   */
  public static function warning($data, $label = null): void
  {
    if (!self::$enabled) {
      return;
    }

    $message = self::formatMessage(self::WARNING, $data);

    if (self::clockworkEnabled() && function_exists('clock')) {
      try {
        /** @phpstan-ignore-next-line */
        clock($message);
      } catch (\Exception $e) {
        error_log("Clockwork warning logging failed: " . $e->getMessage());
      }
    }

    if (self::$logToFile) {
      error_log($message);
    }
  }

  /**
   * Add a debug log to Clockwork and/or file
   * 
   * @param mixed $data Data to log
   * @param string|null $label Optional label (legacy parameter, not used)
   */
  public static function debug($data, $label = null): void
  {
    if (!self::$enabled) {
      return;
    }

    $message = self::formatMessage(self::DEBUG, $data);

    if (self::clockworkEnabled() && function_exists('clock')) {
      try {
        /** @phpstan-ignore-next-line */
        clock($message);
      } catch (\Exception $e) {
        error_log("Clockwork debug logging failed: " . $e->getMessage());
      }
    }

    if (self::$logToFile) {
      error_log($message);
    }
  }

  /**
   * Reset logger state (useful for tests)
   */
  public static function reset(): void
  {
    self::$clockwork = null;
    self::$logToFile = false;
    self::$isFinalized = false;
    self::$enabled = true;
  }

  /**
   * Format the log message with type
   * 
   * @param string $type Log level
   * @param mixed $data Data to format
   * @return string Formatted message
   */
  private static function formatMessage(string $type, $data): string
  {
    $message = is_array($data) || is_object($data) ? print_r($data, true) : (string)$data;
    return "[{$type}] {$message}";
  }
}
