<?php

command_line_only();

# Test temporary download keys are valid. Used by API e.g. api_get_resource_path() to authenticate download links in download.php

$resource_to_get = create_resource(1, 0, -1, "001350_create_resource");
# Below function preserves earlier functionality. Do not update this.
function generate_temp_download_key_pre_v103(int $user, int $resource, string $size): string
{
    if (
        !in_array($size, array('col', 'thm', 'pre'))
        && (($GLOBALS["userref"] != $user && !checkperm_user_edit($user)) || get_resource_access($resource) != 0)
    ) {
        return "";
    }

    $user_data = get_user($user);
    $data =  generateSecureKey(128)
        . ":" . $user
        . ":" . $resource
        . ":" . $size
        . ":" .  time()
        . ":" . hash_hmac("sha256", "user_pass_mac", $user_data['password']);

    return rsEncrypt(
        $data,
        hash_hmac('sha512', 'dld_key', $GLOBALS['api_scramble_key'] . $GLOBALS['scramble_key'])
    );
}

// Check the long keys produced in v10.3 & v10.4
function generate_temp_download_key_pre_v105(int $user, int $resource, string $size): string
{
    if (
        !in_array($size, array('col', 'thm', 'pre'))
        && (($GLOBALS["userref"] != $user && !checkperm_user_edit($user)) || get_resource_access($resource) != 0)
    ) {
        return "";
    }

    $user_data = get_user($user);
    $data =  generateSecureKey(128)
        . ":" . $user
        . ":" . $resource
        . ":" . $size
        . ":" .  time()
        . ":" . hash_hmac("sha256", "user_pass_mac", $user_data['password']);

    return base64_encode(
        rsEncrypt($data, hash_hmac('sha512', 'dld_key', $GLOBALS['api_scramble_key'] . $GLOBALS['scramble_key']))
    );
}

// Subtest 1 - Access keys created pre version 10.3
$pre_v103_access_key = generate_temp_download_key_pre_v103($userref, $resource_to_get, '');
if (!validate_temp_download_key($resource_to_get, $pre_v103_access_key, '', 0, false)) {
    echo 'Subtest 1 - Valid pre 10.3 access key was interpreted as invalid.';
    return false;
}

// Subtest 2 - Access keys created in v10.3 & v10.4
$access_key = generate_temp_download_key_pre_v105($userref, $resource_to_get, '');
if (!validate_temp_download_key($resource_to_get, $access_key, '', 0, false)) {
    echo 'Subtest 2 - Valid access key from pre v10.5 was interpreted as invalid.';
    return false;
}

// Subtest 3 - Access keys created from version 10.5
$access_key = generate_temp_download_key($userref, $resource_to_get, '');
if (!validate_temp_download_key($resource_to_get, $access_key, '', 0, false)) {
    echo 'Subtest 3 - Valid access key was interpreted as invalid.';
    return false;
}


return true;
