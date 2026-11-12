<?php

command_line_only();

include_once __DIR__ . "/../../include/image_processing.php";

$resource500 = create_resource(1, 0);

// Test A - check that minimal previews are created
$enable_thumbnail_creation_on_upload = false;

$valid_upload_paths[] = __DIR__ . '/../../gfx/homeanim/';
upload_file($resource500, true, false, false, __DIR__ . '/../../gfx/homeanim/1.jpg', false, false);

$result = start_previews($resource500);
if (
    $result !== 2
    || file_exists(get_resource_path($resource500, true, 'lpr'))
    || !file_exists(get_resource_path($resource500, true, 'col'))
    || !file_exists(get_resource_path($resource500, true, 'thm'))
    || !file_exists(get_resource_path($resource500, true, 'pre'))
) {
    echo 'Test A (minimal previews not created) - ';
    return false;
}

if (strlen(get_resource_data($resource500, false)['blurhash'] ?? '') !== 28) {
    echo 'Test A (minimal preview hash not stored) - ';
    return false;
}

$previews_allow_enlarge = true; // To always get lpr size
$image_alternatives = [];
$image_alternatives[0]["name"] = "PNG File";
$image_alternatives[0]["source_extensions"] = "jpg";
$image_alternatives[0]["source_params"] = "";
$image_alternatives[0]["filename"] = "alternative_png";
$image_alternatives[0]["target_extension"] = "png";
$image_alternatives[0]["params"] = "-density 300"; # 300 dpi
$image_alternatives[0]["icc"] = false;

// Test B - perform full preview creation
create_previews($resource500);

if (!file_exists(get_resource_path($resource500, true, 'lpr'))) {
    echo 'Test B (full previews not created) - ';
    return false;
}

// Test C - check alternatives
$alternatives = get_alternative_files($resource500);
if (count($alternatives) === 0 || !file_exists(get_resource_path($resource500, true, '', true, 'png', true, 1, false, '', $alternatives[0]["ref"]))) {
    echo 'Test C ($image_alternatives not created) - ';
    return false;
}

// Test D - primary previews store a hash, including minimal previews and regeneration.
ps_query('UPDATE resource SET blurhash=NULL WHERE ref=?', ['i', $resource500]);
create_previews($resource500, onlysizes: ['thm']);
$hash = get_resource_data($resource500, false)['blurhash'];
if (!is_string($hash) || strlen($hash) !== 28) {
    echo 'Test D (thumbnail hash not stored) - ';
    return false;
}
delete_previews($resource500, $alternatives[0]['ref']);
if (get_resource_data($resource500, false)['blurhash'] !== $hash) {
    echo 'Test D (alternative preview deletion changed primary hash) - ';
    return false;
}
if (!tweak_preview_images($resource500, 90, 0) || get_resource_data($resource500, false)['blurhash'] === $hash) {
    echo 'Test D (rotated preview hash not refreshed) - ';
    return false;
}

// Test E - backfill is bounded, skips unreadable thumbnails and progresses past them.
$saved_blurhash_cursor = get_sysvar('blurhash_backfill_cursor', (string) PHP_INT_MAX);
$saved_blurhash_run = get_sysvar('last_blurhash_backfill', '1970-01-01');
try {
    $missing_thumb = create_resource(1, 0);
    $valid_thumb = create_resource(1, 0);
    copy(
        get_resource_path($resource500, true, 'thm'),
        get_resource_path($valid_thumb, true, 'thm', true)
    );
    ps_query(
        'UPDATE resource SET has_image=1, preview_extension=? WHERE ref IN (?, ?)',
        ['s', 'jpg', 'i', $missing_thumb, 'i', $valid_thumb]
    );
    set_sysvar('blurhash_backfill_cursor', $valid_thumb + 1);
    if (
        backfill_resource_blurhashes(1) !== 1
        || strlen(get_resource_data($valid_thumb, false)['blurhash']) !== 28
        || (int) get_sysvar('blurhash_backfill_cursor') !== $valid_thumb
        || get_resource_data($missing_thumb, false)['blurhash'] !== null
        || backfill_resource_blurhashes(1) !== 0
        || (int) get_sysvar('blurhash_backfill_cursor') !== $missing_thumb
    ) {
        echo 'Test E (backfill limit or cursor failed) - ';
        return false;
    }
    $before = get_resource_data($valid_thumb, false)['file_modified'];
    if (backfill_resource_blurhashes(1) !== 0 || (int) get_sysvar('blurhash_backfill_cursor') !== PHP_INT_MAX) {
        echo 'Test E (backfill cursor did not wrap) - ';
        return false;
    }
    ps_query('UPDATE resource SET blurhash=NULL WHERE ref=?', ['i', $valid_thumb]);
    set_sysvar('last_blurhash_backfill', '1970-01-01');
    set_sysvar('blurhash_backfill_cursor', $valid_thumb + 1);
    include __DIR__ . '/../../batch/cron_jobs/017_blurhash.php';
    if (
        strlen(get_resource_data($valid_thumb, false)['blurhash']) !== 28
        || get_resource_data($valid_thumb, false)['file_modified'] !== $before
    ) {
        echo 'Test E (cron backfill failed or changed file modification date) - ';
        return false;
    }
} finally {
    set_sysvar('blurhash_backfill_cursor', $saved_blurhash_cursor);
    set_sysvar('last_blurhash_backfill', $saved_blurhash_run);
}

delete_previews($resource500);
if (get_resource_data($resource500, false)['blurhash'] !== null) {
    echo 'Test F (deleted preview retained hash) - ';
    return false;
}

// Existing installations add the nullable column automatically on first use.
ps_query('ALTER TABLE resource DROP COLUMN blurhash');
if (ps_value('SELECT blurhash AS value FROM resource WHERE ref=?', ['i', $resource500], '') !== null) {
    echo 'Test G (automatic schema upgrade failed) - ';
    return false;
}
