<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$filterorder        = getval("filterorder", "ref");
$filtersort         = getval("filtersort", "ASC");
$revsort            = ($filtersort == "ASC") ? "DESC" : "ASC";
$filterfind         = getval("filterfind", "");
$copy_from          = getval('copy_from', 0, true);
$new_filter_name    = getval("filter_name", "");

$filters = get_filters($filterorder, $filtersort, $filterfind);

$filter_edit_url    = $baseurl . "/pages/admin/admin_filter_edit.php";
$filter_manage_url  = $baseurl . "/pages/admin/admin_filter_manage.php";

$params = array(
    "filterfind"    => $filterfind,
    "filtersort"    => $filtersort,
    "filterorder"   => $filterorder
    );

if ($copy_from > 0 && enforcePostRequest(false)) {
    $new_filter_id  = copy_filter($copy_from);
    $filter_details = get_filter($new_filter_id);
    save_filter($new_filter_id, $filter_details['name'] . ' (' . $lang['copy'] . ')', $filter_details['filter_condition']);
    redirect($baseurl_short . "pages/admin/admin_filter_edit.php?filter=" . $new_filter_id);
} elseif (trim($new_filter_name) == '' && getval('save', '') == 'true' && enforcePostRequest(false)) {
    error_alert($lang['error-invalid_name'], false);
    exit();
} elseif ($new_filter_name != "" && enforcePostRequest(false)) {
    $new_filter_id = save_filter(0, $new_filter_name, RS_FILTER_ALL);
    clear_query_cache("schema");
    redirect($baseurl_short . "pages/admin/admin_filter_edit.php?filter=" . $new_filter_id);
}

include "../../include/header.php";
?>

<div id="CentralSpaceContainer">
    <div id="CentralSpace">
        <div class="BasicsBox">
            <h1><?php echo escape($lang["filter_manage"]); ?></h1>
            <?php
            $links_trail = array(
                array(
                    'title' => $lang["systemsetup"],
                    'href'  => $baseurl_short . "pages/admin/admin_home.php",
                    'menu' =>  true
                ),
                array(
                    'title' => $lang["filter_manage"],
                    'help'  => 'systemadmin/search-filters'
                )
            );

            renderBreadcrumbs($links_trail);
            ?>
                
            <div class="Listview">
                <table id="filter_list_table" class="ListviewStyle">
                    <tbody>
                        <tr class="ListviewTitleStyle">
                            <th>
                                <a href="<?php echo generateURL($filter_manage_url, $params, array("filterorder" => "ref", "sort" => $revsort)); ?>" onclick="return CentralSpaceLoad(this);">
                                    <?php echo escape($lang['property-reference']); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo generateURL($filter_manage_url, $params, array("filterorder" => "name", "sort" => $revsort)); ?>" onclick="return CentralSpaceLoad(this);">
                                    <?php echo escape($lang['property-name']); ?>
                                </a>
                            </th>
                            <th><div class="ListTools"><?php echo escape($lang['tools']); ?></div></th>
                        </tr>

                        <?php for ($n = 0; $n < count($filters); $n++) { ?>
                            <tr class="filter_row" id="field_sort_<?php echo $filters[$n]["ref"];?>">
                                <td>
                                    <a href="<?php echo generateURL($filter_edit_url, $params, array("ref" => $filters[$n]["ref"])); ?>" onclick="return CentralSpaceLoad(this);">
                                        <?php echo $filters[$n]["ref"]; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="ListTitle">
                                        <a href="<?php echo generateURL($filter_edit_url, $params, array("filter" => $filters[$n]["ref"])); ?>" onclick="return CentralSpaceLoad(this);">
                                            <?php echo escape(i18n_get_translated($filters[$n]["name"])); ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div class="ListTools">
                                        <a href="#" onClick="jQuery('#form_copy_from').val('<?php echo escape($filters[$n]["ref"]); ?>');return CentralSpacePost(document.getElementById('admin_filter_form'),true)" >
                                            <?php echo '<i class="icon-copy"></i>&nbsp;' .  escape($lang["copy"]); ?>
                                        </a>
                                        <a href="<?php echo generateURL($filter_edit_url, $params, array("filter" => $filters[$n]["ref"])); ?>" onClick="return CentralSpaceLoad(this,true);">
                                            <?php echo '<i class="icon-square-pen"></i>&nbsp;' . escape($lang["action-edit"]); ?>
                                        </a>
                                        <a
                                            href="#"
                                            onClick='
                                                event.preventDefault();
                        
                                                if (confirm("<?php echo escape($lang["confirm-deletion"]); ?>")) {
                                                    var post_data = {
                                                        ajax: true,
                                                        filter_manage_page: true,
                                                        filter: <?php echo urlencode($filters[$n]['ref']); ?>,
                                                        delete_filter: <?php echo urlencode($filters[$n]['ref']); ?>,
                                                        <?php echo generateAjaxToken('admin_filter_edit'); ?>
                                                    };
                        
                                                    jQuery.post("<?php echo $filter_edit_url; ?>", post_data, function(response) {
                                                        if (response.deleted) {
                                                            var redirect_link = document.createElement("a");
                                                            redirect_link.href = "<?php echo generateURL($filter_manage_url, $params, array("deleted" => $filters[$n]["ref"])); ?>";
                                                            CentralSpaceLoad(redirect_link, true);
                                                        } else {
                                                            errors = "";
                                                            console.log(response.errors);
                                                            for (var i in response.errors) {
                                                                errors += response.errors[i] + "<br />";
                                                            }
                                                            
                                                            styledalert("<?php echo escape($lang["error"]); ?>",errors);
                                                        }
                                                    }, "json"); 
                        
                                                    return false;
                                                } else {
                                                    return false;
                                                }
                                            '>
                                            <?php echo '<i class="icon-trash-2"></i>&nbsp;' . escape($lang["action-delete"]); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        } ?>
                    </tbody>
                </table>
            </div>        
        </div> <!-- End of BasicsBox -->

        <div class="BasicsBox">
            <form method="post" id="admin_filter_form" action="<?php echo $baseurl_short; ?>pages/admin/admin_filter_manage.php" onsubmit="return CentralSpacePost(this,false);">
            <?php generateFormToken("admin_filter_edit"); ?>
                <input type="hidden" name="filter" value="0" />
                <input type="hidden" id="form_copy_from" name="copy_from" value="" />
                <input type="hidden" name="save" value="true" />
                <div class="Question">
                    <label for="filter_name"><?php echo escape($lang["filter_create_name"]); ?></label>
                    <div class="tickset">
                        <div class="Inline">
                            <input type=text name="filter_name" id="filter_name" maxlength="100" class="shrtwidth" />
                        </div>
                        <div class="Inline">
                            <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["create"]); ?>&nbsp;&nbsp;" onclick="return CentralSpacePost(this.form,true);" />
                        </div>
                    </div>
                    <div class="clearerleft"> </div>
                </div>
            </form>
        </div>
    </div> <!-- End of CentralSpace -->
</div> <!-- End of CentralSpaceContainer -->

<?php
include "../../include/footer.php";