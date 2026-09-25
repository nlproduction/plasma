<?php

use Plasma\QueryBuilder;

describe('QueryBuilder', function () {

  it('builds simple WHERE clause', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere(['active' => true]);

    expect($sql)->toContain('WHERE')
      ->and($sql)->toContain('active');
  });

  it('builds WHERE with multiple conditions', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'active' => true,
      'role' => 'admin'
    ]);

    expect($sql)->toContain('WHERE')
      ->and($sql)->toContain('AND')
      ->and($sql)->toContain('active')
      ->and($sql)->toContain('role');
  });

  it('builds WHERE with operators', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'age' => ['gt' => 18]
    ]);

    expect($sql)->toContain('>')
      ->and($sql)->toContain('18');
  });

  it('builds ORDER BY clause', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildOrderBy(['name' => 'asc']);

    expect($sql)->toContain('ORDER BY')
      ->and($sql)->toContain('name')
      ->and($sql)->toContain('ASC');
  });

  it('builds SELECT clause', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildSelect(['id', 'name', 'email']);

    expect($sql)->toContain('id')
      ->and($sql)->toContain('name')
      ->and($sql)->toContain('email')
      ->and($sql)->not->toContain('*');
  });

  it('builds SELECT * by default', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildSelect([]);

    expect($sql)->toBe('*');
  });

  it('handles LIMIT and OFFSET', function () {
    $adapter = mockAdapterWithQuery([
      ['id' => 1, 'name' => 'John'],
      ['id' => 2, 'name' => 'Jane']
    ]);

    $qb = new QueryBuilder('users', $adapter);
    $results = $qb->execute([
      'take' => 10,
      'skip' => 5
    ]);

    expect($results)->toBeArray()
      ->and($results)->toHaveCount(2);
  });
});

describe('QueryBuilder operators', function () {

  it('handles IN operator', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'role' => ['in' => ['admin', 'editor']]
    ]);

    expect($sql)->toContain('IN')
      ->and($sql)->toContain('admin')
      ->and($sql)->toContain('editor');
  });

  it('handles NOT operator', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'status' => ['not' => 'deleted']
    ]);

    expect($sql)->toMatch('/(!=|<>)/');
  });

  it('handles CONTAINS operator', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'email' => ['contains' => '@gmail.com']
    ]);

    expect($sql)->toContain('LIKE')
      ->and($sql)->toContain('%@gmail.com%');
  });

  it('handles comparison operators', function ($operator, $expected) {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'age' => [$operator => 18]
    ]);

    expect($sql)->toContain($expected);
  })->with([
    ['gt', '>'],
    ['gte', '>='],
    ['lt', '<'],
    ['lte', '<=']
  ]);
});


describe('QueryBuilder Prisma boolean AST', function () {
  it('compiles nested AND and OR groups', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'AND' => [
        ['country' => ['equals' => 'DE']],
        [
          'OR' => [
            ['status' => ['equals' => 'active']],
            ['sales' => ['gt' => 1000]],
          ],
        ],
      ],
    ]);

    expect($sql)
      ->toContain('AND')
      ->toContain('OR')
      ->toContain('country')
      ->toContain('status')
      ->toContain('sales');
  });

  it('supports three or more levels of boolean nesting', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'AND' => [
        [
          'OR' => [
            [
              'AND' => [
                ['age' => ['gte' => 18]],
                ['age' => ['lte' => 65]],
              ],
            ],
            ['role' => ['equals' => 'admin']],
          ],
        ],
        ['active' => true],
      ],
    ]);

    expect(substr_count($sql, 'AND'))->toBeGreaterThanOrEqual(2)
      ->and($sql)->toContain('OR')
      ->and($sql)->toContain('>= 18')
      ->and($sql)->toContain('<= 65');
  });

  it('supports logical NOT around a predicate group', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'NOT' => [
        'OR' => [
          ['status' => ['equals' => 'deleted']],
          ['banned' => true],
        ],
      ],
    ]);

    expect($sql)->toContain('NOT (')
      ->and($sql)->toContain('OR');
  });

  it('supports a list of NOT predicates using Prisma semantics', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'NOT' => [
        ['status' => ['equals' => 'deleted']],
        ['role' => ['equals' => 'blocked']],
      ],
    ]);

    expect(substr_count($sql, 'NOT ('))->toBe(2)
      ->and($sql)->toContain(' AND ');
  });

  it('supports field-level nested not filters', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'name' => [
        'not' => [
          'contains' => 'spam',
        ],
      ],
    ]);

    expect($sql)->toContain('NOT (')
      ->and($sql)->toContain('LIKE')
      ->and($sql)->toContain('%spam%');
  });

  it('supports Prisma equals and notIn operators', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'status' => ['equals' => 'active'],
      'role' => ['notIn' => ['blocked', 'deleted']],
    ]);

    expect($sql)->toContain('status')
      ->and($sql)->toContain('NOT IN')
      ->and($sql)->toContain('blocked')
      ->and($sql)->toContain('deleted');
  });

  it('combines scalar and operator predicates in the same group', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    $sql = $qb->buildWhere([
      'active' => true,
      'age' => ['gte' => 18, 'lte' => 65],
    ]);

    expect($sql)->toContain('active')
      ->and($sql)->toContain('>= 18')
      ->and($sql)->toContain('<= 65');
  });

  it('defines empty boolean-group semantics', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect($qb->buildWhere(['AND' => []]))->toContain('1 = 1')
      ->and($qb->buildWhere(['OR' => []]))->toContain('0 = 1')
      ->and($qb->buildWhere(['NOT' => []]))->toContain('1 = 1');
  });

  it('uses IS NULL and IS NOT NULL for Prisma null filters', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect($qb->buildWhere(['deleted_at' => null]))->toContain('IS NULL')
      ->and(
        $qb->buildWhere(['deleted_at' => ['not' => null]])
      )->toContain('IS NOT NULL');
  });

  it('routes nested values through adapter prepare', function () {
    $adapter = Mockery::mock(\Plasma\Adapter\DatabaseAdapter::class);
    $adapter->shouldReceive('getPrefix')->andReturn('');
    $adapter->shouldReceive('prepare')
      ->once()
      ->with(
        Mockery::on(function ($sql) {
          return strpos($sql, 'email') !== false && strpos($sql, '= %s') !== false;
        }),
        "x' OR 1=1 --"
      )
      ->andReturn('prepared-email-condition');

    $qb = new QueryBuilder('users', $adapter);
    $sql = $qb->buildWhere([
      'OR' => [
        ['email' => ['equals' => "x' OR 1=1 --"]],
      ],
    ]);

    expect($sql)->toContain('prepared-email-condition');
  });
});

describe('QueryBuilder validation', function () {
  it('rejects unsupported field operators', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect(fn() => $qb->buildWhere([
      'age' => ['rawSql' => '1=1'],
    ]))->toThrow(\InvalidArgumentException::class);
  });

  it('rejects unsafe field identifiers', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect(fn() => $qb->buildWhere([
      'name;drop' => 'x',
    ]))->toThrow(\InvalidArgumentException::class);
  });

  it('rejects unsafe table identifiers', function () {
    $adapter = mockAdapter();

    expect(fn() => new QueryBuilder('users;drop', $adapter))
      ->toThrow(\InvalidArgumentException::class);
  });

  it('rejects unsafe select and orderBy identifiers', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect(fn() => $qb->buildSelect(['id', 'name;drop']))
      ->toThrow(\InvalidArgumentException::class);

    expect(fn() => $qb->buildOrderBy(['name;drop' => 'asc']))
      ->toThrow(\InvalidArgumentException::class);
  });

  it('rejects invalid order directions', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect(fn() => $qb->buildOrderBy(['name' => 'sideways']))
      ->toThrow(\InvalidArgumentException::class);
  });

  it('requires boolean-group children to be predicate objects', function () {
    $adapter = mockAdapter();
    $qb = new QueryBuilder('users', $adapter);

    expect(fn() => $qb->buildWhere([
      'OR' => ['invalid-child'],
    ]))->toThrow(\InvalidArgumentException::class);
  });

  it('uses the same recursive where compiler for count', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('getVar')
      ->once()
      ->with(Mockery::on(function ($sql) {
        return strpos($sql, 'COUNT(*)') !== false
          && strpos($sql, 'OR') !== false
          && strpos($sql, 'status') !== false;
      }))
      ->andReturn(2);

    $qb = new QueryBuilder('users', $adapter);

    $count = $qb->count([
      'where' => [
        'OR' => [
          ['status' => ['equals' => 'active']],
          ['status' => ['equals' => 'pending']],
        ],
      ],
    ]);

    expect($count)->toBe(2);
  });
});


describe('QueryBuilder distinct', function () {
  it('builds one portable distinct query with filtering ordering and pagination', function () {
    $adapter = mockAdapter();
    $adapter->shouldReceive('query')
      ->once()
      ->with(Mockery::on(function ($sql) {
        return strpos($sql, 'SELECT DISTINCT `status`, `role` FROM `users`') === 0
          && strpos($sql, 'WHERE `active` = 1') !== false
          && strpos($sql, 'ORDER BY `status` ASC, `role` DESC') !== false
          && strpos($sql, 'LIMIT 10 OFFSET 5') !== false;
      }))
      ->andReturn([
        ['status' => 'active', 'role' => 'admin'],
      ]);

    $qb = new QueryBuilder(
      'users',
      $adapter,
      [],
      ['status', 'role', 'active']
    );

    expect($qb->distinct(
      ['status', 'role'],
      [
        'where' => ['active' => true],
        'orderBy' => [['status' => 'asc'], ['role' => 'desc']],
        'take' => 10,
        'skip' => 5,
      ]
    ))->toBe([
      ['status' => 'active', 'role' => 'admin'],
    ]);
  });

  it('rejects unknown duplicate and unselected distinct fields', function () {
    $qb = new QueryBuilder(
      'users',
      mockAdapter(),
      [],
      ['status', 'role', 'active']
    );

    expect(fn() => $qb->distinct([]))
      ->toThrow(InvalidArgumentException::class);
    expect(fn() => $qb->distinct(['unknown']))
      ->toThrow(InvalidArgumentException::class);
    expect(fn() => $qb->distinct(['status', 'status']))
      ->toThrow(InvalidArgumentException::class);
    expect(fn() => $qb->distinct(
      ['status'],
      ['orderBy' => ['role' => 'asc']]
    ))->toThrow(InvalidArgumentException::class);
    expect(fn() => $qb->distinct(
      ['status'],
      ['select' => ['status']]
    ))->toThrow(InvalidArgumentException::class);
  });
});
