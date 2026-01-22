<?php
include __DIR__ . "/../include/consent_functions.php";

function HookConsentmanagerViewCustompanels()
{
    global $lang,$baseurl_short,$ref,$edit_access,$k,$consent_usage_mediums;

    if ($k != "") {
        return false;
    }

    if (!consentmanager_check_read($ref)) {
        return false;
    }

    $consents = consentmanager_get_consents($ref);
    ?>
    <div class="RecordBox">
        <div class="RecordPanel">
            <div class="Title"><?php echo escape($lang["consent_management"]); ?></div>
            <?php if ($edit_access || checkperm("cm")) {
                $new_consent_url_params = array(
                    'ref'        => 'new',
                    'resource'   => $ref,
                    'search'     => getval('search', ''),
                    'order_by'   => getval('order_by', ''),
                    'collection' => getval('collection', ''),
                    'offset'     => getval('offset', 0),
                    'restypes'   => getval('restypes', ''),
                    'archive'    => getval('archive', '')
                );
                $new_consent_url = generateURL($baseurl_short . "plugins/consentmanager/pages/edit.php", $new_consent_url_params);
                ?>
                <p>
                    <a href="<?php echo $new_consent_url ?>" onClick="return CentralSpaceLoad(this,true);">
                        <?php echo LINK_PLUS . $lang["new_consent"]; ?>
                    </a>
                </p>    
            <?php }

            if (count($consents) > 0) { ?>
                <div class="Listview">
                    <table class="ListviewStyle">
                        <tr class="ListviewTitleStyle">
                            <th><?php echo escape($lang["consent_id"]); ?></th>
                            <th><?php echo escape($lang["name"]); ?></th>
                            <th><?php echo escape($lang["usage"]); ?></th>
                            <th><?php echo escape($lang["fieldtitle-expiry_date"]); ?></th>
                            <th><?php echo escape($lang["date_of_consent"]); ?></th>
                            <th><?php echo escape($lang["user_created_by"]); ?></th>

                            <?php if ($edit_access || checkperm("cm")) { ?>
                                <th>
                                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                                </th>
                            <?php } ?>
                        </tr>

                        <?php foreach ($consents as $consent) { ?>
                            <tr>
                                <td><?php echo escape($consent["ref"]); ?></td>
                                <td><?php echo escape($consent["name"]); ?></td>
                                <td>
                                    <?php
                                    $consent_usage_mediums = trim_array(explode(", ", $consent["consent_usage"]));
                                    $translated_mediums = "";

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
                                <?php if ($edit_access || checkperm("cm")) { ?>
                                    <td>
                                        <div class="ListTools">
                                            <a href="<?php echo $baseurl_short ?>plugins/consentmanager/pages/edit.php?ref=<?php echo $consent["ref"]; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">
                                                &gt;&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                            </a>
                                            <a href="<?php echo $baseurl_short ?>plugins/consentmanager/pages/unlink.php?ref=<?php echo $consent["ref"]; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">
                                                &gt;&nbsp;<?php echo escape($lang["action-unlink"]); ?>
                                            </a>
                                        </div>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php
    return false; # Allow further custom panels
}