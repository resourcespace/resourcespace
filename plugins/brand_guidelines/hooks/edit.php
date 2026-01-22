<?php

declare(strict_types=1);

/**
 * Ensure we always pass along the Brand Guidelines callback URL, if one was provided.
 * @global array $uploadparams
 */
function HookBrand_guidelinesEditEditbeforeheader(): void
{
    $guidelines_cb = getval(
        'guidelines_cb',
        '',
        false,
        'Montala\ResourceSpace\Plugins\BrandGuidelines\is_brand_guidelines_callback'
    );
    if ($guidelines_cb !== '') {
        $GLOBALS['urlparams']['guidelines_cb'] = $guidelines_cb;
    }
}

/**
 * Hooks onto the upload review workflow (for upload then edit mode) when "save and next" is carried out and adds the
 * first resource ID (which is supposed to be the last uploaded resource, for brand guidelines) to the manage content
 * callback (redirect URL).
 * @global array $urlparams Gets the redirecturl modified, if applicable
 * @global int $ref Only read access
 */
function HookBrand_guidelinesEditUploadreviewabortnext(): bool
{
    $guidelines_cb = getval(
        'guidelines_cb',
        '',
        false,
        'Montala\ResourceSpace\Plugins\BrandGuidelines\is_brand_guidelines_callback'
    );

    $query_string = parse_url($guidelines_cb, PHP_URL_QUERY);
    if (!($guidelines_cb !== '' && is_string($query_string))) {
        return false;
    }

    $qs_parts = [];
    parse_str($query_string, $qs_parts);
    if (!isset($qs_parts['w_ref'])) {
        $qs_parts['w_ref'] = $GLOBALS['ref'];
        $GLOBALS['urlparams']['redirecturl'] = str_replace($query_string, http_build_query($qs_parts), $guidelines_cb);
        unset($GLOBALS['urlparams']['guidelines_cb']);
    }

    return false;
}

/**
 * Hooks onto the edit then upload mode and changes the redirect URL to the provided callback. This will allow the
 * upload_batch page to use it once the file upload has finished.
 * @global array $uploadparams
 */
function HookBrand_guidelinesEditRedirectaftersavetemplate(): bool
{
    $guidelines_cb = getval(
        'guidelines_cb',
        '',
        false,
        'Montala\ResourceSpace\Plugins\BrandGuidelines\is_brand_guidelines_callback'
    );
    if ($guidelines_cb !== '') {
        $GLOBALS['uploadparams']['redirecturl'] = $guidelines_cb;
    }
    return false;
}
