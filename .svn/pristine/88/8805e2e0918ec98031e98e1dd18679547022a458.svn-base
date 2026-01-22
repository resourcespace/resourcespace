#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * This script can be used to generate metadata for SimpleSAMLphp
 * based on an XML metadata file.
 */

// This is the base directory of the SimpleSAMLphp installation
$baseDir = dirname(__FILE__, 4);

// Add library autoloader.
require_once($baseDir . '/lib/_autoload.php');

if (!\SimpleSAML\Module::isModuleEnabled('metarefresh')) {
    echo "You need to enable the metarefresh module before this script can be used.\n";
    echo "You can enable it by searching for the `module.enable` key in `config.php` and set `metarefresh` to true.\n";
    exit(1);
}

// Initialize the configuration
$configdir = (new \SimpleSAML\Utils\Config())->getConfigDir();
\SimpleSAML\Configuration::setConfigDir($configdir);

// $outputDir contains the directory we will store the generated metadata in
$sysUtils = new \SimpleSAML\Utils\System();
$outputDir = $sysUtils->resolvePath('metadata-generated');

/* $toStdOut is a boolean telling us whether we will print the output to stdout instead
 * of writing it to files in $outputDir.
 */
$toStdOut = false;

/* $certificates contains the certificates which should be used to check the signature of the signed
 * EntityDescriptor in the metadata, or NULL if signature verification shouldn't be done.
 */
$certificates = null;

// This variable contains the files we will parse
$files = [];

// Parse arguments

$progName = array_shift($argv);

foreach ($argv as $a) {
    if (strlen($a) === 0) {
        continue;
    }

    if ($a[0] !== '-') {
        // Not an option. Assume that it is a file we should parse
        $files[] = $a;
        continue;
    }

    if (strpos($a, '=') !== false) {
        $p = strpos($a, '=');
        $v = substr($a, $p + 1);
        $a = substr($a, 0, $p);
    } else {
        $v = null;
    }

    // Map short options to long options
    $shortOptMap = [
        '-h' => '--help',
        '-o' => '--out-dir',
        '-s' => '--stdout',
    ];
    if (array_key_exists($a, $shortOptMap)) {
        $a = $shortOptMap[$a];
    }

    switch ($a) {
        case '--certificate':
            if ($v === null || strlen($v) === 0) {
                echo 'The --certficate option requires a parameter.' . "\n";
                echo 'Please run `' . $progName . ' --help` for usage information.' . "\n";
                exit(1);
            }
            $certificates[] = $v;
            break;
        case '--help':
            printHelp();
            exit(0);
        case '--out-dir':
            if ($v === null || strlen($v) === 0) {
                echo 'The --out-dir option requires a parameter.' . "\n";
                echo 'Please run `' . $progName . ' --help` for usage information.' . "\n";
                exit(1);
            }
            $outputDir = $sysUtils->resolvePath($v);
            break;
        case '--stdout':
            $toStdOut = true;
            break;
        default:
            echo 'Unknown option: ' . $a . "\n";
            echo 'Please run `' . $progName . ' --help` for usage information.' . "\n";
            exit(1);
    }
}

if (count($files) === 0) {
    echo $progName . ': Missing input files. Please run `' . $progName . ' --help` for usage information.' . "\n";
    exit(1);
}

// The metadata global variable will be filled with the metadata we extract
$metaloader = new \SimpleSAML\Module\metarefresh\MetaLoader();

foreach ($files as $f) {
    $source = ['src' => $f];
    if (isset($certificates)) {
        $source['certificates'] = $certificates;
    }
    $metaloader->loadSource($source);
}

if ($toStdOut) {
    $metaloader->dumpMetadataStdOut();
} else {
    $metaloader->writeMetadataFiles($outputDir);
}

/**
 * This function prints the help output.
 */
function printHelp(): void
{
    global $progName;

    /*   '======================================================================' */
    echo 'Usage: ' . $progName . ' [options] [files]' . "\n";
    echo "\n";
    echo 'This program parses SAML metadata files and outputs configuration that can' . "\n";
    echo 'be added to the metadata files in metadata/.' . "\n";
    echo "\n";
    echo 'Options:' . "\n";
    echo ' --certificate=<FILE>         The certificate which should be used' . "\n";
    echo '                              to check the signature of the metadata.' . "\n";
    echo '                              The file are stored in the cert dir.' . "\n";
    echo '                              It is possibility to add multiple' . "\n";
    echo '                              --certificate options to handle' . "\n";
    echo '                              key rollover.' . "\n";
    echo ' -h, --help                   Print this help.' . "\n";
    echo ' -o=<DIR>, --out-dir=<DIR>    Write the output to this directory. The' . "\n";
    echo '                              default directory is metadata-generated/.' . "\n";
    echo '                              Non-absolute paths will be relative to the SimpleSAMLphp' . "\n";
    echo '                              base directory.' . "\n";
    echo ' -s, --stdout                 Write the output to stdout instead of' . "\n";
    echo '                              separate files in the output directory.' . "\n";
    echo "\n";
}
