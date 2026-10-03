<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Plugins\OpenAiImageEdit;

use finfo;
use Throwable;

/**
 * Process an untrusted submitted file that has been base64 encoded.
 *
 * @param string $image_data File data as submitted
 * @param string $output_image_type The desired file type of the final image
 *
 * @return array{status: string, data: array, code?: int} Returns a JSend data structure with an optional code that the
 * caller can use in combination with the {@see ajax_send_response()} (by dropping the code in the final payload).
 */
function process_encoded_file(string $image_data, string $output_image_type): array
{
    $with_code = static fn(int $code, array $result) => array_merge($result, ['code' => $code]);

    // Try extracting the image data from the payload. This is what we expect so any mismatch is invalidated!
    if (!preg_match('/^data:[^;]+;base64,(.*)$/s', $image_data, $matches)) {
        return $with_code(
            400,
            ajax_response_fail(ajax_build_message(text('openai_image_edit_error_invalid_image_data'))),
        );
    }

    $image_decoded = base64_decode(str_replace(' ', '+', $matches[1]), true);
    if ($image_decoded === false || $image_decoded === '') {
        return $with_code(
            400,
            ajax_response_fail(ajax_build_message(text('openai_image_edit_error_invalid_image_data'))),
        );
    }

    // Try and detect the MIME type from the actual image data/contents
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_decoded);

    if (!isset($allowed_types[$mime_type])) {
        return $with_code(
            415,
            ajax_response_fail(ajax_build_message(
                str_replace('[filetype]', $mime_type, text('error_upload_invalid_file')),
            )),
        );
    }

    // Check PHP can actually parse it as an image
    $GLOBALS['use_error_exception'] = true;
    try {
        $image = imagecreatefromstring($image_decoded);
    } catch (Throwable $t) {
        $image = false;
    } finally {
        unset($GLOBALS['use_error_exception']);
    }

    if ($image === false) {
        return $with_code(
            400,
            ajax_response_fail(ajax_build_message(text('openai_image_edit_error_invalid_image'))),
        );
    }

    $extension = isset($allowed_types[$output_image_type])
        ? $allowed_types[$output_image_type]
        : $allowed_types[$mime_type];
    $tmp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . generateSecureKey(24) . ".{$extension}";

    $img_output = match ($output_image_type) {
        'image/jpeg' => imagejpeg($image, $tmp_file, 95),
        'image/png' => imagepng($image, $tmp_file),
        'image/webp' => function_exists('imagewebp')
            ? imagewebp($image, $tmp_file, 95)
            : file_put_contents($tmp_file, $image_decoded),
        default => false,
    };

    if (!$img_output) {
        return $with_code(
            400,
            ajax_response_fail(ajax_build_message(text('openai_image_edit_error_fail_write_tmp_file'))),
        );
    }

    return ajax_response_ok(['file_path' => $tmp_file]);
}
