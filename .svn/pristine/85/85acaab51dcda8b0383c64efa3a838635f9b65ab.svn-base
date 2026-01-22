<?php
function isValidVimeoURL($url)
    {
    // Check if the video exists
    global $vm2rs_videoId;
    // Vimeo url?
    if (preg_match("/vimeo.com\/[a-z1-9.-_]+/", $url))
        {
        preg_match("/vimeo.com\/([a-z1-9.-_]+)/", $url, $matches);
        }
    elseif (preg_match("/vimeo.com(.+)v=([^&]+)/", $url))
        {
        preg_match("/v=([^&]+)/", $url, $matches);
        }

    if (!empty($matches))
        {
        $vm2rs_videoId = $matches[1];

        if (!curl_init($url))
            {
            return false;
            }

        return true;
        }
    }

function Hookvm2rsViewrenderinnerresourcepreview()
    {
    // Replace preview if it's a valid Vimeo URL
    global $ref, $ffmpeg_preview_max_width, $ffmpeg_preview_max_height, $vm2rs_field_id, $vm2rs_videoId;

    $width = $ffmpeg_preview_max_width;
    $height = $ffmpeg_preview_max_height;
    $vimeo_url = get_data_by_field($ref, $vm2rs_field_id);

    if ($vimeo_url == "" || !isValidVimeoURL($vimeo_url))
        {
        return false;
        }
    else
        {
        $vimeo_url_emb = "https://player.vimeo.com/video/" . "$vm2rs_videoId";
        ?>
        <div id="previewimagewrapper">
            <div class="Picture" id="videoContainer" style="width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;">
                <iframe title="Vimeo video player" class="vimeo-player" type="text/html" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="<?php echo $vimeo_url_emb; ?>" frameborder="0" frameborder="0" allowFullScreen>
                </iframe>
            </div>
        </div>
        <?php
        }
    return true;
    }

function Hookvm2rsViewreplacedownloadoptions()
    {
    // Replace download options
    global $ref, $vm2rs_field_id, $baseurl_short, $lang;

    $vimeo_url = get_data_by_field($ref, $vm2rs_field_id);

    if ($vimeo_url !== "" && isValidVimeoURL($vimeo_url))
        { ?>
        <table cellpadding="0" cellspacing="0" id="ResourceDownloadOptions">
            <tr >
                <td><?php echo escape($lang["fileinformation"]) ?></td>
                <td><?php echo escape($lang["filesize"]) ?></td>
                <td><?php echo escape($lang["options"]) ?></td>
            </tr>
            <tr class="DownloadDBlend">
                <td>
                    <h2><?php echo escape($lang["vm2rs_online_preview"]) ?></h2>
                    <p><?php echo escape($lang["vm2rs_youtube_video"]) ?></p>
                </td>
                <td><?php echo escape($lang["notavailableshort"]) ?></td>
                <td class="DownloadButton HorizontalWhiteNav">
                    <a href="<?php echo $baseurl_short; ?>pages/resource_request.php?ref=<?php echo urlencode($ref); ?>&k=<?php echo escape(getval("k", "")); ?>"
                        onClick="return CentralSpaceLoad(this,true);">
                    <?php echo escape($lang["action-request"]) ?>
                </td>
            </tr>
        </table>
        <?php
        return true;
        }
    else
        {
        return false;
        }
    }

