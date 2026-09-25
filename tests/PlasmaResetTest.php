<?php

use Plasma\Plasma;

describe('Plasma reset', function () {

  it('drops all schema tables and extra tables', function () {
    $schemaPath = sys_get_temp_dir() . '/plasma_reset_schema_' . uniqid() . '.json';
    file_put_contents($schemaPath, json_encode([
      'location' => [
        'table' => '__PREFIX__location',
        'primaryKey' => 'id',
        'fields' => ['id' => ['type' => 'int']],
      ],
      'region' => [
        'table' => '__PREFIX__region',
        'primaryKey' => 'id',
        'fields' => ['id' => ['type' => 'int']],
      ],
    ]));

    $queries = [];
    $adapter = mockAdapter();
    $adapter->shouldReceive('getPrefix')->andReturn('wp_plasma_');
    $adapter->shouldReceive('query')->andReturnUsing(function ($sql) use (&$queries) {
      $queries[] = $sql;
      return [];
    });

    $plasma = new Plasma($adapter, [$schemaPath]);
    $plasma->reset(['options', 'migrations']);

    @unlink($schemaPath);

    expect($queries[0])->toBe('SET FOREIGN_KEY_CHECKS=0');
    expect($queries)->toContain('DROP TABLE IF EXISTS `wp_plasma_location`');
    expect($queries)->toContain('DROP TABLE IF EXISTS `wp_plasma_region`');
    expect($queries)->toContain('DROP TABLE IF EXISTS `wp_plasma_options`');
    expect($queries)->toContain('DROP TABLE IF EXISTS `wp_plasma_migrations`');
    expect(end($queries))->toBe('SET FOREIGN_KEY_CHECKS=1');
  });

  it('clears lazy-loaded model cache after reset', function () {
    $schemaPath = sys_get_temp_dir() . '/plasma_reset_cache_' . uniqid() . '.json';
    file_put_contents($schemaPath, json_encode([
      'location' => [
        'table' => '__PREFIX__location',
        'primaryKey' => 'id',
        'fields' => ['id' => ['type' => 'int']],
      ],
    ]));

    $adapter = mockAdapter();
    $adapter->shouldReceive('getPrefix')->andReturn('');
    $adapter->shouldReceive('query')->andReturn([]);

    $plasma = new Plasma($adapter, [$schemaPath]);
    $modelBefore = $plasma->location;
    $plasma->reset([]);
    $modelAfter = $plasma->location;

    @unlink($schemaPath);

    expect($modelBefore)->not->toBe($modelAfter);
  });
});
