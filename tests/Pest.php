<?php


/* Test-only fallback for Termwind on hosts without ext-mbstring. */
if (!function_exists('mb_strimwidth')) {
  function mb_strimwidth($string, $start, $width, $trimMarker = '', $encoding = null)
  {
    $string = (string) $string;
    $encoding = $encoding ?: 'UTF-8';
    $slice = mb_substr($string, (int) $start, null, $encoding);
    if (mb_strwidth($slice, $encoding) <= (int) $width) {
      return $slice;
    }

    $available = max(0, (int) $width - mb_strwidth((string) $trimMarker, $encoding));
    $result = '';
    $resultWidth = 0;
    $length = mb_strlen($slice, $encoding);
    for ($index = 0; $index < $length; $index++) {
      $character = mb_substr($slice, $index, 1, $encoding);
      $characterWidth = mb_strwidth($character, $encoding);
      if ($resultWidth + $characterWidth > $available) {
        break;
      }
      $result .= $character;
      $resultWidth += $characterWidth;
    }

    return $result . (string) $trimMarker;
  }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
  return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @return \Plasma\Adapter\DatabaseAdapter&Mockery\MockInterface
 */
function mockAdapter()
{
  $adapter = Mockery::mock(\Plasma\Adapter\DatabaseAdapter::class);
  $adapter->shouldReceive('prepare')->andReturnUsing(function ($sql, ...$params) {
    $index = 0;
    return preg_replace_callback('/%[sdf]/', function () use ($params, &$index) {
      $value = $params[$index++];
      return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }, $sql);
  })->byDefault();

  $adapter->shouldReceive('escapeLike')->andReturnUsing(function ($value) {
    return addcslashes($value, '_%\\');
  })->byDefault();

  $adapter->shouldReceive('getPrefix')->andReturn('')->byDefault();

  return $adapter;
}

/**
 * @return \Plasma\Adapter\DatabaseAdapter&Mockery\MockInterface
 */
function mockAdapterWithQuery(array $results = [])
{
  $adapter = mockAdapter();
  $adapter->shouldReceive('query')->andReturn($results);
  $adapter->shouldReceive('prepare')->andReturnUsing(function ($sql, ...$params) {
    $index = 0;
    return preg_replace_callback('/%[sdf]/', function () use ($params, &$index) {
      return "'" . addslashes((string) $params[$index++]) . "'";
    }, $sql);
  });
  $adapter->shouldReceive('getPrefix')->andReturn('wp_');

  return $adapter;
}

require_once __DIR__ . '/WordPress/Pest.php';

afterEach(function () { Mockery::close(); });
