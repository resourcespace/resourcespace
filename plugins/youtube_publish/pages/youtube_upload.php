<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";
include "../include/youtube_functions.php";

global $baseurl, $baseurl_short,$view_title_field, $youtube_publish_url_field, $youtube_publish_allow_multiple;

// Limit on title length with YT
$title_character_limit = 100;

$deletetokens = getval("deletetokens", false);
if ($deletetokens) {
    ps_query("UPDATE user SET youtube_access_token = '', youtube_refresh_token = '', youtube_username = '' WHERE ref = ?", array("i", $userref));
}

$ref = getval("resource", "");
if ($ref == "") {
    $ref = getval("state", "");
}

# Load access level
$access = get_resource_access($ref);

# check permissions (error message is not pretty but they shouldn't ever arrive at this page unless entering a URL manually)
if ($access != 0) {
    exit($lang["youtube_publish_accessdenied"]);
}

# fetch the current search (for finding similar matches)
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");
if (strpos($search, "!") !== false) {
    $restypes = "";
}
$archive = getval("archive", 0, true);
$video_status = getval("video_status", 'unlisted');
$video_category = getval("video_category", ""); // This is the uploading video category. There are only certain categories that are accepted.
if ($youtube_publish_url_field > 0) {
    $youtube_url = get_data_by_field($ref, $youtube_publish_url_field);
}
$youtube_error = false;

$default_sort_direction = "DESC";
if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}
$sort = getval("sort", $default_sort_direction);

if ($youtube_publish_client_id == "" || $youtube_publish_client_secret == "") {
    exit($lang["youtube_publish_notconfigured"] . " <a href=\"$baseurl/plugins/youtube_publish/pages/setup.php\">$baseurl/plugins/youtube_publish/pages/setup.php</a>");
}

# next / previous resource browsing

$go = getval("go", "");
if ($go != "") {
    # Re-run the search and locate the next and previous records.
    $result = do_search($search, $restypes, $order_by, $archive, 240 + $offset + 1);
    if (is_array($result)) {
        # Locate this resource
        $pos = -1;
        for ($n = 0; $n < count($result); $n++) {
            if ($result[$n]["ref"] == $ref) {
                $pos = $n;
            }
        }
        if ($pos != -1) {
            if (($go == "previous") && ($pos > 0)) {
                $ref = $result[$pos - 1]["ref"];
            }
            if (($go == "next") && ($pos < ($n - 1))) {
                $ref = $result[$pos + 1]["ref"];
                if (($pos + 1) >= ($offset + 72)) {
                    $offset = $pos + 1;
                }
            } # move to next page if we've advanced far enough
        } else {
            ?>
            <script type="text/javascript">
            alert("<?php echo escape($lang["resourcenotinresults"]); ?>");
            </script>
            <?php
        }
    }
}

global $client,$youtube;
list ($youtube_object, $youtubemessage) = youtube_publish_initialize();

$categories = "";

if (!$youtube_object) {
    $youtube_error = $lang["youtube_access_failed"] . $youtubemessage;
} else {
    $categories = youtube_upload_get_categories();
    if (!is_array($categories)) {
        $youtube_error = $lang["youtube_publish_category_error"] . $categories;
    }
}

$youtube_username = ps_value("SELECT youtube_username AS value FROM user WHERE ref = ?", array("i", $userref), "");

if ($youtube_object && isset($_POST['video_title']) && isset($_POST['video_description'])) {
    $video_title = mb_substr(getval("video_title", ""), 0, $title_character_limit);
    $video_description = getval("video_description", "");
    $video_keywords = getval("video_keywords", "");
    $filename = get_data_by_field($ref, $filename_field);
    //Set values so that upload can be retried if for example the access token has expired and needed to be refreshed

    list ($uploadsuccess, $youtube_new_url) = upload_video();
    if (!$uploadsuccess) {
            $youtube_error = $lang["youtube_publish_failedupload_error"] . ": " . $youtube_new_url;
    } else {
        if ($youtube_publish_url_field > 0) {
            if ($youtube_publish_allow_multiple) {
                if ($youtube_publish_add_anchor) {
                    $save_url = $youtube_url . "<br><a href=\"" . $youtube_new_url . "\" target=\"_blank\">" . $youtube_new_url . "</a>";
                } else {
                    $save_url = $youtube_url . "," . $youtube_new_url;
                }

                    update_field($ref, $youtube_publish_url_field, $save_url);
                    $youtube_old_url = $youtube_url;
                    $youtube_url = $youtube_url . "<br><a href=\"" . $youtube_new_url . "\" target=\"_blank\">" . $youtube_new_url . "</a>";
            } else {
                if ($youtube_publish_add_anchor) {
                    $save_url = "<a href=\"" . $youtube_new_url . "\" target=\"_blank\">" . $youtube_new_url . "</a>";
                } else {
                                            $save_url = $youtube_new_url;
                }
                                        update_field($ref, $youtube_publish_url_field, $save_url);
                                        $youtube_old_url = $youtube_url;
                                        $youtube_url = $youtube_new_url;
            }
        }
            resource_log($ref, 'e', $youtube_publish_url_field ? $youtube_publish_url_field : 0, $lang["youtube_publish_log_share"], $fromvalue = $youtube_old_url, $tovalue = $save_url);
    }
}


$title = get_data_by_field($ref, $youtube_publish_title_field);
$description = "";
foreach ($youtube_publish_descriptionfields as $youtube_publish_descriptionfield) {
    $resource_description = get_data_by_field($ref, $youtube_publish_descriptionfield);
    if ($description != '') {
        $description .= "\r\n";
    }
    $description .= $resource_description;
}


$video_keywords = "";
foreach ($youtube_publish_keywords_fields as $youtube_publish_keywords_field) {
    $resource_keywords = get_data_by_field($ref, $youtube_publish_keywords_field);
    $video_keywords .= $resource_keywords;
}


include "../../../include/header.php";
?>

<script language="JavaScript">
function confirmSubmit()
{
var agree=confirm("<?php echo escape($lang["youtube_publish_legal_warning"]); ?>");
if (agree)
return true ;
else
return false ;
}

</script>
<div class="BasicsBox" >

    <a href="<?php echo $baseurl_short?>pages/view.php?ref=<?php echo $ref?>&search=<?php echo urlencode($search)?>&offset=<?php echo $offset?>&order_by=<?php echo $order_by?>&sort=<?php echo $sort?>&archive=<?php echo $archive?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?></a></p>


    <div class="backtoresults">
        <a class="prevLink" href="<?php echo $baseurl_short?>plugins/youtube_publish/pages/youtube_upload.php?ref=<?php echo $ref?>&search=<?php echo urlencode($search)?>&offset=<?php echo $offset?>&order_by=<?php echo $order_by?>&sort=<?php echo $sort?>&archive=<?php echo $archive?>&go=previous&<?php echo hook("nextpreviousextraurl") ?>" onClick="return CentralSpaceLoad(this);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["previousresult"]); ?></a>
        <?php
        hook("viewallresults");
        ?>
        |
        <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode($search)?>&offset=<?php echo $offset?>&order_by=<?php echo $order_by?>&sort=<?php echo $sort?>&archive=<?php echo $archive?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["viewallresults"]); ?></a>
        |
        <a class="nextLink" href="<?php echo $baseurl_short?>plugins/youtube_publish/pages/youtube_upload.php?ref=<?php echo $ref?>&search=<?php echo urlencode($search)?>&offset=<?php echo $offset?>&order_by=<?php echo escape($order_by); ?>&sort=<?php echo $sort?>&archive=<?php echo $archive?>&go=next&<?php echo hook("nextpreviousextraurl") ?>" onClick="return CentralSpaceLoad(this);"><?php echo escape($lang["nextresult"]) . "&nbsp;" . LINK_CARET ?></a>
    </div>
</div>
<div class="BasicsBox" >

<?php


if (isset($youtube_new_url) && $youtube_new_url) {
            echo "<div><p><b>" . $lang["youtube_publish_success"]   . "</b></p></div>";
} else {
    if ($youtube_error) {
        echo "<div class=\"FormIncorrect\"><p>" . $youtube_error  . "</p></div>";
        echo "</div>";
        include "../../../include/footer.php";
        exit();
    }
}
?>

<div class="Question">

<p> 
<?php echo escape($lang["youtube_publish_existingurl"]) . "<p>";
if (isset($youtube_url) && $youtube_url != "") {
    echo $youtube_url;
    if (!$youtube_publish_allow_multiple && !isset($youtube_new_url)) {
        echo "</p><div class=\"FormIncorrect\"><p><br>" . $lang["youtube_publish_alreadypublished"] . "</p></div>";
        echo "</div>";
        include "../../../include/footer.php";
        exit();
    }
} else {
    echo escape($lang["youtube_publish_notuploaded"]);
}
echo "</p>";
?>

</div>

<?php if ($youtube_username != '') { ?>  
    <div class="Question">
        <p><?php echo strip_tags_and_attributes(str_replace("%youtube_username%", "<strong>" . $youtube_username . "</strong>", $lang["youtube_publishloggedinas"])); ?></p>
        <p>
            <a href="<?php echo $baseurl . '/plugins/youtube_publish/pages/youtube_upload.php?resource=' . (int) $ref . '&deletetokens=true'; ?>">&gt;
                <?php echo escape($lang["youtube_publish_change_login"]); ?>
            </a>
        </p>
    </div>
<?php } ?>

<form action="<?php echo $baseurl ?>/plugins/youtube_publish/pages/youtube_upload.php?resource=<?php echo (int) $ref; ?>" method="post">
    <?php generateFormToken("youtube_upload"); ?>
    <div class="Question" >
        <label for="video_title"><?php echo escape($lang["youtube_publish_video_title"]); ?></label>
        <input type="text" class="stdwidth" name="video_title" value="<?php echo mb_substr(escape($title), 0, $title_character_limit); ?>" maxlength="<?php echo escape($title_character_limit); ?>"/>
        <br>
        <label for="video_description"><?php echo escape($lang["youtube_publish_video_description"]); ?></label>
        <textarea class="stdwidth" rows="6" columns="50" id="video-description" name="video_description"><?php echo strip_tags($description); ?></textarea>
        <br>
        <label for="video_keywords"><?php echo escape($lang["youtube_publish_video_tags"]); ?></label>
        <textarea class="stdwidth" rows="6" columns="50" id="video_keywords" name="video_keywords"><?php echo escape($video_keywords); ?></textarea>
        <br>
    </div>  
    <div class="Question" >
    
        <label for="video_status"><?php echo escape($lang["youtube_publish_access"]); ?></label>
        <select name="video_status">
        <option 
            value="public"
            <?php echo ($video_status == "public" ? "selected" : "");?>
            ><?php echo escape($lang["youtube_publish_public"]) . "&nbsp;&nbsp;" ?>
        </option>
        <option 
            value="private"
            <?php echo ($video_status == "private" ? "selected" : "");?>
            ><?php echo escape($lang["youtube_publish_private"]) . "&nbsp;&nbsp;" ?>
        </option>
        <option
            value="unlisted"
            <?php echo ($video_status == "unlisted" ? "selected" : "");?>
            ><?php echo escape($lang["youtube_publish_unlisted"]) . "&nbsp;&nbsp;" ?>
        </option>

        </select>
        </p>
    </div>  
    
    <div class="Question" >
    
        <label for="video_category"><?php echo escape($lang["youtube_publish_category"]); ?></label>
        <select name="video_category">
        <?php
        if (is_array($categories)) {
            foreach ($categories as $categoryid => $categoryname) {
                echo "<option value='" . $categoryid . "' " . (($video_category == $categoryid) ? "selected" : "") . " >" . $categoryname . "</option>";
            }
        }
        ?>
        </select>
        </p>
    </div>  
    
    <input type="submit" value="<?php echo escape($lang["youtube_publish_button_text"]); ?>" onClick="return confirmSubmit()"/>
    
    
</form> 

</div>  
<?php





include "../../../include/footer.php";

?>
