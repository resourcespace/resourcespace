<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

$order_by = getval("order_by", '');
$sort = getval("sort", "DESC");
$search = getval("search", "");
$restypes = getval('restypes', '');
$archive = getval('archive', '');
$daylimit = getval('daylimit', '');
$offset = getval('offset', '');
$collection = getval('collection', '');
$resources_count = getval('resources_count', '');

$collection_data = get_collection($collection);

render_actions($collection_data, true, false, $collection);
