<?php

command_line_only();

// Test to ensure that searching for resources with diacritics works

// create 5 new resources 2 of type 1, 2 of type 2 and 2 of type 3
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(2, 0);
$resourced = create_resource(2, 0);
$resourcee = create_resource(3, 0);

debug("Resource A: " . $resourcea);
debug("Resource B: " . $resourceb);
debug("Resource C: " . $resourcec);
debug("Resource D: " . $resourced);
debug("Resource E: " . $resourcee);

$keywords_remove_diacritics = true;

// Add text to free text fields, samples thanks to Markus Kuhn <http://www.cl.cam.ac.uk/~mgk25/>
update_field($resourcea, 'title', 'Zwölf Boxkämpfer jagten Eva quer über den Sylter Deich');
update_field($resourceb, 'title', 'Größeren Image');
update_field($resourcec, 'title', 'Heizölrückstoßabdämpfung');
update_field($resourced, 'title', 'Γαζέες καὶ μυρτιὲς δὲν θὰ βρῶ πιὰ στὸ χρυσαφὶ ξέφωτο');
update_field($resourcee, 'title', 'いろはにほへとちりぬるを
  わかよたれそつねならむ
  うゐのおくやまけふこえて
  あさきゆめみしゑひもせす');

$results = do_search('Boxkämpfer');  // this should return asset A
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourcea))
) {
    return false;
}

$results = do_search('Boxkampfer');  // this should also return asset A
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourcea))
) {
    return false;
}

 $results = do_search('Größeren');  // this should return asset B
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourceb))
) {
    return false;
}

$results = do_search('groseren');  // this should return asset B
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourceb))
) {
    return false;
}


$results = do_search('Heizölrückstoßabdämpfung');  // this should return asset C
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourcec))
) {
    return false;
}

  $results = do_search('わかよたれそつねならむ');  // this should return asset C
if (
    count($results) != 1
    ||
    !match_values(array_column($results, 'ref'), array($resourcee))
) {
    return false;
}


return true;
