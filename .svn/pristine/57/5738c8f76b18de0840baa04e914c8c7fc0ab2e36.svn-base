<?php

include_once dirname(__FILE__, 2) . '/include/tesseract_functions.php';

/**
 * Add tesseract processing to the cron.
 *
 * @return void
 */
function HookTesseractAllCron()
{
    tesseract_process_unprocessed();
}

/**
 * Runs tesseract processing after preview creation.
 *
 * @param int $resource    The resource reference ID that has just had previews created.
 * @param int $alternative The alternative file ID, or -1 if processing the main resource.
 * @param bool $generate_all Flag to indicate if hook has been triggered during full preview creation process
 *
 * @return void
 */
function HookTesseractAllAfterpreviewcreation(int $resource, int $alternative, bool $generate_all = false): void
{
    global $lang,$tesseract_extensions;

    $extension = get_resource_data($resource)["file_extension"];

    if ($alternative === -1 && in_array($extension, explode(",",$tesseract_extensions))) {
        // Nothing to do for alternatives
        // Extract if configured file type
        set_processing_message($lang["tesseract-generating"] . " " . $resource);
        tesseract_process($resource);
    }
}