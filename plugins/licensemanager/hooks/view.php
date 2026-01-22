<?php

function HookLicensemanagerViewCustompanels()
    {
    global $lang,$baseurl_short,$ref,$edit_access,$k;

    if($k!=""){return false;}

    # Check if it's necessary to upgrade the database structure
    include __DIR__ . "/../upgrade/upgrade.php";

    $licenses=ps_query("select license.ref,license.outbound,license.holder,license.license_usage,license.description,license.expires from license join resource_license on license.ref=resource_license.license where resource_license.resource=? order by ref", ['i', $ref]);
    ?>
    <div class="RecordBox">
    <div class="RecordPanel">
    <div class="Title"><?php echo escape($lang["license_management"]); ?></div>

    <?php if ($edit_access || checkperm("lm")) { 
        $new_license_url_params = array(
            'ref'        => 'new',
            'resource'   => $ref,
            'search'     => getval('search',''),
            'order_by'   => getval('order_by',''),
            'collection' => getval('collection',''),
            'offset'     => getval('offset',0),
            'restypes'   => getval('restypes',''),
            'archive'    => getval('archive','')
        );
        $new_license_url = generateURL($baseurl_short . "plugins/licensemanager/pages/edit.php",$new_license_url_params);
        ?>    
    <p><a href="<?php echo $new_license_url ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_PLUS . $lang["new_license"]; ?></a></p>    
    <?php } ?>

    <?php if (count($licenses)>0) { ?>
        <div class="Listview">
        <table class="ListviewStyle">
        <tr class="ListviewTitleStyle">
        <th><?php echo escape($lang["license_id"]); ?></a></th>
        <th><?php echo escape($lang["type"]); ?></a></th>
        <th><?php echo escape($lang["licensor_licensee"]); ?></a></th>
        <th><?php echo escape($lang["indicateusagemedium"]); ?></a></th>
        <th><?php echo escape($lang["description"]); ?></a></th>
        <th><?php echo escape($lang["fieldtitle-expiry_date"]); ?></a></th>

        <?php if ($edit_access || checkperm("lm")) { ?>
        <th><div class="ListTools"><?php echo escape($lang["tools"]); ?></div></th>
        <?php } ?>

        </tr>

        <?php
        foreach ($licenses as $license)
            {
            $license_usage_mediums = trim_array(explode(", ", $license["license_usage"]));
            $translated_mediums = "";
            ?>
            <tr>
            <td><?php echo escape($license["ref"]); ?></td>
            <td><?php echo escape($license["outbound"] ? $lang["outbound"] : $lang["inbound"]); ?></td>
            <td><?php echo escape($license["holder"]); ?></td>
            <td><?php
                foreach ($license_usage_mediums as $medium)
                    {
                    $translated_mediums = $translated_mediums . lang_or_i18n_get_translated($medium, "license_usage-") . ", ";
                    }
                $translated_mediums = substr($translated_mediums, 0, -2); # Remove the last ", "
                echo $translated_mediums;
                ?>
            </td>
            <td><?php echo escape($license["description"]); ?></td>
            <td><?php echo escape($license["expires"] == "" ? $lang["no_expiry_date"] : nicedate($license["expires"])); ?></td>

            <?php if ($edit_access || checkperm("lm")) { ?>
            <td><div class="ListTools">
            <a href="<?php echo $baseurl_short ?>plugins/licensemanager/pages/edit.php?ref=<?php echo $license["ref"]; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">&gt;&nbsp;<?php echo escape($lang["action-edit"]); ?></a>
            <a href="<?php echo $baseurl_short ?>plugins/licensemanager/pages/unlink.php?ref=<?php echo $license["ref"]; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">&gt;&nbsp;<?php echo escape($lang["action-unlink"]); ?></a>
            </div></td>
            <?php } ?>

            </tr>
            <?php
            }
        ?>

        </table>
        </div>
    <?php } ?>


    </div>

    </div>
    <?php
    return false; # Allow further custom panels
    }