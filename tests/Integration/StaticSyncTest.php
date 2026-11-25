<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;

$fixtures = null;
$sync = [];
beforeEach(function () use (&$fixtures, &$sync): void {
    $fixtures = new IntegrationFixtures();
    $id = generateRandomTestID(12);
    $directory = get_temp_dir(false, 'pest-staticsync-' . $id);
    $user = $fixtures->user('syncoperator' . $id, 3);
    $prefix = 'sync' . $id;
    $last_run = get_sysvar('lastsync', '');
    $sync = [
        'directory' => $directory,
        'prefix' => $prefix,
        'user' => $user,
        'settings' => [
            'syncdir' => $directory,
            'staticsync_userref' => $user,
            'staticsync_autotheme' => false,
            'staticsync_ingest' => false,
            'staticsync_delete_resources' => false,
            'enable_thumbnail_creation_on_upload' => false,
            'alternative_file_previews' => false,
            'file_upload_block_duplicates' => false,
            'theme_category_levels' => 20,
        ],
    ];
    $fixtures->onCleanup(static function () use ($fixtures, $directory, $prefix, $user, $last_run): void {
        foreach (ps_array('SELECT ref AS value FROM resource WHERE created_by = ?', ['i', $user]) as $ref) {
            $fixtures->trackResource($ref);
        }
        $collections = ps_array(
            'SELECT ref AS value FROM collection WHERE user = ? OR name = ? ORDER BY ref',
            ['i', $user, 's', ucfirst($prefix)]
        );
        foreach ($collections as $ref) {
            $fixtures->trackCollection($ref);
        }
        set_sysvar('lastsync', $last_run);
        rcRmdir($directory);
    });
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

$run = static function (array $sync, array $settings = [], array $arguments = []): string {
    $output = LocalTestScript::run('pages/tools/staticsync.php', $settings + $sync['settings'], $arguments);
    expect($output)->toContain('StaticSync completed')->not->toContain('ERRORS: -');
    clear_query_cache('featured_collections');
    return $output;
};

it('syncs a linked file without creating collections and archives the old path after a move', function () use (
    &$fixtures,
    &$sync,
    $run,
): void {
    $prefix = $sync['prefix'];
    $tree = $fixtures->field('Sync category', 0, FIELD_TYPE_CATEGORY_TREE);
    $path = $fixtures->field('Sync relative path');
    $original = $prefix . '/first';
    $replacement = $prefix . '/renamed';
    $directory = $sync['directory'];
    expect(mkdir($directory . '/' . $original, 0777, true))->toBeTrue();
    expect(copy(testFixture('Images/image_1.jpg'), $directory . '/' . $original . '/photo.jpg'))->toBeTrue();

    $settings = ['staticsync_mapped_category_tree' => $tree, 'staticsync_filepath_to_field' => $path];
    $run($sync, $settings);
    expect($directory . '/' . $original . '/photo.jpg')->toBeFile();
    $find = static fn (string $relative): int => (int) ps_value(
        'SELECT ref AS value FROM resource WHERE created_by = ? AND file_path = ?',
        ['i', $sync['user'], 's', $relative . '/photo.jpg'],
        0
    );
    $first = $find($original);
    expect($first)->toBeGreaterThan(0);
    expect(get_data_by_field($first, $path))->toBe($original . '/photo.jpg')
        ->and(array_column(get_data_by_field($first, $tree, false), 'name'))->toBe([$prefix, 'first']);
    expect(array_column(get_featured_collections(0, ['access_control' => false]), 'name'))->not->toContain(ucfirst($prefix));

    $unrelated = ps_array(
        'SELECT DISTINCT archive AS value FROM resource WHERE LENGTH(file_path) > 0 AND created_by <> ?',
        ['i', $sync['user']]
    );
    expect($unrelated)->toBeEmpty('Start this test with no other linked StaticSync fixtures in the test database.');
    expect(rename($directory . '/' . $original, $directory . '/' . $replacement))->toBeTrue();
    $run($sync, $settings);
    $second = $find($replacement);
    expect($second)->toBeGreaterThan(0)->not->toBe($first);
    expect((int) get_resource_data($first, false)['archive'])->toBe(0);

    $archive = ['staticsync_deleted_state' => 2, 'staticsync_ignore_deletion_states' => []];
    $run($sync, $settings + $archive + ['staticsync_delete_resources' => true], ['-d0']);
    expect((int) get_resource_data($first, false)['archive'])->toBe(0);
    $run($sync, $settings + $archive, ['-d1']);
    expect((int) get_resource_data($first, false)['archive'])->toBe(2)
        ->and((int) get_resource_data($second, false)['archive'])->toBe(0);
    $run($sync, $settings + ['staticsync_ingest' => true, 'staticsync_ingest_force' => true]);
    expect($directory . '/' . $replacement . '/photo.jpg')->not->toBeFile();
    expect((string) get_resource_data($second, false)['file_path'])->toBe('')
        ->and(get_resource_path($second, true, '', false, 'jpg'))->toBeFile();
});

it('ingests nested collections, mapped metadata and alternatives while skipping excluded folders', function () use (
    &$fixtures,
    &$sync,
    $run,
): void {
    $prefix = $sync['prefix'];
    $base = $sync['directory'] . '/' . $prefix;
    $tree = $fixtures->field('Ingest category', 0, FIELD_TYPE_CATEGORY_TREE);
    $project = $fixtures->field('Ingest project');
    $category = $fixtures->field('Ingest valley', 1);
    $name = $prefix . 'primary';
    $project_path = $base . '/projects/conference';
    $type_name = get_resource_type_name(1);
    foreach ([$project_path . '/paper.txt_alternatives', $base . '/ignored', $base . '/landscape/valley/' . $type_name] as $dir) {
        expect(mkdir($dir, 0777, true))->toBeTrue();
    }
    expect(copy(testFixture('Images/image_1.jpg'), $base . '/' . $name . '.jpg'))->toBeTrue();
    foreach ([
        'projects/conference/paper.txt' => 'Primary document',
        'projects/conference/paper.txt_alternatives/first.txt' => 'First appendix',
        'projects/conference/paper.txt_alternatives/second.txt' => 'Second appendix',
        'projects/conference/suffix.txt' => 'Sidecar original',
        'projects/conference/suffix_odd.txt' => 'Odd content',
        'projects/conference/suffix_side.txt' => 'Sidecar content',
        'landscape/valley/' . $type_name . '/mapped.txt' => 'Assigned to Photo by folder',
        $name . '_alt_notes.txt' => 'First alternative',
        'ignored/excluded.txt' => 'Excluded document',
    ] as $path => $content) {
        expect(file_put_contents($base . '/' . $path, $content))->toBeGreaterThan(0);
    }
    $settings = [
        'staticsync_ingest' => true,
        'staticsync_autotheme' => true,
        'staticsync_mapped_category_tree' => $tree,
        'staticsync_extension_mapping' => [1 => ['jpg'], 2 => ['txt']],
        'staticsync_alternative_file_text' => '_alt_',
        'staticsync_alt_suffixes' => true,
        'staticsync_alt_suffix_array' => ['_odd' => 'Odd file', '_side' => 'Sidecar file'],
        'staticsync_mapfolders' => [
            ['match' => '/projects/', 'field' => $project, 'level' => 3],
            ['match' => '/landscape/', 'field' => 'resource_type', 'level' => 4],
            ['match' => '/landscape/', 'field' => $category, 'level' => 3],
        ],
        'nogo' => '[ignored]',
    ];
    $run($sync, $settings);
    expect($base . '/' . $name . '.jpg')->not->toBeFile();
    expect($project_path)->toBeDirectory()
        ->and($base . '/ignored/excluded.txt')->toBeFile();

    $resources = ps_query(
        'SELECT ref, resource_type FROM resource WHERE created_by = ?',
        ['i', $sync['user']]
    );
    $by_filename = [];
    foreach ($resources as $resource) {
        $by_filename[get_data_by_field($resource['ref'], $GLOBALS['filename_field'])] = $resource;
    }
    expect(array_keys($by_filename))->toEqualCanonicalizing([$name . '.jpg', 'paper.txt', 'suffix.txt', 'mapped.txt']);
    expect($by_filename['paper.txt']['resource_type'])->toBe(2)
        ->and($by_filename['mapped.txt']['resource_type'])->toBe(1);
    $original = $by_filename[$name . '.jpg']['ref'];
    $paper = $by_filename['paper.txt']['ref'];
    $sidecars = $by_filename['suffix.txt']['ref'];
    expect(get_data_by_field($paper, $project))->toBe('conference')
        ->and(get_data_by_field($by_filename['mapped.txt']['ref'], $category))->toBe('valley')
        ->and(array_column(get_data_by_field($paper, $tree, false), 'name'))->toBe([$prefix, 'projects', 'conference'])
        ->and(array_column(get_alternative_files($paper), 'file_name'))->toEqualCanonicalizing(['first.txt', 'second.txt'])
        ->and(array_column(get_alternative_files($sidecars), 'name'))->toEqualCanonicalizing(['Odd file', 'Sidecar file'])
        ->and(array_column(get_alternative_files($original), 'description'))->toBe(['notes']);

    $root = array_column(get_featured_collections(0, ['access_control' => false]), 'ref', 'name');
    expect($root)->toHaveKey(ucfirst($prefix));
    $branches = array_column(get_featured_collections($root[ucfirst($prefix)], ['access_control' => false]), 'ref', 'name');
    expect($branches)->toHaveKeys([ucfirst($prefix), 'Projects']);
    expect(get_collection_resources($branches[ucfirst($prefix)]))->toBe([$original]);
    $projects = array_column(get_featured_collections($branches['Projects'], ['access_control' => false]), 'ref', 'name');
    expect($projects)->toHaveKey('Conference');
    expect(get_collection_resources($projects['Conference']))->toContain($paper, $sidecars);

    $later_alternative = $base . '/' . $name . '_alt_later.txt';
    expect(file_put_contents($later_alternative, 'Another alternative'))->toBeGreaterThan(0);
    $run($sync, $settings);
    expect($later_alternative)->not->toBeFile();
    expect(array_column(get_alternative_files($original), 'description'))->toEqualCanonicalizing(['notes', 'later']);
});
