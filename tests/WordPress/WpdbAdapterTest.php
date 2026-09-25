<?php

use Plasma\WordPress\WpdbAdapter;

describe('WpdbAdapter', function () {

  it('gets database prefix', function () {
    // Mock global $wpdb
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->prefix = 'wp_custom_';

    $adapter = new WpdbAdapter();

    expect($adapter->getPrefix())->toBe('wp_custom_');
  });

  it('prepares SQL with parameters', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $adapter = new WpdbAdapter();
    $sql = $adapter->prepare('SELECT * FROM users WHERE id = %d AND name = %s', 1, 'John');

    expect($sql)->toBeString()
      ->and($sql)->toContain('1')
      ->and($sql)->toContain('John');
  });

  it('executes query and returns results', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('get_results')
      ->with(Mockery::type('string'), 'ARRAY_A')
      ->andReturn([
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane']
      ]);

    $adapter = new WpdbAdapter();
    $results = $adapter->query('SELECT * FROM users');

    expect($results)->toBeArray()
      ->and($results)->toHaveCount(2);
  });

  it('inserts a record', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->insert_id = 5;
    $wpdb->shouldReceive('insert')
      ->with('users', ['name' => 'John', 'email' => 'john@test.com'])
      ->andReturn(1);

    $adapter = new WpdbAdapter();
    $id = $adapter->insert('users', ['name' => 'John', 'email' => 'john@test.com']);

    expect($id)->toBe(5);
  });

  it('updates records', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('update')
      ->with('users', ['name' => 'John Updated'], ['id' => 1])
      ->andReturn(1);

    $adapter = new WpdbAdapter();
    $affected = $adapter->update('users', ['name' => 'John Updated'], ['id' => 1]);

    expect($affected)->toBe(1);
  });

  it('deletes records', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('delete')
      ->with('users', ['id' => 1])
      ->andReturn(1);

    $adapter = new WpdbAdapter();
    $affected = $adapter->delete('users', ['id' => 1]);

    expect($affected)->toBe(1);
  });

  it('gets single value', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('get_var')
      ->with('SELECT COUNT(*) FROM users')
      ->andReturn('42');

    $adapter = new WpdbAdapter();
    $count = $adapter->getVar('SELECT COUNT(*) FROM users');

    expect($count)->toBe('42');
  });
});

describe('WpdbAdapter transactions', function () {

  it('begins transaction', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('START TRANSACTION')
      ->once();

    $adapter = new WpdbAdapter();
    $adapter->beginTransaction();

    expect(true)->toBeTrue(); // Just checking no exception
  });

  it('commits transaction', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('START TRANSACTION')
      ->once();
    $wpdb->shouldReceive('query')
      ->with('COMMIT')
      ->once();

    $adapter = new WpdbAdapter();
    $adapter->beginTransaction();
    $adapter->commit();

    expect($adapter->inTransaction())->toBeFalse();
  });

  it('rolls back transaction', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('START TRANSACTION')
      ->once();
    $wpdb->shouldReceive('query')
      ->with('ROLLBACK')
      ->once();

    $adapter = new WpdbAdapter();
    $adapter->beginTransaction();
    $adapter->rollback();

    expect($adapter->inTransaction())->toBeFalse();
  });
});


describe('WpdbAdapter statements and dialect', function () {
  it('executes non-row statements through wpdb query', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('UPDATE wp_items SET active = 1')
      ->andReturn(3);

    $adapter = new WpdbAdapter();

    expect($adapter->execute('UPDATE wp_items SET active = 1'))->toBe(3)
      ->and($adapter->getDialect())->toBe('mysql');
  });

  it('throws when a wpdb statement fails', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->last_error = 'statement failed';
    $wpdb->shouldReceive('query')->andReturn(false);

    $adapter = new WpdbAdapter();

    expect(fn() => $adapter->execute('BROKEN STATEMENT'))
      ->toThrow(RuntimeException::class);
  });
});
