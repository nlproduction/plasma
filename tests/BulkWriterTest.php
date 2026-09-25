<?php

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Adapter\DialectAwareAdapter;
use Plasma\Internal\BulkWriter;

function bulkDialectAdapter(string $dialect)
{
    $adapter = Mockery::mock(
        DatabaseAdapter::class . ', ' . DialectAwareAdapter::class
    );
    $adapter->shouldReceive('getPrefix')->andReturn('')->byDefault();
    $adapter->shouldReceive('getDialect')->andReturn($dialect)->byDefault();
    $adapter->shouldReceive('prepare')->andReturnUsing(function ($sql, ...$params) {
        $index = 0;
        return preg_replace_callback('/%[sdf]/', function () use ($params, &$index) {
            $value = $params[$index++];
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
            return "'" . addslashes((string) $value) . "'";
        }, $sql);
    })->byDefault();

    return $adapter;
}

describe('BulkWriter dialects', function () {
    it('compiles SQLite conflict targets and excluded assignments', function () {
        $adapter = bulkDialectAdapter('sqlite');
        $adapter->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'INSERT INTO `items` (`code`, `label`)') === 0
                    && strpos($sql, "('A', 'Alpha'), ('B', 'Beta')") !== false
                    && strpos($sql, 'ON CONFLICT (`code`) DO UPDATE SET') !== false
                    && strpos($sql, '`label` = excluded.`label`') !== false;
            }))
            ->andReturn(2);

        $writer = new BulkWriter('items', $adapter, ['code', 'label']);
        expect($writer->upsertMany(
            [
                ['code' => 'A', 'label' => 'Alpha'],
                ['label' => 'Beta', 'code' => 'B'],
            ],
            ['code'],
            ['label']
        ))->toBe(2);
    });

    it('compiles SQLite do-nothing upserts when no fields are updated', function () {
        $adapter = bulkDialectAdapter('sqlite');
        $adapter->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'ON CONFLICT (`code`) DO NOTHING') !== false;
            }))
            ->andReturn(0);

        $writer = new BulkWriter('items', $adapter, ['code']);
        expect($writer->upsertMany(
            [['code' => 'A']],
            ['code'],
            []
        ))->toBe(1);
    });

    it('fails explicitly for an unsupported SQL dialect', function () {
        $adapter = bulkDialectAdapter('pgsql');
        $writer = new BulkWriter('items', $adapter, ['code']);

        expect(fn() => $writer->upsertMany(
            [['code' => 'A']],
            ['code'],
            []
        ))->toThrow(RuntimeException::class, 'not supported');
    });

    it('requires a dialect capability for upsert but not insert', function () {
        $adapter = Mockery::mock(DatabaseAdapter::class);
        $adapter->shouldReceive('getPrefix')->andReturn('');
        $writer = new BulkWriter('items', $adapter, ['code']);

        expect(fn() => $writer->upsertMany(
            [['code' => 'A']],
            ['code'],
            []
        ))->toThrow(RuntimeException::class, 'exposes its SQL dialect');
    });
});
