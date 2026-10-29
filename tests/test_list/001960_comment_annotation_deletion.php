<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/comment_functions.php';
    $saved = $GLOBALS['userpermissions'];
    $get = $_GET;
    $post = $_POST;
    $fields = $GLOBALS['annotate_fields'];
    try {
        $resource = create_resource(1, 0);
        $_GET = [];
        $_POST = ['resource_ref' => $resource, 'body' => 'Parent café'];
        comments_submit();
        $parent = ps_value('SELECT ref value FROM comment WHERE resource_ref = ?', ['i', $resource], 0);
        $check($parent > 0, 'Comment creation failed');
        $_POST = ['ref_parent' => $parent, 'resource_ref' => $resource, 'body' => 'Child reply'];
        comments_submit();
        $child = ps_value('SELECT ref value FROM comment WHERE ref_parent = ?', ['i', $parent], 0);
        $check($child > 0, 'Threaded reply not linked');
        $GLOBALS['userpermissions'] = [];
        $before = ps_query('SELECT * FROM comment WHERE resource_ref = ? ORDER BY ref', ['i', $resource]);
        $check(!hide_delete_comment($parent), 'Comment moderation allowed without permission');
        $check(ps_query('SELECT * FROM comment WHERE resource_ref = ? ORDER BY ref', ['i', $resource]) === $before, 'Rejected moderation changed comments');
        $GLOBALS['userpermissions'] = array_unique(array_merge($saved, ['o']));
        $check(hide_delete_comment($parent), 'Moderator cannot hide parent');
        $check(ps_value('SELECT hide value FROM comment WHERE ref = ?', ['i', $parent], 0) == 1, 'Parent with reply not hidden');
        $check(hide_delete_comment($child), 'Moderator cannot remove reply');
        $check(ps_value('SELECT COUNT(*) value FROM comment WHERE resource_ref = ?', ['i', $resource], -1) == 0, 'Empty hidden thread not cleaned');
        $field = create_resource_type_field('Deletion annotation', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'deletionannotation');
        $GLOBALS['annotate_fields'] = [$field];
        $node = set_node(null, $field, 'Annotation tag', '', 10);
        $input = ['resource' => $resource, 'resource_type_field' => $field, 'tags' => [['ref' => $node, 'name' => 'Annotation tag']],
            'shapes' => [['type' => 'rect', 'geometry' => ['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.4]]]];
        $id = createAnnotation($input, []);
        $check($id > 0, 'Annotation fixture creation failed');
        $annotation = getAnnotation($id);
        $GLOBALS['userpermissions'] = [];
        $check(!deleteAnnotation($annotation, []), 'Annotation deletion permitted without access');
        $check(getAnnotation($id) !== [], 'Rejected annotation deletion changed row');
        $GLOBALS['userpermissions'] = $saved;
        $check(deleteAnnotation($annotation, []), 'Authorized annotation deletion failed');
        $check(getAnnotation($id) === []
            && ps_value('SELECT COUNT(*) value FROM annotation_node WHERE annotation = ?', ['i', $id], -1) == 0
            && ps_value('SELECT COUNT(*) value FROM resource_node WHERE resource = ? AND node = ?', ['i', $resource, 'i', $node], -1) == 0, 'Annotation deletion left tags or mappings');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
        $GLOBALS['annotate_fields'] = $fields;
        $_GET = $get;
        $_POST = $post;
    }
})();
