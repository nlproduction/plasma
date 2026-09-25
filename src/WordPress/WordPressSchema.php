<?php

namespace Plasma\WordPress;

use Plasma\Internal\Sql;

/** Loads the bundled WordPress core table metadata. */
final class WordPressSchema
{
    /** @var array<string, array<string, mixed>>|null */
    private static $rawSchema = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function load(string $sitePrefix, ?string $basePrefix = null): array
    {
        if (self::$rawSchema === null) {
            $path = dirname(__DIR__, 2) . '/resources/wordpress/schema.json';
            $json = file_get_contents($path);
            if ($json === false) {
                throw new \RuntimeException('Bundled WordPress schema could not be read.');
            }
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Bundled WordPress schema is invalid.');
            }
            self::$rawSchema = $decoded;
        }

        $schema = self::$rawSchema;
        $basePrefix = $basePrefix ?? $sitePrefix;
        foreach ($schema as $modelName => &$definition) {
            if (($definition['namespace'] ?? null) !== 'wp') {
                continue;
            }
            $logicalTable = $definition['table'];
            $definition['logicalTable'] = $logicalTable;
            $prefix = in_array($modelName, ['user', 'usermeta'], true) ? $basePrefix : $sitePrefix;
            $definition['table'] = Sql::absoluteTable($prefix . $logicalTable);
        }
        unset($definition);

        return $schema;
    }
}
