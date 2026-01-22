<?php

/*
command-line script to split csv file into smaller files
*/

if ('cli' != PHP_SAPI) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Access denied - Command line only!');
}

$errors = csv_split_file_validate_args();
if (count($errors) > 0) {
    foreach ($errors as $error) {
        echo $error . "\n";
    }
    exit;
}

$file               = $argv[1]; // filepath and filename for file to split
$number_lines       = $argv[2]; // integer representing number of lines (plus header line)
$output_filepath    = $argv[3]; // filepath for output files

$handle = fopen($file, 'r');
if ($handle === false) {
    // Can't open csv file
    echo "Error: cannot open csv file";
    exit;
}

$header_line = fgets($handle); // get first line

$f = 1; // counter for new file name

while (!feof($handle)) {
        $newfile = fopen($output_filepath . $f . '.csv', 'w'); //create new file to write to with file counter
    if ($newfile === false) {
        echo "Error: cannot open csv file"; // Can't open csv file
        exit;
    }

        fwrite($newfile, $header_line); // add csv header line

    for ($i = 1; $i <= $number_lines; $i++) { //add number of lines defined by $number_lines
        $line = fgetcsv($handle);
        if (is_array($line)) {
            fputcsv($newfile, $line);
        } else {
            echo $line;
            break;
        }
        if (feof($handle)) {
            break;
        } //If file ends, break loop
    }
        fclose($newfile);

        $f++; //Increment newfile number
}
fclose($handle);

/**
 *
 * validate command line parameters
 * returns array containing any error messages
* @return array $error
*/

function csv_split_file_validate_args()
{
    global $argv;


    $error = array();

    if (count($argv) < 4) {
        $error[] = "Error: Missing required input parameters: 1 => input_csv_filepath; 2 => number_lines; 3 => output_csv_filepath";
        return $error;
    }

    foreach ($argv as $i => $arg) {
        if (!isset($arg)) {
            $error[] = "Error: missing input parameter: " . $i;
        }
            return $error;
    }

    if ((is_numeric($argv[2]) && $argv[2] > 0) === false) {
        $error[] = "Error: parameter 2 (number_lines) needs to be a positive integer greater than 0";
    }

    return $error;
}
