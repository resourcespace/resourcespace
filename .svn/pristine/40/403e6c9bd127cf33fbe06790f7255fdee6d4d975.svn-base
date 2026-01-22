<?php
function HookVimeo_publishViewAfterresourceactions()
    {
    // Adds a "Publish to Vimeo" link under Resource Tools
    global $baseurl, $lang, $ref, $access, $resource, $vimeo_publish_restypes,
    $vimeo_publish_system_token, $vimeo_publish_allow_user_accounts;
    if(0 == $access && in_array($resource['resource_type'], $vimeo_publish_restypes)
        &&
        !(!$vimeo_publish_allow_user_accounts && $vimeo_publish_system_token=="")
        )
        {
        // Can't use CentralSpaceLoad() here or API call will fail
        ?>
        <li>
            <a href="<?php echo $baseurl?>/plugins/vimeo_publish/pages/vimeo_api.php?resource=<?php echo $ref; ?>" ><?php echo "<i class='icon-share-2'></i>&nbsp;" . $lang['vimeo_publish_resource_tool_link']; ?></a>
        </li>
<?php
        }
    }