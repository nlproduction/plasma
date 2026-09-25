<?php

use Plasma\Plasma;
use Plasma\WordPress\WpdbAdapter;

describe('bundled WordPress schema', function () {
  it('loads core WordPress models automatically', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db = new Plasma(new WpdbAdapter('', $wpdb));
    $schema = $db->getSchema();

    expect($schema)
      ->toHaveKeys(['user', 'post', 'postmeta', 'comment', 'term', 'termtaxonomy', 'option'])
      ->and($schema['post']['primaryKey'])->toBe('ID')
      ->and($schema['post']['fields'])->toHaveKey('post_title')
      ->and($schema['post']['relations'])->toHaveKey('author');
  });

  it('keeps WordPress core tables on the site prefix even with a plugin suffix', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $wpdb->shouldReceive('get_results')
      ->once()
      ->with(Mockery::on(function ($sql) {
        return strpos($sql, 'FROM `wp_posts`') !== false
          && strpos($sql, 'wp_mapsvg6_posts') === false;
      }), 'ARRAY_A')
      ->andReturn([
        ['ID' => '7', 'post_author' => '3', 'post_title' => 'Hello'],
      ]);

    $db = new Plasma(new WpdbAdapter('mapsvg6_', $wpdb));
    $rows = $db->post->findMany([
      'select' => ['ID', 'post_author', 'post_title'],
    ]);

    expect($rows[0]['ID'])->toBe(7)
      ->and($rows[0]['post_author'])->toBe(3)
      ->and($rows[0]['post_title'])->toBe('Hello');
  });

  it('still applies the plugin suffix to dynamic application tables', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $wpdb->shouldReceive('get_results')
      ->once()
      ->with(Mockery::on(function ($sql) {
        return strpos($sql, 'FROM `wp_mapsvg6_products`') !== false;
      }), 'ARRAY_A')
      ->andReturn([]);

    $db = new Plasma(new WpdbAdapter('mapsvg6_', $wpdb));
    expect($db->table('products')->findMany())->toBe([]);
  });

  it('loads WordPress relations with include and nested select', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $wpdb->shouldReceive('get_results')
      ->twice()
      ->with(Mockery::type('string'), 'ARRAY_A')
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'FROM `wp_posts`') !== false) {
          return [[
            'ID' => '9',
            'post_author' => '3',
            'post_title' => 'Schema aware',
            'post_status' => 'publish',
          ]];
        }
        if (strpos($sql, 'FROM `wp_users`') !== false) {
          return [['ID' => '3', 'display_name' => 'Ada']];
        }
        throw new RuntimeException('Unexpected SQL: ' . $sql);
      });

    $db = new Plasma(new WpdbAdapter('', $wpdb));
    $post = $db->post->findFirst([
      'where' => ['post_status' => 'publish'],
      'include' => [
        'author' => [
          'select' => ['ID', 'display_name'],
        ],
      ],
    ]);

    expect($post['author'])->toBe([
      'ID' => 3,
      'display_name' => 'Ada',
    ]);
  });

  it('rejects fields missing from a schema-backed WordPress model', function () {
    global $wpdb;
    $wpdb = mockWpdb();

    $db = new Plasma(new WpdbAdapter('', $wpdb));

    expect(fn() => $db->post->findMany([
      'where' => ['definitely_not_a_wp_post_field' => 1],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn() => $db->post->update([
      'where' => ['ID' => 1],
      'data' => ['definitely_not_a_wp_post_field' => 'x'],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn() => $db->post->findMany([
      'select' => ['ID' => true, 'definitely_not_a_wp_post_field' => false],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn() => $db->post->findMany([
      'orderBy' => ['definitely_not_a_wp_post_field' => 'asc'],
    ]))->toThrow(InvalidArgumentException::class);
  });
});

it('uses base_prefix for users in multisite while keeping site tables on prefix', function () {
  global $wpdb;
  $wpdb = mockWpdb();
  $wpdb->prefix = 'wp_2_';
  $wpdb->base_prefix = 'wp_';

  $queries = [];
  $wpdb->shouldReceive('get_results')
    ->twice()
    ->with(Mockery::type('string'), 'ARRAY_A')
    ->andReturnUsing(function ($sql) use (&$queries) {
      $queries[] = $sql;
      return [];
    });

  $db = new Plasma(new WpdbAdapter('', $wpdb));
  $db->post->findMany();
  $db->user->findMany();

  expect($queries[0])->toContain('FROM `wp_2_posts`')
    ->and($queries[1])->toContain('FROM `wp_users`');
});
