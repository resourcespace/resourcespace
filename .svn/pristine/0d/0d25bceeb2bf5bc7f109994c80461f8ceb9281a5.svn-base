<?php

command_line_only();

# Also tests copy_hitcount_to_live() and get_resource_data()
$resourcehit = create_resource(1, 0);

# Update the hit count
update_hitcount($resourcehit);
update_hitcount($resourcehit);

# Transfer hit count data to live.
copy_hitcount_to_live();

# Read the resource data.
$data = get_resource_data($resourcehit, false); # Fetch without caching.

# Should be a hit count of two now.
return $data["hit_count"] == 2;
