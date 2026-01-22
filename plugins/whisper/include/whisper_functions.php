<?php

// Included from preview_preprocessing.php
use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Processes all unprocessed audio/video resources supported by Whisper.
 *
 * This function:
 * - Prevents concurrent execution via process locking.
 * - Selects resources with supported file extensions that have not yet been transcribed.
 * - Invokes whisper_process() for each selected resource.
 * 
 * @param   int      $size_limit The total size of files in GB to be processed in a batch, 0 = no limit
 * @return int|false Returns the number of resources processed, or false if a process lock is active.
 */
function whisper_process_unprocessed(int $size_limit = 0)
{
    // Process all resources that haven't had text extracted yet.
    global $whisper_extensions;

    // Ensure only one instance of this.
    if (is_process_lock(__FUNCTION__)) {
        logScript("Whisper: Process lock is in place");
        return false;
    }
    set_process_lock(__FUNCTION__);

    if ($size_limit <= 0) {
        // Process all unprocessed resources
        logScript("Whisper: Processing all unprocessed files");
        $extensions = explode(",", $whisper_extensions);

        $resources = ps_array("SELECT ref value FROM resource WHERE file_extension IN (" .     ps_param_insert(count($extensions)) . ") AND (whisper_processed IS NULL OR whisper_processed=0) AND archive <> 3 ORDER BY ref DESC", ps_param_fill($extensions, "s"));

        logScript("Whisper: " . count($resources) . " resources to process");
        foreach ($resources as $resource) {
            whisper_process($resource);
        }
    } else {

        // Process resources up to $size_limit, at least process one if a single file exceeds this limit
        $size_limit_bytes = $size_limit * 1024 * 1024 * 1024;
        $processed_size = 0;
        $processed_count = 0;

        logScript("Whisper: Processing " . $size_limit . "GB of files");
        $extensions = explode(",", $whisper_extensions);
        $resources = ps_query("SELECT ref, file_size FROM resource WHERE file_extension IN (" .     ps_param_insert(count($extensions)) . ") AND (whisper_processed IS NULL OR whisper_processed=0) AND archive <> 3 ORDER BY ref DESC", ps_param_fill($extensions, "s"));
        
        foreach ($resources as $resource) {
            if ($processed_size > $size_limit_bytes) {
                break;
            }

            $process_result = whisper_process($resource['ref']);

            if ($process_result) {
                $processed_size += $resource['file_size'];
                $processed_count++;
            }
        }

        logScript("Whisper: Processed " . $processed_count . " resources. Total size: " . formatfilesize($processed_size, false));

        if ($processed_count !== count($resources)) {
            logScript("Whisper: " . (count($resources) - $processed_count) . " resources were not processed");
        }
    }

    clear_process_lock(__FUNCTION__);
    return count($resources);
}

/**
 * Converts a media file to audio, transcribes it using Whisper, and stores the transcript in a metadata field.
 *
 * This function:
 * - Converts the original media file to mono, 16kHz WAV using ffmpeg.
 * - Runs Whisper transcription with an optional initial prompt.
 * - Saves the generated text into the configured resource field.
 * - Optionally adds a generated subtitle file as an alternative file.
 * - Marks the resource as processed by setting `whisper_processed = 1`.
 *
 * @param   int     $resource The resource ID to process.
 * @return  bool    True on successful processing, false if any step fails.
 */
function whisper_process(int $resource): bool
{
    global $lang, $whisper_prompt, $whisper_field, $whisper_subtitles, $whisper_transcript;

    logScript("Whisper: processing resource " . $resource);
    $data = get_resource_data($resource);
    $extension = $data["file_extension"];
    $file_path = get_resource_path($resource, true, '', false, $extension);
    if (!file_exists($file_path)) {
        logScript("Whisper: Could not find file for resource $resource, path was $file_path");
    }

    // File exists, process to the required audio format.
    $ffmpeg_fullpath = get_utility_path("ffmpeg");
    $folder = dirname($file_path);
    $audio_file = $folder . "/whisper.wav";
    $shell_exec_cmd = "$ffmpeg_fullpath -y -loglevel error -i %%FILE%% -ac 1 -ar 16000 -c:a pcm_s16le %%TARGETFILE%%";
        $shell_exec_params = [
            "%%FILE%%" => new CommandPlaceholderArg($file_path, 'is_safe_basename'),
            "%%TARGETFILE%%" => new CommandPlaceholderArg($audio_file, 'is_safe_basename'),
        ];
        logScript("Whisper: Starting audio proxy creation...");
        run_command($shell_exec_cmd, false, $shell_exec_params);
        if (!file_exists($audio_file)) {
            logScript("Whisper: Audio proxy was not created");
            return false;
        }
        logScript("Whisper: Audio proxy created at " . $audio_file);

        // Audio file created. Proceed to process using Whisper.
        $whisper_prompt = preg_replace('/[^a-zA-Z0-9 \.\,\?\!\:\-\(\)]/', '', $whisper_prompt); // Sanitise prompt.
        $shell_exec_cmd = "whisper %%FILE%% --model_dir %%MODEL_DIR%% --output_dir %%DIR%% --initial_prompt %%PROMPT%%";
        $modeldir = $GLOBALS['whisper_model_dir'] ?? get_temp_dir();
        $shell_exec_params = [
            "%%FILE%%" => new CommandPlaceholderArg($audio_file, 'is_safe_basename'),
            "%%MODEL_DIR%%" => new CommandPlaceholderArg($modeldir, [CommandPlaceholderArg::class, 'alwaysValid']), // Either set in config, or by get_temp_dir()
            "%%DIR%%" => new CommandPlaceholderArg($folder, [CommandPlaceholderArg::class, 'alwaysValid']), // Dir is always safe
            "%%PROMPT%%" => new CommandPlaceholderArg($whisper_prompt, [CommandPlaceholderArg::class, 'alwaysValid']) // Sanitised above
        ];
        logScript("Whisper: Starting Whisper...");
        run_command($shell_exec_cmd, false, $shell_exec_params);
        $text_file = dirname($file_path) . "/whisper.txt";
        if (!file_exists($text_file)) {
            logScript("Whisper: Text file was not created.");
            return false;
        }
        $text = file_get_contents($text_file);
        logScript("Whisper: Text file created at " . $text_file . ", " . strlen($text) . " characters.");

        update_field($resource, $whisper_field, $text);
        logScript("Whisper: Updated field with value.");

        if ($whisper_transcript) {
            $alt = add_alternative_file($resource, str_replace("?", "TXT", $lang["fileoftype"]), $lang["whisper_transcript_name"], $resource . ".txt", "txt", filesize($text_file));
            logScript("Whisper: TXT alternative file added with ref " . $alt);
            $alt_path = get_resource_path($resource, true, '', true, "txt", true, 1, false, '', $alt);
            logScript("Whisper: TXT alternative file path is " . $alt_path);
            copy($text_file, $alt_path);
        }

        $subtitle_path = $folder . "/whisper.srt";
        if ($whisper_subtitles && file_exists($subtitle_path)) {
            $alt = add_alternative_file($resource, str_replace("?", "SRT", $lang["fileoftype"]), $lang["whisper_subtitles_name"], $resource . ".srt", "srt", filesize($subtitle_path));
            logScript("Whisper: SRT alternative file added with ref " . $alt);
            $alt_path = get_resource_path($resource, true, '', true, "srt", true, 1, false, '', $alt);
            logScript("Whisper: SRT alternative file path is " . $alt_path);
            copy($subtitle_path, $alt_path);
        }

        $vtt_path = $folder . "/whisper.vtt";
        if ($whisper_subtitles && file_exists($vtt_path)) {
            $alt = add_alternative_file($resource, str_replace("?", "VTT", $lang["fileoftype"]), $lang["whisper_subtitles_name"], $resource . ".vtt", "vtt", filesize($vtt_path));
            logScript("Whisper: VTT alternative file added with ref " . $alt);
            $alt_path = get_resource_path($resource, true, '', true, "vtt", true, 1, false, '', $alt);
            logScript("Whisper: VTT alternative file path is " . $alt_path);
            copy($vtt_path, $alt_path);
        }

    // Clean up audio proxy
        unlink($audio_file);

    // Set processed
        ps_query("update resource set whisper_processed=1 where ref=?", ["i",$resource]);
        return true;
}
