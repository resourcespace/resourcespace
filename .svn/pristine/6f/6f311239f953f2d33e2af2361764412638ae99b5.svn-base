<?php

function HookEmbedslideshowCollection_shareExtra_share_options()
{
    global $ref,$lang,$baseurl,$embedslideshow_min_size,$embedslideshow_max_size, $embedslideshow_dynamic_size, $anonymous_login, $username;

    if (isset($anonymous_login) && $username == $anonymous_login) {
        return false;
    }
    ?>
    <li>
        <i aria-hidden="true" class="icon-presentation"></i>&nbsp;
        <a onClick="return CentralSpaceLoad(this,true);" href="collection_share.php?ref=<?php echo $ref?>&embedslideshow=true">
            <?php echo escape($lang["embedslideshow"]); ?>
        </a>
    </li>
    <?php

    if (!is_int_loose($ref)) {
        return false;
    }

    if (getval("embedslideshow", "") != "") {
        ?>
        <p><?php echo escape($lang["embedslideshow_action_description"]); ?></p>
                
        <div class="Question">      
            <label><?php echo escape($lang["embedslideshow_size"]); ?></label>
            <select name="size" class="stdwidth">
                <?php
                $sizes = get_all_image_sizes(true);
                foreach ($sizes as $size) {
                    if ($size["width"] <= $embedslideshow_max_size && $size["width"] >= $embedslideshow_min_size) { # Include only sensible sizes
                        # Slideshow size is max of height/width so that all images will fit within the slideshow area (for default installs height/width is the same anyway though)
                        ?>
                        <option value="<?php echo $size["id"]; ?>" <?php echo ($size["id"] == getval("size", "pre")) ? "selected" : ''; ?>>
                            <?php echo str_replace(array("%name", "%pixels"), array($size["name"], max($size["width"], $size["height"])), $lang["sizename_pixels"]) ?>
                        </option>
                        <?php
                    }
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>      

        <div class="Question">      
            <label><?php echo escape($lang["embedslideshow_transitiontime"]); ?></label>
            <select name="transition" class="stdwidth">
                <option value="0"><?php echo escape($lang["embedslideshow_notransition"]); ?></option>
                <?php for ($n = 1; $n < 20; $n++) { ?>
                    <option value="<?php echo $n ?>" <?php echo ($n == getval("transition", "4")) ? "selected" : ''; ?>>
                        <?php echo str_replace("?", $n, $lang["embedslideshow_seconds"]) ?>
                    </option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">      
            <label><?php echo escape($lang["embedslideshow_maximise_option"]); ?></label>
            <input type="checkbox" value="1" name="maximise" <?php echo (isset($_POST["maximise"]) && $_POST["maximise"] == "1") ? "checked" : ''; ?>>
            <div class="clearerleft"></div>
        </div>

        <?php
        global $embedslideshow_textfield;
        if ($embedslideshow_textfield) { ?>
            <div class="Question">      
                <label><?php echo escape($lang['embedslideshow_textfield']); ?></label>
                <input type="checkbox" value="1" name="showtext" <?php echo (isset($_POST["showtext"]) && $_POST["showtext"] == "1") ? "checked" : ''; ?>>
                <div class="clearerleft"></div>
            </div>
            <?php
        } ?>

        <div class="QuestionSubmit" style="padding-top:0;margin-top:0;">
            <input name="generateslideshow" type="submit" value="<?php echo escape($lang["generateslideshowhtml"]); ?>" />
        </div>
        <?php
    }

    if (getval("generateslideshow", "") != "") {
        # Create a new external access key
        $key = generate_collection_access_key($ref, 0, $lang["slideshow"], 1, '');

        # Find image size
        $sizes = get_all_image_sizes(true);
        foreach ($sizes as $size) {
            if ($size["id"] == getval("size", "")) {
                break;
            }
        }

        # Slideshow size is max of height/width so that all images will fit within the slideshow area (for default installs height/width is the same anyway though)
        $width = max($size["width"], $size["height"]);
        $height = $width;

        $width_w_border = $width + 8; //expands width to display border
        $height += 48; // Enough space for controls

        # Create embed code
        $embed = "";

        if ($width < 850 && getval("maximise", "") == 1) {
            # Maxmimise function only necessary for < screen size slideshows
            $minimise_src_url = generateURL(
                $baseurl . "/plugins/embedslideshow/pages/viewer.php",
                ["ref"          => $ref,
                "k"             => $key,
                "size"          => getval("size", ""),
                "transition"    => getval("transition", ""),
                "width"         => $width,
                "height"        => $height,
                "showtext"      => getval("showtext", "0")]
            );

            $maximise_src_url = generateURL(
                $baseurl . "/plugins/embedslideshow/pages/viewer.php",
                ["ref"          => $ref,
                "k"             => $key,
                "size"          => "scr",
                "transition"    => getval("transition", ""),
                "showtext"      => getval("showtext", "0")]
            );

            $embed .= "
            <div id=\"embedslideshow_back_$ref\" style=\"display:none;position:absolute;top:0;left:0;width:100%;height:100%;min-height: 100%;background-color:#000;opacity: .5;filter: alpha(opacity=50); z-index: 100\"></div>
            <div id=\"embedslideshow_minimise_$ref\" style=\"position:absolute;top:5px;left:20px;background-color:white;border:1px solid black;display:none;color:black;z-index:1000;\"><a style=\"color:#000\" href=\"#\" onClick=\"
            var ed=document.getElementById('embedslideshow_$ref');
            ed.width='$width_w_border';
            ed.height='$height';
            ed.style.position='relative';
            ed.style.top='0';
            ed.style.left='0';
            ed.src='$minimise_src_url';
            document.getElementById('embedslideshow_minimise_$ref').style.display='none';
            document.getElementById('embedslideshow_maximise_$ref').style.display='block';	
            document.getElementById('embedslideshow_back_$ref').style.display='none';
            \">" . $lang["embedslideshow_minimise"] . "</a></div>
            <div id=\"embedslideshow_maximise_$ref\" class=\"embedslideshow_maximise\"><a href=\"#\" onClick=\"
            var ed=document.getElementById('embedslideshow_$ref');
            ed.width='100%';
            ed.height='100%';
            ed.style.position='absolute';
            ed.style.top='20px';
            ed.style.left='20px';
            ed.src='$maximise_src_url&height=' + ((window.innerHeight)-40) + '&width=' + ((window.innerWidth)-40);
            ed.style.zIndex=999;
            document.getElementById('embedslideshow_minimise_$ref').style.display='block';
            document.getElementById('embedslideshow_maximise_$ref').style.display='none';	
            document.getElementById('embedslideshow_back_$ref').style.display='block';	
            \">" . $lang["embedslideshow_maximise"] . "</a></div>";
        }

        $iframe_src_url = generateURL(
            $baseurl . "/plugins/embedslideshow/pages/viewer.php",
            ["ref"          => $ref,
            "k"             => $key,
            "size"          => getval("size", ""),
            "transition"    => getval("transition", ""),
            "width"         => $width,
            "height"        => $height,
            "showtext"      => getval("showtext", "0")]
        );

        $embed .= "<iframe id=\"embedslideshow_$ref\" allowtransparency=\"true\" cursor: pointer;\" width=\"$width_w_border\" height=\"$height\" src=\"$iframe_src_url\" frameborder=0 scrolling=no>Your browser does not support frames.</iframe>";

        # Compress embed HTML.
        $embed = str_replace("\n", " ", $embed);
        $embed = str_replace("\t", " ", $embed);

        while (strpos($embed, "  ") !== false) {
            $embed = str_replace("  ", " ", $embed);
        }

        global $embedslideshow_dynamic_size;

        if ($embedslideshow_dynamic_size) {
            $embed .= sprintf(
                "
                <script>
                jQuery(document).ready(function() {
                    function change_src_size()
                        {
                        var embedded_iframe = document.getElementById('embedslideshow_%s');
                        var embedded_src    = embedded_iframe.src;

                        // Set resource preview size to iFrame source size
                        embedded_src = ReplaceUrlParameter(embedded_src, 'width', embedded_iframe.width);
                        embedded_src = ReplaceUrlParameter(embedded_src, 'height', embedded_iframe.height);
                        // Let ResourceSpace know this should be dynamic
                        embedded_src += '&dynamic=true';
                        embedded_iframe.setAttribute('src', embedded_src);

                        return true;
                        }
                    change_src_size();
                });
                </script>
                ",
                $ref
            );
        }
        ?>

        <div class="Question">      
            <label><?php echo escape($lang["slideshowhtml"]); ?></label>
            <textarea style="width:535px;height:120px;"><?php echo escape($embed); ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">      
            <label><?php echo escape($lang["embedslideshow_directlink"]); ?></label>
            <?php
            $embedslideshow_src_url = generateURL(
                "$baseurl/plugins/embedslideshow/pages/viewer.php",
                ["ref"          => $ref,
                "k"             => $key,
                "size"          => escape(getval("size", "")),
                "transition"    => escape(getval("transition", "")),
                "width"         => $width,
                "height"        => $height,
                "showtext"      => escape(getval("showtext", "0"))
                ]
            );?>
            <div class="Fixed">
                <a href="<?php echo $embedslideshow_src_url?>" target="_blank"><?php echo escape($lang["embedslideshow_directlinkopen"]); ?></a>
            </div>
            <div class="clearerleft"></div>
        </div>
                
        <div class="Question">      
            <label><?php echo escape($lang["slideshowpreview"]); ?></label>
            <div class="Fixed"><?php echo $embed ?></div>
            <div class="clearerleft"></div>
        </div>
        <?php
    }
    return true;
}

?>
