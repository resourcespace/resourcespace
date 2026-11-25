<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'field_column_string_separator', 'data_joins', 'view_title_field', 'use_native_input_for_date_field',
        'userpermissions', 'language', 'defaultlanguage', 'edit_contributed_by', 'use_error_exception',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('stores text, category paths and translated keywords and logs their changes', function () use (&$fixtures): void {
    $title = $fixtures->field('Editable title', 1);
    $tree = $fixtures->field('Editable category tree', 1, FIELD_TYPE_CATEGORY_TREE);
    $keywords = $fixtures->field('Translated dynamic keywords', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $resource = $fixtures->resource(1, 0);
    $GLOBALS['view_title_field'] = $title;
    check_db_structs();
    $errors = [];
    expect(update_field($resource, $title, 'Cheddar', $errors))->toBeTrue()
        ->and(get_data_by_field($resource, $title))->toBe('Cheddar')
        ->and(get_resource_data($resource, false)['field' . $title])->toBe('Cheddar');
    $log = get_resource_log($resource, 1, ['r.type' => LOG_CODE_EDITED])['data'][0];
    expect($log['title'])->toBe('Editable title')->and($log['diff'])->toBe('+ Cheddar');

    $fruit = set_node(null, $tree, 'Fruit', null, 10);
    $apple = set_node(null, $tree, 'Apple', $fruit, 10);
    set_node(null, $tree, 'Pear', $fruit, 20);
    expect(update_field($resource, $tree, 'Apple' . NODE_NAME_STRING_SEPARATOR . 'Fruit', $errors))->toBeTrue()
        ->and(get_resource_nodes($resource, $tree))->toEqualCanonicalizing([$fruit, $apple])
        ->and(get_data_by_field($resource, $tree))->toBe('Fruit/Apple');
    $log = get_resource_log($resource, 1, ['r.type' => LOG_CODE_EDITED])['data'][0];
    expect($log['title'])->toBe('Editable category tree')
        ->and(explode("\n", $log['diff']))->toEqualCanonicalizing(['+ Fruit/Apple', '+ Fruit']);

    $GLOBALS['language'] = 'en';
    $GLOBALS['defaultlanguage'] = 'en';
    $option = set_node(null, $keywords, '~en:Edit properties~fr:Modifier les proprietes', null, null);
    $GLOBALS['userpermissions'][] = 'bdk' . $keywords;
    expect(update_field($resource, $keywords, 'Edit properties', $errors))->toBeTrue()
        ->and(get_resource_nodes($resource, $keywords))->toBe([$option]);
});

it('preserves the stored date when an invalid edit is rejected', function (string $entry_point) use (&$fixtures): void {
    $GLOBALS['use_native_input_for_date_field'] = true;
    $field = $fixtures->field('Validated date', 1, FIELD_TYPE_DATE);
    $first = $fixtures->resource(1, 0);
    $resources = [$first];
    $collection = null;
    if ($entry_point === 'batch') {
        $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Date edit ' . generateRandomTestID(10));
        $resources[] = $fixtures->resource(1, 0);
        foreach ($resources as $ref) {
            expect(add_resource_to_collection($ref, $collection))->toBeTrue();
        }
    }
    $save_date = static function (string $value) use ($entry_point, $first, $field, $collection): array|bool {
        if ($entry_point === 'batch') {
            return save_resource_data_multi($collection, [], [
                'field_' . $field => $value, 'editthis_field_' . $field => 'yes', 'modeselect_' . $field => 'RT',
            ]);
        }
        if ($entry_point === 'autosave') {
            $_POST = ['field_' . $field => $value];
            return save_resource_data($first, false, $field);
        }
        $errors = [];
        return update_field($first, $field, $value, $errors, false) ? true : $errors;
    };

    expect($save_date('2023-04-18'))->toBeTrue();
    $invalid = $save_date('01-04-2022');
    expect($invalid)->toBeArray()->toHaveCount(1);
    foreach ($resources as $ref) {
        expect(get_data_by_field($ref, $field))->toBe('2023-04-18');
    }
})->with(['direct update', 'autosave', 'batch']);

it('honours disabled options and stores joined field values in configured order', function (bool $bulk) use (&$fixtures): void {
    $checkbox = $fixtures->field('Checkbox data', 1, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $tree = $fixtures->field('Tree data', 1, FIELD_TYPE_CATEGORY_TREE, '', true);
    $dropdown = $fixtures->field('Dropdown data', 1, FIELD_TYPE_DROP_DOWN_LIST);
    $scanned = set_node(null, $checkbox, '~en:Scanned negative~fr:Negatif scanne', null, 20);
    $digital = set_node(null, $checkbox, '~en:Digital camera~fr:Appareil photo', null, 10);
    $inactive = set_node(null, $checkbox, 'Inactive checkbox', null, 30);
    $replacement = set_node(null, $dropdown, 'Replacement', null, 10);
    $retired = set_node(null, $dropdown, 'Retired', null, 20);
    $colours = set_node(null, $tree, 'Colours', null, 10);
    $red = set_node(null, $tree, 'Red', $colours, 20);
    $black = set_node(null, $tree, 'Black', $colours, 10);
    $numbers = set_node(null, $tree, 'Numbers', null, 20);
    expect(toggle_active_state_for_nodes([$inactive, $retired]))->toEqual([$inactive => 0, $retired => 0]);
    $resources = [$fixtures->resource(1, 0)];
    $collection = null;
    if ($bulk) {
        $resources[] = $fixtures->resource(1, 0);
        $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Joined metadata ' . generateRandomTestID(10));
        foreach ($resources as $ref) {
            expect(add_resource_to_collection($ref, $collection))->toBeTrue();
        }
    }
    $GLOBALS['field_column_string_separator'] = '|:|';
    $GLOBALS['data_joins'] = array_merge($GLOBALS['data_joins'], [$checkbox, $tree]);
    check_db_structs();
    $save_nodes = static function (int $field, array $nodes, string $mode = 'RT') use ($bulk, $collection, $resources): void {
        if ($bulk) {
            expect(save_resource_data_multi($collection, [], [
                'nodes' => [$field => $nodes], 'editthis_field_' . $field => 'yes', 'modeselect_' . $field => $mode,
            ]))->toBeTrue();
        } else {
            $_POST = ['nodes' => [$field => $nodes]];
            expect(save_resource_data($resources[0], false, $field))->toBeTrue();
        }
    };

    $save_nodes($checkbox, [$scanned, $digital]);
    $save_nodes($tree, [$colours, $red, $black]);
    foreach ($resources as $ref) {
        expect(get_resource_nodes($ref, $checkbox))->toEqualCanonicalizing([$scanned, $digital]);
        $data = get_resource_data($ref, false);
        expect($data['field' . $checkbox])->toBe('~en:Digital camera~fr:Appareil photo|:|~en:Scanned negative~fr:Negatif scanne')
            ->and($data['field' . $tree])->toBe('Colours|:|Colours/Black|:|Colours/Red');
    }

    $save_nodes($checkbox, [$scanned, $inactive, $digital]);
    foreach ($resources as $ref) {
        expect(get_resource_nodes($ref, $checkbox))->toEqualCanonicalizing([$scanned, $digital]);
        add_resource_nodes($ref, [$inactive, $retired], false, false);
    }

    $save_nodes($checkbox, [$digital]);
    $save_nodes($dropdown, [$replacement]);
    foreach ($resources as $ref) {
        expect(get_resource_nodes($ref, $checkbox))->toEqualCanonicalizing([$digital, $inactive])
            ->and(get_resource_nodes($ref, $dropdown))->toBe([$replacement]);
    }
    if ($bulk) {
        $save_nodes($tree, [$numbers, $colours, $red, $black], 'AP');
        foreach ($resources as $ref) {
            expect(get_resource_data($ref, false)['field' . $tree])->toBe('Colours|:|Colours/Black|:|Colours/Red|:|Numbers');
        }
    }
})->with(['autosave' => false, 'batch' => true]);

it('preserves formatting in valid rich text and rejects disallowed markup', function (
    string $html,
    bool $valid,
) use (&$fixtures): void {
    $field = $fixtures->field('Formatted metadata', 1, FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE);
    $resource = $fixtures->resource(1, 0);
    $_POST = ['field_' . $field => $html];

    $result = save_resource_data($resource, false, $field);
    if ($valid) {
        expect($result)->toBeTrue()->and(get_data_by_field($resource, $field))->toBe($html);
    } else {
        expect($result)->toBe([$field => 'Formatted metadata: ' . $GLOBALS['lang']['save-error-invalid']])
            ->and(get_data_by_field($resource, $field))->toBe('');
    }
})->with([
    'formatted content' => ['<p><span style="text-decoration: underline;">A</span> <strong>bold</strong> <em>example</em></p>', true],
    'script element' => ['<p>Invalid content</p><script>console.log("fixture");</script>', false],
    'event handler' => ['<p><a href="#" onclick="console.log(1)">Link</a></p>', false],
    'script link' => ['<p><a href="javascript:console.log(1)">Link</a></p>', false],
]);

it('saves both date range endpoints and replaces them on the next form save', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $range = $fixtures->field('Collection date range', 1, FIELD_TYPE_DATE_RANGE);
    $_POST = [
        "field_{$range}_start-y" => '2021',
        "field_{$range}_start-m" => '01',
        "field_{$range}_start-d" => '15',
        "field_{$range}_end-y" => '2021',
        "field_{$range}_end-m" => '07',
        "field_{$range}_end-d" => '09',
    ];
    expect(save_resource_data($resource, false, $range))->toBeTrue()
        ->and(array_column(get_data_by_field($resource, $range, false), 'name'))
        ->toEqualCanonicalizing(['2021-01-15', '2021-07-09']);

    $_POST = ["field_{$range}_edtf" => '2023-01-09/2024-02-20'];
    expect(save_resource_data($resource, false, $range))->toBeTrue()
        ->and(array_column(get_data_by_field($resource, $range, false), 'name'))
        ->toEqualCanonicalizing(['2023-01-09', '2024-02-20']);
});

it('omits disabled fields and excludes private metadata from the external view', function () use (&$fixtures): void {
    $ref = $fixtures->resource(1, 0);
    $visible = $fixtures->field('Public summary', 1);
    $private = $fixtures->field('Internal summary', 1);
    $disabled = $fixtures->field('Disabled summary', 1);
    $errors = [];
    foreach ([$visible, $private, $disabled] as $field) {
        expect(update_field($ref, $field, 'Stored description ' . $field, $errors))->toBeTrue();
    }
    $columns = array_intersect_key(get_resource_type_field_columns(), ['active' => true, 'external_user_access' => true]);
    expect(save_resource_type_field($visible, $columns, ['active' => 1, 'external_user_access' => 1]))->toBeTrue()
        ->and(save_resource_type_field($private, $columns, ['active' => 1, 'external_user_access' => 0]))->toBeTrue()
        ->and(save_resource_type_field($disabled, $columns, ['active' => 0]))->toBeTrue();

    $internal = array_column(get_resource_field_data($ref), 'value', 'ref');
    $external = array_column(get_resource_field_data($ref, external_access: true), 'value', 'ref');
    expect($internal[$private])->toBe('Stored description ' . $private)
        ->and($external[$visible])->toBe('Stored description ' . $visible);
    expect($internal)->not->toHaveKey($disabled);
    expect($external)->not->toHaveKey($disabled)->not->toHaveKey($private);
});

it('links each displayed metadata pill to the matching resource search', function (int $type, bool $multiple) use (
    &$fixtures,
): void {
    $id = generateRandomTestID(12);
    $user = $fixtures->user('pillreader' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $field = $fixtures->field('Catalogue medium ' . $id, 1, $type);
    $labels = ['Glass plate', 'Paper print', 'Film negative'];
    $nodes = [];
    foreach ($labels as $index => $label) {
        $nodes[] = set_node(null, $field, $label, null, ($index + 1) * 10);
    }
    [$glass, $paper, $film] = $nodes;
    $both = $fixtures->resource(1, 0);
    $paper_only = $fixtures->resource(1, 0);
    $film_only = $fixtures->resource(1, 0);
    add_resource_nodes($both, $multiple ? [$glass, $paper] : [$glass]);
    add_resource_nodes($paper_only, $multiple ? [$paper] : [$glass]);
    add_resource_nodes($film_only, [$film]);

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $view = $server->request('/pages/view.php?ref=' . $both, headers: $headers);
    expect($view['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//a[div[@class="fixedlistnodepill"]]');
    $pills = [];
    foreach ($links as $link) {
        $label = str_replace("\u{200B}", '', trim($link->textContent));
        if (in_array($label, $labels, true)) {
            $pills[$label] = $link->getAttribute('href');
        }
    }
    $expected = $multiple
        ? [$labels[0] => [$glass, [$both]], $labels[1] => [$paper, [$both, $paper_only]]]
        : [$labels[0] => [$glass, [$both, $paper_only]]];
    expect(array_keys($pills))->toEqualCanonicalizing(array_keys($expected));
    foreach ($expected as $label => [$node, $refs]) {
        $url = parse_url($pills[$label]);
        expect($url['path'])->toBe('/pages/search.php');
        parse_str($url['query'], $query);
        expect($query['search'])->toBe(NODE_TOKEN_PREFIX . $node);
        $results = $server->request($url['path'] . '?' . $url['query'], headers: $headers);
        expect($results['status'])->toBe(200);
        $document->loadHTML($results['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $cards = (new DOMXPath($document))->query('//*[@id="CentralSpace"]//*[starts-with(@id, "ResourceShell")]');
        $actual = [];
        foreach ($cards as $card) {
            $actual[] = (int) substr($card->getAttribute('id'), strlen('ResourceShell'));
        }
        expect($actual)->toEqualCanonicalizing($refs);
    }
})->with([
    'checkbox list' => [FIELD_TYPE_CHECK_BOX_LIST, true],
    'dropdown' => [FIELD_TYPE_DROP_DOWN_LIST, false],
    'category tree' => [FIELD_TYPE_CATEGORY_TREE, true],
    'dynamic keywords' => [FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, true],
    'radio buttons' => [FIELD_TYPE_RADIO_BUTTONS, false],
]);

it('lets an editor save only the individually permitted metadata fields', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $editable = $fixtures->field('Editable caption ' . $id, 1);
    $locked = $fixtures->field('Read-only caption ' . $id, 1);
    $resource = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($resource, $editable, 'Old caption', $errors))->toBeTrue()
        ->and(update_field($resource, $locked, 'Protected caption', $errors))->toBeTrue();

    $group = $fixtures->group('Field editors ' . $id, 's,g,e0,f*,F*,F-' . $editable);
    $user = $fixtures->user('fieldeditor' . $id, $group);
    setup_user(get_user($user));
    expect(get_edit_access($resource))->toBeTrue()
        ->and(metadata_field_edit_access($editable))->toBeTrue()
        ->and(metadata_field_edit_access($locked))->toBeFalse();
    $_POST = [
        'field_' . $editable => 'New caption',
        'field_' . $locked => 'Disallowed change',
    ];
    expect(save_resource_data($resource, false, [$editable, $locked]))->toBeTrue();
    expect(get_data_by_field($resource, $editable))->toBe('New caption')
        ->and(get_data_by_field($resource, $locked))->toBe('Protected caption');
});

it('renders the metadata edit control with its required marker, number input and help for the assigned resource type', function () use (
    &$fixtures,
): void {
    $id = generateRandomTestID(10);
    $admin = $fixtures->user('metadata_controls_' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $admin,
    ]);
    $photo = $fixtures->resource(1, 0);
    $document = $fixtures->resource(2, 0);
    $field = $fixtures->field('Catalogue number ' . $id, 1);
    $help = 'Enter the numeric catalogue reference.';
    $columns = array_intersect_key(get_resource_type_field_columns(), array_fill_keys([
        'active', 'required', 'field_constraint', 'help_text',
    ], true));
    expect(save_resource_type_field($field, $columns, [
        'active' => 1, 'required' => 1, 'field_constraint' => 1, 'help_text' => $help,
    ]))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $edit = static function (int $resource) use ($server, $headers): DOMXPath {
        $response = $server->request('/pages/edit.php?ref=' . $resource, headers: $headers);
        expect($response['status'])->toBe(200);
        $page = new DOMDocument();
        $page->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($page);
    };
    $input = '//form[@id="mainform"]//input[@name="field_' . $field . '"]';
    $xpath = $edit($photo);
    expect($xpath->evaluate('string(' . $input . '/@type)'))->toBe('number');
    expect($xpath->evaluate('normalize-space(//label[@for="field_' . $field . '"]/sup)'))->toBe('*');
    expect($xpath->evaluate('normalize-space(//div[@id="help_' . $field . '"])'))->toBe($help);
    expect($edit($document)->evaluate('count(' . $input . ')'))->toBe(0.0);

    update_resource_type_field_resource_types($field, [2]);
    expect($edit($photo)->evaluate('count(' . $input . ')'))->toBe(0.0)
        ->and($edit($document)->evaluate('count(' . $input . ')'))->toBe(1.0);

    expect(save_resource_type_field($field, $columns, ['active' => 0]))->toBeTrue();
    expect($edit($document)->evaluate('count(' . $input . ')'))->toBe(0.0);
});

it('changes a contributor only when the setting and editor permission allow it', function (
    bool $enabled,
    bool $admin,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $contributor = $fixtures->user('original_contributor_' . $id);
    $new_name = 'new_contributor_' . $id;
    $new_contributor = $fixtures->user($new_name);
    $ref = $fixtures->resource(1, 0, $contributor);
    $GLOBALS['use_error_exception'] = true;
    $GLOBALS['edit_contributed_by'] = $enabled;
    $GLOBALS['userpermissions'] = $admin ? ['e0', 'v'] : ['e0'];
    $new_value_applied = $enabled && $admin;
    $existing_post = $_POST;

    try {
        $_POST = ['created_by' => (string) $new_contributor];
        expect(save_resource_data($ref, false))->toBeTrue();
    } finally {
        $_POST = $existing_post;
    }
    expect((int) get_resource_data($ref, false)['created_by'])
        ->toBe($new_value_applied ? $new_contributor : $contributor);
    $log = bypass_permissions(['v'], get_resource_log(...), [
        $ref, 1, ['r.type' => LOG_CODE_CREATED_BY_CHANGED],
    ])['data'];
    expect($log)->toHaveCount($new_value_applied ? 1 : 0);
    if ($new_value_applied) {
        expect($log[0]['diff'])->toContain($new_name);
    }
})->with([
    'admin with the setting' => [true, true],
    'admin without the setting' => [false, true],
    'editor without administrative permission' => [true, false],
]);
