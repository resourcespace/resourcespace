<?php

/*

Populate ResourceSpace with random images from Wikimedia Commmons to aid testing at scale.

*/

include_once dirname(__FILE__) . '/../../include/boot.php';
include_once dirname(__FILE__) . '/../../include/image_processing.php';

command_line_only();

// Settings
$resource_type = 1; // Adjust to your desired type
$title_field = 8;   // Adjust to your 'Title' field ref
$image_size = 400; // Image size in pixels to request

$help_text = "NAME
    commons_import - a tool for populating ResourceSpace with random images from Wikimedia Commmons

DESCRIPTION
    A tool for populating ResourceSpace with random images from Wikimedia Commmons to aid testing at scale.

OPTIONS SUMMARY

    -h, --help      display this help and exit
    -u, --user      run script as a ResourceSpace user. Use the ID of the user
    -c, --count     a count of how many resources should be populated with random images

DEPENDENCIES
    None.

EXAMPLES
    Import 10 images as user ID 1:
    php /path/to/pages/tools/commons_import.php --user 1 --count 10

    Import 50 images as user ID 2:
    php /path/to/pages/tools/commons_import.php -u 2 -c 50

    " . PHP_EOL;

$cli_short_options = "hu:c:";
$cli_long_options  = array(
    "help",
    "user:",
    "count:",
);

$options = getopt($cli_short_options, $cli_long_options);

$user   = false;
$count  = false;

if (!$options) {
    echo $help_text;
    exit(0);
}

foreach ($options as $option_name => $option_value) {
    if (in_array($option_name, array("h", "help"))) {
        echo $help_text;
        exit(0);
    }

    if (in_array($option_name, array("u", "user")) && !is_array($option_value)) {
        if (!is_numeric($option_value) || (int) $option_value <= 0) {
            logScript("ERROR: Invalid 'user' value provided: '{$option_value}' of type " . gettype($option_value));
            exit(1);
        }

        $user = $option_value;
    }

    if (in_array($option_name, array("c", "count")) && !is_array($option_value)) {
        if (!is_numeric($option_value) || (int) $option_value <= 0) {
            logScript("ERROR: Invalid 'count' value provided: '{$option_value}' of type " . gettype($option_value));
            exit(1);
        }

        $count = $option_value;
    }
}

if (!$user || !$count) {
    logScript("ERROR: Required parameter not provided.");
    echo PHP_EOL . $help_text;
    exit(1);
}

$user_select_sql = new PreparedStatementQuery();
$user_select_sql->sql = "u.ref = ?";
$user_select_sql->parameters = ["i", $user];
$user_data = validate_user($user_select_sql, true);

if (!is_array($user_data) || count($user_data) == 0) {
    logScript("ERROR: Unable to validate user ID #{$user}!");
    exit(1);
}

setup_user($user_data[0]);

if (!(checkperm('c') || checkperm('d'))) {
    logScript("ERROR: User ID #{$user} does not have permissions to add resources!");
    exit(1);
}

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);


function download_file_with_user_agent($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Replace with your own project/contact info per Wikimedia policy
    curl_setopt($ch, CURLOPT_USERAGENT, 'ResourceSpaceCommonsImport/1.0 (info@resourcespace.com)');

    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code !== 200 || $data === false) {
        echo "  CURL error or non-200 response ($http_code) from $url\n";
        return false;
    }

    unset($ch);
    return $data;
}

$number_to_import = $count;

echo "Importing $number_to_import images from Wikimedia Commons...\n";

// Function to fetch random image metadata from Wikimedia Commons
function get_random_commons_images($count)
{
    global $image_size;

    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'generator' => 'random',
        'grnnamespace' => 6, // File namespace
        'grnlimit' => $count,
        'prop' => 'imageinfo',
        'iiprop' => 'url',
        'iiurlwidth' => $image_size,            // Request 400px wide image
        'iiurlheight' => $image_size,           // (optional) limit height too
        'format' => 'json'
    ]);

    $response = file_get_contents($url);
    if ($response === false) {
        die("Error: Failed to fetch from Wikimedia Commons API.\n");
    }

    $data = json_decode($response, true);
    if (!isset($data['query']['pages'])) {
        die("Error: No images found in API response.\n");
    }

    $images = [];
    foreach ($data['query']['pages'] as $page) {
        if (!isset($page['imageinfo'][0]['thumburl'])) {
            continue; // No thumbnail, skip
        }
        $images[] = [
            'title' => $page['title'],
            'url' => $page['imageinfo'][0]['thumburl']
        ];
    }

    return $images;
}

$imported_count = 0;
$batch_size = 10;

while ($imported_count < $number_to_import) {
    $remaining = $number_to_import - $imported_count;
    $fetch_count = min($batch_size, $remaining);

    $images = get_random_commons_images($fetch_count);

    foreach ($images as $img) {
        echo "Processing: {$img['title']}\n";

        // Skip non-JPEGs based on file extension
        $ext = strtolower(pathinfo(parse_url($img['url'], PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg'])) {
            echo "  Skipping non-JPEG file (.$ext).\n";
            continue;
        }

        // Download the image
        $tmp_file = get_temp_dir() . '/' . uniqid('commons_') . '.jpg';
        $image_data = download_file_with_user_agent($img['url']);
        if ($image_data === false) {
            echo "  Failed to download image.\n";
            continue;
        }

        file_put_contents($tmp_file, $image_data);

        // Create new resource
        $ref = create_resource($resource_type, 0, $user);
        if ($ref <= 0) {
            echo "  Failed to create resource.\n";
            unlink($tmp_file);
            continue;
        }

        // Set title
        $clean_title = preg_replace('/^File:/', '', $img['title']);
        $clean_title = preg_replace('/\.[^.]+$/', '', $clean_title);
        update_field($ref, $title_field, $clean_title);

        echo "  Uploading from $tmp_file\n";

        // Upload file to resource
        $result = upload_file($ref, true, false, false, $tmp_file, false);
        if ($result !== false) {
            echo "  Resource $ref created and image uploaded.\n";
            $imported_count++;
            echo "  Imported so far: $imported_count / $number_to_import\n";
        } else {
            echo "  Failed to upload image to resource $ref.\n";
            delete_resource($ref);
        }

        if (file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        if ($imported_count >= $number_to_import) {
            break; // Exit early if we hit the target mid-batch
        }
    }
}

echo "✅ Import complete: $imported_count JPEG images added.\n";
