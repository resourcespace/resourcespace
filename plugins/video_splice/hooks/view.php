<?php

function HookVideo_spliceViewAfterresourceactions()
    {
    global $videosplice_allowed_extensions, $resource, $baseurl, $urlparams, $lang, $access, $username, $anonymous_login;

    $editaccess = get_edit_access($resource['ref'], $resource["archive"], $resource);
    $can_create_resource = $editaccess && (checkperm("d") || checkperm("c"));
    $can_create_alternative = $editaccess && !checkperm("A");
    $can_download = $access === RESOURCE_ACCESS_FULL;

    if (
        !in_array($resource["file_extension"], $videosplice_allowed_extensions) 
        || ($resource['ref'] < 0 || !$can_create_resource && !$can_create_alternative && !$can_download)
        || (isset($anonymous_login) && $username == $anonymous_login)
    ) {
        
        return false;
    }

    ?>
    <li><a href="<?php echo generateURL($baseurl . "/plugins/video_splice/pages/trim.php", $urlparams);?>" onclick="return ModalLoad(this, true);">
    <?php echo "<i class='icon-scissors'></i>&nbsp;" . $lang["action-trim"]; ?>
    </a></li>
    <?php
    }
?>