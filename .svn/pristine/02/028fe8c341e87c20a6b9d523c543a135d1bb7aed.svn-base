<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

include "../../include/header.php";

$find = getval("find", "");
$order_by = getval("orderby", "width");

// Construct the search query.
$sql = "SELECT ref, id, internal, width, height, name FROM preview_size";
$params = array();
if ($find != "") {
    $sql .= " where id like ? or name like ? or width like ? or height like ?";
    $params[] = "s";
    $params[] = "%{$find}%";
    $params[] = "s";
    $params[] = "%{$find}%";
    $params[] = "s";
    $params[] = "%{$find}%";
    $params[] = "s";
    $params[] = "%{$find}%";
}
$order_by = in_array($order_by, array("width","height","id","name")) ? $order_by : "width"; // Force $order_by to something we expect so it's SQL safe.
$sql .= " ORDER BY {$order_by}";

$sizes = ps_query($sql, $params);
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["page-title_size_management"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["page-title_size_management"]
        )
    );

    renderBreadcrumbs($links_trail);
    ?>
    <p>
        <?php
        echo escape($lang['page-subtitle_size_management']);
        render_help_link('systemadmin/manage_sizes');
        ?>
    </p>

    <?php
    function addColumnHeader($orderName, $labelKey)
    {
        global $baseurl, $order_by, $find, $lang;

        if ($order_by == $orderName) {
            $image = '<span class="ASC"></span>';
        } elseif ($order_by == $orderName . ' desc') {
            $image = '<span class="DESC"></span>';
        } else {
            $image = '';
        }
        ?>
        <th>
            <a
                href="<?php echo $baseurl ?>/pages/admin/admin_size_management.php?<?php
                    echo ($find != "") ? "&find=" . escape($find) : ''; ?>&orderby=<?php
                    echo $orderName . ($order_by == $orderName ? '+desc' : ''); ?>"
                onClick="return CentralSpaceLoad(this);">
                <?php echo escape($lang[$labelKey]) . $image ?>
            </a>
        </th>
        <?php
    }
    ?>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <?php addColumnHeader("id", "property-id"); ?>
                <?php addColumnHeader("name", "property-name"); ?>
                <?php addColumnHeader("width", "property-width"); ?>
                <?php addColumnHeader("height", "property-height"); ?>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php
            foreach ($sizes as $size) {
                if ($size['internal'] == '1' && !$internal_preview_sizes_editable) {
                    $edit_url = "";
                } else {
                    $edit_url = "{$baseurl_short}pages/admin/admin_size_management_edit.php?ref={$size["ref"]}" . ($find == "" ? "" : "&find={$find}") . ($order_by == "name" ? "" : "&orderby={$order_by}");
                }
                ?>
                <tr>
                    <td>
                        <?php if ($edit_url != "") { ?>
                            <a href="<?php echo escape($edit_url); ?>" onClick="return CentralSpaceLoad(this,true);">
                        <?php }

                        echo escape($size["id"]);
                        
                        if ($edit_url != "") { ?>
                            </a>
                            <?php
                        } ?>
                    </td>
                    <td>
                        <?php if ($edit_url != "") { ?>
                            <a href="<?php echo escape($edit_url); ?>" onClick="return CentralSpaceLoad(this,true);">
                        <?php }
                        
                        echo escape($size["name"]);
                        
                        if ($edit_url != "") { ?>
                            </a>
                            <?php
                        } ?>
                    </td>
                    <td>
                        <?php if ($edit_url != "") { ?>
                            <a href="<?php echo escape($edit_url); ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php
                        }
                        
                        echo escape($size["width"]);
                        if ($edit_url != "") { ?>
                            </a>
                            <?php
                        } ?>
                    </td>
                    <td>
                        <?php if ($edit_url != "") { ?>
                            <a href="<?php echo escape($edit_url); ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php
                        }
                        
                        echo escape($size["height"]);
                        
                        if ($edit_url != "") { ?>
                            </a>
                            <?php
                        } ?>
                    </td>
                    <td>
                        <?php if ($edit_url != "") { ?>
                            <div class="ListTools">
                                <a href="<?php echo escape($edit_url); ?>" onClick="return CentralSpaceLoad(this,true);">
                                    <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                </a>
                            </div>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
</div><!-- end of BasicsBox -->

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/admin/admin_size_management.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_size_management"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["property-search_filter"]); ?></label>
            <input name="find" type="text" class="medwidth" value="<?php echo escape($find); ?>">
            <input name="save" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>">
            <div class="clearerleft"></div>
        </div>

        <?php if ($find != "") { ?>
            <div class="QuestionSubmit">
                <input
                    name="buttonsave"
                    type="button"
                    onclick="CentralSpaceLoad('admin_size_management.php',false);"
                    value="<?php echo escape($lang["clearbutton"]); ?>"
                >
            </div>
            <?php
        }
        ?>
    </form>
</div>

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short; ?>pages/admin/admin_size_management_edit.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_size_management_edit"); ?>
        <div class="Question">
            <label for="name"><?php echo escape($lang['action-title_create_size_with_id']); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input name="newsizeid" type="text" value="" class="shrtwidth" maxlength="3">
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["create"]); ?>" onclick="return (this.form.elements[0].value!='');">
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>

        <?php if ($order_by) { ?>
            <input type="hidden" name="orderby" value="<?php echo $order_by; ?>">
            <?php
        }

        if ($find) { ?>
            <input type="hidden" name="find" value="<?php echo escape($find); ?>">
            <?php
        }
        ?>
    </form>
</div>

<?php
include "../../include/footer.php";

