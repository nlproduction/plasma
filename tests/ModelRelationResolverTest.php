<?php

use Plasma\Model;

describe('Model relation resolver', function () {

  it('uses model resolver for related models when loading include', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('getPrefix')->andReturn('wp_plasma_');

    $queryCall = 0;
    $adapter->shouldReceive('query')->andReturnUsing(function () use (&$queryCall) {
      $queryCall++;
      if ($queryCall === 1) {
        return [['id' => 1, 'name' => 'HQ']];
      }

      return [['id' => 10, 'location_id' => 1, 'name' => 'North']];
    });

    $regionCalls = 0;
    $regionModel = new Model('wp_plasma_region', $adapter);

    $location = new Model('wp_plasma_location', $adapter);
    $location->hasMany('regions', 'wp_plasma_region', 'location_id', null, 'region');
    $location->setModelResolver(function ($key) use (&$regionCalls, $regionModel) {
      if ($key === 'region') {
        $regionCalls++;
        return $regionModel;
      }
      throw new RuntimeException('Unexpected model key: ' . $key);
    });

    $results = $location->findMany([
      'include' => ['regions' => true],
    ]);

    expect($regionCalls)->toBe(1)
      ->and($results[0]['regions'])->toHaveCount(1)
      ->and($results[0]['regions'][0]['name'])->toBe('North');
  });
});
