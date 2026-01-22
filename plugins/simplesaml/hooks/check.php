<?php
function HooksimplesamlCheckAddinstallationcheck()
{
    ?>
    <tr>
        <td class="BorderBottom"; colspan='3'>
            <b>SimpleSAML</b>
        </td>
    </tr>
    <?php
    display_extension_status('zlib');
    display_extension_status('openssl');
    display_extension_status('mbstring');
    display_extension_status('ldap');
    display_extension_status('cURL');

    if ($GLOBALS['simplesaml_rsconfig'] === 2) { 
        // Check updates from metadata URL
        $lastupdate = get_sysvar("saml_idp_metadata_last_updated");
        $errormessage = trim((string) get_sysvar("saml_idp_metadata_error"));
        $lastupdatetext = $lastupdate != "" ? date("l F jS Y @ H:i:s", $lastupdate): $GLOBALS['lang']["status-never"];
        $updatestatus = time() - $lastupdate > 60*60*24*7 ? $GLOBALS['lang']['status-fail'] : $GLOBALS['lang']['status-ok'];
        $idpname = isset($GLOBALS['simplesamlconfig']['metadata']) && count($GLOBALS['simplesamlconfig']['metadata']) > 0
            ? array_key_first($GLOBALS['simplesamlconfig']['metadata'])
            : $GLOBALS['lang']['notavailableshort'];
        $metadatastatus = (
            time() - $lastupdate > 60*60*24*7
            || !isset($GLOBALS['simplesamlconfig']['metadata'])
            || count($GLOBALS['simplesamlconfig']['metadata']) < 1
        )
            ? $GLOBALS['lang']['status-fail']
            : $GLOBALS['lang']['status-ok'];
        ?>
        <tr>
            <td colspan='3'>
                <b><?php echo escape($GLOBALS["lang"]['simplesaml_metadata_updates']); ?></b>
            </td>
        </tr>
         <tr>
            <td><?php echo escape($GLOBALS['lang']['lastupdated']); ?></td>
            <td><?php echo escape($lastupdatetext); ?></td>
            <td><b><?php echo escape($updatestatus); ?></b></td>
        </tr>
        <?php if (simplesaml_use_idp_metadata_url_mode()) {
            $validurl = filter_var($GLOBALS["simplesaml_metadata_url"], FILTER_VALIDATE_URL);
            ?>
            <tr>
                <td><?php echo escape($GLOBALS['lang']['simplesaml_config_source_url']); ?></td>
                <td>
                    <a href='<?php echo sanitise_url($GLOBALS["simplesaml_metadata_url"]); ?>' 
                       target='_blank' rel="noopener">
                       <?php echo escape($GLOBALS["simplesaml_metadata_url"]); ?>
                    </a>
                </td>
                <td>
                    <b><?php
                    echo escape($validurl 
                    ? $GLOBALS['lang']['status-ok']
                    : $GLOBALS['lang']['status-fail']); ?>
                    </b>
                </td>
            </tr><?php
        } ?>
        <tr>
            <td><?php echo escape($GLOBALS['lang']['simplesaml_idp_configuration']); ?></td>
            <td><?php echo escape($idpname); ?></td>
            <td><b><?php echo escape($metadatastatus); ?></b></td>
        </tr><?php

        if ($errormessage !== '') {?>
            <tr>
                <td><?php echo escape($GLOBALS['lang']['errors']); ?></td>
                <td><?php echo escape($errormessage); ?></td>
                <td><b><?php echo escape($GLOBALS['lang']['status-fail']); ?></b></td>
            </tr><?php
        }
    }

    if (isset($GLOBALS["simplesamlconfig"]["metadata"]) && $GLOBALS['simplesaml_check_idp_cert_expiry']) {
        // Check expiry date of IdP certificates
        // Only possible to check if using ResourceSpace stored SAML config
        ?>
        <tr>
            <td colspan='3'>
                <b><?php echo escape($GLOBALS["lang"]['simplesaml_idp_certs']); ?></b>
            </td>
        </tr>
        <?php
        $idpindex = 1; // Some systems have multiple IdPs
        foreach ($GLOBALS["simplesamlconfig"]["metadata"] as $idpid => $idpdata) {
            $idpname = $idpid; // IdP may not have a friendly readable name configured
            $latestexpiry = get_saml_metadata_expiry($idpid);
            if (isset($idpdata["name"])) {
                if (is_string($idpdata["name"])) {
                    $idpfriendlyname = $idpdata["name"];
                } else {
                    $idpfriendlyname = (string) ($idpdata["name"][$GLOBALS['language']] ?? reset($idpdata["name"]));
                }
                $idpname .= " (" . $idpfriendlyname . ")";
            }
            $placeholders = ["%idpname", "%expiretime"];
            $replace = [$idpname, $latestexpiry];
            // show status
            if ($latestexpiry < date("Y-m-d H:i")) {
                $status  = $GLOBALS['lang']['status-fail'];
                $info = str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expired']);
            } elseif ($latestexpiry < date("Y-m-d H:i", time() + 60 * 60 * 24 * 7)) {
                $status  = $GLOBALS['lang']['status-fail'];
                $info = str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expiring']);
            } else {
                $status  = $GLOBALS['lang']['status-ok'];
                $info = str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expires']);
            }
            ?>
            <tr>
                <td><?php echo escape($idpname); ?></td>
                <td><?php echo escape($info); ?></td>
                <td><b><?php echo escape($status); ?></b></td>
            </tr>
            <?php
            $idpindex++;
        }
    }
}
