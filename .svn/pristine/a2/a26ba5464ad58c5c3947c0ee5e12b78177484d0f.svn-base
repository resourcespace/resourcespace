<?php 

function HookTransformAllAdditionalheaderjs()
    {
    global $baseurl_short, $css_reload_key;?>
    <link rel="stylesheet" href="<?php echo $baseurl_short?>plugins/transform/lib/jcrop/css/jquery.Jcrop.min.css?css_reload_key=<?php echo $css_reload_key; ?>" type="text/css" />
    <script type="text/javascript" src="<?php echo $baseurl_short ?>plugins/transform/lib/jcrop/js/jquery.Jcrop.min.js?css_reload_key=<?php echo $css_reload_key; ?>" language="javascript"></script>
    <script type="text/javascript" src="<?php echo $baseurl_short?>lib/jQueryRotate/jQueryRotate.js?css_reload_key=<?php echo $css_reload_key; ?>" language="javascript"></script>
    <?php
    }


function HookTransformAllRender_actions_add_collection_option($top_actions,$options,$collection_data, array $urlparams)
    {
    global $cropper_transform_original, $cropper_enable_batch,$count_result,$lang, $baseurl, $userref, $internal_share_access;
    
    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }

    $k = trim((isset($urlparams["k"]) ? $urlparams["k"] : ""));

    if($k != "" && $internal_share_access === false)
        {
        return $options;
        }

    if ($cropper_enable_batch 
        && $cropper_transform_original
        && $count_result > 0
        &&  (
            $userref == $collection_data['user']
            || $collection_data['allow_changes'] == 1
            || checkperm('h')
            )
        )
        {

        $annotate_option = array(
            "value" => "transform",
            "label" => $lang["transform"],
            "category" => ACTIONGROUP_EDIT,
            "data_attr" => array(
                "url" => generateURL(
                    "{$baseurl}/plugins/transform/pages/collection_transform.php",
                    $urlparams,
                    array(
                        "collection" => $collection_data['ref'],
                    )),
            ),
        );
        $options[] = $annotate_option;

        return $options;
        }
    }
    
function HookTransformAllAdditional_title_pages_array()
    {
    return array("crop","collection_transform");
    }
    
function HookTransformAllAdditional_title_pages()
    {
    global $pagename,$lang,$applicationname;
    switch($pagename)
        {
        case "crop":
            global $original;
            if($original)
                {
                $pagetitle=$lang['transform_original'];
                }
            else
                {
                $pagetitle=$lang['transformimage'];
                }
            break;
        
        case "collection_transform":
            $pagetitle=$lang['batchtransform'];
            break;
        }
    if(isset($pagetitle))
        {
        echo "<script language='javascript'>\n";
        echo "document.title = \"$applicationname - $pagetitle\";\n";
        echo "</script>";
        }
    }

function HookTransformAllreplace_resource_file_extra($resource)
    {
    // Delete the original_copy alternative when replacing the file via upload_batch
    if(getval('saveaction', '') === '')
        {
        $path = get_resource_path($resource['ref'],true,"original_copy",false,$resource['file_extension']);
        if(file_exists($path))
            {
            unlink($path);
            }
        }
    }
