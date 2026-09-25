<?php

use Plasma\Adapter\PdoAdapter;
use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Events\DatabaseEvent;
use Plasma\Events\DatabaseEventDispatcher;
use Plasma\Logging\ClockworkListener;
use Plasma\Logging\Logger;

beforeEach(function () { Logger::reset(); Logger::setEnabled(true); });
afterEach(function () { Logger::reset(); });

it('keeps optional Clockwork integration inert until enabled', function () {
    $listener = new ClockworkListener();
    $listener->handle(new DatabaseEvent('query', '', null, null, [], 0.1, 'SELECT 1'));
    expect(Logger::clockworkEnabled())->toBeFalse();
});

it('runs real SQLite queries with the optional listener disabled', function () {
    $pdo = new PdoAdapter(['dsn' => 'sqlite::memory:']);
    $dispatcher = new DatabaseEventDispatcher();
    $dispatcher->addListener(new ClockworkListener());
    $adapter = new EventDatabaseAdapter($pdo, $dispatcher);
    expect((int) $adapter->getVar('SELECT 42'))->toBe(42);
    expect($pdo->getTransactionDepth())->toBe(0);
});
