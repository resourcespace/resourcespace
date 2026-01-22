<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

if ($simple_search_reset_after_search) {
    $restypes = "";
    $search = "";
    $quicksearch = "";
} else {
    # Pull values from cookies if necessary, for non-search pages where this info hasn't been submitted
    if (!isset($restypes)) {
        $restypes = isset($_COOKIE['restypes']) ? $_COOKIE['restypes'] : "";
    }

    if (!isset($search) || strpos($search, '!') !== false) {
        $quicksearch = (isset($_COOKIE['search']) ? $_COOKIE['search'] : '') ;
    } else {
        $quicksearch = $search;
    }
}

$initial_tags = explode(',', $quicksearch);

include_once '../../include/searchbar.php';
?>
<script type="text/javascript">
    jQuery(window).on("load", function() {
        if (typeof AdditionalJs == 'function') {
            AdditionalJs();  
        }
    });
</script>
