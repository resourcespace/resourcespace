<?php
# track_field_history field history log page.
# NOTE: requires System setup permission

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}
include '../include/track_field_history_functions.php';

include '../../../include/header.php';

$resource_id = getval('ref', '', true);
$field_id = getval('field', '', true);
$field_title = getval('field_title', '');
$search = getval('search', '');

$no_records = false;

$field_log_records = track_field_history_get_field_log($resource_id, $field_id);

if(empty($field_log_records)) {
    $no_records = true;
}

$get_params = array("ref" => $resource_id, "search" => $search);
$url        = generateURL($baseurl_short . 'pages/view.php', $get_params );

?>
<p>
    <a href="<?php echo $url; ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo '<?php echo LINK_CARET_BACK ?>' . $lang['backtoresourceview']; ?></a>
</p>
<div class="BasicsBox">
    <h1><?php echo escape(str_replace('%fieldtitle%', $field_title, $lang['track_field_history_field_history_page_title'])); ?></h1>
    <div class="clearerleft"></div>
    <div class="Listview">
        <table id="track_field_history_table" class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th width="10%"><?php echo escape($lang['date']); ?></th>
                <th width="10%"><?php echo escape($lang['user']); ?></th>
                <th><?php echo escape($lang['track_field_history_change']); ?></th>
            </tr>
            <?php
            if($no_records) { ?>
                <tr><td colspan="10"><b><?php echo escape($lang['track_field_history_error_no_records']); ?></b></td></tr>
            <?php }

            foreach ($field_log_records as $field_log_record) {
                ?>
                <tr>
                    <td nowrap><?php echo nicedate($field_log_record['date'], true, true, true); ?></td>
                    <td><?php echo $field_log_record['user']; ?></td>
                    <td><?php echo nl2br(escape(strip_tags($field_log_record['diff']))); ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
</div>
<?php
include '../../../include/footer.php';
?>
