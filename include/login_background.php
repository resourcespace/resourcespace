<script>
var SlideshowImages = new Array();
var SlideshowCurrent = -1;
var SlideshowTimer = 0;
var big_slideshow_timer = <?php echo $slideshow_photo_delay; ?>;
<?php
foreach (get_slideshow_files_data() as $slideshow_file_info) {
    if ((bool) $slideshow_file_info['login_show'] === false) {
        continue;
    }

    $image_download_url = "{$baseurl_short}pages/download.php?slideshow={$slideshow_file_info['ref']}";
    $image_resource = isset($slideshow_file_info['link']) ? $slideshow_file_info['link'] : '';
    ?>

    RegisterSlideshowImage('<?php echo $image_download_url; ?>', '<?php echo $image_resource; ?>');
    <?php
}
?>

jQuery(document).ready(function() 
    {
    ClearTimers();       
    ActivateSlideshow(true);
    });
</script>
<div id="login_box">