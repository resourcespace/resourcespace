<?php

// Display only; request authorisation and file operations live in storage_test_functions.php.
if (!storage_test_allowed()) {
    return;
}
$storage_test_strings = array_filter(
    $lang,
    static fn($key) => str_starts_with($key, 'storage_test_'),
    ARRAY_FILTER_USE_KEY
);
?>
<div id="storage-test" class="RecordPanel" hidden>
    <h2><?php echo escape($lang['storage_test_title']); ?></h2>
    <p><?php echo escape($lang['storage_test_intro']); ?></p>
    <p id="storage-test-status" role="status" aria-live="polite"></p>
    <p>
        <?php echo escape($lang['storage_test_elapsed']); ?>: <span id="storage-test-elapsed">0</span> s<br>
        <span id="storage-test-waiting" role="status"></span>
    </p>
    <p>
        <label for="storage-test-progress"><?php echo escape($lang['storage_test_progress']); ?></label>
        <progress id="storage-test-progress" max="100" value="0"></progress>
        <span id="storage-test-percent">0%</span>
    </p>
    <p>
        <?php echo escape($lang['storage_test_operations']); ?>:
        <span id="storage-test-operations">0</span> / <span id="storage-test-operations-total">0</span><br>
        <?php echo escape($lang['storage_test_written']); ?>: <span id="storage-test-written">0</span><br>
        <?php echo escape($lang['storage_test_read_bytes']); ?>: <span id="storage-test-read">0</span><br>
        <?php echo escape($lang['storage_test_last_operation']); ?>: <span id="storage-test-last-operation"></span><br>
        <strong>
            <?php echo escape($lang['storage_test_operation']); ?>: <span id="storage-test-operation"></span>
        </strong>
    </p>
    <button type="button" id="storage-test-cancel"><?php echo escape($lang['cancel']); ?></button>
    <p id="storage-test-warning" role="status" hidden></p>
    <div id="storage-test-results" hidden>
        <h3><?php echo escape($lang['storage_test_results_heading']); ?></h3>
        <p><?php echo escape($lang['storage_test_results_explanation']); ?></p>
        <div class="Listview">
            <table class="ListviewStyle">
                <thead>
                    <tr class="ListviewTitleStyle">
                        <th scope="col"><?php echo escape($lang['storage_test_metric']); ?></th>
                        <th scope="col"><?php echo escape($lang['storage_test_result']); ?></th>
                    </tr>
                </thead>
                <tbody id="storage-test-metrics"></tbody>
            </table>
        </div>
        <p><?php echo escape($lang['storage_test_workload']); ?></p>
        <p><?php echo escape($lang['storage_test_method']); ?></p>
        <button type="button" id="storage-test-copy"><?php echo escape($lang['storage_test_copy']); ?></button>
        <label for="storage-test-summary"><?php echo escape($lang['storage_test_summary']); ?></label>
        <textarea id="storage-test-summary" readonly rows="12"></textarea>
        <p id="storage-test-copy-status" role="status"></p>
    </div>
</div>
<script>
(function () {
    const panel = document.getElementById('storage-test');
    const options = {
        url: <?php echo encode_js_value($baseurl_short . 'pages/ajax/storage_test.php'); ?>,
        strings: <?php echo encode_js_value($storage_test_strings); ?>,
        token: {<?php echo generateAjaxToken('storage_test'); ?>}
    };
    jQuery.getScript(<?php echo encode_js_value($baseurl_short . 'js/storage_test.js?v=' . $css_reload_key); ?>)
        .done(function () { storageTestInit(panel, options); })
        .fail(function () {
            panel.hidden = false;
            panel.querySelector('#storage-test-status').textContent = options.strings.storage_test_connection;
        });
}());
</script>
