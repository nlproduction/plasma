<?php

use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Events\{DatabaseEvent, DatabaseEventListener, DatabaseEventDispatcher};

describe('EventDatabaseAdapter', function () {
    it('dispatches events on insert', function () {
        $baseAdapter = mockAdapter();
        $baseAdapter->shouldReceive('insert')->andReturn(1);
        $baseAdapter->shouldReceive('query')->andReturn([['id' => 1, 'name' => 'Test']]);
        
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->insert('test_table', ['name' => 'Test']);
        
        expect($events)->toHaveCount(1);
        expect($events[0]->operation)->toBe('create');
        expect($events[0]->table)->toBe('test_table');
        expect($events[0]->data)->toBe(['name' => 'Test']);
    });
    
    it('dispatches events on update', function () {
        $baseAdapter = mockAdapterWithQuery([]);
        $baseAdapter->shouldReceive('update')->andReturn(1);
        
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->update('test_table', ['name' => 'Updated'], ['id' => 1]);
        
        expect($events)->toHaveCount(1);
        expect($events[0]->operation)->toBe('update');
        expect($events[0]->table)->toBe('test_table');
        expect($events[0]->data)->toBe(['name' => 'Updated']);
        expect($events[0]->where)->toBe(['id' => 1]);
    });
    
    it('dispatches events on delete', function () {
        $baseAdapter = mockAdapterWithQuery([]);
        $baseAdapter->shouldReceive('delete')->andReturn(1);
        
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->delete('test_table', ['id' => 1]);
        
        expect($events)->toHaveCount(1);
        expect($events[0]->operation)->toBe('delete');
        expect($events[0]->table)->toBe('test_table');
        expect($events[0]->where)->toBe(['id' => 1]);
    });
    
    it('dispatches events on query', function () {
        $baseAdapter = mockAdapterWithQuery([['id' => 1]]);
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->query('SELECT * FROM test');
        
        expect($events)->toHaveCount(1);
        expect($events[0]->operation)->toBe('query');
    });
    
    it('includes duration in events', function () {
        $baseAdapter = mockAdapterWithQuery([]);
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->query('SELECT * FROM test');
        
        expect($events[0]->duration)->toBeGreaterThanOrEqual(0);
    });
    
    it('supports multiple listeners', function () {
        $baseAdapter = mockAdapterWithQuery([]);
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events1 = [];
        $events2 = [];
        
        $dispatcher->addListener(new class($events1) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $dispatcher->addListener(new class($events2) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        $adapter->query('SELECT * FROM test');
        
        expect($events1)->toHaveCount(1);
        expect($events2)->toHaveCount(1);
    });
    
    it('captures errors in events', function () {
        $baseAdapter = mockAdapter();
        $baseAdapter->shouldReceive('query')->andThrow(new \Exception('Database error'));
        
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        try {
            $adapter->query('SELECT * FROM test');
        } catch (\Exception $e) {
            // Expected
        }
        
        expect($events)->toHaveCount(1);
        expect($events[0]->error)->toBeInstanceOf(\Exception::class);
        expect($events[0]->error->getMessage())->toBe('Database error');
    });
    
    it('does not dispatch events for helper methods', function () {
        $baseAdapter = mockAdapter();
        $baseAdapter->shouldReceive('getPrefix')->andReturn('wp_');
        
        $dispatcher = new DatabaseEventDispatcher();
        $adapter = new EventDatabaseAdapter($baseAdapter, $dispatcher);
        
        $events = [];
        $dispatcher->addListener(new class($events) implements DatabaseEventListener {
            public function __construct(private array &$events) {}
            
            public function handle(DatabaseEvent $event): void {
                $this->events[] = $event;
            }
        });
        
        // These should not trigger events
        $adapter->prepare('SELECT * FROM %s', 'test');
        $adapter->escapeLike('test%');
        $adapter->getPrefix();
        
        expect($events)->toHaveCount(0);
    });
});


describe('EventDatabaseAdapter capabilities', function () {
    it('dispatches dedicated statement events', function () {
        $baseAdapter = mockAdapter();
        $baseAdapter->shouldReceive('execute')
            ->with('UPDATE test SET active = 1')
            ->andReturn(3);

        $events = [];
        $dispatcher = new \Plasma\Events\DatabaseEventDispatcher();
        $dispatcher->addListener(new class($events) implements \Plasma\Events\DatabaseEventListener {
            public function __construct(private array &$events) {}
            public function handle(\Plasma\Events\DatabaseEvent $event): void
            {
                $this->events[] = $event;
            }
        });

        $adapter = new \Plasma\Adapter\EventDatabaseAdapter($baseAdapter, $dispatcher);
        expect($adapter->execute('UPDATE test SET active = 1'))->toBe(3);
        expect($events)->toHaveCount(1)
            ->and($events[0]->operation)->toBe('statement')
            ->and($events[0]->table)->toBe('')
            ->and($events[0]->sql)->toBe('UPDATE test SET active = 1')
            ->and($events[0]->result)->toBe(3);
    });

    it('captures statement failures and rethrows them', function () {
        $baseAdapter = mockAdapter();
        $failure = new RuntimeException('statement failed');
        $baseAdapter->shouldReceive('execute')->andThrow($failure);

        $events = [];
        $dispatcher = new \Plasma\Events\DatabaseEventDispatcher();
        $dispatcher->addListener(new class($events) implements \Plasma\Events\DatabaseEventListener {
            public function __construct(private array &$events) {}
            public function handle(\Plasma\Events\DatabaseEvent $event): void
            {
                $this->events[] = $event;
            }
        });

        $adapter = new \Plasma\Adapter\EventDatabaseAdapter($baseAdapter, $dispatcher);
        expect(fn() => $adapter->execute('BROKEN STATEMENT'))
            ->toThrow(RuntimeException::class, 'statement failed');
        expect($events)->toHaveCount(1)
            ->and($events[0]->operation)->toBe('statement')
            ->and($events[0]->sql)->toBe('BROKEN STATEMENT')
            ->and($events[0]->error)->toBe($failure);
    });

    it('forwards adapter-provided default schemas through the decorator', function () {
        $baseAdapter = Mockery::mock(
            \Plasma\Adapter\DatabaseAdapter::class . ', ' .
            \Plasma\Adapter\SchemaProvidingAdapter::class
        );
        $schema = [
            'Intrinsic' => [
                'table' => 'intrinsic',
                'fields' => ['id' => ['type' => 'int']],
            ],
        ];
        $baseAdapter->shouldReceive('getPrefix')->andReturn('');
        $baseAdapter->shouldReceive('defaultSchemas')->andReturn([$schema]);

        $adapter = new \Plasma\Adapter\EventDatabaseAdapter($baseAdapter);
        $db = new \Plasma\Plasma($adapter);

        expect($db->getSchema())->toBe($schema);
    });

    it('forwards SQL dialect capability or fails explicitly', function () {
        $dialectAdapter = Mockery::mock(
            \Plasma\Adapter\DatabaseAdapter::class . ', ' .
            \Plasma\Adapter\DialectAwareAdapter::class
        );
        $dialectAdapter->shouldReceive('getDialect')->andReturn('sqlite');
        expect((new \Plasma\Adapter\EventDatabaseAdapter($dialectAdapter))->getDialect())
            ->toBe('sqlite');

        expect(fn() => (new \Plasma\Adapter\EventDatabaseAdapter(mockAdapter()))->getDialect())
            ->toThrow(RuntimeException::class, 'does not expose its SQL dialect');
    });
});
