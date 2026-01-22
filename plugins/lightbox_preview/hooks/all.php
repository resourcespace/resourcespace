<?php

include_once __DIR__ . "/../include/utility.php";

function HookLightbox_previewAllAdditionalheaderjs()
    {
    global $baseurl_short, $css_reload_key;
    echo '<script src="' . $baseurl_short . 'lib/lightbox/js/lightbox.min.js" type="text/javascript" ></script>';
    echo '<link type="text/css" href="' . $baseurl_short . 'lib/lightbox/css/lightbox.min.css?css_reload_key=' . $css_reload_key . '" rel="stylesheet" />';

    ?>
    <script>
    function closeModalOnLightBoxEnable()
        {
        setTimeout(function() {
            if(jQuery('#lightbox').is(':visible'))
                {
                ModalClose();
                }
        }, 10);
        }

    jQuery(document).ready(function()
        {
        lightbox.option({
            'resizeDuration': 300,
            'imageFadeDuration': 300,
            'fadeDuration': 300,
            'alwaysShowNavOnTouchDevices': true})
        });
    </script>
    <?php
    }

function HookLightbox_previewAllReplacefullscreenpreviewicon()
    {
    global $baseurl_short, $ref, $result, $n, $k, $search, $offset, $sort, $order_by, $archive,
        $lang, $showkeypreview, $value, $view_title_field, $resource_view_title;

    global $usersearchfilter;
    $usersearchfilter_original = $usersearchfilter;
    # Performance improvement - Don't check search filters again in get_resource_access as $result contains only resources allowed by the search filter.
    $usersearchfilter = '';
    $url = getPreviewURL($result[$n]);
    $usersearchfilter = $usersearchfilter_original;

    if ($url === false) {
        return false;
    }

    $showkeypreview = true;

    # Replace the link to add the 'previewlink' ID
    ?>
    <span class="IconPreview">
        <a class="icon-maximize-2"
            id="previewlink<?php echo escape($order_by) . $ref;?>"
            href="<?php generateURL(
                $baseurl_short . 'pages/preview.php',
                [
                    'from' => 'search',
                    'ref' => $ref,
                    'ext' => $result[$n]['preview_extension'],
                    'search' => $search,
                    'offset' => $offset,
                    'order_by' => $order_by,
                    'sort' => $sort,
                    'archive' => $archive,
                    'k' => $k
                ]) ?>"
            title="<?php echo escape((($resource_view_title != "") ? " - " . $resource_view_title : "")); ?>">
        </a>
    </span>

    <?php
    addLightBox('#previewlink' . escape($order_by) . $ref, $url, $result[$n]["field".$view_title_field], escape($order_by));
    return true;
    }

?>