<?php

/*
 *
 *   Example of API integration
 *   --------------------------
 *
 *   Pure PHP example... does not require any local RS elements (connects to RS via HTTP).
 *   This code would be on a client (non ResourceSpace) system.
 *
 *   For documentation please see: http://www.resourcespace.com/knowledge-base/api/
 *
*/

$private_key = "e6ee5970359e1cfc24091aa7b0237feb25db1efb69a8b83d7959fb2f6b340ee0"; # <---  From RS user edit page for the user to log in as
$user = "admin"; # <-- RS username of the user you want to log in as

# Some example function calls.
#
#$query="user=" . $user . "&function=do_search&param1="; # <--- The function to execute, and parameters
$query = "user=" . $user . "&function=get_resource_field_data&param1=1"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=create_resource&param1=1"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=update_field&param1=1&param2=8&param3=Example"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=delete_resource&param1=1"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=copy_resource&param1=2"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=get_resource_data&param1=2"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=get_alternative_files&param1=2"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=get_resource_types"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=add_alternative_file&param1=2&param2=Test"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=get_resource_log&param1=2"; # <--- The function to execute, and parameters
#$query="user=" . $user . "&function=upload_file_by_url&param1=2&param2=&param3=&param4=&param5=" . urlencode("http://www.montala.com/img/slideshow/montala-bg.jpg"); # <--- The function to execute, and parameters
# Create resource, add a file and add metadata in one pass.
$query = "user=" . $user . "&function=create_resource&param1=1&param2=&param3=" . urlencode("http://www.montala.com/img/slideshow/montala-bg.jpg") . "&param4=&param5=&param6=&param7=" . urlencode(json_encode(array(1 => "Foo",8 => "Bar"))); # <--- The function to execute, and parameters

# Sign the query using the private key
$sign = hash("sha256", $private_key . $query);

# Make the request.
$results = file_get_contents("http://localhost/resourcespace/api/?" . $query . "&sign=" . $sign);

# Output the JSON
echo "<pre>";
echo escape($results);
