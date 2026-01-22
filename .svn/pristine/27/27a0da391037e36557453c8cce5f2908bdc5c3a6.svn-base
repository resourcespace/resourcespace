<?php

include_once __DIR__ . "/../../include/migration_functions.php";

# Report migration from resource_data to resource_node

$code_to_migrate = array();

$code_to_migrate[0]["category"] = "Shipped reports (4,5,6,7,11,14)";
$code_to_migrate[0]["fingerprint"] =
"(\n            SELECT rd.value \n              FROM resource_data AS rd \n             WHERE rd.resource = r.ref \n               AND rd.resource_type_field = [title_field]\n             LIMIT 1\n         )";
$code_to_migrate[0]["replacement"] =
"( SELECT n.name 
FROM resource_node rn, node n 
WHERE rn.resource = r.ref AND n.ref = rn.node and n.resource_type_field = [title_field]
LIMIT 1 )";

$code_to_migrate[1]["category"] = "Shipped reports (8,9)";
$code_to_migrate[1]["fingerprint"] =
"(\n            SELECT rd.value \n              FROM resource_data AS rd \n             WHERE rd.resource = ref \n               AND rd.resource_type_field = [title_field]\n             LIMIT 1\n         )";
$code_to_migrate[1]["replacement"] =
"( SELECT n.name 
FROM resource_node rn, node n 
WHERE rn.resource = resource.ref AND n.ref = rn.node and n.resource_type_field = [title_field]
LIMIT 1 )";

$code_to_migrate[2]["category"] = "Shipped reports (3,23)";
$code_to_migrate[2]["fingerprint"] =
"(SELECT `value` FROM resource_data AS rd WHERE rd.resource = r.ref AND rd.resource_type_field = [title_field] LIMIT 1)";
$code_to_migrate[2]["replacement"] =
"( SELECT n.name 
FROM resource_node rn, node n 
WHERE rn.resource = r.ref AND n.ref = rn.node and n.resource_type_field = [title_field]
LIMIT 1 )";

$code_to_migrate[3]["category"] = "Shipped reports (22,24)";
$code_to_migrate[3]["fingerprint"] =
"(SELECT rd.value FROM resource_data AS rd WHERE rd.resource = r.ref AND rd.resource_type_field = [title_field] LIMIT 1)";
$code_to_migrate[3]["replacement"] =
"( SELECT n.name 
FROM resource_node rn, node n 
WHERE rn.resource = r.ref AND n.ref = rn.node and n.resource_type_field = [title_field]
LIMIT 1 )";

$code_to_migrate[4]["category"] = "Shipped report (13)";
$code_to_migrate[4]["fingerprint"] =
"select distinct resource.ref 'Resource ID',resource.field8 'Resource Title',resource_data.value 'Expires' from resource join resource_data on resource.ref=resource_data.resource join resource_type_field on resource_data.resource_type_field=resource_type_field.ref where resource_type_field.type=6 and value>=date('[from-y]-[from-m]-[from-d]') and value<=adddate(date('[to-y]-[to-m]-[to-d]'),1) and length(value)>0";
$code_to_migrate[4]["replacement"] =
"select distinct resource.ref 'Resource ID',resource.field8 'Resource Title',node.name 'Expires' 
from resource 
join resource_node on resource.ref=resource_node.resource 
join node on node.ref=resource_node.node 
join resource_type_field on node.resource_type_field=resource_type_field.ref 
where resource_type_field.type=6 and node.name>=date('[from-y]-[from-m]-[from-d]') and node.name<=adddate(date('[to-y]-[to-m]-[to-d]'),1) and length(node.name)>0";

$reports = ps_query("select ref,name,query from report");

foreach ($reports as $report) {
    t022_perform_migration($report, $code_to_migrate);
}



function t022_perform_migration($rep, $code_to_migrate)
{

    foreach ($code_to_migrate as $code) {
        $position = strpos((string) $rep["query"], (string) $code["fingerprint"]);
        if ($position > 0 || $position === 0) {
            echo "Migrating report {$rep['ref']}<br><br>";
            $new_query = str_replace($code["fingerprint"], $code["replacement"], $rep["query"]);
            ps_query("UPDATE report SET query=? WHERE ref=?", array("s",$new_query, "i",$rep['ref']));
        }
    }
}
