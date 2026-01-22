<?php
include "../include/boot.php";
include "../include/authenticate.php";
include "../include/header.php";

$ref = getval("ref", "", true);
$resource = get_resource_data($ref);
# fetch the current search (for finding simlar matches)
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive = getval("archive", 0, true);
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
?>

<div class="BasicsBox"> 
    <?php if (is_array($resource)) { ?>
        <p>
            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/pages/view.php?ref=<?php echo urlencode($ref)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php echo urlencode($archive)?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"])?>
            </a>
        </p>

        <h1><?php echo escape($lang["userratingstatsforresource"] . " " . $ref);?></h1>
        
        <table class="InfoTable">
            <tr>
                <td><b><?php echo escape($lang["user"])?></b></td>
                <td><b><?php echo escape($lang["rating"])?></b></td>
            </tr>
            <?php
            $users = get_users(0, "", "u.username", true);
            $ratings = ps_query("select " . columns_in("user_rating") . " from user_rating where ref = ?", array("i", $ref));

            for ($n = 0; $n < count($ratings); $n++) {
                for ($x = 0; $x < count($users); $x++) {
                    if ($ratings[$n]['user'] == $users[$x]['ref']) {
                        $username = $users[$x]['fullname'] . " (" . $users[$x]['username'] . ")";
                    }
                }
                ?>
                <tr>
                    <td><?php echo escape($username)?></td>
                    <td>
                        <div class="RatingStars" >
                            <?php for ($y = 0; $y < $ratings[$n]['rating']; $y++) { ?>
                                <span class="IconUserRatingStar" style="float:left;display:block;"></span>
                            <?php } ?>
                        </div>
                        <br />
                    </td>
                </tr>
            <?php } ?>

            <tr>
                <td><b><?php echo escape($lang['average'])?></b></td>
                <td>
                    <?php for ($y = 0; $y < $resource['user_rating']; $y++) { ?>
                        <span class="IconUserRatingStar" style="float:left;display:block;"></span>
                    <?php } ?>
                    <br />
                </td>
            </tr>
        </table>
    </div>
        <?php
    } else {
        exit($lang['resourcenotfound']);
    }

    include "../include/footer.php";
    ?>
