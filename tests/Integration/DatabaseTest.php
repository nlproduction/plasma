<?php

use Plasma\Adapter\PdoAdapter;
use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Events\DatabaseEvent;
use Plasma\Events\DatabaseEventListener;
use Plasma\Events\DatabaseEventDispatcher;
use Plasma\Plasma;
use Plasma\QueryBuilder;
use Plasma\SchemaFieldCaster;

beforeEach(function () {
    // External DSN MUST point at a disposable test database.
    $this->adapter = new PdoAdapter([
        'dsn' => getenv('PLASMA_TEST_DSN') ?: 'sqlite::memory:',
        'username' => getenv('PLASMA_TEST_USER') ?: '',
        'password' => getenv('PLASMA_TEST_PASSWORD') ?: '',
        'prefix' => 'plasma_test_' . bin2hex(random_bytes(4)) . '_',
    ]);
    $this->prefix = $this->adapter->getPrefix();
    $auto = $this->adapter->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INTEGER PRIMARY KEY AUTO_INCREMENT';
    $this->adapter->query('CREATE TABLE ' . $this->prefix . 'authors (ID ' . $auto . ', name VARCHAR(255))');
    $this->adapter->query('CREATE TABLE ' . $this->prefix . 'books (id ' . $auto . ', author_id INTEGER, title VARCHAR(255), active INTEGER, price INTEGER, meta TEXT, big_value VARCHAR(30))');
    $this->schema = [
        'Author' => [
            'table' => '__PREFIX__authors', 'primaryKey' => 'ID',
            'fields' => ['ID' => ['type' => 'int'], 'name' => ['type' => 'string']],
            'relations' => ['books' => ['type' => 'hasMany', 'model' => 'Book', 'foreignKey' => 'author_id']],
        ],
        'Book' => [
            'table' => 'books', 'primaryKey' => 'id',
            'fields' => ['id' => ['type' => 'int'], 'author_id' => ['type' => 'int'], 'active' => ['type' => 'boolean'], 'meta' => ['type' => 'json'], 'big_value' => ['type' => 'bigint']],
            'relations' => ['author' => ['type' => 'belongsTo', 'model' => 'Author', 'foreignKey' => 'author_id']],
        ],
    ];
    $this->db = (new Plasma($this->adapter))->registerSchema($this->schema);
});

afterEach(function () {
    if (isset($this->adapter)) {
        while ($this->adapter->inTransaction()) { $this->adapter->rollback(); }
        foreach (['books', 'authors'] as $name) {
            $this->adapter->query('DROP TABLE IF EXISTS ' . $this->prefix . $name);
        }
    }
});

it('uses the same prefixed table for all CRUD paths', function () {
    $book = $this->db->Book->create(['data' => ['title' => 'One', 'active' => true, 'meta' => ['ok' => 1]]]);
    expect($book['active'])->toBeTrue()->and($book['meta'])->toBe(['ok' => 1]);
    expect($this->db->table('books')->count())->toBe(1);
    expect($this->db->table($this->prefix . 'books')->count())->toBe(1);
    expect($this->db->books->count())->toBe(1);
    $this->db->Book->update(['where' => ['id' => $book['id']], 'data' => ['active' => false, 'title' => 'Two']]);
    expect($this->db->Book->findUnique(['where' => ['id' => $book['id']], 'select' => ['title' => true, 'active' => true]]))
        ->toBe(['title' => 'Two', 'active' => false]);
    expect($this->db->Book->delete(['id' => $book['id']]))->toBe(1);
    expect($this->db->Book->count())->toBe(0);
});

it('quotes values once without treating their content as placeholders or replacements', function () {
    $values = ["%s next $1", "O'Reilly", "\\1 \\ $2", "x' OR 1=1 --", '100%_\\', 'Привет'];
    foreach ($values as $value) {
        $this->db->Book->create(['data' => ['title' => $value, 'active' => false]]);
    }
    $sql = $this->adapter->prepare('SELECT %s AS a, %s AS b', '%s $1', "O'Reilly");
    expect($this->adapter->query($sql))->toBe([['a' => '%s $1', 'b' => "O'Reilly"]]);
    foreach ($values as $value) {
        expect($this->db->Book->count(['where' => ['title' => ['equals' => $value]]]))->toBe(1);
    }
    expect($this->db->Book->count(['where' => ['title' => ['contains' => '100%_\\']]]))->toBe(1);
    expect($this->db->Book->count(['where' => ['active' => false]]))->toBe(count($values));
});

it('retains predicate grouping and identical findMany/count semantics', function () {
    foreach ([['a', 5], ['b', 20], ['c', 30], ['d', 40]] as $row) {
        $this->db->Book->create(['data' => ['title' => $row[0], 'price' => $row[1], 'active' => true]]);
    }
    $where = ['AND' => [
        ['OR' => [['title' => 'a'], ['price' => ['gte' => 20, 'lte' => 30]]]],
        ['NOT' => ['title' => 'b']],
    ]];
    $rows = $this->db->Book->findMany(['where' => $where, 'orderBy' => ['price' => 'asc']]);
    expect(array_column($rows, 'title'))->toBe(['a', 'c']);
    expect($this->db->Book->count(['where' => $where]))->toBe(count($rows));
    expect($this->db->Book->count(['where' => ['title' => ['not' => ['not' => 'a']]]]))->toBe(1);
    expect($this->db->Book->count(['where' => ['OR' => []]]))->toBe(0);
    expect($this->db->Book->count(['where' => ['id' => ['in' => []]]]))->toBe(0);
    expect($this->db->Book->count(['where' => ['id' => ['notIn' => []]]]))->toBe(4);
});

it('handles null filters and zero or offset-only pagination', function () {
    foreach (['a', 'b', 'c'] as $title) { $this->db->Book->create(['title' => $title]); }
    expect($this->db->Book->findMany(['take' => 0]))->toBe([]);
    expect(array_column($this->db->Book->findMany(['skip' => 1, 'orderBy' => ['id' => 'asc']]), 'title'))->toBe(['b', 'c']);
    expect($this->db->Book->count(['where' => ['author_id' => null]]))->toBe(3);
    expect($this->db->Book->count(['where' => ['author_id' => ['not' => null]]]))->toBe(0);
    expect($this->db->Book->update(['where' => ['author_id' => null], 'data' => ['active' => false]]))->toBe(3);
});

it('loads belongsTo by the foreign key and the targets non-id primary key', function () {
    $author = $this->db->Author->create(['ID' => 100, 'name' => 'Ada']);
    $book = $this->db->Book->create(['id' => 7, 'title' => 'Notes', 'author_id' => $author['ID']]);
    $result = $this->db->Book->findUnique(['where' => ['id' => $book['id']], 'include' => ['author' => true]]);
    expect($result['author']['name'])->toBe('Ada')->and($result['author']['ID'])->toBe(100);
    $projected = $this->db->Book->findFirst(['include' => ['author' => ['select' => ['name']]]]);
    expect($projected['author'])->toBe(['name' => 'Ada']);
    expect($this->db->Book->findFirst(['include' => ['author' => false]]))->not->toHaveKey('author');
});

it('batches hasMany and preserves caller filters instead of overwriting the join constraint', function () {
    $this->db->Author->create(['ID' => 100, 'name' => 'Ada']);
    $this->db->Author->create(['ID' => 200, 'name' => 'Grace']);
    $this->db->Book->create(['title' => 'First', 'author_id' => 100]);
    $this->db->Book->create(['title' => 'Second', 'author_id' => 200]);
    $authors = $this->db->Author->findMany(['orderBy' => ['ID' => 'asc'], 'include' => ['books' => true]]);
    expect(array_column($authors[0]['books'], 'title'))->toBe(['First']);
    expect(array_column($authors[1]['books'], 'title'))->toBe(['Second']);
    $filtered = $this->db->Author->findFirst(['where' => ['ID' => 100], 'include' => ['books' => ['where' => ['author_id' => 200]]]]);
    expect($filtered['books'])->toBe([]);
});

it('keeps outer work when an inner transaction rolls back', function () {
    $this->db->transaction(function (Plasma $db) {
        $db->Book->create(['title' => 'outer']);
        try {
            $db->transaction(function (Plasma $db) {
                $db->Book->create(['title' => 'inner']);
                throw new Error('abort inner');
            });
        } catch (Error $error) {
            expect($error->getMessage())->toBe('abort inner');
        }
    });
    expect(array_column($this->db->Book->findMany(), 'title'))->toBe(['outer']);
    expect($this->adapter->getTransactionDepth())->toBe(0);
});

it('rolls back PHP Errors as well as Exceptions', function () {
    expect(function () {
        $this->db->transaction(function (Plasma $db) {
            $db->Book->create(['title' => 'discard']);
            throw new TypeError('abort');
        });
    })->toThrow(TypeError::class);
    expect($this->db->Book->count())->toBe(0)->and($this->adapter->inTransaction())->toBeFalse();
});

it('emits SQL for reads and correctly delegates count through event decorators', function () {
    $events = [];
    $dispatcher = new DatabaseEventDispatcher();
    $dispatcher->addListener(new class($events) implements DatabaseEventListener {
        private $events;
        public function __construct(array &$events) { $this->events = &$events; }
        public function handle(DatabaseEvent $event): void { $this->events[] = $event; }
    });
    $adapter = new EventDatabaseAdapter($this->adapter, $dispatcher);
    $db = (new Plasma($adapter))->registerSchema($this->schema);
    $db->Book->create(['title' => 'Observed']);
    expect($db->Book->count())->toBe(1);
    $event = end($events);
    expect($event->operation)->toBe('query')->and($event->sql)->toContain('COUNT(*)')->and($event->error)->toBeNull();
    expect(function () use ($event) { $event->operation = 'changed'; })->toThrow(LogicException::class);
});

it('fails rather than hiding database errors or unsafe mutation filters', function () {
    expect(fn() => $this->adapter->getVar('SELECT * FROM no_such_table'))->toThrow(PDOException::class);
    expect(fn() => $this->adapter->query('SELECT * FROM no_such_table'))->toThrow(PDOException::class);
    expect(fn() => $this->db->Book->delete([]))->toThrow(InvalidArgumentException::class);
    expect(fn() => $this->db->Book->update(['where' => ['id' => ['gt' => 1]], 'data' => ['title' => 'x']]))->toThrow(InvalidArgumentException::class);
    expect(fn() => $this->adapter->insert('books', ['title`' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn() => $this->adapter->prepare('SELECT %s, %s', 'only one'))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid filters pagination and oversized inputs before database execution', function () {
    $invalid = [['where' => false], ['where' => null], ['take' => -1], ['take' => '2'], ['skip' => 1.2], ['select' => ['title' => false]], ['cursor' => 1]];
    foreach ($invalid as $options) {
        expect(fn() => $this->db->Book->findMany($options))->toThrow(InvalidArgumentException::class);
    }
    foreach ([[[]], [null], [new stdClass()], [INF]] as $values) {
        expect(fn() => $this->db->Book->findMany(['where' => ['id' => ['in' => $values]]]))->toThrow(InvalidArgumentException::class);
    }
    $qb = new QueryBuilder('books', $this->adapter, ['maxDepth' => 3]);
    expect(fn() => $qb->buildWhere(['NOT' => ['NOT' => ['NOT' => ['id' => 1]]]]))->toThrow(InvalidArgumentException::class);
    expect(fn() => $this->db->Book->findMany(['include' => ['author' => ['take' => 1]]]))->toThrow(InvalidArgumentException::class);
});

it('supports in-memory and file schemas and invalidates cached model metadata', function () {
    $file = tempnam(sys_get_temp_dir(), 'plasma_schema_');
    try {
        file_put_contents($file, json_encode($this->schema));
        expect((new Plasma($this->adapter, [$file]))->getSchema())->toBe($this->schema);
        expect((new Plasma($this->adapter, [$this->schema]))->getSchema())->toBe($this->schema);
    } finally { unlink($file); }
    $before = $this->db->Book;
    $this->db->registerSchema($this->schema);
    expect($this->db->Book)->not->toBe($before);
    expect($this->db->resolveModelKey($this->prefix . 'books'))->toBe('Book');
    expect(fn() => new Plasma($this->adapter, ['/not/a/schema.json']))->toThrow(InvalidArgumentException::class);
    expect(fn() => $this->db->registerSchema(['Invalid' => ['table' => 'x;DROP']]))->toThrow(InvalidArgumentException::class);
    expect($this->db->getSchema())->toBe($this->schema);
});

it('preserves unsigned BigInt values and refuses invalid JSON', function () {
    $large = '18446744073709551615';
    $book = $this->db->Book->create(['title' => 'big', 'big_value' => $large, 'meta' => ['ok' => true]]);
    expect($book['big_value'])->toBe($large);
    expect(SchemaFieldCaster::serializeValue('false', 'boolean'))->toBe(0);
    expect(fn() => $this->db->Book->create(['meta' => '{invalid']))->toThrow(JsonException::class);
    expect(SchemaFieldCaster::hydrateValue('42', 'bigint'))->toBe(42);
});
