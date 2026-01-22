<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Plugins\BrandGuidelines;

/** Get all Brand Guidelines pages */
function get_all_pages(): array
{
    return ps_query(
        sprintf(
            'SELECT %s FROM brand_guidelines_pages ORDER BY parent ASC, order_by ASC',
            BRAND_GUIDELINES_DB_COLS_PAGES
        ),
        [],
        'brand_guidelines_pages'
    );
}

/** Get a Brand Guidelines page (entire) content */
function get_page_contents(int $id): array
{
    return ps_query(
        sprintf(
            'SELECT %s FROM brand_guidelines_content WHERE `page` = ? ORDER BY order_by ASC',
            BRAND_GUIDELINES_DB_COLS_CONTENT
        ),
        ['i', $id],
        'brand_guidelines_content'
    );
}

/** Get a specific page content item */
function get_page_content_item(int $id): array
{
    $item = ps_query(
        sprintf('SELECT %s FROM brand_guidelines_content WHERE `ref` = ?', BRAND_GUIDELINES_DB_COLS_CONTENT),
        ['i', $id],
        'brand_guidelines_content'
    );
    return $item !== [] ? $item[0] : [];
}

/**
 * Create new Brand Guidelines page/section.
 * @param string $name Page/Section name
 * @param int $parent The sections' ID a page belongs to. Use zero for sections (i.e. root pages)
 */
function create_page(string $name, int $parent): int
{
    db_begin_transaction('brand_guidelines_create_page');
    ps_query(
        'INSERT INTO `brand_guidelines_pages` (`name`, `parent`, `order_by`)
        SELECT ?, ?, coalesce(max(order_by), 0) + 10 FROM brand_guidelines_pages WHERE `parent` = ?',
        [
            's', sql_truncate_text_val($name, 255),
            'i', $parent,
            'i', $parent,
        ]
    );
    $ref = sql_insert_id();
    log_activity(null, LOG_CODE_CREATED, $name, 'brand_guidelines_pages', 'name', $ref, null, '');
    db_end_transaction('brand_guidelines_create_page');
    clear_query_cache('brand_guidelines_pages');
    return $ref;
}

/**
 * Edit Brand Guidelines page/section.
 * @param int $ref Page ID
 * @param string $name Page name
 * @param int $parent The sections' ID a page belongs to. Use zero for sections (i.e. root pages)
 */
function save_page(int $ref, string $name, int $parent): void
{
    db_begin_transaction('brand_guidelines_save_page');
    log_activity(null, LOG_CODE_EDITED, $name, 'brand_guidelines_pages', 'name', $ref, null, null, null, true);
    log_activity(null, LOG_CODE_EDITED, $parent, 'brand_guidelines_pages', 'parent', $ref, null, null, null, true);
    ps_query(
        'UPDATE `brand_guidelines_pages` SET `name` = ?, `parent` = ? WHERE `ref` = ?',
        [
            's', sql_truncate_text_val($name, 255),
            'i', $parent,
            'i', $ref,
        ]
    );
    db_end_transaction('brand_guidelines_save_page');
    clear_query_cache('brand_guidelines_pages');
}

/**
 * Delete Brand Guidelines pages
 *
 * @param list<int> $refs List of page IDs
 * @return bool True if it executed the query, false otherwise
 */
function delete_pages(array $refs): bool
{
    $relevant_content_items = [];
    foreach ($refs as $page_ref) {
        $relevant_content_items = array_merge(
            $relevant_content_items,
            array_column(get_page_contents($page_ref), 'ref')
        );
    }

    db_begin_transaction('delete_brand_guidelines_pages');
    $deleted = db_delete_table_records(
        'brand_guidelines_pages',
        $refs,
        fn($ref) => log_activity(null, LOG_CODE_DELETED, null, 'brand_guidelines_pages', 'name', $ref)
    );
    if ($deleted && $relevant_content_items !== []) {
        $deleted = db_delete_table_records('brand_guidelines_content', $relevant_content_items, fn() => null);
    }
    db_end_transaction('delete_brand_guidelines_pages');
    clear_query_cache('brand_guidelines_pages');
    clear_query_cache('brand_guidelines_content');
    return $deleted;
}

/**
 * Re-order databse records (where order_by exists)
 * @param string $table Database table (where the operation takes place)
 * @param array{ref: int|numeric-string, order_by: int|numeric-string}
 * @param null|callable $filter Optional filter, if the $list has unnecessary elements
 */
function reorder_items(string $table, array $list, ?callable $filter): void
{
    $list_filtered = $filter === null ? array_filter($list) : array_filter($list, $filter); # PHP 7.4 support
    $ref_ob_map = array_map('intval', array_column($list_filtered, 'order_by', 'ref'));
    asort($ref_ob_map, SORT_NUMERIC);
    sql_reorder_records($table, array_keys($ref_ob_map));
    clear_query_cache($table);
}

/**
 * Create new database record for a content item
 * @param int $page The page ID the content item belongs to
 * @param array{type: BRAND_GUIDELINES_CONTENT_TYPES, fields: array, position_after: int} $item A content item data
 * structure
 * @return int Returns the ID of the new database record or zero otherwise.
 */
function create_content_item(int $page, array $item): int
{
    $content = json_encode(convert_to_db_content($item['fields']));
    if ($content === false) {
        debug(json_last_error_msg());
        return 0;
    }

    db_begin_transaction('brand_guidelines_create_content_item');
    ps_query(
        'INSERT INTO `brand_guidelines_content` (`page`, `type`, `content`, `order_by`)
              SELECT
                     ?,
                     ?,
                     ?,
                     coalesce(
                         (SELECT order_by + 5 FROM brand_guidelines_content WHERE ref = ?),
                         max(order_by) + 10,
                         10
                     )
                FROM brand_guidelines_content
               WHERE `page` = ?',
        [
            'i', $page,
            'i', $item['type'],
            's', $content,
            'i', $item['position_after'],
            'i', $page,
        ]
    );
    $ref = sql_insert_id();
    log_activity(null, LOG_CODE_CREATED, $content, 'brand_guidelines_content', 'content', $ref, null, '');
    db_end_transaction('brand_guidelines_create_content_item');
    clear_query_cache('brand_guidelines_content');
    return $ref;
}

/**
 * Save a content item database record
 * @param int $ref The content item ID
 * @param array{type: BRAND_GUIDELINES_CONTENT_TYPES, fields: array} A content item data structure
 */
function save_content_item(int $ref, array $item): bool
{
    $content = json_encode(convert_to_db_content($item['fields']));
    if ($content === false) {
        debug(json_last_error_msg());
        return false;
    }

    db_begin_transaction('brand_guidelines_save_page_content_item');
    log_activity(null, LOG_CODE_EDITED, $content, 'brand_guidelines_content', 'content', $ref, null, '');
    ps_query(
        'UPDATE `brand_guidelines_content` SET `content` = ? WHERE `ref` = ?',
        [
            's', $content,
            'i', $ref,
        ]
    );
    db_end_transaction('brand_guidelines_save_page_content_item');
    clear_query_cache('brand_guidelines_content');
    return true;
}

/**
 * Delete a Brand Guideline page content
 *
 * @param list<int> $refs List of page content IDs
 * @return bool True if it executed the query, false otherwise
 */
function delete_page_content(array $refs): bool
{
    db_begin_transaction('delete_brand_guidelines_content');
    $deleted = db_delete_table_records(
        'brand_guidelines_content',
        $refs,
        fn($ref) => log_activity(null, LOG_CODE_DELETED, null, 'brand_guidelines_content', 'content', $ref)
    );
    db_end_transaction('delete_brand_guidelines_content');
    clear_query_cache('brand_guidelines_content');
    return $deleted;
}
