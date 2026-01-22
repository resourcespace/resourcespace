<?php

function HookEmbeddocumentViewAfterresourceactions2()
{
    global $embeddocument_resourcetype,$resource,$ref,$baseurl,$lang,$access, $username, $anonymous_login;

    if (
        $resource["resource_type"] != $embeddocument_resourcetype
        || !$GLOBALS["allow_share"]
        || checkperm("noex")
        || get_resource_access($resource) != 0
        || (isset($anonymous_login) && $username == $anonymous_login)
    ) {
        return false;
    }

    # filter out resources without previews
    $thumbwidth = $resource["thumb_width"];
    $thumbheight = $resource["thumb_height"];

    if ($thumbwidth == 0) {
        // The resource has no preview.
        return false;
    }

    # Resolve dimensions of document viewer
    # Set default viewer widths--subtract 2 pixels for border
    $portrait = 358; //Default portrait width
    $landscape = 478; //Default landscape width

    #sets width for either portrait or landscape
    $ratio = $thumbwidth / $thumbheight;

    if ($ratio > 1) {
        $width = $landscape;
    } else {
        $width = $portrait;
    }

    $width_w_border = (int)$width + 2; //expands width to display border
    $height = floor($width / $ratio);
    $height += 40; // Enough space for controls

    # Create key to allow docviewer to access resource files
    $key = generate_resource_access_key($ref, 0, 0, "", $lang["embeddocument_embed_share"]);
    $access_key = generateSecureKey(64);
    $download_key = rsEncrypt($access_key . ":" . $key . ":" . $ref, $access_key . $GLOBALS["scramble_key"]);

    $urlparams = ["ref" => (int)$ref,"k" => $key,"width" => (int)$width];
    $viewer_url = generateURL($baseurl . "/plugins/embeddocument/pages/viewer.php", $urlparams);
    $max_url = generateURL($baseurl . "/plugins/embeddocument/pages/viewer.php", $urlparams, ["width" => (int)$width * 2]);
    $download_url = generateURL($baseurl . "/plugins/embeddocument/pages/viewer.php", $urlparams, ["dk" => $download_key,"ak" => $access_key,"noattach" => true]);

    $embed = "<div id=\"embeddocument_back_" . (int)$ref . "\" style=\"display:none;position:absolute;top:0;left:0;width:100%;height:100%;min-height: 100%;background-color:#000;opacity: .5;filter: alpha(opacity=50);\"></div>
    <div id=\"embeddocument_minimise_" . (int)$ref . "\" style=\"position:absolute;top:5px;left:20px;background-color:white;border:1px solid black;display:none;\"><a href=\"#\" onClick=\"
    var ed=document.getElementById('embeddocument_" . (int)$ref . "');
    ed.width='" . (int)$width . "';
    ed.style.position='relative';
    ed.style.top='0';
    ed.style.left='0';
    ed.src='" . $viewer_url . "';
    document.getElementById('embeddocument_minimise_" . (int)$ref . "').style.display='none';
    document.getElementById('embeddocument_maximise_" . (int)$ref . "').style.display='block';
    document.getElementById('embeddocument_back_" . (int)$ref . "').style.display='none';
    \">" . $lang["embeddocument_minimise"] . "</a></div>
    <div id=\"embeddocument_maximise_" . (int)$ref . "\" class=\"embeddocument_maximise\"><a href=\"#\" onClick=\"
    var ed=document.getElementById('embeddocument_" . (int)$ref . "');
    ed.width='" . (int)$width * 2 . "';
    ed.height='" . (int)$height * 2 . "';
    ed.style.position='absolute';
    ed.style.top='20px';
    ed.style.left='20px';
    ed.src='" . $max_url . "';
    ed.style.zIndex=999;
    document.getElementById('embeddocument_minimise_" . (int)$ref . "').style.display='block';
    document.getElementById('embeddocument_maximise_" . (int)$ref . "').style.display='none';	
    document.getElementById('embeddocument_back_" . (int)$ref . "').style.display='block';	
    \">" . $lang["embeddocument_maximise"] . "</a></div><iframe id=\"embeddocument_" . (int)$ref . "\" Style=\"background-color:#fff;cursor: pointer;\" width=\"$width_w_border\" height=\"$height\" src=\"" . $viewer_url . "\" frameborder=0 scrolling=no>Your browser does not support frames.</iframe>";

    # Compress embed HTML.
    $embed = str_replace("\n", " ", $embed);
    $embed = str_replace("\t", " ", $embed);
    while (strpos($embed, "  ") !== false) {
        $embed = str_replace("  ", " ", $embed);
    }
    ?>

    <li>
        <a href="#" onClick="
            if (document.getElementById('embeddocument').style.display=='block') {
                document.getElementById('embeddocument').style.display='none';
            } else {
                document.getElementById('embeddocument').style.display='block';
            }
            if (document.getElementById('embeddocument2').style.display=='block') {
                document.getElementById('embeddocument2').style.display='none';
            } else {
                document.getElementById('embeddocument2').style.display='block';
            }
            return false;">
            <?php echo "<i class='icon-share-2'></i>&nbsp;" . escape($lang["embeddocument_embed"]) ?>
        </a>
    </li>

    <p id="embeddocument2" style="display:none;padding:10px 0 3px 0;">
        <?php echo escape($lang["embeddocument_help"]) ?>
        <br/>
        <br/>

        <?php if ($access == 0) { ?>
            <input type="checkbox" onClick="
                if (this.checked) {
                    document.getElementById('embeddocument').style.display='none';
                    document.getElementById('embeddocument_download').style.display='block';
                } else {
                    document.getElementById('embeddocument').style.display='block'; 
                    document.getElementById('embeddocument_download').style.display='none';
                }
            ">
            <?php
            echo escape($lang["embeddocument_allow_original_download"]);
        } ?>

        <textarea id="embeddocument" style="width:335px;height:120px;display:none;"><?php echo escape($embed); ?></textarea>
        <textarea id="embeddocument_download" style="width:335px;height:120px;display:none;"><?php echo escape(str_replace($viewer_url, $download_url, $embed)); ?></textarea>
    </p>
    <?php
    return true;
}
