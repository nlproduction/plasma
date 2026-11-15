<?php

use Plasma\Internal\Sql;
use Plasma\WordPress\WpdbAdapter;
use Plasma\Adapter\PdoAdapter;
use Plasma\Plasma;

describe('Sql identifiers', function () {
  it('accepts WordPress prefixes that start with a digit', function () {
    expect(Sql::identifier('1abc_mapsvg6_validation'))->toBe('1abc_mapsvg6_validation');
    expect(Sql::table('validation', '1abc_mapsvg6_'))->toBe('1abc_mapsvg6_validation');
    expect(Sql::quote('1abc_mapsvg6_settings'))->toBe('`1abc_mapsvg6_settings`');
  });

  it('accepts conventional letter-leading identifiers and underscores', function () {
    expect(Sql::identifier('wp_2_mapsvg6_settings'))->toBe('wp_2_mapsvg6_settings');
    expect(Sql::identifier('_private'))->toBe('_private');
    expect(Sql::identifier('a1'))->toBe('a1');
  });

  it('rejects injection payloads and all-digit names', function ($name) {
    expect(fn () => Sql::identifier($name))
      ->toThrow(InvalidArgumentException::class, 'Invalid SQL identifier.');
  })->with([
    [''],
    ['123'],
    ['0'],
    ['users;drop'],
    ['foo bar'],
    ['foo.bar'],
    ['foo-bar'],
    ['`users`'],
    ["users\n"],
    ['users`'],
  ]);
});

it('round-trips records in a real database with digit-leading physical tables', function (string $sitePrefix) {
  $adapter = new PdoAdapter([
    'dsn' => getenv('PLASMA_TEST_DSN') ?: 'sqlite::memory:',
    'username' => getenv('PLASMA_TEST_USER') ?: '',
    'password' => getenv('PLASMA_TEST_PASSWORD') ?: '',
    'prefix' => $sitePrefix . 'plasma_test_' . bin2hex(random_bytes(4)) . '_',
  ]);
  $table = Sql::quote($adapter->getPrefix() . 'records');
  try {
    $adapter->execute('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, title VARCHAR(255))');
    $db = (new Plasma($adapter))->registerSchema([
      'Record' => [
        'table' => 'records', 'primaryKey' => 'id',
        'fields' => ['id' => ['type' => 'int'], 'title' => ['type' => 'string']],
      ],
    ]);
    $db->Record->create(['id' => 1, 'title' => "O'Reilly"]);
    expect($db->Record->findUnique(['where' => ['id' => 1]]))
      ->toBe(['id' => 1, 'title' => "O'Reilly"]);
    $db->Record->update(['where' => ['id' => 1], 'data' => ['title' => 'Updated']]);
    expect($db->Record->findUnique(['where' => ['id' => 1]])['title'])->toBe('Updated');
    expect($db->Record->delete(['id' => 1]))->toBe(1);
    expect($db->Record->count())->toBe(0);
  } finally {
    $adapter->execute('DROP TABLE IF EXISTS ' . $table);
  }
})->with(['1abc_', '1abc_2_']);

describe('WpdbAdapter prefixes', function () {
  it('constructs with a digit-leading WordPress table prefix', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->prefix = '1abc_';
    $wpdb->base_prefix = '1abc_';

    $adapter = new WpdbAdapter('mapsvg6_');

    expect($adapter->getPrefix())->toBe('1abc_mapsvg6_');
  });

  it('constructs with a multisite site prefix and a digit-leading base prefix', function () {
    global $wpdb;
    $wpdb = mockWpdb();
    $wpdb->prefix = '1abc_2_';
    $wpdb->base_prefix = '1abc_';

    $adapter = new WpdbAdapter('mapsvg6_');

    expect($adapter->getPrefix())->toBe('1abc_2_mapsvg6_');
  });
});
