<?php

use Montala\ResourceSpace\CommandPlaceholderArg;

/**
* Initialize facial recognition functionality.
*
* IMPORTANT: only one field can be setup for the annotation side and it also MUST be a dynamic keywords list
*
* @uses ps_value()
*
* @return boolean
*/
function initFacialRecognition()
{
    global $facial_recognition_tag_field, $facial_recognition_face_recognizer_models_location, $annotate_enabled,
           $annotate_fields;

    if (!is_numeric($facial_recognition_tag_field) || 0 >= $facial_recognition_tag_field) {
        return false;
    }

    if (!is_dir($facial_recognition_face_recognizer_models_location)) {
        if (strpos($facial_recognition_face_recognizer_models_location, $GLOBALS["storagedir"]) === 0) {
            // Create the directory as it is in the configured filestore
            mkdir($facial_recognition_face_recognizer_models_location, 0777, true);
        } else {
            // Folder needs to be created by server admin
            $error = str_replace(
                ["%variable","%path"],
                ["\$facial_recognition_face_recognizer_models_location", $facial_recognition_face_recognizer_models_location],
                $GLOBALS["lang"]["error_invalid_path"]
            );
            if (PHP_SAPI == "cli") {
                echo $error . PHP_EOL;
            } else {
                debug($error);
            }
            return false;
        }
    }

    $facial_recognition_rtf_type = ps_value(
        "SELECT `type` AS `value`
           FROM resource_type_field
          WHERE ref = ?
        ",
        array("i",$facial_recognition_tag_field),
        null,
        "schema"
    );

    if (FIELD_TYPE_DYNAMIC_KEYWORDS_LIST != $facial_recognition_rtf_type) {
        $error = str_replace(
            ["%variable","%type"],
            ["\$facial_recognition_tag_field", $GLOBALS["lang"]["fieldtype-dynamic_keywords_list"]],
            $GLOBALS["lang"]["error_invalid_field_type"]
        );
        if (PHP_SAPI == "cli") {
            echo $error . PHP_EOL;
        } else {
            debug($error);
        }
        return false;
    }

    $annotate_enabled = true;
    $annotate_fields[] = $facial_recognition_tag_field;

    return true;
}

/**
* Crops out a selected area of an image and makes it ready to be used by FaceRecognizer.
*
* Note: The selected area should follow the normalized coordinate system.
*
* @uses get_utility_path()
* @uses debug()
*
* @param string   $image_path           Path of the source image
* @param string   $prepared_image_path  Path of the prepared image
* @param float    $x                    X position
* @param float    $y                    Y position
* @param float    $width                Width
* @param float    $height               Height
* @param boolean  $overwrite_existing   Set to TRUE to overwrite existing prepared image (if any exists)
*
* @return boolean
*/
function prepareFaceImage($image_path, $prepared_image_path, $x, $y, $width, $height, $overwrite_existing = false)
{
    if (!file_exists($image_path)) {
        debug("FACIAL_RECOGNITION: Could not find image at '{$image_path}'");
        return false;
    }

    // Use existing prepared image if one is found
    if (!$overwrite_existing && file_exists($prepared_image_path)) {
        return true;
    }

    // X, Y, width and height MUST be numeric
    if (!is_numeric($x) || !is_numeric($y) || !is_numeric($width) || !is_numeric($height)) {
        return false;
    }

    $convert_fullpath = get_utility_path('im-convert');
    if (false === $convert_fullpath) {
        debug('FACIAL_RECOGNITION: Could not find ImageMagick "convert" utility!');
        return false;
    }

    list($image_width, $image_height) = getimagesize($image_path);

    $image_path_escaped          = escapeshellarg($image_path);
    $prepared_image_path_escaped = escapeshellarg($prepared_image_path);

    $x      = escapeshellarg(round($x * $image_width, 0));
    $y      = escapeshellarg(round($y * $image_height, 0));
    $width  = escapeshellarg(round($width * $image_width, 0));
    $height = escapeshellarg(round($height * $image_height, 0));

    $cmd  = $convert_fullpath;
    $cmd .= " {$image_path_escaped} -colorspace gray -depth 8";
    $cmd .= " -crop {$width}x{$height}+{$x}+{$y}";
    $cmd .= " -resize 90x90\>";
    $cmd .= " +repage {$prepared_image_path_escaped}";

    if ('' !== run_command($cmd)) {
        return false;
    }

    return true;
}

/**
* Use FaceRecognizer to predict the association between a face and a label (i.e person name)
*
* @param string $model_file_path Path to the FaceRecognizer model state file
* @param string $test_image_path Path to the prepared image we are testing
*
* @return boolean|array  Return the label ID and probability on successful prediction or FALSE on error
*/
function faceRecognizerPredict($model_file_path, $test_image_path)
{
    if (!file_exists($model_file_path)) {
        debug("FACIAL_RECOGNITION: Could not find model at '{$model_file_path}'");
        return false;
    }

    if (!file_exists($test_image_path)) {
        debug("FACIAL_RECOGNITION: Could not find the test image at '{$test_image_path}'");
        return false;
    }

    $python_fullpath = get_utility_path('python');
    if (false === $python_fullpath) {
        debug('FACIAL_RECOGNITION: Could not find Python!');
        return false;
    }

    $faceRecognizer_path = __DIR__ . '/../lib/facial_recognition/faceRecognizer.py';
    $cmdparams = [
        '[MODEL_PATH]' => new CommandPlaceholderArg(
            $model_file_path,
            fn($p) => is_valid_rs_path($p,
                [
                $GLOBALS['storagedir'],
                $GLOBALS['facial_recognition_face_recognizer_models_location'],
            ])
        ),
        '[IMAGE_PATH]' => new CommandPlaceholderArg($test_image_path, 'is_valid_rs_path'),
    ];
    
    $command = "{$python_fullpath} {$faceRecognizer_path} [MODEL_PATH] [IMAGE_PATH]";
    $prediction = run_command($command, false, $cmdparams, 300);
    $prediction = json_decode($prediction);

    if (null === $prediction || 2 > count($prediction)) {
        return false;
    }

    return $prediction;
}
