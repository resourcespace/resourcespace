<?php

hook('add_to_resource_tools', '', array($ref));

// Edit icon
// The permissions check here is intentionally more basic. It doesn't check edit_filter as this would be computationally intensive
// when displaying many resources. As such this is a convenience feature for users that have system-wide edit access to the given
// access level.
if (
    $thumbs_edit
    &&
    (
    checkperm("e" . $result[$n]["archive"])
    || ($edit_access_for_contributor && $userref == $result[$n]["created_by"])
    )
    && $allow_share
    && ($k == "" || $internal_share_access)
) {
    ?>
    <a
        class="icon-pencil"
        href="<?php echo str_replace("view.php", "edit.php", $url) ?>"  
        onClick="return <?php echo $resource_view_modal ? "Modal" : "CentralSpace"; ?>Load(this, true);" 
        title="<?php echo escape($lang["action-editmetadata"] . (($resource_view_title != "") ? " - " . $resource_view_title : "")) ?>">
    </a>
    <?php
}

// Collection comment icon
if (
    ($k == "" || $internal_share_access)
    && $collection_commenting
    && (substr($search, 0, 11) == "!collection")
) {
    ?>
    <a
        class="icon-message-circle"
        href="<?php echo generateURL($baseurl_short . 'pages/collection_comment.php', ['ref' => $ref, 'collection' => trim(substr($search, 11))]); ?>"
        onClick="return ModalLoad(this,true);" 
        title="<?php echo escape($lang["addorviewcomments"] . (($resource_view_title != "") ? " - " . $resource_view_title : "")) ?>">
    </a>
    <?php
}

// Preview icon
if (
    $thumbs_expand 
    && !hook("replacefullscreenpreviewicon")
    && (int) $result[$n]["has_image"] !== RESOURCE_PREVIEWS_NONE
) {
    ?>
    <a
        class="icon-maximize-2"
        onClick="return CentralSpaceLoad(this,true);"
        href="<?php echo generateURL($baseurl_short . 'pages/preview.php', ['from' => 'search','ref' => $ref,'ext' => $result[$n]['preview_extension'],'search' => $search,'offset' => $offset,'order_by' => $order_by,'sort' => $sort,'archive' => $archive,'k' => $k]); ?>"
        title="<?php echo escape($lang["fullscreenpreview"] . (($resource_view_title != "") ? " - " . $resource_view_title : "")) ?>">
    </a>
    <?php
} /* end hook replacefullscreenpreviewicon */

// Share icon
if ($thumbs_share && $allow_share && ($k == "" || $internal_share_access)) { ?>
    <a class="icon-share-2"
        href="<?php echo generateURL($baseurl_short . 'pages/resource_share.php', ['ref' => $ref,'search' => $search,'offset' => $offset,'order_by' => $order_by,'sort' => $sort,'archive' => $archive,'k' => $k]); ?>"
        onClick="return CentralSpaceLoad(this,true);"  
        title="<?php echo escape($lang["share-resource"] . (($resource_view_title != "") ? " - " . $resource_view_title : "")) ?>">
    </a>
    <?php
} 

// Remove from collection icon
if (!checkperm('b') && ($k == '' || $internal_share_access)) {
    $col_link_class = ['icon-circle-minus'];

    if (
        isset($usercollection_resources)
        && is_array($usercollection_resources)
        && !in_array($ref, $usercollection_resources)
    ) {
        $col_link_class[] = 'DisplayNone';
    }

    $onclick = 'toggle_addremove_to_collection_icon(this);';
    echo remove_from_collection_link($ref, implode(' ', $col_link_class), $onclick, 0, $resource_view_title) . '</a>';
}

// Add to collection icon
if (
    $pagename != "collections"
    && !checkperm('b')
    && !in_array($result[$n]['resource_type'], $collection_block_restypes)
    && ('' == $k || $internal_share_access)
) {
    $col_link_class = ['icon-circle-plus'];

    if (
        isset($usercollection_resources)
        && is_array($usercollection_resources)
        && in_array($ref, $usercollection_resources)
    ) {
        $col_link_class[] = 'DisplayNone';
    }

    $onclick = 'toggle_addremove_to_collection_icon(this);';
    echo add_to_collection_link($ref, $onclick, '', implode(' ', $col_link_class), $resource_view_title) . '</a>';
} 
?>

<div class="clearer"></div>
