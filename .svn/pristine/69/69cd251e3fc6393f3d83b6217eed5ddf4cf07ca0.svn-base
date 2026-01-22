<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';
include_once '../include/vimeo_publish_functions.php';

use Vimeo\Vimeo;
use Vimeo\Exceptions\VimeoUploadException;

// May have come from plugin setup page to get or delete a system wide token
$systemtoken    = getval("systemtoken","") != "" && checkperm('a');
$ref            = getval('resource',0, true);
if(!$systemtoken && $ref > 0)
    {
    $access        = get_resource_access($ref);
    if(0 != $access)
        {
        exit($lang['vimeo_publish_access_denied']);
        }
    
    $resource_data = get_resource_data($ref);
    $error         = '';
    }
$modal = (getval("modal", "") == "true");

$publish_params = array();
$publish_params["resource"]     = $ref;
$publish_params["systemtoken"]  = $systemtoken;
$vimeo_publish_url = generateURL($vimeo_callback_url, $publish_params);

if(getval('delete_token','') != '')
    {
    if($vimeo_publish_allow_user_accounts)
        {
        delete_vimeo_token($userref);
        }
    elseif($systemtoken)
        {
        delete_vimeo_token();
        }
    }

if(!$systemtoken && !$vimeo_publish_allow_user_accounts && trim($vimeo_publish_system_token)=="")
    {
    exit($lang['vimeo_publish_not_configured'] . "<a href=\"{$baseurl}/plugins/vimeo_publish/pages/setup.php\">$baseurl/plugins/vimeo_publish/pages/setup.php</a>");
    }

// Initialize VIMEO
init_vimeo_api($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_url);
$vimeo_publish_access_token = get_access_token($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_url);
$vimeo_user_data            = array();
if($systemtoken)
    {
    // Just came here to get/delete a token, return to setup page
    redirect(generateURL($baseurl_short . "plugins/vimeo_publish/pages/setup.php"));
    }

// Try uploading resource to Vimeo
$successfully_uploaded = false;
if(getval('upload', '') !=  '' && enforcePostRequest(false))
    {
    $video_id   = '';
    $file_path  = get_resource_path($ref, true, '', false, $resource_data['file_extension'], -1, 1, false, '', -1);
    $parameters = array(
        'name'        => getval('video_title', ''),
        'description' => getval('video_description', '')
    );

    if(vimeo_upload($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_access_token, $ref, $file_path, $vimeo_publish_vimeo_link_field, $video_id, $error)
        && set_video_information($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_access_token, $video_id, $parameters)
    )
        {
        $successfully_uploaded = true;
        }
    }

if(0 < $vimeo_publish_vimeo_link_field)
    {
    $vimeo_url = get_data_by_field($ref, $vimeo_publish_vimeo_link_field);

    // Show confirmation message
    if($successfully_uploaded && '' !== $vimeo_url)
        {
        $error = str_replace('[vimeo_url]', "<a href=\"{$vimeo_url}\" target=\"_blank\">{$vimeo_url}</a>", $lang['vimeo_publish_resource_published']);
        }
    // Show warning that video has already been published to Vimeo
    elseif('' !== $vimeo_url)
        {
        $error = str_replace(array('[ref]', '[vimeo_url]'), array($ref, "<a href=\"{$vimeo_url}\" target=\"_blank\">{$vimeo_url}</a>"), $lang['vimeo_publish_resource_already_published']);
        }
    }

if(0 < $vimeo_publish_video_title_field)
    {
    $default_video_title = get_data_by_field($ref, $vimeo_publish_video_title_field);
    }

if(0 < $vimeo_publish_video_description_field)
    {
    $default_video_description = get_data_by_field($ref, $vimeo_publish_video_description_field);
    }

include '../../../include/header.php';

$params= get_search_params();
$params["ref"] = $ref;
?>

<div class="BasicsBox">
    <div class="RecordHeader">
        <h1><?php echo escape($lang["vimeo_publish_resource_tool_link"]); ?></h1>
        <p>
            <a href="<?php echo generateURL($baseurl_short . 'pages/view.php', $params); ?>" onClick="return CentralSpaceLoad(this,true);">
                <?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
    </div>

<?php
if('' != $error)
    {
    ?>
    <div class="PageInformal"><?php echo $error; ?></div>
    <?php
    include '../../../include/footer.php';

    exit();
    }

// Show which user we will be publishing as...

if($vimeo_publish_allow_user_accounts && get_vimeo_user($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_access_token, $vimeo_user_data))
    {
    ?>
    <div class="Question">
        <p><?php echo escape($lang['vimeo_publish_publish_as_user']); ?><a href="<?php echo $vimeo_user_data['link']; ?>" target="_blank"><strong><?php echo $vimeo_user_data['name']; ?></strong> (<?php echo ucfirst($vimeo_user_data['account']); ?> account - <?php echo formatfilesize($vimeo_user_data['upload_quota_free']); ?> free)</a></p>
        <?php
        if($vimeo_publish_allow_user_accounts)
            {
            ?>
            <p>
                <a href="<?php echo $vimeo_callback_url; ?>?resource=<?php echo $ref; ?>&delete_token=true">&gt;&nbsp;<?php echo escape($lang['vimeo_publish_delete_token']); ?></a>
            </p>
            <?php
            }?>
    </div>
    <?php
    }
    ?>

<form method="post" action="<?php echo $vimeo_callback_url; ?>?resource=<?php echo $ref; ?>">
    <?php generateFormToken("vimeo_api"); ?>
    <input type="hidden" name="upload" value="true"/>
    <div class="Question">
        <br>
        <h2><?php echo escape($lang['vimeo_publish_video_details']); ?></h2>
    </div>
    <div class="Question">
        <label for="video_title"><?php echo escape($lang['vimeo_publish_video_title']); ?></label>
        <input type="text" class="stdwidth" name="video_title" value="<?php echo $default_video_title; ?>"/>
        <br>
        <label for="video_description"><?php echo escape($lang['vimeo_publish_video_description']); ?></label>
        <textarea class="stdwidth" rows="6" columns="50" id="video_description" name="video_description"><?php echo strip_tags($default_video_description); ?></textarea>
        <br>
        <input type="submit" value="<?php echo escape($lang['vimeo_publish_button_text']); ?>" onClick="return confirmSubmit();"/>
    </div>
    <script>
    function confirmSubmit()
        {
        var agree = confirm("<?php echo escape($lang['vimeo_publish_legal_warning']); ?>");

        if(agree)
            {
            return true;
            }

        return false;
        }
    </script>
</form>
</div> <!-- End of BasicsBox -->
<?php
include '../../../include/footer.php';