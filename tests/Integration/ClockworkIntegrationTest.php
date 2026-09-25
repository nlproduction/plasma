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

it('runs real database queries with the optional listener disabled', function () {
    $dsn = getenv('PLASMA_TEST_DSN');
    if (!$dsn && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('No SQLite PDO driver or PLASMA_TEST_DSN is available.');
    }

    $pdo = new PdoAdapter([
        'dsn' => $dsn ?: 'sqlite::memory:',
        'username' => getenv('PLASMA_TEST_USER') ?: '',
        'password' => getenv('PLASMA_TEST_PASSWORD') ?: '',
    ]);
    $dispatcher = new DatabaseEventDispatcher();
    $dispatcher->addListener(new ClockworkListener());
    $adapter = new EventDatabaseAdapter($pdo, $dispatcher);
    expect((int) $adapter->getVar('SELECT 42'))->toBe(42);
    expect($pdo->getTransactionDepth())->toBe(0);
});
