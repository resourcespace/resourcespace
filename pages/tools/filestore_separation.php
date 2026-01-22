<?php

include "../../include/boot.php";

command_line_only();

# This file converts existing filestore to the filestore separation
# method or restore separated filestore to the default. The config
# setting $originals_separate_storage dictates what the script will do.

# It is strongly recommended that you backup your filestore before
# running this script!

$help_text = <<<'HELP'
NAME
    filestore_separation - Separate a filestore or restore a separated filestore, based off of the $originals_separate_storage config

SYNOPSIS
    php /path/to/pages/tools/filestore_separation.php [OPTIONS] refs...

DESCRIPTION
    A tool to help with the separation or merging of a filestore. A separated filestore will result in resources being stored 
    separately from any previews generated. A separated filestore can also be merged using this script, the operation is determined by
    the value of the $originals_separate_storage config with a value of true meaning that the filestore will be split. 

OPTIONS SUMMARY

    -h, --help                 Display this help text and exit

EXAMPLES
    php filestore_separation.php 1 2 3 4
                                 ^ A list of resource refs to be processed.
                                 If no refs are provided then all resources will be processed. 

HELP;

$options = getopt('h', ['help']);
if (count($options) > 0) {
    echo $help_text . PHP_EOL;
    exit();
}

$refs = array_filter($argv, function($ref){
    return is_positive_int_loose($ref);
});

$cleanup = false;

function reverse_filestore_location($path, $size, $url = false, $ffmpeg_alt = false)
{
    global $originals_separate_storage, $originals_separate_storage_ffmpegalts_as_previews, $storagedir, $storageurl;

    // take the storagedir/storageurl out of the path and see what's next
    if ($url) {
        $remove = $storageurl;
    } else {
        $remove = $storagedir;
    }

    $path_trim = str_replace($remove, "", $path);
    echo "Path trim:$path_trim" . PHP_EOL;
    if ($originals_separate_storage) {
        if ($originals_separate_storage_ffmpegalts_as_previews && $ffmpeg_alt) {
            // we have to consider the fact that this may be in either location
            if (strpos($path_trim, '/original') === 0) {
                $path_trim = substr($path_trim, 9);
            } elseif (strpos($path_trim, '/resized') === 0) {
                $path_trim = substr($path_trim, 8);
            }
        }
        // take the separator out of the path
        elseif ($size == '' || $size == 'o') {
            $path_trim = substr($path_trim, 9);
        } else {
            $path_trim = substr($path_trim, 8);
        }
        echo "Removed path part:$path_trim" . PHP_EOL;
    } else {
        // add the separator into the path
        if ($originals_separate_storage_ffmpegalts_as_previews) {
            if ($ffmpeg_alt) {
                $path_trim = "/resized" . $path_trim;
            } else {
                $path_trim = "/original" . $path_trim;
            }
        } elseif ($size == '' || $size == 'o') {
            $path_trim = "/original" . $path_trim;
        } else {
            $path_trim = "/resized" . $path_trim;
        }
        echo "Added path part:$path_trim" . PHP_EOL;
    }
    return $remove . $path_trim;
}

function filestore_relocate($from, $to)
{
    $filepath = $to;
    $otherpath = $from;

    $file_dir = explode("/", $filepath);
    array_pop($file_dir);
    $file_dir = implode("/", $file_dir);
    echo "Copying file to proper location: $file_dir" . PHP_EOL;
    if (!file_exists($file_dir)) {
        echo "Need to make directory first...";
        @mkdir($file_dir, 0777, true);
        chmod($file_dir, 0777);
        echo "done!" . PHP_EOL;
    }
    if (!copy($otherpath, $filepath)) {
        echo "Failed to copy file...skipping" . PHP_EOL;
    } else {
        echo "Copy complete!" . PHP_EOL;
        // remove the file
        unlink($otherpath);
    }
}

if (count($refs) == 0) {
    # start with a list of all resources
    $refs = ps_array("select ref value from resource where ref>0 order by ref");
}

// check for the presence of the separation folders in filestore
if (!file_exists($storagedir . "/original") && $originals_separate_storage) {
    echo "Original directory not present in filestore...making...";
    @mkdir($storagedir . "/original");
    chmod($storagedir . "/original", 0777);
    echo "done!" . PHP_EOL;
}
if (!file_exists($storagedir . "/resized") && $originals_separate_storage) {
    echo "Resized directory not present in filestore...making...";
    @mkdir($storagedir . "/resized");
    chmod($storagedir . "/resized", 0777);
    echo "done!" . PHP_EOL;
}

foreach ($refs as $ref) {
    $resource_data = get_resource_data($ref);

    # get the current filepath of the original based on the current setting of $originals_separate_storage
    $filepath = get_resource_path($ref, true, '', false, $resource_data['file_extension']);
    # also get the other possible path
    $otherpath = reverse_filestore_location($filepath, '');

    echo"Filepath:";
    print_r($filepath);
    echo PHP_EOL;
    if (file_exists($filepath)) {
        // original exists where it should
        echo "Original file found in proper location" . PHP_EOL;
        // if the file also exists in the old location delete it
        if (file_exists($otherpath)) {
            // remove the file
            unlink($otherpath);
        }
    } else {
        // original needs to be moved
        echo "Original file not found in proper location" . PHP_EOL;

        // test for the presense of the file in the alternate location
        echo "Other path:$otherpath" . PHP_EOL;
        if (file_exists($otherpath)) {
            // let's move it to where it belongs. start by trimming the filename off the path
            filestore_relocate($otherpath, $filepath);
        } else {
            echo "No original file found!" . PHP_EOL;
        }
    }

    // now we need to deal with the other files...start with alternatives
    echo "Checking for alternative files...";
    $alts = get_alternative_files($ref);
    if (!empty($alts)) {
        echo "alts found!" . PHP_EOL;
        // these get moved to originals
        foreach ($alts as $alt) {
            echo "Alt:";
            print_r($alt);
            echo PHP_EOL;
            $ffmpeg_alt = alt_is_ffmpeg_alternative($alt);
            $alt_filepath = get_resource_path($ref, true, '', false, $alt['file_extension'], -1, 1, false, '', $alt["ref"]);
            if ($ffmpeg_alt) {
                if (strpos($alt_filepath, '/original/') !== false) {
                    $ffmpeg_alt_filepath = str_replace('/original/', '/resized/', $alt_filepath);
                } elseif (strpos($alt_filepath, '/resized/') !== false) {
                    $ffmpeg_alt_filepath = str_replace('/resized/', '/original/', $alt_filepath);
                }
                if (isset($ffmpeg_alt_filepath)) {
                    echo 'ffmpeg_alt_filepath=' . $ffmpeg_alt_filepath . PHP_EOL;
                }
            }
            echo 'Alt Filepath:' . $alt_filepath . PHP_EOL;
            $alt_otherpath = reverse_filestore_location($alt_filepath, '', false, $ffmpeg_alt);

            if (file_exists($alt_filepath)) {
                echo "Alt file " . $alt["ref"] . " found in proper location" . PHP_EOL;
                if (file_exists($alt_otherpath)) {
                    // remove the file
                    unlink($alt_otherpath);
                }
            } else {
                echo "Alt file " . $alt["ref"] . " not found in proper location" . PHP_EOL;
                if (file_exists($alt_otherpath)) {
                    // let's move it to where it belongs. start by trimming the filename off the path
                    filestore_relocate($alt_otherpath, $alt_filepath);
                } elseif ($ffmpeg_alt && isset($ffmpeg_alt_filepath) && file_exists($ffmpeg_alt_filepath)) {
                    echo "Alt file is ffmpeg_alt in old setting location" . PHP_EOL;
                    filestore_relocate($ffmpeg_alt_filepath, $alt_filepath);
                } else {
                    echo "Alternative file not found!" . PHP_EOL;
                }
            }
        }
    } else {
        echo "none found" . PHP_EOL;
    }

    // finally, move everything else in the directory
    echo "Checking for previews...";

    $other_dir = explode("/", $otherpath);
    array_pop($other_dir);
    $other_dir = implode("/", $other_dir);
    echo $storagedir . "/original" . PHP_EOL;
    if (!$originals_separate_storage && strpos($other_dir, $storagedir . "/original") !== false) {
        echo "replacing...";
        $other_dir = str_replace($storagedir . "/original", $storagedir . "/resized", $other_dir);
    }

    echo "Other dir=$other_dir" . PHP_EOL;
    // get a list of what's left:
    if (file_exists($other_dir)) {
        $previews = array_diff(scandir($other_dir), array('..', '.'));
        echo "Previews:";
        print_r($previews);
        echo PHP_EOL;
        if (!empty($previews)) {
            echo "previews found!" . PHP_EOL;
            // grab any preview filepath
            $template_path = get_resource_path($ref, true, 'pre', false, 'jpg');
            $template_otherpath = reverse_filestore_location($template_path, 'pre');

            $file_dir = explode("/", $template_path);
            array_pop($file_dir);
            $file_dir = implode("/", $file_dir);

            $other_dir = explode("/", $template_otherpath);
            array_pop($other_dir);
            $other_dir = implode("/", $other_dir);

            foreach ($previews as $preview) {
                $preview_filepath = $file_dir . "/" . $preview;
                $preview_otherpath = $other_dir . "/" . $preview;
                if (file_exists($preview_filepath)) {
                    echo "Preview " . $preview . " found in proper location" . PHP_EOL;
                    if (file_exists($preview_otherpath)) {
                        unlink($preview_otherpath);
                    }
                } else {
                    echo "Preview " . $preview . " not found in proper location" . PHP_EOL;
                    if (file_exists($preview_otherpath)) {
                        echo "Moving $preview...";
                        filestore_relocate($preview_otherpath, $preview_filepath);
                    } else {
                        echo "Preview not found!" . PHP_EOL;
                    }
                }
            }
        } else {
            echo "no previews found!" . PHP_EOL;
        }
    } else {
        echo "no previews directory found!" . PHP_EOL;
    }
}
echo "Move complete!" . PHP_EOL;
if ($cleanup) {
    // get rid of the old directories...this will only be implemented when we're sure the script works flawlessly
}
