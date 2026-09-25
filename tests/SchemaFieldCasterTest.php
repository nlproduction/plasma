<?php

use Plasma\SchemaFieldCaster;

it('hydrates json int boolean and datetime fields', function () {
    $types = [
        'context' => 'json',
        'priority' => 'int',
        'active' => 'boolean',
        'started_at' => 'datetime',
    ];

    $row = [
        'context' => '{"user_id":1}',
        'priority' => '7',
        'active' => '1',
        'started_at' => '2026-01-01 12:00:00',
    ];

    $hydrated = SchemaFieldCaster::hydrateRecord($row, $types);

    expect($hydrated['context'])->toBe(['user_id' => 1]);
    expect($hydrated['priority'])->toBe(7);
    expect($hydrated['active'])->toBeTrue();
    expect($hydrated['started_at'])->toBe('2026-01-01 12:00:00');
});

it('serializes json and boolean for database', function () {
    $types = [
        'context' => 'json',
        'active' => 'boolean',
    ];

    $data = [
        'context' => ['foo' => 'bar'],
        'active' => false,
    ];

    $serialized = SchemaFieldCaster::serializeRecord($data, $types);

    expect($serialized['context'])->toBe('{"foo":"bar"}');
    expect($serialized['active'])->toBe(0);
});
