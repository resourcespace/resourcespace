<?php

include __DIR__ . "/../../include/boot.php";

ob_end_flush(); // Disable output buffering (causes the output to appear in bursts)
ini_set('memory_limit', '4096M'); // Needed as large systems can produce a very large list of files

// Output all files in a folder, recursively.
function ShowFilesInFolderRecursive($path)
{
    global $storagedir;
    $foldercontents = new DirectoryIterator($path);
    foreach ($foldercontents as $object) {
        if ($object->isDot()) {
            continue;
        }
        $objectname = $object->getFilename();

        if ($object->isDir() && $objectname !== "tmp") {
            ShowFilesInFolderRecursive($path . DIRECTORY_SEPARATOR . $objectname);
        } elseif (
            !is_banned_extension(parse_filename_extension($objectname))
            && parse_filename_extension($objectname) != 'php'
        ) {
            // Don't attempt to get banned extensions, or PHP as will just execute ‌on ‌the ‌remote ‌server
            echo (substr($path, strlen($storagedir)) . DIRECTORY_SEPARATOR . $objectname) . "\t" . $object->getSize() . "\n";
        }
    }
}

$access_key = hash("sha256", date("Y-m-d") . $scramble_key); // Generate an access key that changes every day.

if (php_sapi_name() != "cli") {
    // Mode is fetch of file list - being accessed remotely
    if (getval("access_key", "") != $access_key) {
        exit("Access denied");
    }

    $GLOBALS["userpermissions"] = ["s","v","j*"];
    $collections = getval("collections", "", false, 'validate_digit_csv');

    if ($collections != "") {
        $collections = explode(',', $collections);
        $collections_processed = [];
        $collections_not_processed = [];
        $resources_processed = 0;

        foreach ($collections as $collection) {
                $collection = get_collection($collection);
            if ($collection !== false) {
                debug("Processing " . (int) $collection["ref"] . " - " . escape($collection["name"]) . PHP_EOL);

                if ($collection["type"] == 3) {
                    $resources = [];
                    foreach (get_featured_collection_categ_sub_fcs($collection) as $child) {
                        $resources = array_merge($resources, get_collection_resources($child));
                    }
                } else {
                    $resources = get_collection_resources($collection["ref"]);
                }
                $resources = array_unique($resources);

                # process resources
                foreach ($resources as $resource) {
                    $path = get_resource_path($resource, true);
                    $path = dirname($path);
                    ShowFilesInFolderRecursive($path);
                    $resources_processed++;
                }
                $collections_processed[] = $collection['ref'];
            } else {
                debug("Collection $collection not found");
                $collections_not_processed[] = $collection['ref'];
            }
        }

        debug("Processed collections: " . implode(",", $collections_processed));
        debug("Invalid or missing collections: " . implode(",", array_map('escape', $collections_not_processed)));
        debug("Total resources processed $resources_processed");
    } else {
        ShowFilesInFolderRecursive($storagedir);
    }
    exit();
}

$help_text = <<<'HELP'
NAME
    filestore_sync - Copy files from a remote ResourceSpace system

SYNOPSIS
    php /path/to/pages/tools/filestore_sync.php -u[username:password@][base url of remote system] [OPTIONS]

DESCRIPTION
    Connect to the remote system, retrieve a list of filestore files and start the download.
    Username and password for basic auth can be provided if required.
    Collections can also be specified to enable partial migrations.
    Before running this the \$scramble_key must be the same on both systems.
    Copy over all relevant config.php entries first.

REQUIRED SUMMARY
    -u, --url               username and url for the remote system
                            username@https://acme.resourcespace.com
                            Optional: the password can be provided here or a prompt will ask for it
                            username:password@https://acme.resourcespace.com
OPTIONS SUMMARY
    -c, --collections       Collection IDs of resources to sync, comma separated
    -t, --timelimit         Time limit in seconds for each download, default to 10s
    -w, --writetodisk       Write files directly to disk instead of loading to memory

EXAMPLES
    php filestore_sync.php --url=a.user:mypassword@https://acme.myresourcespace.com
                                                  ^ Source URL
                                       ^ Password
                                ^ Username
    php filestore_sync.php -ua.user:mypassword@https://acme.myresourcespace.com
                            ^ alternative short form


    php filestore_sync.php --url=a.user@https://acme.myresourcespace.com
                            ^ to use basic authentication but prompt for password

    php filestore_sync.php --url=a.user@https://acme.myresourcespace.com -c123,456
    php filestore_sync.php --url=a.user@https://acme.myresourcespace.com --collections 123,456
                                                                            ^ collection IDs to sync

    php filestore_sync.php -ua.user@https://acme.myresourcespace.com -t300
                                                                        ^ limit downloads to 5 minutes

HELP;

// CLI access, connect to the remote system, retrieve the list and start the download.
// username and password for basic auth can be provided if required
if (!isset($argv[1]) || in_array($argv[1], ['-h','--help','-help'])) {
    echo $help_text . PHP_EOL;
    exit();
}

$pattern = '
    /^                      # Start of the string
    ([a-zA-Z0-9_]+)         # Capturing group 1: username (one or more word characters)
    (?:                     # Start of non-capturing group (optional password)
        :                   # Literal colon
        ([^@]+)             # Capturing group 2: password (one or more non-@ characters)
    )?                      # End of non-capturing group, make it optional
    @                       # Literal @ symbol
    (                       # Start of capturing group 3 (URL)
        https?              # http or https
        :\/\/               # Colon followed by two forward slashes
        (?:                 # Start of non-capturing group for domain
            [a-zA-Z0-9-]+   # Domain part (letters, numbers, hyphens)
            \.              # Literal dot
        )+                  # One or more domain parts
        [a-zA-Z]{2,}        # Top-level domain (2 or more letters)
    )                       # End of capturing group 3
    $                       # End of the string
/x';

$options = getopt('u:c:t:w', ['url:','collections:', 'timelimit:', 'writetodisk']);
$writetodisk = false;

foreach ($options as $option_name => $option_value) {
    if (in_array($option_name, ["u", "url"])) {
        $url = $option_value;
        if (!preg_match($pattern, $url)) {
            echo "Invalid url entered, must be in form:\n";
            echo "a.user@https://acme.resourcespace.com\n";
            echo "a.user:mypassword@https://acme.myresourcespace.com\n";
            exit();
        }
        $auth_part = strpos($url, "@");
        if ($auth_part !== false) {
            // Get basic auth credentials
            $credentials = substr($url, 0, $auth_part);
            $url = substr($url, $auth_part + 1);
            if (strpos($credentials, ":") !== false) {
                $credparts = explode(":", $credentials);
                $remote_user = trim($credparts[0]);
                $remote_password = trim($credparts[1] ?? "");
            } else {
                // Prompt for password
                $remote_user = $credentials;
                echo "Enter password for " . $remote_user . ": ";
                system('stty -echo');
                $remote_password = trim(fgets(STDIN));
                system('stty echo');
                if ($remote_password === false) {
                    echo "  A password must be entered. " . PHP_EOL;
                    exit(1);
                }
            }
        }
        $params['access_key'] = $access_key;
    }

    if (in_array($option_name, ["c", "collections"])) {
        if (validate_digit_csv($option_value)) {
            $params['collections'] = $option_value;
        } else {
            echo "Invalid collections specified - must be comma separated integers\n";
            exit();
        }
    }
    if (in_array($option_name, ["t", "timelimit"])) {
        if (is_int_loose($option_value)) {
            $download_time_limit = $option_value;
        } else {
            echo "Invalid download time limit specified - must be integer";
            exit();
        }
    }
    if (in_array($option_name, ['w', 'writetodisk'])) {
        $writetodisk = true;
    }
}

$curl_request = generateURL(
    $url . "/pages/tools/filestore_sync.php",
    $params
);

// Get the file
$ch = curl_init($curl_request);
if (trim($remote_user ?? "") !== "" && trim($remote_password ?? "") !== "") {
    echo PHP_EOL . "Using basic authentication for " . $remote_user . PHP_EOL;
    curl_setopt($ch, CURLOPT_USERPWD, $remote_user . ":" . $remote_password);
}
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1 hour
$file_list = curl_exec($ch);

if ($file_list == "Access denied") {
    exit("Access denied by source system\nCheck scramble key matches\n");
}

// Process the file list
$files = explode("\n", $file_list);
array_pop($files); // Last one is blank as terminates with \n
echo "File list fetched - " . count($files) . " files to check.\n";
flush();

$counter = 0;
foreach ($files as $file) {
    if (substr($file, 0, 3) == "tmp") {
        continue;
    }
    $counter++;
    $s = explode("\t", $file);
    $file = $s[0];
    $filesize = $s[1];
    $file = str_replace("\\", "/", $file); // Windows path support

    if (is_banned_extension(parse_filename_extension($file))) {
        echo "Warning: File $file has banned extension, skipping...\n";
        continue;
    }

    if (!file_exists($storagedir . $file) || filesize($storagedir . $file) != $filesize) {
        echo "(" . $counter . "/" . count($files) . ") Copying " . $file . " - " . formatfilesize($filesize, false) . "\n";
        flush();

        // Download the file
        $ch = curl_init($url . "/filestore/" . $file);
        if (trim($remote_user ?? "") !== "" && trim($remote_password ?? "") !== "") {
            echo "Using basic authentication for " . $remote_user . PHP_EOL;
            curl_setopt($ch, CURLOPT_USERPWD, $remote_user . ":" . $remote_password);
        }

        if ($writetodisk) {
            // Check folder exists
            $s = explode("/", dirname($file));
            $checkdir = $storagedir;
            foreach ($s as $dirpart) {
                $checkdir .= $dirpart . "/";
                if (!file_exists($checkdir)) {
                    mkdir($checkdir, 0777);
                }
            }
            try_unlink($storagedir . $file);
            $fp = fopen($storagedir . $file, "w");
            curl_setopt($ch, CURLOPT_FILE, $fp);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $download_time_limit ?? 10);
        $result = curl_exec($ch);

        if ($result !== false) {
            $contents = $result;
        } else {
            echo "Error: " . curl_errno($ch) . PHP_EOL;
            if ($writetodisk) {
                unlink($storagedir . $file);
            }
            continue;
        }

        if (!$writetodisk) {
            // Check folder exists
            $s = explode("/", dirname($file));
            $checkdir = $storagedir;
            foreach ($s as $dirpart) {
                $checkdir .= $dirpart . "/";
                if (!file_exists($checkdir)) {
                    mkdir($checkdir, 0777);
                }
            }

            // Write the file to disk
            file_put_contents($storagedir . $file, $contents);
        }
    } else {
        echo "In place and size matches: " . $file . "\n";
    }
}
echo "Complete.\n";
