<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("i")) {
    exit("Permission denied.");
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["managearchiveresources"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["managearchiveresources"],
            'help'  => "resourceadmin/archives"
        )
    );

    renderBreadcrumbs($links_trail);
    ?>
  
    <p><?php echo escape(text("introtext")); ?></p>

    <div class="VerticalNav">
        <ul>
            <li>
                <a href="<?php echo $baseurl_short?>pages/edit.php?ref=-<?php echo (int) $userref?>&single=true&status=2" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET . escape($lang["newarchiveresource"]); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $baseurl_short?>pages/search_advanced.php?archive=2" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET . escape($lang["searcharchivedresources"]); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!archivepending")?>" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET . escape($lang["viewresourcespendingarchive"]); ?>
                </a>
            </li>
        </ul>
    </div>
    
  </div>

<?php
include "../../include/footer.php";
?>
