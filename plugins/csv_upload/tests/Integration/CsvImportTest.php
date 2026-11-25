<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once dirname(__DIR__, 2) . '/include/csv_functions.php';
require_once dirname(__DIR__, 2) . '/include/meta_functions.php';

$fixtures = null;
$directory = null;
$import = null;
beforeEach(function () use (&$fixtures, &$directory, &$import): void {
    $fixtures = new IntegrationFixtures([
        'lang', 'tempdir', 'search_all_workflow_states', 'usercollection', 'use_error_exception',
    ]);
    $GLOBALS['use_error_exception'] = true;
    $lang = [];
    require dirname(__DIR__, 2) . '/languages/en.php';
    $GLOBALS['lang'] = array_merge($GLOBALS['lang'], $lang);

    $directory = get_temp_dir(false, 'pest_csv_' . generateRandomTestID(16));
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $GLOBALS['tempdir'] = $directory;
    expect(mkdir($directory . '/csv_upload', 0700))->toBeTrue();

    $import = static function (array $rows, array $options, bool $apply = false) use ($directory): array {
        $path = $directory . '/input.csv';
        $file = fopen($path, 'wb');
        expect($file)->toBeResource();
        foreach ($rows as $row) {
            fputcsv($file, $row, escape: '');
        }
        fclose($file);
        $options += [
            'csv_mode' => 'create_new',
            'add_to_collection' => 0,
            'csv_update_col' => 0,
            'csv_update_col_id' => 0,
            'id_column' => 0,
            'id_column_match' => 0,
            'multiple_match' => 0,
            'resource_type_column' => '',
            'resource_type_default' => 1,
            'status_column' => '',
            'status_default' => 0,
            'access_column' => '',
            'access_default' => 0,
            'location_column' => '',
            'longitude_column' => '',
            'fieldmapping' => [],
            'log_file' => $directory . '/processing.log',
        ];

        $messages = [];
        $meta = meta_get_map();
        $types = array_column(get_resource_types(), null, 'ref');
        ob_start();
        try {
            $ok = csv_upload_process($path, $meta, $types, $messages, $options, 100, $apply);
        } finally {
            ob_end_clean();
        }
        return [$ok, $messages, file_get_contents($options['log_file'])];
    };
});

afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('rejects invalid UTF-8 and strips a BOM before reading the sample rows', function () use (&$directory): void {
    $file = $directory . '/encoding.csv';
    file_put_contents($file, "Title,Notes\nBad,\xFF\n");
    $invalid = csv_check_utf8($file);
    expect($invalid['success'])->toBeFalse()->and($invalid['message'])->toEndWith('2');

    file_put_contents($file, "\xEF\xBB\xBFTitle,Notes\n\"A, B\",Valid example\n");
    expect(csv_check_utf8($file))->toBe(['success' => true, 'message' => '']);
    $messages = [];
    $sample = csv_upload_get_info($file, $messages);
    expect($sample['row_count'])->toBe(1)
        ->and($sample[0])->toBe(['header' => 'Title', 'values' => ['A, B']])
        ->and($sample[1])->toBe(['header' => 'Notes', 'values' => ['Valid example']])
        ->and($messages)->toBeEmpty();
});

it('validates mapped CSV without writing before creating resources with the configured defaults', function () use (
    &$fixtures,
    &$import,
): void {
    $id = generateRandomTestID(12);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'CSV import ' . $id);
    $GLOBALS['usercollection'] = $collection;
    $title_field = (int) $GLOBALS['view_title_field'];
    $title = 'CSV title, ' . $id;
    $rows = [['Type', 'Title', 'Status', 'Access'], ['Unknown', $title, 'Unknown', 'Unknown']];
    $options = [
        'resource_type_column' => 0, 'status_column' => 2, 'access_column' => 3,
        'status_default' => -1, 'access_default' => RESOURCE_ACCESS_RESTRICTED,
        'fieldmapping' => [1 => $title_field], 'add_to_collection' => 1,
    ];
    [$ok, $messages] = $import($rows, $options);
    expect($ok)->toBeTrue()->and(implode("\n", $messages))->toContain(
        'Invalid resource type', 'Invalid resource workflow state', 'Invalid resource access level', 'successfully validated'
    );
    expect(get_collection_resources($collection))->toBeEmpty();

    [$ok, $messages] = $import($rows, $options, true);
    $resources = get_collection_resources($collection);
    foreach ($resources as $ref) {
        $fixtures->trackResource($ref);
    }
    expect($ok)->toBeTrue()->and($resources)->toHaveCount(1)->and(implode("\n", $messages))
        ->toContain('Created new resource:', 'successfully processed');
    $ref = $resources[0];
    $resource = get_resource_data($ref, false);
    expect($resource['resource_type'])->toBe(1)->and($resource['archive'])->toBe(-1)
        ->and($resource['access'])->toBe(RESOURCE_ACCESS_RESTRICTED)
        ->and(get_data_by_field($ref, $title_field))->toBe($title);
});

it('updates mapped status and access and preserves both when the CSV leaves them unmapped', function (
    string $mode,
    int $expected_archive,
    int $expected_access,
) use (&$fixtures, &$import): void {
    $resource = $fixtures->resource(1, 0);
    $title = 'Updated by CSV ' . generateRandomTestID(12);
    $title_field = (int) $GLOBALS['view_title_field'];
    $options = [
        'csv_mode' => 'update_existing',
        'fieldmapping' => [1 => $title_field],
        'status_default' => -1,
        'access_default' => RESOURCE_ACCESS_RESTRICTED,
    ];
    if ($mode !== 'unmapped') {
        $options += ['status_column' => 2, 'access_column' => 3];
    }
    $input = $mode === 'mapped' ? ['2', '2'] : ['Invalid status', 'Invalid access'];
    $rows = [['Resource ID', 'Title', 'Status', 'Access'], [$resource, $title, ...$input]];
    [$ok, $messages] = $import($rows, $options);
    expect($ok)->toBeTrue()
        ->and(get_resource_data($resource, false)['archive'])->toBe(0)
        ->and(get_resource_data($resource, false)['access'])->toBe(RESOURCE_ACCESS_FULL);
    if ($mode === 'fallback') {
        expect(implode("\n", $messages))->toContain('Invalid resource workflow state', 'Invalid resource access level');
    }

    [$ok] = $import($rows, $options, true);
    $stored = get_resource_data($resource, false);
    expect($ok)->toBeTrue()->and($stored['archive'])->toBe($expected_archive)
        ->and($stored['access'])->toBe($expected_access)
        ->and(get_data_by_field($resource, $title_field))->toBe($title);
})->with([
    'mapped columns' => ['mapped', 2, RESOURCE_ACCESS_CONFIDENTIAL],
    'invalid columns use defaults' => ['fallback', -1, RESOURCE_ACCESS_RESTRICTED],
    'unmapped columns ignore defaults' => ['unmapped', 0, RESOURCE_ACCESS_FULL],
]);

it('matches the whole original filename while validating and applying an update', function () use (
    &$fixtures,
    &$import,
): void {
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $field = (int) $GLOBALS['filename_field'];
    $title_field = (int) $GLOBALS['view_title_field'];
    $title = 'Exact filename import ' . generateRandomTestID(12);
    $errors = [];
    expect(update_field($first, $field, 'Day 2 (17).jpg', $errors))->toBeTrue()
        ->and(update_field($second, $field, '17.jpg', $errors))->toBeTrue();
    $rows = [['Original filename', 'Title'], ['17.jpg', $title]];
    $options = ['csv_mode' => 'update_existing', 'id_column_match' => $field, 'fieldmapping' => [1 => $title_field]];

    [$ok, , $log] = $import($rows, $options);
    expect($ok)->toBeTrue()->and($log)->toContain('Found resource ID : ' . $second . ' matching the identifier 17.jpg')
        ->not->toContain('Found resource ID : ' . $first);
    [$ok] = $import($rows, $options, true);
    expect($ok)->toBeTrue()->and(get_data_by_field($second, $title_field))->toBe($title)
        ->and(get_data_by_field($first, $title_field))->not->toBe($title);
});

it('creates an alternative file record only for a valid mapped filename', function (
    string $filename,
    bool $match_metadata,
    ?string $diagnostic,
) use (&$fixtures, &$import): void {
    $resource = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $match_field = 0;
    $match = $resource;
    if ($match_metadata) {
        $match_field = (int) $GLOBALS['view_title_field'];
        $match = 'Alternative target ' . generateRandomTestID(12);
        $errors = [];
        expect(update_field($resource, $match_field, $match, $errors))->toBeTrue();
    }
    $rows = [['Identifier', 'Filename', 'Name', 'Description'], [$match, $filename, 'Alternate crop', 'Client copy']];
    $options = [
        'csv_mode' => 'create_alternatives', 'id_column_match' => $match_field,
        'alternative_file_filename_column' => 1, 'alternative_file_name_column' => 2,
        'alternative_file_description_column' => 3,
    ];

    [$ok, $messages] = $import($rows, $options);
    expect(get_alternative_files($resource))->toBeEmpty();
    if ($diagnostic !== null) {
        expect($ok)->toBeFalse()->and(implode("\n", $messages))->toContain($diagnostic);
        return;
    }
    expect($ok)->toBeTrue();
    [$ok] = $import($rows, $options, true);
    $alternatives = get_alternative_files($resource);
    expect($ok)->toBeTrue()->and($alternatives)->toHaveCount(1)->and(get_alternative_files($control))->toBeEmpty();
    expect($alternatives[0])->toMatchArray([
        'file_name' => $filename, 'file_extension' => 'jpg', 'name' => 'Alternate crop', 'description' => 'Client copy',
    ]);
})->with([
    'resource ID' => ['alternate.jpg', false, null],
    'metadata match' => ['alternate.jpg', true, null],
    'missing extension' => ['alternate', false, 'unable to extract extension'],
    'banned extension' => ['alternate.php', false, 'contains banned extension'],
]);
