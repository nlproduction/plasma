<?php

/*
|--------------------------------------------------------------------------
| WordPress Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * @return Mockery\MockInterface&\stdClass
 */
function mockWpdb()
{
  /** @var Mockery\MockInterface&\stdClass $wpdb */
  $wpdb = Mockery::mock('stdClass');
  $wpdb->prefix = 'wp_';
  $wpdb->base_prefix = 'wp_';
  $wpdb->shouldReceive('get_results')
    ->with(Mockery::type('string'), 'ARRAY_A')
    ->andReturn([])
    ->byDefault();
  $wpdb->shouldReceive('prepare')->andReturnUsing(function ($sql, ...$params) {
    $index = 0;
    return preg_replace_callback('/%[sdf]/', function () use ($params, &$index) {
      return "'" . addslashes((string) $params[$index++]) . "'";
    }, $sql);
  });
  $wpdb->shouldReceive('insert')->andReturn(1)->byDefault();
  $wpdb->shouldReceive('update')->andReturn(1)->byDefault();
  $wpdb->shouldReceive('delete')->andReturn(1)->byDefault();
  $wpdb->shouldReceive('get_var')->andReturn(0)->byDefault();
  $wpdb->last_error = '';
  $wpdb->insert_id = 0;

  return $wpdb;
}

function mockWpdbAdapter(): \Plasma\WordPress\WpdbAdapter
{
  // For integration tests, we'd need actual WordPress
  // For unit tests, we'll use mocking
  return Mockery::mock(\Plasma\WordPress\WpdbAdapter::class);
}
