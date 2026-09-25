<?php

namespace Plasma\WordPress;

use Plasma\Plasma;

/**
 * Optional WordPress convenience singleton for Plasma.
 *
 * The host application supplies schema paths and its logical table prefix.
 * Example: DB::configure([$schema], 'mapsvg_').
 */
class DB
{
  /** @var Plasma|null */
  private static $instance = null;

  /** @var array */
  private static array $schemaPaths = [];

  private static string $tablePrefix = '';

  /**
   * @param array $schemaPaths Absolute paths to schema.json files
   * @param string $tablePrefix Prefix after the WordPress prefix, e.g. "mapsvg_"
   */
  public static function configure(array $schemaPaths = [], string $tablePrefix = ''): void
  {
    self::$schemaPaths = $schemaPaths;
    self::$tablePrefix = $tablePrefix;
    self::$instance = null;
  }

  public static function get(): Plasma
  {
    if (self::$instance === null) {
      self::$instance = new Plasma(
        new WpdbAdapter(self::$tablePrefix),
        self::$schemaPaths
      );
    }

    return self::$instance;
  }
}
