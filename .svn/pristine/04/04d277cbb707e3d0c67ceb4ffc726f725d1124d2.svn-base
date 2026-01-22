<?php

include __DIR__ . '/../../include/boot.php';
command_line_only();
ob_end_flush();
restore_error_handler();

$help_text = <<<'HELP'
NAME
    facial_recognition_trainer - Face recogniser (OpenCV) trainer

SYNOPSIS
    php pages/tools/facial_recognition_trainer.php [OPTIONS]

DESCRIPTION
    A tool to help administrators maintain the LBPH persistent model up to date as users add more tags in ResourceSpace.

OPTIONS SUMMARY

    -h, --help                  Display this help text and exit
    --overwrite-existing        Allows the trainer to recreate the prepared data (tag) image
    --force-processing          Allows for this tool to be run without setting the $facial_recognition_mark_for_training_field config.
                                It is advised that you set the config to minimize the resources being processed.

EXAMPLES
    # Update training data
    php facial_recognition_trainer.php


HELP;
// Script options @see https://www.php.net/manual/en/function.getopt.php
$cli_short_options = 'h';
$cli_long_options  = [
    'help',
    'overwrite-existing',
    'force-processing'
];

// CLI options check
$overwrite_existing = false;
$force_processing   = $facial_recognition_mark_for_training_field == -1; // System wide override option
foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
    if (in_array($option_name, array('h', 'help'))) {
        echo $help_text;
        exit(0);
    } elseif ('overwrite-existing' == $option_name) {
        $overwrite_existing = true;
    } elseif ('force-processing' == $option_name) {
        $force_processing = true;
    }
}

if (!$facial_recognition_active) {
    echo 'Error: Facial recognition is not enabled!' . PHP_EOL;
    exit(1);
}

if (!is_positive_int_loose($facial_recognition_mark_for_training_field) && !$force_processing) {
    echo 'Error: Please set the $facial_recognition_mark_for_training_field config option before trying to run this tool. Alternatively use the force-processing option' . PHP_EOL;
    exit(1);
}

// Init
$convert_fullpath             = get_utility_path('im-convert');
$python_fullpath              = get_utility_path('python');
$faceRecognizerTrainer_path   = __DIR__ . '/../../lib/facial_recognition/faceRecognizerTrainer.py';
$facial_recognition_tag_field = (int) $facial_recognition_tag_field;
$allow_training               = false;
$no_previews_found_counter    = 0;
$prepared_trainer_data        = '';

if ('' === $facial_recognition_face_recognizer_models_location) {
    echo 'Error: No location set for FaceRecognizer data!' . PHP_EOL;
    exit(1);
}

if (false === $convert_fullpath) {
    echo 'Error: Could not find ImageMagick "convert" utility!' . PHP_EOL;
    exit(1);
}

if (false === $python_fullpath) {
    echo 'Error: Could not find Python!' . PHP_EOL;
    exit(1);
}

// Step 1: Preparing the data
$training_data_set_condition = $facial_recognition_mark_for_training_field > 0
    ? new PreparedStatementQuery(
        'AND EXISTS (
            SELECT *
            FROM resource_node AS rn
            INNER JOIN node AS n ON n.ref = rn.node AND n.resource_type_field = ?
            WHERE rn.`resource` = a.`resource`
        )',
        ['i', $facial_recognition_mark_for_training_field]
    )
    : new PreparedStatementQuery();
$annotations = ps_query(
    "SELECT a.resource,
               a.x,
               a.y,
               a.width,
               a.height,
               n.ref AS node_id,
               (SELECT preview_extension FROM resource AS r WHERE r.ref = a.resource) AS resource_preview_ext
          FROM annotation_node AS an
    INNER JOIN annotation AS a ON a.ref = an.annotation
    INNER JOIN node AS n ON n.ref = an.node AND n.resource_type_field = a.resource_type_field
         WHERE a.resource_type_field = ?
         {$training_data_set_condition->sql}
      ORDER BY n.ref ASC",
    array_merge(['i', $facial_recognition_tag_field], $training_data_set_condition->parameters)
);

foreach ($annotations as $annotation) {
    $preview_image_path  = get_resource_path(
        $annotation['resource'],
        true,
        'pre',
        true,
        $annotation['resource_preview_ext']
    );

    if (!file_exists($preview_image_path)) {
        echo "Could not find the preview image at '{$preview_image_path}'" . PHP_EOL;
        $no_previews_found_counter++;
        continue;
    }

    $prepared_image_path = get_resource_path(
        $annotation['resource'],
        true,
        FACIAL_RECOGNITION_CROP_SIZE_PREFIX . $annotation['node_id'],
        true,
        FACIAL_RECOGNITION_PREPARED_IMAGE_EXT
    );

    echo "Preparing image for resource ID {$annotation['resource']} and node ID {$annotation['node_id']}" . PHP_EOL;

    $is_image_prepared = prepareFaceImage(
        $preview_image_path,
        $prepared_image_path,
        $annotation['x'],
        $annotation['y'],
        $annotation['width'],
        $annotation['height'],
        $overwrite_existing
    );

    if (!$is_image_prepared) {
        echo 'Warning: Could not prepare image' . PHP_EOL;
        continue;
    }

    $prepared_trainer_data .= "{$prepared_image_path};{$annotation['node_id']}" . PHP_EOL;
}


// Do not proceed with the training if no previews could be found or no annotations are found
if ($no_previews_found_counter === count($annotations)) {
    exit(1);
}

$prepared_data_path = "{$facial_recognition_face_recognizer_models_location}/prepared_data.csv";

// Save prepared data to a CSV file
$prepared_data_file = fopen($prepared_data_path, 'w+b');

if (false === $prepared_data_file) {
    exit(1);
}

fwrite($prepared_data_file, $prepared_trainer_data);
fclose($prepared_data_file);


// Step 2: Training FaceRecognizer
if (!file_exists($prepared_data_path)) {
    echo 'Error: Could not find the prepared data CSV file for FaceRecognizer trainer!' . PHP_EOL;
    exit(1);
}

$command        = "{$python_fullpath} {$faceRecognizerTrainer_path} {$prepared_data_path}";
$command_output = run_command($command);

echo $command_output;
echo PHP_EOL;
