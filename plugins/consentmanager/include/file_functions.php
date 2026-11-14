<?php

/**
 * Resolve an attachment path, creating the consent directory when needed.
 * @param int|string $ref Consent reference.
 * @return string Scrambled attachment path.
 * @throws RuntimeException If attachment storage cannot be created.
 */
function get_consent_file_path($ref)
{
    global $storagedir,$scramble_key;

    if (!is_dir($storagedir . '/consent_files/')) {
        $previous = $GLOBALS['use_error_exception'] ?? false;
        $GLOBALS['use_error_exception'] = true;
        try {
            if (!mkdir($storagedir . '/consent_files/', 0700) && !is_dir($storagedir . '/consent_files/')) {
                throw new RuntimeException('cm_external_storage_failed');
            }
        } catch (Throwable $e) {
            debug('Consent Manager: attachment directory unavailable');
            throw new RuntimeException('cm_external_storage_failed');
        } finally {
            $GLOBALS['use_error_exception'] = $previous;
        }
    }

    return $storagedir . "/consent_files/" . $ref . "_" . md5($scramble_key . $ref) . ".bin";
}
