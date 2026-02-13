<?php
include "../include/boot.php";


# External access support (authenticate only if no key provided, or if invalid access key provided)
$k=getval("k","");if (($k=="") || (!check_access_key(getval("ref","",true),$k))) {include "../include/authenticate.php";}

include_once '../include/annotation_functions.php';

# Save Existing Thumb Cookie Status Then Hide the collection Bar 
# - Restores Status on Unload (See Foot of page)
$saved_thumbs_state = "";
$thumbs=getval("thumbs","unset");
if($thumbs != "unset" && $thumbs != "hide")
    {
    $saved_thumbs_state = "show";
    }
$thumbs = "hide";
rs_setcookie("thumbs", $thumbs, 1000,"","",false,false);

$ref=getval("ref","",true);
$search=getval("search","");
$offset=getval("offset",0,true);
$order_by=getval("order_by","");
$archive=getval("archive","",true);
$restypes=getval("restypes","");
$page=getval("page",1,true);
$alternative=getval("alternative", -1, true);
if (strpos($search,"!")!==false) {$restypes="";}

$order_by = check_order_by_in_table_joins($order_by);

$default_sort_direction="DESC";
if (substr($order_by,0,5)=="field"){$default_sort_direction="ASC";}
$sort=getval("sort",$default_sort_direction);
if($sort != 'ASC' && $sort != 'DESC') {$sort = $default_sort_direction;}

# Get alternative files and configure next and previous buttons relative to the current file
if($alternative != "-1")
    {
    $alt_order_by="";$alt_sort="";
    if ($alt_types_organize)
        {
        $alt_order_by="alt_type";
        $alt_sort="asc";
        }
    $altfiles=get_alternative_files($ref,$alt_order_by,$alt_sort);
    for ($n=0;$n<count($altfiles);$n++)
        {	
        if ($altfiles[$n]["ref"] == $alternative)
            {
            if ($n == count($altfiles) - 1)
                {
                $alt_next = $altfiles[$n]["ref"];
                }
            else
                {
                $alt_next = $altfiles[++$n]["ref"];
                --$n;
                }
            if ($n == "0")
                {
                $alt_previous = $altfiles[$n]["ref"];
                }
            else
                {
                $alt_previous = $altfiles[--$n]["ref"];
                ++$n;
                }
            }
        }
    }

# next / previous resource browsing
$go=getval("go","");
if ($go!="")
    {
    $origref = $ref; # Store the reference of the resource before we move, in case we need to revert this.

    # Re-run the search and locate the next and previous records.
    $modified_result_set=hook("modifypagingresult"); 
    if ($modified_result_set){
        $result=$modified_result_set;
    } else {
        $result=do_search($search,$restypes,$order_by,$archive,-1,$sort,false,DEPRECATED_STARSEARCH,false,false,"",false,true,true);
    }
    if (is_array($result) && !empty($result))
        {
        # Locate this resource
        $pos=-1;
        for ($n=0;$n<count($result);$n++)
            {
            if ($result[$n]["ref"]==$ref) {$pos=$n;}
            }
        if ($pos!=-1)
            {
            if (($go=="previous") && ($pos>0)) {$ref=$result[$pos-1]["ref"];}
            if (($go=="next") && ($pos<($n-1))) {$ref=$result[$pos+1]["ref"];if (($pos+1)>=($offset+72)) {$offset=$pos+1;}} # move to next page if we've advanced far enough
            }
        }

    # Check access permissions for this new resource, if an external user.
    if ($k!="" && !check_access_key($ref, $k)) {$ref = $origref;} # Cancel the move.
    }


$resource=get_resource_data($ref);

if ($resource===false)
    {
    exit(escape($lang['resourcenotfound']));
    }

$ext="jpg";
if ($ext!="" && $ext!="gif" && $ext!="jpg" && $ext!="png") {$ext="jpg";$border=false;} # Supports types that have been created using ImageMagick

# Check permissions (error message is not pretty but they shouldn't ever arrive at this page unless entering a URL manually)
$access=get_resource_access($ref);
if($access == RESOURCE_ACCESS_CONFIDENTIAL) 
    {
    exit(escape($lang["error-permissiondenied"]));
    }

$use_watermark=check_use_watermark();

hook('replacepreview');

# Locate the resource
if($resource['has_image'] === 0)
    {
    // No preview. If configured, try and use a preview from a related resource
    $pullresource = related_resource_pull($resource);
    if($pullresource !== false)
        {
        $ref = $pullresource["ref"];
        $resource = $pullresource;
        $access = get_resource_access($pullresource);
        }
    }

// Full screen preview should always use screen size in preference to preview size if possible
$previewsizes = ["scr", "pre"];

$imagepre = get_resource_preview($resource,$previewsizes, $access, $use_watermark, $page, true, $alternative);
if($imagepre)
    {
    $url = $imagepre["url"] . "&iaccept=on";
    }
else
    {
    $url = $GLOBALS["baseurl_short"] . 'gfx/no_preview/default.png';
    }

// Here we check for the presence or otherwise of the relevant multi-page preview files (for PDFs and potentially others) 
// The resulting values control the presence or otherwise of the navigation chevrons used for Next and Previous page browsing
// Multi-page preview files for the second page onwards will either be "scr" or "pre" size. 
// The size to look for here is governed by $resource_view_use_pre. If false then "scr" else true means "pre" 

//Previous page check
$previouspage = $page - 1;

if (!file_exists(get_resource_path($ref,true,$previewsizes[0],false,$ext,-1,$previouspage,$use_watermark,"",$alternative))
    && !file_exists(get_resource_path($ref,true,"",false,$ext,-1,$previouspage,$use_watermark,"",$alternative))
    && !file_exists(get_resource_path($ref,true,$previewsizes[1],false,$ext,-1,$previouspage,$use_watermark,"",$alternative))) {        
    
    $previouspage = -1;        
}

//Next page check
$nextpage = $page + 1;
if (!file_exists(get_resource_path($ref,true,$previewsizes[0],false,$ext,-1,$nextpage,$use_watermark,"",$alternative))
    && !file_exists(get_resource_path($ref,true,$previewsizes[1],false,$ext,-1,$nextpage,$use_watermark,"",$alternative))) {
    
    $nextpage = -1;    
}

// get mp3 paths if necessary and set $use_mp3_player switch
if (!(isset($resource['is_transcoding']) && $resource['is_transcoding']==1) && (in_array($resource["file_extension"],$ffmpeg_audio_extensions) || $resource["file_extension"]=="mp3") && $mp3_player){
        $use_mp3_player=true;
    }
    else {
        $use_mp3_player=false;
    }
if ($use_mp3_player){
    $mp3realpath=get_resource_path($ref,true,"",false,"mp3");
    if (file_exists($mp3realpath)){
        $mp3path=get_resource_path($ref,false,"",false,"mp3");
    }
}

include "../include/header.php";

if (!hook("fullpreviewresultnav")) {
    if (!hook("replacepreviewbacktoview")) { ?>
        <p style="margin:7px 0 7px 0;padding:0;">
            <a class="enterLink"
                href="<?php echo $baseurl_short?>pages/view.php?ref=<?php echo urlencode($ref) ?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php echo urlencode($archive)?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&k=<?php echo urlencode($k)?>&<?php echo hook("viewextraurl") ?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
    <?php } /*end hook replacepreviewbacktoview*/

    if ($k == "") {
        if (!checkperm("b") && !in_array($resource["resource_type"], $collection_block_restypes)) { ?>
            &nbsp;
            <?php echo add_to_collection_link(escape($ref)); ?>
            <i aria-hidden="true" class="icon-circle-plus"></i>
            &nbsp;
            <?php echo escape($lang["action-addtocollection"]) ?>
            </a>
        <?php }

        if ($search == "!collection" . $usercollection) { ?>
            &nbsp;
            <?php echo remove_from_collection_link(escape($ref)); ?>
            <i aria-hidden="true" class="icon-circle-minus"></i>
            &nbsp;
            <?php echo escape($lang["action-removefromcollection"]) ?>
            </a>
        <?php }

        if(count(canSeeAnnotationsFields()) > 0) { ?>
            &nbsp;
            <a href="#" onclick="toggleAnnotationsOption(this); return false;">
                <i class='icon-square-pen' aria-hidden="true"></i>
                <span><?php echo escape($lang['annotate_text_link_label']); ?></span>
            </a>
        <?php }
    }

# If viewing alternative files allow tabbing through them, else tab through resources in the collection
if ($alternative != "-1")
    {
    $thumbs_show="";
    if ($saved_thumbs_state=="show")
        {
        $thumbs_show="show";
        }
    $defaultparams = array(
        "ref"         =>  $ref,
        "k"           =>  $k,
        "from"        =>  getval("from",""),
        "search"      =>  $search,
        "offset"      =>  $offset,
        "order_by"    =>  $order_by,
        "sort"        =>  $sort,
        "archive"     =>  $archive,
        "thumbs"      =>  $thumbs_show);

         ?>
         &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
         <a class="prevLink icon-arrow-left" onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/preview.php", $defaultparams, isset($alt_previous)?array("alternative"=>$alt_previous):"");?>" title="<?php echo escape($lang["previousresult"]); ?>"></a>
         &nbsp;
         <a class="enterLink" href="<?php echo generateURL($baseurl_short . "pages/view.php", $defaultparams, array("from"=>""))."&".hook("viewextraurl");?>"><?php echo escape($lang["vieworiginalresource"]); ?></a>
         &nbsp;
         <a class="prevLink icon-arrow-right" onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/preview.php", $defaultparams, isset($alt_next)?array("alternative"=>$alt_next):"");?>" title="<?php echo escape($lang["nextresult"]); ?>"></a><?php
    }
else
    {
      # View All Results buttons will not be shown for a single resource available to external users
     if ($search != "" || ($search == "" && $k == "")) 
         {
         ?>
         &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
         <a class="prevLink icon-arrow-left" onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/preview.php?from=<?php echo urlencode(getval("from",""))?>&ref=<?php echo urlencode($ref) ?>&k=<?php echo urlencode($k)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&archive=<?php echo urlencode($archive)?>&go=previous&<?php echo hook("nextpreviousextraurl") ?>" title="<?php echo escape($lang["previousresult"]); ?>"></a>
          &nbsp;
         <a  class="upLink" onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/search.php?<?php if (strpos($search,"!")!==false) {?>search=<?php echo urlencode($search)?>&k=<?php echo urlencode($k)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?><?php } ?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&<?php echo hook("searchextraurl") ?>"><?php echo escape($lang["viewallresults"]); ?></a>
          &nbsp;
         <a class="nextLink icon-arrow-right" onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/preview.php?from=<?php echo urlencode(getval("from",""))?>&ref=<?php echo urlencode($ref) ?>&k=<?php echo urlencode($k)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&archive=<?php echo urlencode($archive)?>&go=next&<?php echo hook("nextpreviousextraurl") ?>" title="<?php echo escape($lang["nextresult"]); ?>"></a><?php
         }
    }
?>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?php

if (
    !hook("replacepreviewpager")
    && ($nextpage != -1 || $previouspage != -1) 
    && $nextpage != -0
    ) {
        $pagecount = ($access == RESOURCE_ACCESS_RESTRICTED) ? -2 : get_page_count($resource,$alternative);
        if ($pagecount!=null && $pagecount!=-2){
        ?>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo escape($lang['page']);?>: <select class="ListDropdown" style="width:auto" onChange="CentralSpaceLoad('<?php echo $baseurl_short?>pages/preview.php?ref=<?php echo urlencode($ref) ?>&alternative=<?php echo urlencode($alternative)?>&ext=<?php echo urlencode($ext)?>&k=<?php echo urlencode($k)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&archive=<?php echo urlencode($archive)?>&page='+this.value);"><?php 
        for ($n=1;$n<$pagecount+1;$n++)
            {
            if ($n<=$pdf_pages)
                {
                ?><option value="<?php echo $n?>" <?php if ($page==$n){?>selected<?php } ?>><?php echo $n?><?php
                }
            }
        if ($pagecount>$pdf_pages){?><option value="1">...<?php } ?>
        </select><?php
        }
}
?>


</p>
<?php } 

$urlparams = array(
    "ref"           => $ref,
    "alternative"   => $alternative,
    "ext"           => $ext,
    "k"             => $k,
    "search"        => $search,
    "offset"        => $offset,
    "order_by"      => $order_by,
    "sort"          => $sort,
    "archive"       => $archive
);
if($saved_thumbs_state=="show") {
    $urlparams["thumbs"] = "show";
}

if (!hook("previewimage")) { 
    if (!hook("previewimage2")) { 
?>
<table cellpadding="0" cellspacing="0">
<tr>
<td valign="middle">
    <?php 
    if ($resource['file_extension']!="jpg" && $previouspage!=-1 &&resource_download_allowed($ref,"scr",$resource["resource_type"])) {
        $urlparams["page"] = $previouspage;
    ?>
    <a onClick="return CentralSpaceLoad(this);" 
        href="<?php echo generateURL($baseurl_short . "pages/preview.php",$urlparams); ?>" class="PDFnav  pagePrev">&lt;</a>
    <?php } 
    elseif ($nextpage!=-1 && resource_download_allowed($ref,"scr",$resource["resource_type"]) || $use_watermark) {
    ?>
    <a href="#" class="PDFnav pagePrev">&nbsp;&nbsp;&nbsp;</a>
    <?php } 
    ?>
</td>
<?php 
$video_preview_file=get_resource_path($ref,true,"pre",false,$ffmpeg_preview_extension,-1,1,false,"",$alternative);
$block_video_playback = resource_has_access_denied_by_RT_size($resource['resource_type'], 'pre');
if (!file_exists($video_preview_file) || $block_video_playback) 
    {
    $video_preview_file=get_resource_path($ref,true,"",false,$ffmpeg_preview_extension,-1,1,false,"",$alternative);
    $block_video_playback = resource_has_access_denied_by_RT_size($resource['resource_type'], '');
    }
if (!(isset($resource['is_transcoding']) && $resource['is_transcoding']==1) && file_exists($video_preview_file) && !resource_has_access_denied_by_RT_size($resource['resource_type'], 'pre') && !$block_video_playback && (strpos(strtolower($video_preview_file),".".$ffmpeg_preview_extension)!==false))
    {
    # Include the video player if a video preview exists for this resource.
    $download_multisize=false;
    if(!hook("customflvplay")) // Note - legacy hook name, FLV files no longer used
        {
        include "video_player.php";
        }
    }
    elseif ($use_mp3_player && file_exists($mp3realpath) && hook("custommp3player")){
        // leave player to place image
        }   
    else
        {
        if(!hook('replacepreviewimage'))
            {
            ?>
            <td>
                <a onClick="return CentralSpaceLoad(this);" href="<?php echo getval("from","") == "search" ? $baseurl_short."pages/search.php?" : $baseurl_short."pages/view.php?ref=" . urlencode($ref) . "&"; ?>search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?><?php if($saved_thumbs_state=="show"){?>&thumbs=show<?php } ?>&archive=<?php echo urlencode($archive)?>&k=<?php echo urlencode($k)?>&<?php echo hook("viewextraurl") ?>">
                    <img id="PreviewImageLarge"
                         class="Picture"
                         alt="<?php echo escape(i18n_get_translated($resource['field'.$view_title_field] ?? ""));?>"
                         src="<?php echo $url; ?>"
                         <?php
                         if(count(canSeeAnnotationsFields()) > 0)
                            {
                            ?>
                            data-original="<?php echo "{$baseurl}/annotation/resource/{$ref}"; ?>"
                            <?php
                            }
                            ?>
                         alt="" />
                </a>
                <?php
                hook('afterpreviewimage');
                ?>
            </td>
            <?php
            } // end hook replacepreviewimage 
        }
        ?>

<td valign="middle">
    <?php 
    if ($nextpage!=-1 && resource_download_allowed($ref,"scr",$resource["resource_type"]) || $use_watermark) {
        $urlparams["page"] = $nextpage;
    ?>
    <a onClick="return CentralSpaceLoad(this);" 
        href="<?php echo generateURL($baseurl_short . "pages/preview.php",$urlparams); ?>" class="PDFnav pageNext">&gt;
    </a>
    <?php } 
    ?>
</td>
</tr></table>

<?php } // end hook previewimage2 ?>
<?php } // end hook previewimage

if(!IsModal())
    {
    ?>
    <script>
    // Don't need space for Simple Search box
    jQuery('#CentralSpaceContainer').width('94%');
    </script>
    <?php
    }
    
if ($show_resource_title_in_titlebar){
    $title =  escape(i18n_get_translated(get_data_by_field($ref,$view_title_field)));
    if (strlen($title) > 0){
        echo "<script language='javascript'>\n";
        echo "document.title = \"$applicationname - $title\";\n";
        echo "</script>";
    }
}

if(count(canSeeAnnotationsFields()) > 0 && isset($imagepre['height']) && isset($imagepre['width']))
    {
    ?>
    <!-- Annotorious -->
    <link type="text/css" rel="stylesheet" href="<?php echo generateURL("{$baseurl}/lib/annotorious_0.6.4/css/theme-dark/annotorious-dark.css", ['v' => $css_reload_key]); ?>" />
    <script src="<?php echo generateURL("{$baseurl}/lib/annotorious_0.6.4/annotorious.min.js", ['v' => $css_reload_key]); ?>"></script>

    <!-- Annotorious plugin(s) -->
    <link type="text/css" rel="stylesheet" href="<?php echo generateURL("{$baseurl}/lib/annotorious_0.6.4/plugins/RSTagging/rs_tagging.css", ['v' => $css_reload_key]); ?>" />
    <script src="<?php echo generateURL("{$baseurl}/lib/annotorious_0.6.4/plugins/RSTagging/rs_tagging.js", ['v' => $css_reload_key]); ?>"></script>
    <?php
    if ($facial_recognition_active) {
        ?>
        <script src="<?php echo $baseurl_short; ?>lib/annotorious_0.6.4/plugins/RSFaceRecognition/rs_facial_recognition.js"></script>
        <?php
    } ?>
    <!-- End of Annotorious -->

    <script>
    var rs_tagging_plugin_added = false;

    function toggleAnnotationsOption(element)
        {
        var option             = jQuery(element);
        var preview_image      = jQuery('#PreviewImageLarge');
        var preview_image_link = preview_image.parent();
        var img_copy_id        = 'previewimagecopy';
        var img_src            = preview_image.attr('src');

        // Setup Annotorious (has to be done only once)
        if(!rs_tagging_plugin_added)
            {
            anno.addPlugin('RSTagging',
                {
                annotations_endpoint: '<?php echo $baseurl; ?>/pages/ajax/annotations.php',
                nodes_endpoint      : '<?php echo $baseurl; ?>/pages/ajax/get_nodes.php',
                resource            : <?php echo (int) $ref; ?>,
                read_only           : false,
                lang: <?php echo json_encode(get_annotorious_lang($lang)); ?>,
                rs_config: <?php echo json_encode(get_annotorious_resourcespace_config()); ?>,
                // First page of a document is exactly the same as the preview
                page                : <?php echo 1 >= $page ? 0 : (int) $page; ?>,
                // We pass CSRF token identifier separately in order to know what to get in the Annotorious plugin file
                csrf_identifier: '<?php echo $CSRF_token_identifier; ?>',
                <?php echo generateAjaxToken('RSTagging'); ?>
                });

    <?php
    if($facial_recognition)
        {
        ?>
            anno.addPlugin('RSFaceRecognition',
                {
                facial_recognition_endpoint: '<?php echo $baseurl; ?>/pages/ajax/facial_recognition.php',
                resource                   : <?php echo (int) $ref; ?>,
                // We pass CSRF token identifier separately in order to know what to get in the Annotorious plugin file
                fr_csrf_identifier: '<?php echo $CSRF_token_identifier; ?>',
                <?php echo generateAjaxToken('RSFaceRecognition'); ?>
                });
        <?php
        }
        ?>

            rs_tagging_plugin_added = true;

            // We have to wait for initialisation process to finish as this does ajax calls
            // in order to set itself up
            setTimeout(function ()
                {
                toggleAnnotationsOption(element);
                }, 
                1000);

            return false;
            }

        // Feature enabled? Then disable it.
        if(option.hasClass('Enabled'))
            {
            anno.destroy(img_src);

            // Remove the copy and show the linked image again
            jQuery('#' + img_copy_id).remove();
            preview_image_link.show();

            toggleMode(element);

            return false;
            }

        // Enable feature
        // Hide the linked image for now and use a copy of it to annotate
        var preview_image_copy = preview_image.clone(true);
        preview_image_copy.prop('id', img_copy_id);
        preview_image_copy.prop('src', img_src);

        // Set the width and height of the image otherwise if the source of the file
        // is fetched from download.php, Annotorious will not be able to determine its
        // size
        preview_image_copy.width(<?php echo (int) $imagepre['width'] ?? 0; ?>);
        preview_image_copy.height(<?php echo (int) $imagepre['height'] ?? 0; ?>);

        preview_image_copy.prependTo(preview_image_link.parent());
        preview_image_link.hide();

        anno.makeAnnotatable(document.getElementById(img_copy_id));

        toggleMode(element);

        return false;
        }


    function toggleMode(element)
        {
        jQuery(element).toggleClass('Enabled');
        }
    </script>
    <?php
    }

include "../include/footer.php";