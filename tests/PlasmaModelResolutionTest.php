<?php

use Plasma\Model;
use Plasma\Plasma;

function plasmaWithLocationSchema(): Plasma
{
  $schemaPath = sys_get_temp_dir() . '/plasma_model_resolution_' . uniqid() . '.json';
  file_put_contents($schemaPath, json_encode([
    'location' => [
      'table' => '__PREFIX__location',
      'primaryKey' => 'id',
      'fields' => ['id' => ['type' => 'int']],
      'relations' => [
        'regions' => [
          'model' => 'region',
          'type' => 'hasMany',
        ],
      ],
    ],
    'region' => [
      'table' => '__PREFIX__region',
      'primaryKey' => 'id',
      'fields' => ['id' => ['type' => 'int']],
    ],
  ]));

  $adapter = mockAdapter();
  $adapter->shouldReceive('getPrefix')->andReturn('wp_plasma_');

  return new Plasma($adapter, [$schemaPath]);
}

describe('Plasma model resolution', function () {

  it('resolves model key from schema key', function () {
    $plasma = plasmaWithLocationSchema();

    expect($plasma->resolveModelKey('location'))->toBe('location');
  });

  it('resolves model key from logical table name', function () {
    $plasma = plasmaWithLocationSchema();

    expect($plasma->resolveModelKey('location'))->toBe('location');
    expect($plasma->resolveModelKey('wp_plasma_location'))->toBe('location');
  });

  it('returns null for unknown table', function () {
    $plasma = plasmaWithLocationSchema();

    expect($plasma->resolveModelKey('unknown_xyz'))->toBeNull();
  });

  it('resolves model from data source config model key', function () {
    $plasma = plasmaWithLocationSchema();

    $model = $plasma->resolveModelFromDataSourceConfig(['model' => 'location']);

    expect($model)->toBeInstanceOf(Model::class);
    expect($plasma->location)->toBe($model);
  });

  it('resolves model from data source config table name', function () {
    $plasma = plasmaWithLocationSchema();

    $model = $plasma->resolveModelFromDataSourceConfig(['table' => 'location']);

    expect($plasma->location)->toBe($model);
  });

  it('schema model has relations for include', function () {
    $plasma = plasmaWithLocationSchema();
    $location = $plasma->model('location');

    $reflection = new ReflectionClass($location);
    $property = $reflection->getProperty('relations');
    $property->setAccessible(true);
    $relations = $property->getValue($location);

    expect($relations)->toHaveKey('regions')
      ->and($relations['regions']['modelKey'])->toBe('region');
  });
});
