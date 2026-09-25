<?php

use Plasma\Tests\Support\RealWpdbFixture;

it('runs bulk, distinct, statement, and schema-forwarding contracts through real wpdb', function () {
    if (!RealWpdbFixture::environmentConfigured()) {
        $this->markTestSkipped(
            'Configure PLASMA_TEST_WP_ROOT, PLASMA_TEST_WP_CONFIG, '
            . 'PLASMA_TEST_DB_NAME, and PLASMA_TEST_DB_DISPOSABLE=1.'
        );
    }

    $fixture = RealWpdbFixture::fromEnvironment();

    try {
        $db = $fixture->db();
        expect($db->getSchema())->toHaveKeys(['post', 'user', 'contractItem']);
        expect($fixture->adapter()->getDialect())->toBe('mysql');

        expect($db->contractItem->createMany(['data' => [
            [
                'code' => 'alpha',
                'label' => 'Alpha',
                'active' => true,
                'payload' => ['rank' => 1],
            ],
            [
                'payload' => ['rank' => 2],
                'active' => false,
                'label' => 'Beta',
                'code' => 'beta',
            ],
        ]]))->toBe(2);

        expect($db->contractItem->upsertMany([
            'data' => [
                ['code' => 'alpha', 'label' => 'Alpha 2', 'active' => false],
                ['code' => 'gamma', 'label' => 'Gamma', 'active' => true],
            ],
            'conflictFields' => ['code'],
            'updateFields' => ['label', 'active'],
        ]))->toBe(2);

        $rows = $db->contractItem->findMany([
            'orderBy' => ['code' => 'asc'],
        ]);
        expect(array_column($rows, 'label'))->toBe(['Alpha 2', 'Beta', 'Gamma']);
        expect($rows[0]['active'])->toBeFalse();
        expect($rows[0]['payload'])->toBe(['rank' => 1]);

        expect($db->contractItem->distinct(
            ['active'],
            ['orderBy' => ['active' => 'asc']]
        ))->toBe([false, true]);

        $updated = $fixture->adapter()->execute(
            "UPDATE `{$fixture->adapter()->getPrefix()}items` "
            . "SET `label` = 'Beta 2' WHERE `code` = 'beta'"
        );
        expect($updated)->toBe(1);
        expect($db->contractItem->findFirst([
            'where' => ['code' => 'beta'],
        ])['label'])->toBe('Beta 2');
    } finally {
        $fixture->cleanup();
    }
})->setRunTestInSeparateProcess(true)->setPreserveGlobalState(false);
