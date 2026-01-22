<?php

include "../include/boot.php";

# External access support (authenticate only if no key provided, or if invalid access key provided)
$k = getval("k", "");
if ($k != "") {
    die();
}

include "../include/authenticate.php";

$ref = getval("ref", "", true);
$collections = get_resource_collections($ref);

if (count($collections) != 0) {
    ?>
    <div class="RecordBox">
        <div class="RecordPanel">
            <div id="AssociatedCollections"> 
                <div class="Title"><?php echo escape($lang['associatedcollections'])?></div>
                <div class="Listview nopadding">
                    <table class="ListviewStyle">
                        <tr class="ListviewTitleStyle">
                            <th><?php echo escape($lang["collectionname"])?></th>
                            <th><?php echo escape($lang["owner"])?></th>
                            <th><?php echo escape($lang["id"])?></th>
                            <th><?php echo escape($lang["created"])?></th>
                            <th><?php echo escape($lang["itemstitle"])?></th>
                            <th><?php echo escape($lang["access"])?></th>
                            <th>
                                <div class="ListTools"><?php echo escape($lang["actions"])?></div>
                            </th>
                        </tr>

                        <?php for ($n = 0; $n < count($collections); $n++) { ?>
                            <tr>
                                <td>
                                    <div class="ListTitle">
                                        <a
                                            onclick="return CentralSpaceLoad(this,true);"
                                            <?php if ($collections[$n]["type"] == COLLECTION_TYPE_FEATURED) { ?>
                                                style="font-style: italic;"
                                            <?php } ?>
                                            href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $collections[$n]["ref"])?>"
                                        >
                                            <?php echo i18n_get_collection_name($collections[$n])?>
                                        </a>
                                    </div>
                                </td>
                                <td><?php echo escape($collections[$n]["fullname"])?></td>
                                <td><?php echo $collections[$n]["ref"]; ?></td>
                                <td><?php echo nicedate($collections[$n]["created"], true)?></td>
                                <td><?php echo $collections[$n]["count"]; ?></td>
                                <td>
                                    <?php
                                    switch ($collections[$n]["type"]) {
                                        case COLLECTION_TYPE_PUBLIC:
                                            echo escape($lang["public"]);
                                            break;

                                        case COLLECTION_TYPE_FEATURED:
                                            echo escape($lang["theme"]);
                                            break;

                                        case COLLECTION_TYPE_STANDARD:
                                        default:
                                            echo escape($lang["private"]);
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="ListTools">
                                        <?php render_actions($collections[$n], false, false); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
} ?>
