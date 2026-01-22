<?php
function HookClipViewCustompanels()
{
    global $lang,$ref,$baseurl,$clip_service_url,$mysql_db;
    if (checkperm("clip-v")) {
        return false;
    }

    $vectors = ps_value("select count(*) value from resource_clip_vector where resource=?", ["i",$ref], 0);
    if ($vectors == 0) {
        return false;
    } // No vectors yet.

    $search_url = generateURL("{$baseurl}/pages/search.php", array("search" => "!clipsimilar{$ref}"));
    $duplicate_url = generateURL("{$baseurl}/pages/search.php", array("search" => "!clipduplicate{$ref}"));
    ?>
    <div class="RecordBox">
        <div class="RecordPanel">
            <div class="Title"><?php echo escape($lang["clip-ai-smart-search"]); ?></div>
                <p>
                    <a href="<?php echo $search_url ?>" onClick="return CentralSpaceLoad(this,true);">
                    <i class="icon-search"></i>&nbsp;<?php echo escape($lang["clip-visually-similar-images"]); ?>
                    </a>
                    &nbsp;&nbsp;&nbsp;
                    <a href="<?php echo $duplicate_url ?>" onClick="return CentralSpaceLoad(this,true);">
                    <i class="icon-search"></i>&nbsp;<?php echo escape($lang["clip-duplicate-images"]); ?>
                    </a>
                </p>    
        </div>
    </div>
    <?php
    return false; # Allow further custom panels
}
