<?php

use Plasma\Model;

describe('Model', function () {

  it('finds many records', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John', 'active' => true],
      ['id' => 2, 'name' => 'Jane', 'active' => true]
    ]);

    $proxy = new Model('users', $adapter, 'id');
    $results = $proxy->findMany(['where' => ['active' => true]]);

    expect($results)->toBeArray()
      ->and($results)->toHaveCount(2)
      ->and($results[0]['name'])->toBe('John');
  });

  it('finds unique record', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John', 'email' => 'john@test.com']
    ]);

    $proxy = new Model('users', $adapter, 'id');
    $result = $proxy->findUnique(['where' => ['id' => 1]]);

    expect($result)->toBeArray()
      ->and($result['name'])->toBe('John')
      ->and($result['email'])->toBe('john@test.com');
  });

  it('finds first record', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John']
    ]);

    $proxy = new Model('users', $adapter, 'id');
    $result = $proxy->findFirst(['where' => ['active' => true]]);

    expect($result)->toBeArray()
      ->and($result['name'])->toBe('John');
  });

  it('returns null when no record found', function () {
    $adapter = mockAdapterWithQuery([]);

    $proxy = new Model('users', $adapter, 'id');
    $result = $proxy->findUnique(['where' => ['id' => 999]]);

    expect($result)->toBeNull();
  });

  it('creates a record', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('insert')
      ->with('users', ['name' => 'John', 'email' => 'john@test.com'])
      ->andReturn(1);
    $adapter->shouldReceive('query')
      ->andReturn([['id' => 1, 'name' => 'John', 'email' => 'john@test.com']]);

    $proxy = new Model('users', $adapter, 'id');
    $result = $proxy->create([
      'name' => 'John',
      'email' => 'john@test.com'
    ]);

    expect($result)->toBeArray()
      ->and($result['id'])->toBe(1);
  });

  it('updates a record', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('update')
      ->with('users', ['name' => 'John Updated'], ['id' => 1])
      ->andReturn(1);

    $proxy = new Model('users', $adapter, 'id');
    $affected = $proxy->update([
      'where' => ['id' => 1],
      'data' => ['name' => 'John Updated']
    ]);

    expect($affected)->toBe(1);
  });

  it('deletes a record', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('delete')
      ->with('users', ['id' => 1])
      ->andReturn(1);

    $proxy = new Model('users', $adapter, 'id');
    $affected = $proxy->delete(['where' => ['id' => 1]]);

    expect($affected)->toBe(1);
  });

  it('counts records', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('getVar')
      ->andReturn(10);

    $proxy = new Model('users', $adapter, 'id');
    $count = $proxy->count(['where' => ['active' => true]]);

    expect($count)->toBe(10);
  });
});

describe('Model relations', function () {

  it('throws error when using select and include together', function () {
    $adapter = mockAdapter();
    $proxy = new Model('users', $adapter, 'id');

    expect(fn() => $proxy->findMany([
      'select' => ['id', 'name'],
      'include' => ['posts' => true]
    ]))->toThrow(\InvalidArgumentException::class);
  });

  it('allows select without include', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John']
    ]);

    $proxy = new Model('users', $adapter, 'id');
    $results = $proxy->findMany([
      'select' => ['id', 'name']
    ]);

    expect($results)->toBeArray()
      ->and($results)->toHaveCount(1);
  });

  it('allows include without select', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John', 'active' => true]
    ]);

    $proxy = new Model('users', $adapter, 'id');

    expect(fn() => $proxy->findMany([
      'where' => ['active' => true]
    ]))->not->toThrow(\InvalidArgumentException::class);
  });
});
