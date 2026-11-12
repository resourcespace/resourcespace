<?php

command_line_only();

return (static function (): bool {
    $check = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $permissions = $GLOBALS['userpermissions'];
    $language_before = $GLOBALS['language'];
    $default_before = $GLOBALS['defaultlanguage'];
    $fields = [];
    try {
        $GLOBALS['userpermissions'] = ['f*'];
        $GLOBALS['language'] = 'fr';
        $GLOBALS['defaultlanguage'] = 'en';
        $flat = create_resource_type_field('Search options', 0, FIELD_TYPE_CHECK_BOX_LIST, 'searchoptions');
        $fields[] = $flat;
        $first = set_node(null, $flat, 'Alpha', null, 10);
        $second = set_node(null, $flat, 'Beta', null, 20);
        $translated = set_node(null, $flat, '~en:English~fr:Francais', null, 30);
        $default = set_node(null, $flat, '~en:Default~de:Deutsch', null, 40);
        $first_language = set_node(null, $flat, '~de:Erste~es:Otra', null, 50);
        $accent = set_node(null, $flat, 'Caf' . "\u{00e9}", null, 60);
        $literal = set_node(null, $flat, '100%_!', null, 70);
        $inactive = set_node(null, $flat, 'Inactive', null, 90);
        ps_query('UPDATE node SET active = 0 WHERE ref = ?', ['i', $inactive]);
        $hidden_child = set_node(null, $flat, 'Hidden child', $first, 1);
        clear_query_cache('schema');

        $preprocess = static function (string $search): string {
            return resolve_field_search_node_names(
                $search,
                split_keywords($search, false, false, false, false, true)
            );
        };
        $cases = [
            'searchoptions:Alpha' => 'searchoptions:Alpha',
            'searchoptions:alpha;BETA' => NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $second,
            'searchoptions:Alpha;missing' => NODE_TOKEN_PREFIX . $first,
            'searchoptions:missing;unknown' => 'searchoptions:missing;unknown',
            'unknown:Alpha;Beta' => 'unknown:Alpha;Beta',
            'searchoptions:Al%;missing' => 'searchoptions:Al%;missing',
            'searchoptions:Alpha;Alpha' => NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $first,
            'searchoptions:Francais;English' => NODE_TOKEN_PREFIX . $translated,
            'searchoptions:Default;Erste' => NODE_TOKEN_PREFIX . $default . NODE_TOKEN_PREFIX . $first_language,
            'searchoptions:Cafe;CAF' . "\u{00c9}" => NODE_TOKEN_PREFIX . $accent,
            'searchoptions:100%_!;Inactive' => NODE_TOKEN_PREFIX . $literal . NODE_TOKEN_PREFIX . $inactive,
            '"searchoptions:Hidden child;Beta"' => NODE_TOKEN_PREFIX . $second,
            '"searchoptions: Alpha ;Beta"' => NODE_TOKEN_PREFIX . $second,
            '"searchoptions:Alpha;Beta:other"' => '"searchoptions:Alpha;Beta:other"',
        ];
        foreach ($cases as $input => $expected) {
            $check($preprocess($input) === $expected, 'Unexpected preprocessing for ' . $input);
        }
        $repeated = 'searchoptions:Alpha;Beta searchoptions:Alpha;Beta';
        $tokens = NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $second;
        $check($preprocess($repeated) === $tokens . ' ' . $tokens, 'Repeated terms changed');
        $GLOBALS['userpermissions'] = ['f*', 'f-' . $flat];
        $check($preprocess($repeated) === $repeated, 'Hidden field resolved');
        $GLOBALS['userpermissions'] = [];
        $check($preprocess($repeated) === $repeated, 'Field resolved without view access');
        $GLOBALS['userpermissions'] = ['f*'];

        $tree = create_resource_type_field('Search tree', 0, FIELD_TYPE_CATEGORY_TREE, 'searchtree');
        $fields[] = $tree;
        $parent = set_node(null, $tree, 'Parent', null, 10);
        $child = set_node(null, $tree, 'Duplicate', $parent, 100);
        set_node(null, $tree, 'Duplicate', null, 20);
        $check(
            $preprocess('searchtree:Duplicate;Parent') === NODE_TOKEN_PREFIX . $child . NODE_TOKEN_PREFIX . $parent,
            'Tree did not resolve duplicate name in traversal order'
        );

        $large = create_resource_type_field('Large search text', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'searchlarge');
        $fields[] = $large;
        $partial = set_node(null, $large, 'Contains target', null, 0);
        // Bulk fixture insertion avoids indexing thousands of unused test nodes.
        for ($batch = 0; $batch < 10; $batch++) {
            $values = [];
            $parameters = [];
            for ($i = 0; $i < 1000; $i++) {
                $index = $batch * 1000 + $i + 1;
                $values[] = '(?, ?, NULL, ?)';
                array_push($parameters, 'i', $large, 's', 'Option ' . $index, 'i', $index);
            }
            ps_query('INSERT INTO node (resource_type_field, name, parent, order_by) VALUES '
                . implode(', ', $values), $parameters);
        }
        $outside = set_node(null, $large, 'target', null, 10001);
        $remote = set_node(null, $large, '~en:Remote~fr:Eloigne', null, 10002);
        $edge = get_node_id('Option 9999', $large);
        $expected = NODE_TOKEN_PREFIX . $partial . NODE_TOKEN_PREFIX . $edge;
        $check(
            $preprocess('"searchlarge:target;Option 9999"') === $expected,
            'Large-field fallback changed exact-match precedence at the cap'
        );
        $check(
            $preprocess('searchlarge:Remote;absent') === NODE_TOKEN_PREFIX . $remote,
            'Large-field fallback stopped matching raw multilingual values'
        );
        $check(
            $preprocess('searchlarge:;absent') === NODE_TOKEN_PREFIX . $partial,
            'Empty-option fallback changed'
        );
        $check(
            $preprocess('searchlarge:tar%;absent') === NODE_TOKEN_PREFIX . $partial,
            'Large-field wildcard fallback changed'
        );

        // Fixed vocabularies have no cap: an exact match beyond 10k wins.
        ps_query(
            'UPDATE resource_type_field SET type = ? WHERE ref = ?',
            ['i', FIELD_TYPE_CHECK_BOX_LIST, 'i', $large]
        );
        clear_query_cache('schema');
        $check(
            $preprocess('searchlarge:target;absent') === NODE_TOKEN_PREFIX . $outside,
            'Fixed vocabulary was capped'
        );
        $check(
            $preprocess('searchlarge:Remote;absent') === 'searchlarge:Remote;absent',
            'Fixed vocabulary incorrectly used the large-field fallback'
        );
        delete_node($outside);
        delete_node($remote);
        delete_node(get_node_id('Option 10000', $large));
        $check(
            $preprocess('searchlarge:target;absent') === NODE_TOKEN_PREFIX . $partial,
            'Exactly 10k fixed options lost the legacy fallback'
        );
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $permissions;
        $GLOBALS['language'] = $language_before;
        $GLOBALS['defaultlanguage'] = $default_before;
        foreach ($fields as $field) {
            delete_resource_type_field($field);
        }
    }
})();
