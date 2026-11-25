<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/csv_export_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'hidden_fields_cache']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('exports the selected resources with the correct metadata and field visibility for each mode', function (
    bool $personal_only,
    bool $all_fields,
    bool $has_shape,
    bool $has_technical,
) use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $person_label = 'Contributors ' . $id;
    $shape_label = 'Shape ' . $id;
    $technical_label = 'Technical ' . $id;
    $hidden_label = 'Restricted ' . $id;
    $people = $fixtures->field($person_label, 0, FIELD_TYPE_CHECK_BOX_LIST);
    $shape = $fixtures->field($shape_label);
    $technical = $fixtures->field($technical_label);
    $hidden = $fixtures->field($hidden_label);
    foreach ([
        [$people, 1, 1], [$shape, 0, 1], [$technical, 0, 0], [$hidden, 0, 1],
    ] as [$field, $personal, $include]) {
        $columns = array_intersect_key(get_resource_type_field_columns(), ['personal_data' => true, 'include_in_csv_export' => true]);
        expect(save_resource_type_field($field, $columns, ['personal_data' => $personal, 'include_in_csv_export' => $include]))
            ->toBeTrue();
    }
    $first = set_node(null, $people, 'Contributor A', null, 10);
    $second = set_node(null, $people, 'Contributor B', null, 20);
    $rows = [];
    foreach ([[1, [$first, $second], 'Circle'], [1, [$first], 'Triangle'], [2, [$second], 'Square']] as [$type, $nodes, $name]) {
        $ref = $fixtures->resource($type, 0);
        $errors = [];
        add_resource_nodes($ref, $nodes);
        $value = "Technical note for {$name}";
        expect(update_field($ref, $shape, $name, $errors))->toBeTrue()
            ->and(update_field($ref, $technical, $value, $errors))->toBeTrue()
            ->and(update_field($ref, $hidden, 'Hidden fixture', $errors))->toBeTrue();
        $rows[$ref] = [$name, $value, implode(', ', array_map(
            static fn (int $node): string => $node === $first ? 'Contributor A' : 'Contributor B',
            $nodes
        ))];
    }
    $GLOBALS['userpermissions'][] = 'f-' . $hidden;
    $GLOBALS['hidden_fields_cache'] = null;
    $path = get_temp_dir(false) . '/metadata_' . $id . '.csv';
    $fixtures->onCleanup(static function () use ($path): void {
        if (is_file($path)) {
            unlink($path);
        }
    });
    $results = do_search('!list' . implode(':', array_keys($rows)));
    expect(generateResourcesMetadataCSV(array_column($results, 'ref'), $personal_only, $all_fields, $path))->toBeTrue();

    $handle = fopen($path, 'rb');
    expect($handle)->toBeResource();
    try {
        $headers = fgetcsv($handle, escape: '');
        expect($headers)->toContain($person_label)->not->toContain($hidden_label);
        expect(in_array($shape_label, $headers, true))->toBe($has_shape);
        expect(in_array($technical_label, $headers, true))->toBe($has_technical);
        $refs = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $ref = (int) $row[0];
            $refs[] = $ref;
            $values = array_combine($headers, $row);
            expect($values[$person_label])->toBe($rows[$ref][2])
                ->and($values[$GLOBALS['lang']['status']])->toBe($GLOBALS['lang']['status0']);
            if ($has_shape) {
                expect($values[$shape_label])->toBe($rows[$ref][0]);
            }
            if ($has_technical) {
                expect($values[$technical_label])->toBe($rows[$ref][1]);
            }
        }
        expect($refs)->toEqualCanonicalizing(array_keys($rows));
    } finally {
        fclose($handle);
    }
})->with([
    'personal fields' => [true, false, false, false],
    'ordinary export' => [false, false, true, false],
    'all visible data' => [false, true, true, true],
]);
