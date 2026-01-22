
<?php
## for existing installations that have extensive resource view
## hit counts and want to switch to tracking hit counts as downloads, rather than views.

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

set_time_limit(60 * 60 * 40);

echo "Resetting hit counts to download counts derived from the resource log...";

$rd = ps_query("select ref from resource");
for ($n = 0; $n < count($rd); $n++) {
    $ref = $rd[$n]['ref'];
    echo "Updating " . $ref . "<br />";
    $parameters = array("i",$ref);
    $count = ps_value("select count(*) value from resource_log where resource=? and type='d'", $parameters, 0);
    $parameters = array("i",$count, "i",$ref);
    ps_query("update resource set hit_count=0,new_hit_count=? where ref=?", $parameters);
}
echo "...done.";
