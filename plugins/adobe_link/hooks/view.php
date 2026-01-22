<?php

/**
 * Replace download links with code to support importing resources into Adobe CC
 */
function HookAdobe_linkViewBefore_footer_always()
{
    global $lang, $resource, $userref;
    if (
        !isset($_SERVER['HTTP_USER_AGENT'])
        ||
        !in_array($_SERVER['HTTP_USER_AGENT'], array("InDesign-DAMConnect","PhotoShop-DAMConnect"))
        ||
        ($resource["lock_user"] > 0 && $resource["lock_user"] != $userref)
    ) {
        return false;
    }
    ?>
    <script>

    jQuery(document).ready(function()
        {
        window.addEventListener("message", function(event) {
            if(event.data && event.data.eventType=="assetReadyToPlace")
                {
                styledalert('<?php echo escape($lang["adobe_link_title"]); ?>','<?php echo escape($lang["adobe_link_copy_successful"]); ?>');
                }    
            });
        });

    function extractJSON(obj, indent) {
        for (const i in obj) {
            if (Array.isArray(obj[i]) || typeof obj[i] === 'object') {
                console.log(indent + i + ' is array or object');
                extractJSON(obj[i], indent + ' > ' + i + ' > ');
            } else {
                var content = jQuery('.js-textarea').val();
                if(content != '')
                    content = content + "\n";
            }
        }
    }

    function sendDownloadDocumentMessageToCCApp(objData) {
        var objMessage = {};
        objMessage.items = [];
        objMessage.items.push({
                'assetId' : objData.asset_id,
                'versionId' : objData.version_id,
                'documentName' : objData.document_name,
                'url' : objData.url 
        });
        
        objMessage.count = objMessage.items.length;
        objMessage.eventType = "downloadDocument";

        styledalert('<?php echo escape($lang["adobe_link_title"]); ?>','<?php echo escape($lang["adobe_link_import_successful"]); ?>');

        window.postMessage(objMessage, '*');
        }

    function sendDownloadAssetMessageToCCApp(objData) {

        var objMessage = {};

        objMessage.items = [];
        objMessage.items.push({
                'assetId' : objData.asset_id,
                'versionId' : objData.version_id,
                'assetName' : objData.asset_name,
                'url' : objData.url,
                'placeOnExisting' : objData.placeOnExisting
        });
        
        objMessage.count = objMessage.items.length;
        objMessage.eventType = "downloadAsset"

        window.postMessage(objMessage, '*');
        <?php
        if ($_SERVER['HTTP_USER_AGENT'] != "InDesign-DAMConnect") {
            // Photoshop not always showing the 'Asset ready to place' message
            echo "styledalert('" . escape($lang["adobe_link_title"]) . "','" . escape($lang["adobe_link_copy_successful"]) . "');";
        }
        ?>
        
    }
    
    function sendResizePanelMessageToCCApp(objData) {
        var objMessage = {};

        objMessage.items = [];
        objMessage.items.push({
                'width' : objData.width,
                'height' : objData.height                       
        });
        
        objMessage.count = objMessage.items.length;
        objMessage.eventType = "resizePanel"

        window.postMessage(objMessage, '*');
    }

    function AdobeLinkAssetDownload(link){
        var objData = {};
        objData.url = jQuery(link).attr('data-attribute-path');
        objData.asset_id = jQuery(link).attr('data-attribute-ref');
        objData.version_id = jQuery(link).attr('data-attribute-ref');
        objData.asset_name = jQuery(link).attr('data-attribute-name');
        objData.placeOnExisting = true;
        sendDownloadAssetMessageToCCApp(objData);
    }

    function AdobeLinkDocumentDownload(link){
        var objData = {};
        objData.url = jQuery(link).attr('data-attribute-path');
        objData.asset_id = jQuery(link).attr('data-attribute-ref');
        objData.version_id = jQuery(link).attr('data-attribute-ref');
        objData.document_name = jQuery(link).attr('data-attribute-name');
        objData.placeOnExisting = true;
        sendDownloadDocumentMessageToCCApp(objData);
    }
    

    jQuery('#resizePanel').click(function(){
        var objData = {};
        var size = jQuery(this).parents('.js-group').find('input').val();
        objData.width = size.split(",")[0];
        objData.height = size.split(",")[1];
        sendResizePanelMessageToCCApp(objData);
    }); 

   function AdobeLinkDocumentSave(link){

        var objMessage = {};
        objMessage.eventType = "uploadDocument";
        var objData = {};
        objData.uploadUrl = jQuery(link).attr('data-attribute-path');
        objMessage.data = objData;
        window.postMessage(objMessage, '*');
        styledalert('<?php echo escape($lang["adobe_link_saving"]); ?>','<?php echo escape($lang["adobe_link_saving_wait"]); ?>');
        window.setTimeout("imgsrc = jQuery('#previewimage').attr('src');jQuery('#previewimage').attr('src',imgsrc + new Date().getTime());",5000);
   }          
        
    </script>
    <?php
}

function HookAdobe_linkViewDownloadbuttonreplace()
{
    global $userref, $baseurl, $urlparams, $ref, $resource, $size_info_array, $lang, $adobe_link_asset_extensions;
    global $adobe_link_document_extensions, $edit_access, $CSRF_token_identifier, $usersession;

    # Adds a special link to the download button.
    $adb_ext = "";
    
    if (is_array($size_info_array)) {
        $adb_ext = $size_info_array["id"] == "" ? $resource['file_extension'] : $size_info_array["extension"];
    }

    if (
        !isset($_SERVER['HTTP_USER_AGENT'])
        ||
        !in_array($_SERVER['HTTP_USER_AGENT'], array("InDesign-DAMConnect","PhotoShop-DAMConnect"))
        ||
        (!in_array(strtolower($adb_ext), $adobe_link_asset_extensions) && !in_array(strtolower($adb_ext), $adobe_link_document_extensions))
    ) {
        return false;
    }

    if (in_array(strtolower($adb_ext), $adobe_link_document_extensions) && is_int_loose($resource["lock_user"]) && $resource["lock_user"] != 0 && $resource["lock_user"] != $userref) {
        return false;
    }
    $adobefilename = get_download_filename($ref, '', -1, $adb_ext);
    $extraparams = array();
    $extraparams["noattach"] = "true";
    $extraparams["size"] = $size_info_array["id"];
    $extraparams["ext"] = $adb_ext;

    // Add a temporary key for this resource download that will be checked by hook in download.php
    $adb_tmp_key = adobe_link_genkey($userref, $ref);
    $extraparams["adb_key"] = $userref . ":" . $adb_tmp_key;
    $path = generateURL($baseurl . "/pages/download.php", $urlparams, $extraparams);

    $adobelink = "<a href='" . $path . "'";
    $adobelink .= "data-attribute-path='" . escape($path) . "' ";
    $adobelink .= "data-attribute-ref='" . escape($ref) . "' ";
    $adobelink .= "data-attribute-name='" . escape($adobefilename) . "' ";

    if (in_array(strtolower($adb_ext), $adobe_link_asset_extensions)) {
        $adobelink .= "onclick='AdobeLinkAssetDownload(this);return false;' ";
        $adobelink .= ">" . $lang["adobe_link_import"] . "</a>";
    } else {
        $adobelink .= "onclick='AdobeLinkDocumentDownload(this);return false;' ";
        $adobelink .= ">" . $lang["adobe_link_open"] . "</a>";
    }
    echo $adobelink;

    if ($edit_access && in_array(strtolower($adb_ext), $adobe_link_document_extensions)) {
        $extraparams["replace_resource"] =  $ref;
        $extraparams["processupload"]    =  "true";
        $extraparams["no_exif"]          =  "yes";
        $extraparams[$CSRF_token_identifier] =  generateCSRFToken($usersession, "adobe_upload");
        $uploadpath = generateURL($baseurl . "/pages/upload_batch.php", $urlparams, $extraparams);
        $adobesavelink = "</td><td class='DownloadButton'><a href='" . $uploadpath . "' ";
        $adobesavelink .= "data-attribute-path='" . escape($uploadpath) . "' ";
        $adobesavelink .= "onclick='AdobeLinkDocumentSave(this);return false;' ";
        $adobesavelink .= ">" . $lang["adobe_link_upload_document"] . "</a>";
        echo $adobesavelink;
    }

    return true;
}

function HookAdobe_linkViewOrigdownloadlink()
{
    # Adds a special link to the download button.
    global $userref, $usergroup, $lang, $ref, $access, $resource, $k, $size_info, $baseurl, $urlparams, $path, $direct_download, $alternative;
    global $adobe_link_document_extensions, $adobe_link_asset_extensions, $lang, $edit_access, $adb_ext;

    if (
        !isset($_SERVER['HTTP_USER_AGENT'])
        ||
        !in_array($_SERVER['HTTP_USER_AGENT'], array("InDesign-DAMConnect","PhotoShop-DAMConnect"))
        ||
        (!in_array(strtolower($resource['file_extension']), $adobe_link_asset_extensions) && !in_array(strtolower($resource['file_extension']), $adobe_link_document_extensions))
    ) {
        return false;
    }
    if (in_array(strtolower($adb_ext), $adobe_link_document_extensions) && is_int_loose($resource["lock_user"]) && $resource["lock_user"] != 0 && $resource["lock_user"] != $userref) {
        return false;
    }
    $extraparams = array();
    $extraparams["noattach"] = "true";
    $extraparams["ext"] = $resource['file_extension'];
    $adb_tmp_key = adobe_link_genkey($userref, $ref);
    $extraparams["adb_key"] = $userref . ":" . $adb_tmp_key;

    $path = generateURL($baseurl . "/pages/download.php", $urlparams, $extraparams);
    $adobefilename = get_download_filename($ref, '', -1, $resource['file_extension']);

    $adobelink = "<a href='" . $path . "'";
    $adobelink .= "data-attribute-path='" . escape($path) . "' ";
    $adobelink .= "data-attribute-ref='" . escape($ref) . "' ";
    $adobelink .= "data-attribute-name='" . escape($adobefilename) . "' ";

    if (in_array(strtolower($resource['file_extension']), $adobe_link_asset_extensions)) {
        $adobelink .= "onclick='AdobeLinkAssetDownload(this);return false;' ";
        $adobelink .= ">" . $lang["adobe_link_import"] . "</a>";
    } else {
        $adobelink .= "onclick='AdobeLinkDocumentDownload(this);return false;' ";
        $adobelink .= ">" . $lang["adobe_link_open"] . "</a>";
    }

    // Show the link
    ?>
    <tr class="DownloadDBlend">
    <td class="DownloadFileName">
        <h2>
            <?php echo escape(str_replace_formatted_placeholder("%extension", $resource["file_extension"], $lang["originalfileoftype"])); ?>
        </h2>
    </td>
    <td class="DownloadFileSize"><?php echo formatfilesize(filesize_unlimited($path))?></td>
    <td class="DownloadButton">
    <?php echo $adobelink;

    if ($edit_access && in_array(strtolower($resource['file_extension']), $adobe_link_document_extensions)) {
        $extraparams["replace_resource"] =  $ref;
        $extraparams["processupload"]    =  "true";
        $extraparams["no_exif"]          =  "yes";
        $uploadpath = generateURL($baseurl . "/pages/upload_batch.php", $urlparams, $extraparams);
        $adobesavelink = "</td><td class='DownloadButton'><a href='" . $uploadpath . "'";
        $adobesavelink .= "data-attribute-path='" . escape($uploadpath) . "' ";
        $adobesavelink .= "onclick='AdobeLinkDocumentSave(this);return false;' ";
        $adobesavelink .= ">" . $lang["adobe_link_upload_document"] . "</a>";
        echo $adobesavelink;
        ?>
        </td>
        </tr><?php
    }

    ?>
    </td>
    </tr>
    <?php
    return true;
}

function HookAdobe_linkViewReplacepreviewlink(string $preview_title)
{
    global $previewimagelink;
    $previewimagelink .= "?reload=" . uniqid();
}