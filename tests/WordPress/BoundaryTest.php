<?php

use Plasma\WordPress\WpdbAdapter;

it('normalizes prefix exactly once for WordPress writes', function () {
    $wp = mockWpdb();
    $wp->insert_id = 12;
    $wp->shouldReceive('insert')->once()->with('wp_shop_products', ['active' => 0])->andReturn(1);
    $wp->shouldReceive('update')->once()->with('wp_shop_products', ['name' => 'x'], ['id' => 12])->andReturn(1);
    $adapter = new WpdbAdapter('shop_', $wp);
    expect($adapter->insert('products', ['active' => false]))->toBe(12);
    expect($adapter->update('wp_shop_products', ['name' => 'x'], ['id' => 12]))->toBe(1);
});

it('does not disguise a WordPress database error as empty rows', function () {
    $wp = mockWpdb();
    $wp->last_error = 'synthetic failure';
    $adapter = new WpdbAdapter('', $wp);
    expect(fn() => $adapter->query('SELECT 1'))->toThrow(RuntimeException::class);
    expect(fn() => $adapter->getVar('SELECT 1'))->toThrow(RuntimeException::class);
});

it('preserves transaction depth when WordPress commit fails', function () {
    $wp = mockWpdb();
    $wp->shouldReceive('query')->once()->with('START TRANSACTION')->andReturn(0);
    $wp->shouldReceive('query')->once()->with('COMMIT')->andReturn(false);
    $adapter = new WpdbAdapter('', $wp);
    $adapter->beginTransaction();
    expect(fn() => $adapter->commit())->toThrow(RuntimeException::class);
    expect($adapter->getTransactionDepth())->toBe(1);
});

it('tracks nested WordPress savepoints and releases rolled-back savepoints', function () {
    $wp = mockWpdb();
    foreach (['START TRANSACTION', 'SAVEPOINT LEVEL1', 'ROLLBACK TO SAVEPOINT LEVEL1', 'RELEASE SAVEPOINT LEVEL1', 'COMMIT'] as $sql) {
        $wp->shouldReceive('query')->once()->ordered()->with($sql)->andReturn(0);
    }
    $adapter = new WpdbAdapter('', $wp);
    $adapter->beginTransaction(); $adapter->beginTransaction(); $adapter->rollback();
    expect($adapter->getTransactionDepth())->toBe(1);
    $adapter->commit();
    expect($adapter->getTransactionDepth())->toBe(0);
});
