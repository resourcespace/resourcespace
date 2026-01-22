<?php

// Included from preview_preprocessing.php
use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Processes all unprocessed audio/video resources supported by tesseract.
 *
 * This function:
 * - Prevents concurrent execution via process locking.
 * - Selects resources with supported file extensions that have not yet been transcribed.
 * - Invokes tesseract_process() for each selected resource.
 *
 * @return int|false Returns the number of resources processed, or false if a process lock is active.
 */
function tesseract_process_unprocessed()
{
    // Process all resources that haven't had text extracted yet.
    global $tesseract_extensions, $tesseract_field;
    
    // Ensure tesseract field is set before continuing
    if (!$tesseract_field) {
        logScript("tesseract: Extracted text field unset");
        return false;
    }

    // Ensure only one instance of this.
    if (is_process_lock(__FUNCTION__)) {
        logScript("tesseract: Process lock is in place");
        return false;
    }
    set_process_lock(__FUNCTION__);

    $extensions = explode(",", $tesseract_extensions);

    $resources = ps_array("SELECT ref value FROM resource WHERE file_extension in (" .     ps_param_insert(count($extensions)) . ") and (tesseract_processed is null or tesseract_processed=0) ORDER BY ref desc", ps_param_fill($extensions, "s"));

    logScript("tesseract: " . count($resources) . " resources to process.");
    foreach ($resources as $resource) {
        tesseract_process($resource);
    }

    clear_process_lock(__FUNCTION__);
    return count($resources);
}

/**
 * Extracts text from an image or scanned document using tesseract OCR.
 * Marks the resource as processed by setting `tesseract_processed = 1`.
 *
 * @param   int     $resource The resource ID to process.
 * @return  bool    True on successful processing, false if any step fails.
 */
function tesseract_process(int $resource): bool
{
    global $lang, $tesseract_field;

    // Ensure tesseract field is set before continuing
    if (!$tesseract_field) {
        logScript("tesseract: Extracted text field unset");
        return false;
    }

    logScript("tesseract: processing resource " . $resource);
    $data = get_resource_data($resource);
    $extension = $data["file_extension"];

    $page=1;$text="";
    while(true)
    {
        $file_path = get_resource_path($resource, true, '', false, "jpg", true, $page);
        if (!file_exists($file_path)) {
            $file_path = get_resource_path($resource, true, 'scr', false, "jpg", true, $page); // Try scr also.
        }
        if (!file_exists($file_path)) {
            logScript("tesseract: Page " . $page . " does not exist, finishing.");
            break;
        }

        // File exists for this page, process.
        $folder = dirname($file_path);
        $text_base = $folder . "/tesseract";
        $text_file = $text_base . ".txt";
        $shell_exec_cmd = "tesseract %%FILE%% %%OUTPUT%%";
        $shell_exec_params = [
                "%%FILE%%" => new CommandPlaceholderArg($file_path, 'is_safe_basename'),
                "%%OUTPUT%%" => new CommandPlaceholderArg($text_base, [CommandPlaceholderArg::class, 'alwaysValid']) // Always valid, we generated it above
            ];
        logScript("tesseract: Starting tesseract...");
        run_command($shell_exec_cmd, false, $shell_exec_params);

        if (!file_exists($text_file)) {
                logScript("tesseract: Text file was not created.");
                return false;
            }

        $text .= file_get_contents($text_file);
        logScript("tesseract: Text file created at " . $text_file . ", " . strlen($text) . " characters.");

        // Clean up text file
        unlink($text_file);
        $page++;
    }

    if (strlen($text)>0)
    {
        update_field($resource, $tesseract_field, $text);
        logScript("tesseract: Updated field with value.");
    }
    else   
    {
        logScript("tesseract: No text extracted.");
    }

    // Set processed
    ps_query("update resource set tesseract_processed=1 where ref=?", ["i",$resource]);
    return true;
}
