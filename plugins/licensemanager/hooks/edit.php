<?php

function HookLicensemanagerEditEndofmetadataaddcustomfield()
{
    global $license_attach_upload, $lang, $locked_fields, 
           $multiple, $resource, $save_errors, $upload_review_mode;

    if ($license_attach_upload == true && $multiple == false && $upload_review_mode == true) {

        if (!licensemanager_check_read()) {
            return false;
        }

        if (!isset($resource['resource_license'])) {
            $resource['resource_license'] = getval("resource_license", 0, false, is_positive_int_loose(...));
        }

        $licenses = licensemanager_get_all_licenses_grouped();

        if (empty($licenses)) {
            return false;
        }

        ?>
        <div class="Question <?php if($upload_review_mode && in_array("resource_license", $locked_fields)){echo "lockedQuestion ";} if(isset($save_errors) && is_array($save_errors) && array_key_exists('resource_license',$save_errors)) { echo 'FieldSaveError'; } ?>" id="resource_license" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
            <label for="resource_license">
            <?php

            echo escape($lang["managelicenses"]);

            if ($upload_review_mode) {
                renderLockButton('resource_license', $locked_fields);
            }

            ?>
            </label>      
            <select class="stdwidth" name="resource_license" id="resource_license">
                <option value="0"></option>
                <?php

                    $current_group = null;

                    foreach ($licenses as $license) {
                        if ($license['license_status'] !== $current_group) {

                            // Close previous optgroup if not the first
                            if ($current_group !== null) {
                                echo '</optgroup>';
                            }

                            // Start new optgroup
                            $current_group = $license['license_status'];
                            echo '<optgroup label="' . escape($lang["license_status_" . $current_group]) . '">';
                        }

                        // Output the option
                        echo '<option value="' . (int) $license['ref'] . '"';
                        echo (isset($resource['resource_license']) && $resource['resource_license'] == $license['ref']) ? ' selected': '';
                        echo '>';
                        echo escape($license['holder'] . ' (ID: ' . (int) $license['ref'] . ')');
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

function HookLicensemanagerEditAftersaveresourcedata(int|array $ref): bool|array
{
    global $license_attach_upload, $multiple, $upload_review_mode;

    if ($license_attach_upload == true && $multiple == false && $upload_review_mode == true) {

        if (!licensemanager_check_read()) {
            return false;
        }

        $val = getval("resource_license", 0, false, is_positive_int_loose(...));
        if ($val !== 0) {
            // Attempt to add license record
            $result = licensemanager_link_license($val, $ref);

            if(!$result) {
                return ['Error adding license record'];
            }
        }
    }

    return true;
}

function HookLicensemanagerEditCopy_locked_data_extra(array $resource, array $locked_fields, int $last_edited): bool|array
{
    global $license_attach_upload;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $resource = $GLOBALS["hook_return_value"];
    }

    if ($license_attach_upload && in_array('resource_license', $locked_fields)) {

        if (!licensemanager_check_read()) {
            return false;
        }

        // Get the license ref of the record added to the last edited resource
        $last_edited_consent = licensemanager_get_licenses($last_edited);

        if (!empty($last_edited_consent)) {
            $resource['resource_license'] = $last_edited_consent[0]['ref'];
            return $resource;
        } else {
            return false;
        }
    } else {
        // Return original resource due to way hook is implemented ($last_hook_value_wins = true)
        return $resource;
    }
}