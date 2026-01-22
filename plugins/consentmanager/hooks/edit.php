<?php

function HookConsentmanagerEditEndofmetadataaddcustomfield()
{
    global $consent_attach_upload, $lang, $locked_fields, 
           $multiple, $resource, $save_errors, $upload_review_mode;

    if ($consent_attach_upload == true && $multiple == false && $upload_review_mode == true) {

        if (!consentmanager_check_read()) {
            return false;
        }

        if (!isset($resource['resource_consent'])) {
            $resource['resource_consent'] = getval("resource_consent", 0, false, is_positive_int_loose(...));
        }

        $consents = consentmanager_get_all_consents_grouped();

        if (empty($consents)) {
            return false;
        }

        ?>
        <div class="Question <?php if($upload_review_mode && in_array("resource_consent", $locked_fields)){echo "lockedQuestion ";} if(isset($save_errors) && is_array($save_errors) && array_key_exists('resource_consent',$save_errors)) { echo 'FieldSaveError'; } ?>" id="resource_consent" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
            <label for="resource_consent">
            <?php

            echo escape($lang["manageconsent"]);

            if ($upload_review_mode) {
                renderLockButton('resource_consent', $locked_fields);
            }

            ?>
            </label>      
            <select class="stdwidth" name="resource_consent" id="resource_consent">
                <option value="0"></option>
                <?php

                    $current_group = null;

                    foreach ($consents as $consent) {
                        if ($consent['consent_status'] !== $current_group) {

                            // Close previous optgroup if not the first
                            if ($current_group !== null) {
                                echo '</optgroup>';
                            }

                            // Start new optgroup
                            $current_group = $consent['consent_status'];
                            echo '<optgroup label="' . escape($lang["consent_status_" . $current_group]) . '">';
                        }

                        // Output the option
                        echo '<option value="' . (int) $consent['ref'] . '"';
                        echo (isset($resource['resource_consent']) && $resource['resource_consent'] == $consent['ref']) ? ' selected': '';
                        echo '>';
                        echo escape($consent['name'] . ' (ID: ' . (int) $consent['ref'] . ')');
                        echo '</option>';
                    }

                    // Close the last optgroup
                    if ($current_group !== null) {
                        echo '</optgroup>';
                    }
                ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
    <?php
    }    
}

function HookConsentmanagerEditAftersaveresourcedata(int|array $ref): bool|array 
{
    global $consent_attach_upload, $multiple, $upload_review_mode;

    if ($consent_attach_upload == true && $multiple == false && $upload_review_mode == true) {

        if (!consentmanager_check_read()) {
            return false;
        }

        $val = getval("resource_consent", 0, false, is_positive_int_loose(...));
        if ($val !== 0) {
            // Attempt to add consent record
            $result = consentmanager_link_consent($val, $ref);

            if(!$result) {
                return ['Error adding consent record'];
            }
        }
    }

    return true;
}

function HookConsentmanagerEditCopy_locked_data_extra(array $resource, array $locked_fields, int $last_edited): bool|array
{
    global $consent_attach_upload;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $resource = $GLOBALS["hook_return_value"];
    }

    if ($consent_attach_upload && in_array('resource_consent', $locked_fields)) {

        if (!consentmanager_check_read()) {
            return false;
        }

        // Get the consent ref of the record added to the last edited resource
        $last_edited_consent = consentmanager_get_consents($last_edited);

        if (!empty($last_edited_consent)) {
            $resource['resource_consent'] = $last_edited_consent[0]['ref'];
            return $resource;
        } else {
            return false;
        }
    } else {
        // Return original resource due to way hook is implemented ($last_hook_value_wins = true)
        return $resource;
    }
}