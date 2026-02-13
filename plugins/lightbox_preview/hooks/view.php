<?php

include_once __DIR__ . "/../include/utility.php";

function HookLightbox_previewViewRenderbeforerecorddownload($disable_flag)
    {
    if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos(strtoupper($_SERVER['HTTP_USER_AGENT']),"TRIDENT") !== false || strpos(strtoupper($_SERVER['HTTP_USER_AGENT']),"MSIE") !== false || $disable_flag === true))
        {
        return false;
        }
    
    ?>
    <script type="text/javascript">

    // Ensure each preview "View" button causes a link to its correct image size
    jQuery(document).on('click', '.previewsizelink', function(event) {
        var data_viewsize= event.target.getAttribute('data-viewsize');
        var data_viewsizeurl= event.target.getAttribute('data-viewsizeurl');
        event.preventDefault();
        var default_viewsizeurl = document.getElementById("previewimagelink").getAttribute("href");    
        document.getElementById("previewimagelink").setAttribute("href", data_viewsizeurl);    
        jQuery('#previewimage').click(); 
        document.getElementById("previewimagelink").setAttribute("href", default_viewsizeurl);    
        });
    
    </script>
    <?php

    global $resource, $title_field;

    // Establish the default preview url for this resource
    $url = getPreviewURL($resource);
    if(false === $url)
        {
        return;
        }

    $title             = get_data_by_field($resource['ref'], $title_field);
    $page_count        = (get_resource_access($resource) == RESOURCE_ACCESS_RESTRICTED) ? 1 : get_page_count($resource);

    for($i = 1; $i < $page_count + 1; $i++)
        {
        // Handle first preview (regardless if it is multi page or just one preview)
        if(1 == $i)
            {
            addLightBox('#previewimagelink', $url, $title, $resource['ref']);

            continue;
            }

        // This applies only to resources that have multi page previews
        $preview_url = getPreviewURL($resource, -1, $i);

        if(false === $preview_url)
            {
            continue;
            }
            ?>
            <a href="<?php echo $preview_url; ?>"
                data-lightbox='lightbox<?php echo $resource['ref']; ?>'
                data-title="<?php echo str_replace(array("\r","\n")," ", escape(strip_tags(i18n_get_translated($title)))); ?>">
            </a>
        <?php
        }
    }

function HookLightbox_previewViewGetpreviewurlforsize()
    {
    global $resource, $data_viewsize;

    // Establish the default preview url for this resource
    return getPreviewURLForType($resource, $data_viewsize);
    
    }


/**
 * HookLightbox_previewViewRenderaltthumb
 *
 * @param  integer $n index value for alternative file
 * @param  array   $altfile parameters of alternative file to be rendered
 * @return boolean
 */
function HookLightbox_previewViewRenderaltthumb(int $n,array $altfile)
    {
    if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'],"TRIDENT") !== false || strpos($_SERVER['HTTP_USER_AGENT'],"MSIE") !== false))
        {
        return false;
        }
    global $baseurl_short, $ref, $resource, $alt_thm, $k, $search,
            $offset, $sort, $order_by, $archive;

    $url = getPreviewURL($resource, $altfile['ref']);
    if ($url === false) {
        return false;
    }

    # Replace the link to add the 'altlink' ID
    ?>
    <a id="altlink_<?php echo $n; ?>" class="AltThumbLink" href="<?php echo $baseurl_short?>pages/preview.php?ref=<?php
            echo urlencode($ref)?>&alternative=<?php echo $altfile['ref']; ?>&k=<?php
            echo urlencode($k)?>&search=<?php echo urlencode($search)?>&offset=<?php echo
            urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo
            urlencode($sort)?>&archive=<?php echo urlencode($archive)?>&<?php
            echo hook("previewextraurl") ?>">
        <img alt="<?php echo escape(i18n_get_translated($altfile['name'] ?? ""));?>"
        src="<?php echo $alt_thm; ?>" class="AltThumb">
    </a>
    <?php
    addLightBox('#altlink_' . $n, $url, $altfile['name'], "alt");

    return true;
    }

function HookLightbox_previewViewAftersearchimg()
    {
    if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos(strtoupper($_SERVER['HTTP_USER_AGENT']),"TRIDENT") !== false || strpos(strtoupper($_SERVER['HTTP_USER_AGENT']),"MSIE") !== false))
        {
        return false;
        }
    // Prevent loading of Central Space when clicking preview image
    ?>

    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery('#previewimagelink').removeAttr('onclick');
        });
    </script>

<?php
    }
