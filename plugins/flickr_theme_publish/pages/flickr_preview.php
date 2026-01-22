<?php
// grabs preview image to show while publishing
include "../../../include/boot.php";
include "../../../include/authenticate.php";

$ref=getval("ref",0,true);
if($ref>0)
    {
    $access = get_resource_access($ref);
    if($access != 0)
        {
        exit($lang['error-permissiondenied']);
        }
    $path=get_resource_path($ref,false,"thm",false);
    
    $title          = get_data_by_field($ref,$view_title_field);
    $title          = i18n_get_translated($title);  
    $description    = get_data_by_field($ref,$flickr_caption_field);
    $keywords       = get_data_by_field($ref,$flickr_keywords_field);
    $photoid        = ps_value("SELECT flickr_photo_id value FROM resource WHERE ref = ?", array("i",$ref), "");
    $results=array($path,$title,$description,$keywords,$photoid);
    header('Content-Type: application/json');
    echo json_encode($results);
    }
