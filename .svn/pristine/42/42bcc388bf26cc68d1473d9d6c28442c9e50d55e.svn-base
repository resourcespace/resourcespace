<?php
include "../include/boot.php";
include "../include/authenticate.php";

$section = getval("section", "");
$page = getval("page", "");

include "../include/header.php";
?>

<div class="BasicsBox"> 
    <?php
    $onClick = 'return CentralSpaceLoad(this, true);';

    if ($help_modal) {
        $onClick = 'return ModalLoad(this, true);';
    }

    if ($section == '') {
        ?>
        <div class="HelpHeader">
            <?php if ($help_modal) { ?>
                <div class="backtoresults">
                    <a href="#" onclick="ModalClose();" class="closeLink icon-x" title="<?php echo escape($lang["close"]); ?>"></a>
                </div>
            <?php } ?>
            <h1><?php echo escape($lang['helpandadvice']); ?></h1>
        </div>

        <p>
            <?php
            if ($page != "") {
                // Build link for the specified KnowlegeBase page
                echo '<iframe src="https://www.resourcespace.com/knowledge-base/' . escape($page) . '?from_rs=true" style="width:1235px;height:600px;border:none;margin:-20px;" id="knowledge_base" />';
            } else {
                echo strip_tags_and_attributes(text("introtext"), ['iframe', 'a'], ['src', 'href']);
            }
            ?>
        </p>

        <div class="VerticalNav">
            <ul>
                <?php
                $sections = get_section_list("help");
                for ($n = 0; $n < count($sections); $n++) {
                    ?>
                    <li>
                        <a
                            onclick="<?php echo $onClick; ?>"
                            href="<?php echo $baseurl_short?>pages/help.php?section=<?php echo urlencode($sections[$n]); ?>"
                        >
                            <?php echo escape($sections[$n]); ?>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div>
        
        <?php
    } else {
        ?>
        <h1><?php echo escape($section); ?></h1>
        <p><?php echo strip_tags_and_attributes(text($section), ['a'], ['href']); ?></p>
        <p>
            <a onclick="<?php echo $onClick; ?>" href="<?php echo $baseurl_short?>pages/help.php">
                <?php echo LINK_CARET_BACK . escape($lang["backtohelphome"]); ?>
            </a>
        </p>
        <?php
    }
    ?>
</div>

<?php
include "../include/footer.php";
?>
