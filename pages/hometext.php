<?php

include "../include/boot.php";
include "../include/authenticate.php";

include "../include/header.php";

global $language, $usergroup;

?>
<div class="BasicsBox BasicsBoxHomeText"> 
    <?php
    $onClick = "return CentralSpaceLoad('{$baseurl}/pages/home.php', true)";

    if (getval("modal", "") != "") {
        $onClick = 'ModalClose();';
    }

    ?>
    <div class="BasicsBoxLeft">
        <h1><b>
            <?php # Include version number
            echo strip_tags_and_attributes(str_replace("[ver]", str_replace("SVN", "", $productversion), get_site_text('home', 'welcometitle', $language, $usergroup)));
            ?>
        </b></h1>
        <p><?php echo strip_tags_and_attributes(get_site_text('home', 'welcometext', $language, $usergroup), ['a'], ['href']); ?></p>
    </div>
    <div class="BasicsBoxRight" style="margin-top:0px;">
        <div class="FloatingPreviewContainer">
            <a class="HomeTextClose" href="#" onclick="<?php echo $onClick; ?>">
                <span class="wrap-icon" aria-hidden="true">
                    <i class="icon-x default-icon-size" aria-hidden="true"></i>
                </span>
            </a>
        </div>
    </div>
</div>

<?php
include "../include/footer.php";
?>
