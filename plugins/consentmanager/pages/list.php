<?php
include __DIR__ . "/../../../include/boot.php";
include __DIR__ . "/../../../include/authenticate.php";

$is_admin = checkperm("t");

if (!consentmanager_check_read()) {
    exit(escape($lang["error-permissiondenied"]));
}

global $baseurl;

$offset = getval("offset", 0, true);

if (array_key_exists("findtext", $_POST)) {
    $offset = 0;
} # reset page counter when posting

$findtext = getval("findtext", "");
$delete = getval("delete", "");
$consent_status = getval("consent_status", "all");

if ($delete != "" && enforcePostRequest(false)) {
    # Delete consent
    consentmanager_delete_consent($delete);
}

include __DIR__ . "/../../../include/header.php";

$url_params = array(
    'search'         => getval('search', ''),
    'order_by'       => getval('order_by', ''),
    'collection'     => getval('collection', ''),
    'offset'         => getval('offset', 0),
    'restypes'       => getval('restypes', ''),
    'archive'        => getval('archive', ''),
    'consent_status' => getval('consent_status', '')
);
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["manageconsents"]); ?></h1>

    <?php
    $links_trail = array(
        array(
            'title' => !$is_admin ? escape($lang["home"]) : escape($lang["teamcentre"]),
            'href'  => $baseurl_short . (!$is_admin ? "pages/home.php" : "pages/team/team_home.php"),
            'menu'  => !$is_admin ? false : true
        ),
        array(
            'title' => $lang["manageconsents"]
        )
    );

    renderBreadcrumbs($links_trail);
    ?>
        
    <form method=post id="consentlist" action="<?php echo $baseurl_short ?>plugins/consentmanager/pages/list.php" onSubmit="CentralSpacePost(this);return false;">
        <?php generateFormToken("consentlist"); ?>
        <input type=hidden name="delete" id="consentdelete" value="">
 
        <?php
        $consents = consentmanager_get_all_consents($findtext, $consent_status);

        # pager
        $per_page = $default_perpage_list;
        $results = count($consents);
        $totalpages = ceil($results / $per_page);
        $curpage = floor($offset / $per_page) + 1;
        $url = "list.php?findtext=" . urlencode($findtext) . "&offset=" . $offset;
        $jumpcount = 1;
        ?>

        <p>
            <a href="<?php echo $baseurl_short ?>plugins/consentmanager/pages/edit.php?ref=new" onClick="CentralSpaceLoad(this);return false;">
                <?php echo LINK_PLUS_CIRCLE . $lang["new_consent"]; ?>
            </a>
        </p>

        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang["consent_id"]); ?></th>
                    <th><?php echo escape($lang["name"]); ?></th>
                    <th><?php echo escape($lang["usage"]); ?></th>
                    <th><?php echo escape($lang["fieldtitle-expiry_date"]); ?></th>
                    <th><?php echo escape($lang["date_of_consent"]); ?></th>
                    <th><?php echo escape($lang["user_created_by"]); ?></th>
                    <th>
                        <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                    </th>
                </tr>

                <?php
                for ($n = $offset; (($n < count($consents)) && ($n < ($offset + $per_page))); $n++) {
                    $consent = $consents[$n];
                    $consent_usage_mediums = trim_array(explode(", ", $consent["consent_usage"]));
                    $translated_mediums = "";
                    $url_params['ref'] = $consent["ref"];
                    ?>
                    <tr>
                        <td><?php echo escape($consent["ref"]); ?></td>
                        <td><?php echo escape($consent["name"]); ?></td>
                        <td>
                            <?php
                            foreach ($consent_usage_mediums as $medium) {
                                $translated_mediums = $translated_mediums . lang_or_i18n_get_translated($medium, "consent_usage-") . ", ";
                            }
                            $translated_mediums = substr($translated_mediums, 0, -2); # Remove the last ", "
                            echo escape($translated_mediums);
                            ?>
                        </td>
                        <td><?php echo escape($consent["expires"] == "" ? $lang["no_expiry_date"] : nicedate($consent["expires"])); ?></td>
                        <td><?php echo escape($consent["date_of_consent"] == "" ? $lang["no_consent_date"] : nicedate($consent["date_of_consent"])); ?></td>
                        <td>
                            <?php                             
                                $created_by_user = get_user($consent['created_by']);
                                if (is_array($created_by_user)) {
                                    echo escape($created_by_user["fullname"] == "" ? $created_by_user["username"] : $created_by_user["fullname"]);
                                }                                                            
                            ?>
                        </td>
                        <td>
                            <div class="ListTools">
                                <a href="<?php echo generateURL($baseurl_short . "pages/search.php", ['search' => '!consent' . $consent['ref']]); ?>" onClick="return CentralSpaceLoad(this,true);">
                                    <i class="icon-search"></i>&nbsp;<?php echo escape($lang['consent_view_linked_resources_short']); ?>
                                </a>
                                <a href="<?php echo generateURL($baseurl_short . "plugins/consentmanager/pages/edit.php", $url_params); ?>" onClick="return CentralSpaceLoad(this,true);">
                                    <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                </a>
                                <a href="<?php echo generateURL($baseurl_short . "plugins/consentmanager/pages/delete.php", $url_params); ?>" onClick="return CentralSpaceLoad(this,true);">
                                    <i class="icon-trash-2"></i>&nbsp;<?php echo escape($lang["action-delete"]); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>

        <div class="BottomInpageNav"><?php pager(true); ?></div>
        
        <div class="Question">  
            <label for="consent_status"><?php echo escape($lang["consent_status"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <select name="consent_status" id="consent_status" onChange="this.form.submit();">
                        <option value="all" <?php echo ($consent_status == 'all') ? " selected" : ''; ?>>
                            <?php echo escape($lang["consent_status_all"]); ?>
                        </option>
                        <option value="active" <?php echo ($consent_status == 'active') ? " selected" : ''; ?>>
                            <?php echo escape($lang["consent_status_active"]); ?>
                        </option>
                        <option value="expiring" <?php echo ($consent_status == 'expiring') ? " selected" : ''; ?>>
                            <?php echo escape($lang["consent_status_expiring"]); ?>
                        </option>
                        <option value="expired" <?php echo ($consent_status == 'expired') ? " selected" : ''; ?>>
                            <?php echo escape($lang["consent_status_expired"]); ?>
                        </option>
                    </select>
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="find"><?php echo escape($lang["consentsearch"]); ?><br/></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text placeholder="<?php echo escape($lang['searchbytext']); ?>" name="findtext" id="findtext" value="<?php echo escape($findtext); ?>" maxlength="100" class="shrtwidth" />
                    <input type="button" value="<?php echo escape($lang['clearbutton']); ?>" onClick="jQuery('#findtext').val('');CentralSpacePost(document.getElementById('consentlist'));return false;" />
                    <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php
include __DIR__ . "/../../../include/footer.php";
