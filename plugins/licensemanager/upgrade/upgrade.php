<?php
# Included in the various places the license management functionality appears

# Upgrade from the old (single resource_license table) scheme to the new (license table with resource_license managing the join only) when it's detected that it's necessary.

$upgrade=ps_value("select (select count(*) from license)=0 and (select count(*) from resource_license) > 0 value",[],0);
if ($upgrade)
    {
    # There are no license records but there are rows in the join table (old style). Migration necessary.
    $licenses=ps_array("select ref value from resource_license", []);
    foreach ($licenses as $license)
        {
        # Copy the data to the new license table, and make sure the join is in place.
        ps_query("insert into license (outbound,holder,license_usage,description,expires) select outbound,holder,license_usage,description,expires from resource_license where ref= ?", ['i', $license]);
        $new=sql_insert_id();
        ps_query("update resource_license set license= ? where ref= ?", ['i', $new, 'i', $license]);
        }
    }
