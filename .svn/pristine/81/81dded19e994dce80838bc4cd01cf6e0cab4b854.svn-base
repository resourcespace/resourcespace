<?php

include "../include/boot.php";
include "../include/authenticate.php";

$ref = getval("ref", "", true);
$alt = getval("alternative", "", true);

$search = getval("search", "");
$offset = getval("offset", 0, true);
$order_by = getval("order_by", "");
$archive = getval("archive", "", true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$modal = (getval("modal", "") == "true");
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$curpos = getval("curpos", "");
$go = getval("go", "");

$urlparams = array(
    'resource' => $ref,
    'ref' => $ref,
    'search' => $search,
    'order_by' => $order_by,
    'offset' => $offset,
    'restypes' => $restypes,
    'archive' => $archive,
    'default_sort_direction' => $default_sort_direction,
    'sort' => $sort,
    'curpos' => $curpos,
    "modal" => ($modal ? "true" : ""),
);

# Fetch resource data.
$resource = get_resource_data($ref);

if (!is_array($resource)) {
    http_response_code(403);
    exit($lang['resourcenotfound']);
}

$editaccess = get_edit_access($ref, $resource["archive"], $resource);

# Not allowed to edit this resource?
if (!($editaccess || checkperm('A')) && $ref > 0) {
    exit("Permission denied.");
}

if ($resource["lock_user"] > 0 && $resource["lock_user"] != $userref) {
    $error = get_resource_lock_message($resource["lock_user"]);
    http_response_code(403);
    exit($error);
}

# Handle deleting a file
if (getval("filedelete", "") != "" && enforcePostRequest(getval("ajax", false))) {
    $filedelete = explode(',', getval('filedelete', ''));
    foreach ($filedelete as $filedel) {
        if (is_numeric($filedel) && $filedel != 'on') {// safety checks
            delete_alternative_file($ref, $filedel);
        }
    }
}

$alt_order_by = "";
$alt_sort = "";

if ($alt_types_organize) {
    $alt_order_by = "alt_type";
    $alt_sort = "asc";
}

$files = get_alternative_files($ref, $alt_order_by, $alt_sort);

include "../include/header.php";
?>

<script type="text/javascript">
    function clickDelete() {
        var files = [];
        var errors = 0;

        jQuery('#altlistitems input:checked').not("#toggleall").each(function() {
            files.push(this.value);
        });

        files.forEach((file) => {
            postdata = {
                'resource' : '<?php echo $ref;?>',
                'ref'      : file,
            }

            api(
                'delete_alternative_file',
                postdata,
                function(response) {
                    if (response == true) {
                        document.getElementById('altlistrow' + file).remove();
                    } else {
                        error++;
                    }
                },
                <?php echo generate_csrf_js_object('delete_alternative_file'); ?>
            );
        });

        if (errors > 0) {
            styledalert('<?php echo escape($lang['error']); ?>','<?php echo escape($lang['altfilesdeletefail']); ?>');
            return false;
        } else {
            return true;
        }
    }

    function toggleAll() {
        jQuery("#toggleall").click(function() {
            var checkBoxes = jQuery("input[name=altcheckbox\\[\\]]");
            checkBoxes.prop("checked", jQuery("#toggleall").prop("checked"));
        });  
    }
</script>

<div class="BasicsBox">
    <?php
    if (getval("context", false) == 'Modal') {
        $previous_page_modal = true;
    } else {
        $previous_page_modal = false;
    }

    if (!$modal) {
        ?>
        <p>
            <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/edit.php", $urlparams); ?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoeditmetadata"]); ?>
            </a>
            <br>
            <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/view.php", $urlparams); ?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
        <?php
    } elseif ($previous_page_modal) {
        $urlparams["context"] = 'Modal';
        ?>
        <p>
            <a onClick="return ModalLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/edit.php", $urlparams); ?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoeditmetadata"]); ?>
            </a>
            <br>
            <a onClick="return ModalLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/view.php", $urlparams); ?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
        <?php
    }
    ?>
    <div class="RecordHeader">
        <div class="BackToResultsContainer">
            <div class="backtoresults"> 
                <?php if ($modal) { ?>
                    <a class="maxLink icon-maximize-2" href="<?php echo generateURL($baseurl_short . "pages/alternative_files.php", $urlparams, array("modal" => "")); ?>" onclick="return CentralSpaceLoad(this);" title="<?php echo escape($lang["maximise"]); ?>"></a>
                    &nbsp;<a href="#" class="closeLink icon-x" onclick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php
    if (
        $alternative_file_resource_preview
        && file_exists(get_resource_path($resource['ref'], true, 'col', false))
    ) {
        ?>
        <img alt="<?php echo escape(i18n_get_translated($resource['field' . $view_title_field] ?? "")); ?>" src="<?php echo get_resource_path($resource['ref'], false, 'col', false); ?>"/>
        <?php
    }

    if (isset($resource['field' . $view_title_field])) {
        echo "<h2>" . escape(i18n_get_translated($resource['field' . $view_title_field])) . "</h2><br/>";
    }
    ?>

    <h1>
        <?php
        echo escape($lang["managealternativefilestitle"]);
        render_help_link('user/alternative-files');
        ?>
    </h1>

    <?php if (count($files) > 0) { ?>
        <a
            href="#"
            id="deletechecked"
            onclick="if (confirm('<?php echo escape($lang['confirm-deletion']); ?>')) {clickDelete();} return false;"
        >
            <?php echo LINK_CARET . escape($lang["action-deletechecked"]); ?>
        </a>
    <?php } ?>

    <form method=post id="fileform" action="<?php echo generateURL($baseurl . "/pages/alternative_files.php", $urlparams); ?>">
        <input type=hidden name="filedelete" id="filedelete" value="">
        <?php generateFormToken("fileform"); ?>
        <div class="Listview" id="altlistitems">
            <table class="ListviewStyle">
                <!--Title row-->    
                <tr class="ListviewTitleStyle">
                    <th>
                        <?php if (count($files) > 0) { ?>
                            <input type="checkbox" class="checkbox" onclick="toggleAll();" id="toggleall" />
                        <?php } ?>
                    </th>
                    <th><?php echo escape($lang["name"]); ?></th>
                    <th><?php echo escape($lang["description"]); ?></th>
                    <th><?php echo escape($lang["filetype"]); ?></th>
                    <th><?php echo escape($lang["filesize"]); ?></th>
                    <th><?php echo escape($lang["date"]); ?></th>
                    <?php if (count($alt_types) > 1) { ?>
                        <th><?php echo escape($lang["alternatetype"]); ?></th>
                    <?php } ?>
                    <th>
                        <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                    </th>
                </tr>

                <?php for ($n = 0; $n < count($files); $n++) { ?>
                    <!--List Item-->
                    <tr
                        <?php echo ($files[$n]["ref"] == $alt) ? "class='Highlight' " : ''; ?>
                        id='altlistrow<?php echo $files[$n]['ref']; ?>'
                    >
                        <td>
                            <input type="checkbox" class="checkbox" name="altcheckbox[]" value="<?php echo $files[$n]["ref"];?>" />
                        </td>
                        <td><?php echo escape($files[$n]["name"])?></td>  
                        <td><?php echo escape($files[$n]["description"])?>&nbsp;</td>
                        <td><?php echo escape($files[$n]["file_extension"] == "" ? $lang["notuploaded"] : str_replace_formatted_placeholder("%extension", $files[$n]["file_extension"], $lang["cell-fileoftype"])); ?></td> 
                        <td><?php echo formatfilesize($files[$n]["file_size"])?></td>   
                        <td><?php echo nicedate($files[$n]["creation_date"], true)?></td>
                        <?php if (count($alt_types) > 1) { ?>
                            <td><?php echo $files[$n]["alt_type"]; ?></td>
                        <?php } ?>
                        <td>
                            <div class="ListTools">
                                <a
                                    href="#"
                                    onclick="
                                        if (confirm('<?php echo escape($lang['filedeleteconfirm']); ?>')) {
                                            postdata = {
                                                'resource' : '<?php echo $ref;?>',
                                                'ref'      : '<?php echo $files[$n]['ref'];?>',
                                            }

                                            api(
                                                'delete_alternative_file',
                                                postdata,
                                                function(response) {
                                                    if (response == true) {
                                                        document.getElementById('altlistrow<?php echo $files[$n]['ref'];?>').remove();
                                                        return true;
                                                    } else {
                                                        styledalert('<?php echo escape($lang['error']) ?>','<?php echo escape($lang['altfiledeletefail'])?>');
                                                    }
                                                },
                                                <?php echo escape(generate_csrf_js_object('delete_alternative_file')); ?>
                                            );
                                        }
                                        return false;"
                                >
                                    <?php echo LINK_CARET . escape($lang["action-delete"])?>
                                </a>
                                &nbsp;
                                <a
                                    onclick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this, true);"
                                    href="<?php echo generateURL($baseurl . "/pages/alternative_file.php", $urlparams, array("ref" => $files[$n]["ref"])); ?>"
                                >
                                    <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                </a>

                                <?php
                                if (
                                    $editaccess
                                    && (
                                        file_exists(get_resource_path($ref, true, '', true, 'jpg', true, 1, false, '', $files[$n]["ref"], true))
                                        || file_exists(get_resource_path($ref, true, 'hpr', true, 'jpg', true, 1, false, '', $files[$n]["ref"], true))
                                    )
                                ) {
                                    echo "<a href=\"#\" onclick=\"previewform=jQuery('#previewform');jQuery('#upload_pre_alt').val('" . escape($files[$n]["ref"]) . "');return " . ($modal ? "Modal" : "CentralSpace") . "Post(previewform, true);\">" . LINK_CARET . escape($lang["useaspreviewimage"]) . "</a>";
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>
        <?php if (!resource_is_template($ref)) { ?>
            <p>
                <a onclick="return CentralSpaceLoad(this, true);" href="<?php echo generateURL($baseurl . "/pages/upload_batch.php", $urlparams, array('alternative' => $ref)); ?>">
                    <?php echo LINK_CARET . escape($lang["alternativebatchupload"]); ?>
                </a>
            </p>
        <?php } ?>
    </form>

    <form method=post id="previewform" name="previewform" action="<?php echo generateURL($baseurl . "/pages/upload_preview.php", $urlparams) ; ?>">
        <?php generateFormToken("previewform"); ?>
        <input type=hidden name="ref", id="upload_ref" value="<?php echo escape($ref); ?>"/>
        <input type=hidden name="previewref", id="upload_pre_ref" value="<?php echo escape($ref); ?>"/>
        <input type=hidden name="previewalt", id="upload_pre_alt" value=""/>
    </form>
</div> <!-- end of basicbox -->

<script type="text/javascript">
    jQuery('#altlistitems').tshift(); // make the select all checkbox work
    jQuery('#altlistitems input[type=checkbox]').click(function() {   
        if (jQuery(this).not(':checked').length) { // clear checkall
            jQuery("#toggleall").prop("checked", false);
        }
    }); 
</script>

<?php
include "../include/footer.php";