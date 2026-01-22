<?php
function Hookyt2rsUpload_batchUpload_page_bottom()
    {
    global $userref, $yt2rs_field_id, $lang;
    $ref_user = 0 - $userref;
    $youtube_copy_path = get_data_by_field($ref_user, $yt2rs_field_id);
    $matches = [];

    if ($youtube_copy_path == "")
        {
        return false;
        }
      elseif (preg_match("/youtu.be\/[a-z1-9.-_]+/", $youtube_copy_path))
        {
        preg_match("/youtu.be\/([a-z1-9.-_]+)/", $youtube_copy_path, $matches);
        }
      elseif (preg_match("/youtube.com(.+)v=([^&]+)/", $youtube_copy_path))
        {
        preg_match("/v=([^&]+)/", $youtube_copy_path, $matches);
        }

    if(!isset($matches[1]))
        {
        return false;
        }

    $ytthumb_id = $matches[1];
    $thumb_path = 'http://img.youtube.com/vi/' . $ytthumb_id . '/mqdefault.jpg';
?>
    <h1><?php echo escape($lang['yt2rs_thumb']); ?></h1>
<?php
    echo escape($thumb_path);
    }