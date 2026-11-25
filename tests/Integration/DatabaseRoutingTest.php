<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'db_connection_mode', 'use_error_exception',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('rejects writes on a configured read-only account and clears the mode between calls', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!db_use_multiple_connection_modes()) {
        $this->markTestSkipped('This environment has no configured read-only database account.');
    }
    db_set_connection_mode('read_only');
    expect(db_get_connection_mode())->toBe('read_only');
    db_clear_connection_mode();
    expect(db_get_connection_mode())->toBe('');

    $GLOBALS['use_error_exception'] = true;
    $prefix = 'pest_connector_' . generateRandomTestID(12);
    foreach ([
        'INSERT INTO sysvars (name, value) VALUES (?, ?)' => $prefix,
        'INSERT INTO sysvars SET name = ?, value = ?' => $prefix . '_second',
    ] as $sql => $name) {
        $fixtures->onCleanup(static function () use ($name): void {
            db_clear_connection_mode();
            ps_query('DELETE FROM sysvars WHERE name = ?', ['s', $name]);
        });
        db_set_connection_mode('read_only');
        expect(static fn () => ps_query(
            $sql,
            ['s', $name, 's', 'x'],
            dbstruct: false
        ))->toThrow(ErrorException::class);
    }
});

it('routes uncommitted reads through the writer and persists only committed changes', function () use (&$fixtures): void {
    $first = 'pest_tx_' . generateRandomTestID(12);
    $other = $first . '_other';
    $keys = array_fill_keys([
        'db', 'prepared_statement_cache', 'read_only_db_username', 'read_only_db_password',
        'use_db_transaction',
    ], true);
    $previous = array_intersect_key($GLOBALS, $keys);
    $fixtures->onCleanup(static function () use ($previous, $keys, $first, $other): void {
        try {
            if (isset($GLOBALS['sql_transaction_in_progress'])) {
                db_rollback_transaction('pest cleanup');
            }
            db_clear_connection_mode();
            if (isset($GLOBALS['db']['read_write'])) {
                ps_query('DELETE FROM sysvars WHERE name IN (?, ?)', ['s', $first, 's', $other]);
            }
        } finally {
            $GLOBALS['prepared_statement_cache'] = [];
            foreach ($GLOBALS['db'] as $connection) {
                $connection->close();
            }
            foreach ($keys as $name => $unused) {
                unset($GLOBALS[$name]);
            }
            foreach ($previous as $name => $setting) {
                $GLOBALS[$name] = $setting;
            }
        }
    });
    // Use separate connections even when development has only one database account.
    $GLOBALS['read_only_db_username'] = $GLOBALS['mysql_username'];
    $GLOBALS['read_only_db_password'] = $GLOBALS['mysql_password'];
    $GLOBALS['db'] = [];
    $GLOBALS['prepared_statement_cache'] = [];
    sql_connect();
    $GLOBALS['use_db_transaction'] = true;
    $value = static fn (string $key): mixed => ps_value('SELECT value FROM sysvars WHERE name = ?', ['s', $key], null);
    expect($value($first))->toBeNull();

    db_set_connection_mode('read_only');
    expect(db_get_connection_mode())->toBe('read_only');
    db_clear_connection_mode();
    expect(db_get_connection_mode())->toBe('');
    $reader = $GLOBALS['db']['read_only'];
    expect($reader->begin_transaction(MYSQLI_TRANS_START_READ_ONLY))->toBeTrue();
    $GLOBALS['use_error_exception'] = true;
    try {
        foreach ([
            'INSERT INTO sysvars (name, value) VALUES (?, ?)' => $first,
            'INSERT INTO sysvars SET name = ?, value = ?' => $other,
        ] as $sql => $name) {
            db_set_connection_mode('read_only');
            expect($value($name))->toBeNull()->and(db_get_connection_mode())->toBe('');
            db_set_connection_mode('read_only');
            expect(static fn () => ps_query(
                $sql,
                ['s', $name, 's', 'x'],
                dbstruct: false
            ))->toThrow(ErrorException::class, 'READ ONLY');
        }
    } finally {
        $reader->rollback();
        db_clear_connection_mode();
    }

    expect(db_begin_transaction('pest insert'))->toBeTrue()
        ->and(db_get_connection_mode())->toBe('read_write');
    ps_query('INSERT INTO sysvars (name, value) VALUES (?, ?)', ['s', $first, 's', 'original']);
    expect($value($first))->toBe('original')->and(db_end_transaction('pest insert'))->toBeTrue();
    db_set_connection_mode('read_only');
    expect(db_get_connection_mode())->toBe('read_only')->and($value($first))->toBe('original');

    expect(db_begin_transaction('pest rollback'))->toBeTrue();
    ps_query('UPDATE sysvars SET value = ? WHERE name = ?', ['s', 'changed', 's', $first]);
    ps_query('INSERT INTO sysvars (name, value) VALUES (?, ?)', ['s', $other, 's', 'new']);
    expect($value($first))->toBe('changed')->and($value($other))->toBe('new');
    expect(db_rollback_transaction('pest rollback'))->toBeTrue();
    db_set_connection_mode('read_only');
    expect(db_get_connection_mode())->toBe('read_only')
        ->and($value($first))->toBe('original')->and($value($other))->toBeNull();

    foreach ([false, true] as $commit) {
        expect(db_begin_transaction('pest deletion'))->toBeTrue();
        ps_query('DELETE FROM sysvars WHERE name = ?', ['s', $first]);
        expect($value($first))->toBeNull();
        $closed = $commit ? db_end_transaction('pest deletion') : db_rollback_transaction('pest deletion');
        expect($closed)->toBeTrue()->and($value($first))->toBe($commit ? null : 'original');
        if (!$commit) {
            expect(db_begin_transaction('pest update'))->toBeTrue();
            ps_query('UPDATE sysvars SET value = ? WHERE name = ?', ['s', 'sustained', 's', $first]);
            expect(db_end_transaction('pest update'))->toBeTrue()->and($value($first))->toBe('sustained');
        }
    }
});

it('deletes only specified table records and calls the logger for each deleted reference', function () use (&$fixtures): void {
    $refs = [];
    for ($i = 0; $i < 5; $i++) {
        $refs[] = $fixtures->resource(1, 0, run_macro: false);
    }
    [$first, $second, $third, $fourth, $control] = $refs;
    $logged = [];
    $logger = static function (int $ref) use (&$logged): void {
        $logged[] = $ref;
    };

    expect(db_delete_table_records('resource', [$first], $logger))->toBeTrue()
        ->and(db_delete_table_records('resource', [$second, $third], $logger))->toBeTrue()
        ->and(db_delete_table_records('resource_node', [$fourth], $logger))->toBeFalse()
        ->and(db_delete_table_records('resource', [], $logger))->toBeFalse()
        ->and($logged)->toBe([$first, $second, $third]);
    expect(get_resource_data($fourth, false))->toBeArray()
        ->and(get_resource_data($control, false))->toBeArray();
    expect(db_delete_table_records('resource', [$fourth], $logger))->toBeTrue()
        ->and($logged)->toBe([$first, $second, $third, $fourth]);

    $remaining = ps_array('SELECT ref AS value FROM resource WHERE ref IN (?, ?, ?, ?, ?)', ps_param_fill($refs, 'i'));
    expect($remaining)->toBe([$control]);
});
