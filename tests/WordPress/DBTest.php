<?php

use Plasma\Model;
use Plasma\Plasma;
use Plasma\WordPress\DB;

// Reset singleton before each test so tests are isolated
beforeEach(function () {
  DB::configure([]);
});

describe('DB', function () {

  it('get() returns a Plasma instance', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db = DB::get();

    expect($db)->toBeInstanceOf(Plasma::class);
  });

  it('get() returns the same instance on repeated calls', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db1 = DB::get();
    $db2 = DB::get();

    expect($db1)->toBe($db2);
  });

  it('configure() resets singleton so next get() creates fresh instance', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db1 = DB::get();
    DB::configure([]);
    $db2 = DB::get();

    expect($db1)->not->toBe($db2);
  });

  it('provides dynamic access to any table as Model', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db = DB::get();

    // Tables not in schema return dynamic Model proxy
    expect($db->custom_table)->toBeInstanceOf(Model::class);
  });

  it('provides access to schema models as Model', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    // Configure with real schema file
    $schemaPath = __DIR__ . '/../../../../wp-plugin/db/plasma/schema.json';
    if (file_exists($schemaPath)) {
      DB::configure([$schemaPath]);
    }

    $db = DB::get();

    // 'location' is defined in schema
    expect($db->location)->toBeInstanceOf(Model::class);
  });

  it('executes transaction successfully', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('START TRANSACTION')
      ->once();
    $wpdb->shouldReceive('query')
      ->with('COMMIT')
      ->once();

    $db = DB::get();
    $result = $db->transaction(function () {
      return 'success';
    });

    expect($result)->toBe('success');
  });

  it('rolls back transaction on exception', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('query')
      ->with('START TRANSACTION')
      ->once();
    $wpdb->shouldReceive('query')
      ->with('ROLLBACK')
      ->once();

    $db = DB::get();

    expect(fn() => $db->transaction(function () {
      throw new \Exception('Test error');
    }))->toThrow(\Exception::class);
  });
});

describe('DB integration scenarios', function () {

  it('can query a dynamic table', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('get_results')
      ->andReturn([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta']
      ]);

    $db = DB::get();
    $rows = $db->any_table->findMany([
      'where' => ['active' => true]
    ]);

    expect($rows)->toBeArray();
  });

  it('supports select for specific fields', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->shouldReceive('get_results')
      ->andReturn([
        ['id' => 1, 'name' => 'Alpha']
      ]);

    $db = DB::get();
    $rows = $db->any_table->findMany([
      'select' => ['id', 'name']
    ]);

    expect($rows)->toBeArray();
  });
});
