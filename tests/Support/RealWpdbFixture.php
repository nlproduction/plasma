<?php

namespace Plasma\Tests\Support;

use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Plasma;
use Plasma\WordPress\WpdbAdapter;

final class RealWpdbFixture
{
    private const MARKER_TABLE = 'plasma_contract_fixture_marker';

    /** @var \wpdb */
    private $wpdb;

    private string $databaseName;
    private string $tablePrefix;
    private string $tableName;
    private WpdbAdapter $adapter;
    private Plasma $db;

    public static function environmentConfigured(): bool
    {
        foreach (array(
            'PLASMA_TEST_WP_ROOT',
            'PLASMA_TEST_WP_CONFIG',
            'PLASMA_TEST_DB_NAME',
            'PLASMA_TEST_DB_DISPOSABLE',
        ) as $name) {
            if (getenv($name) === false || trim((string) getenv($name)) === '') {
                return false;
            }
        }
        return true;
    }

    public static function fromEnvironment(): self
    {
        if (!self::environmentConfigured()) {
            throw new \RuntimeException('The real wpdb test environment is incomplete.');
        }

        return new self(
            (string) getenv('PLASMA_TEST_WP_ROOT'),
            (string) getenv('PLASMA_TEST_WP_CONFIG'),
            (string) getenv('PLASMA_TEST_DB_NAME')
        );
    }

    private function __construct(string $wordPressRoot, string $configPath, string $databaseName)
    {
        $this->assertDisposableDatabase($databaseName);

        $wordPressRoot = realpath($wordPressRoot) ?: '';
        $configPath = realpath($configPath) ?: '';
        if ($wordPressRoot === '' || !is_file($wordPressRoot . '/wp-includes/class-wpdb.php')) {
            throw new \RuntimeException('PLASMA_TEST_WP_ROOT is not a WordPress root.');
        }
        if ($configPath === '' || !is_file($configPath)) {
            throw new \RuntimeException('PLASMA_TEST_WP_CONFIG is not a readable file.');
        }

        require_once __DIR__ . '/minimal-wordpress-functions.php';
        $this->defineConstant('WPINC', 'wp-includes');
        require_once $configPath;

        foreach (array('DB_USER', 'DB_PASSWORD', 'DB_HOST') as $constant) {
            if (!defined($constant)) {
                throw new \RuntimeException($constant . ' is missing from the test config.');
            }
        }

        require_once $wordPressRoot . '/wp-includes/class-wpdb.php';
        $host = (string) constant('DB_HOST');
        if (defined('DB_PORT') && strpos($host, ':') === false) {
            $host .= ':' . (int) constant('DB_PORT');
        }

        $this->wpdb = new \wpdb(
            (string) constant('DB_USER'),
            (string) constant('DB_PASSWORD'),
            $databaseName,
            $host
        );
        if ((string) $this->wpdb->get_var('SELECT 1') !== '1') {
            throw new \RuntimeException('Could not query the disposable wpdb test database.');
        }

        $this->databaseName = $databaseName;
        $this->tablePrefix = 'plasma_test_' . bin2hex(random_bytes(4)) . '_';
        $this->wpdb->set_prefix($this->tablePrefix);
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->assertMarkerTable();

        $this->adapter = new WpdbAdapter('', $this->wpdb);
        $this->tableName = $this->tablePrefix . 'items';

        try {
            $this->adapter->execute(
                'CREATE TABLE `' . $this->tableName . '` ('
                . '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . '`code` VARCHAR(100) NOT NULL,'
                . '`label` VARCHAR(255) NOT NULL,'
                . '`active` TINYINT(1) NOT NULL DEFAULT 0,'
                . '`payload` LONGTEXT NULL,'
                . 'PRIMARY KEY (`id`),'
                . 'UNIQUE KEY `code_unique` (`code`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $this->db = new Plasma(new EventDatabaseAdapter($this->adapter));
            $this->db->registerSchema([
                'contractItem' => [
                    'table' => 'items',
                    'primaryKey' => 'id',
                    'fields' => [
                        'id' => ['type' => 'int'],
                        'code' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'payload' => ['type' => 'json'],
                    ],
                ],
            ]);
        } catch (\Throwable $error) {
            $this->adapter->execute('DROP TABLE IF EXISTS `' . $this->tableName . '`');
            throw $error;
        }
    }

    public function db(): Plasma
    {
        return $this->db;
    }

    public function adapter(): WpdbAdapter
    {
        return $this->adapter;
    }

    public function cleanup(): void
    {
        if (isset($this->adapter)) {
            $this->adapter->execute('DROP TABLE IF EXISTS `' . $this->tableName . '`');
        }
    }

    private function assertMarkerTable(): void
    {
        $marker = $this->wpdb->get_var($this->wpdb->prepare(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES '
            . 'WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
            $this->databaseName,
            self::MARKER_TABLE
        ));
        if ($marker !== self::MARKER_TABLE) {
            throw new \RuntimeException(
                'Disposable database lacks the Plasma contract fixture marker.'
            );
        }
    }

    private function assertDisposableDatabase(string $databaseName): void
    {
        $flag = strtolower(trim((string) getenv('PLASMA_TEST_DB_DISPOSABLE')));
        if (!in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
            throw new \RuntimeException('Real wpdb tests require PLASMA_TEST_DB_DISPOSABLE=1.');
        }
        if (!preg_match('/(?:^|[_-])(test|testing|ci)(?:$|[_-])/i', $databaseName)) {
            throw new \RuntimeException('Disposable database name must contain test/testing/ci.');
        }
    }

    /** @param mixed $value */
    private function defineConstant(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
