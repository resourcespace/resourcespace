<?php

/**
 * Register metadata field settings so configured fields cannot be deleted.
 *
 * @return void
 */
function HookPanoramic_viewerAllInitialise()
{
    global $panoramic_viewer_fieldvars;
    config_register_core_fieldvars('Panoramic Viewer plugin', $panoramic_viewer_fieldvars);
}

/**
 * Load the locally bundled viewer and styles.
 *
 * @return void
 */
function HookPanoramic_viewerAllAdditionalheaderjs()
{
    global $baseurl_short, $css_reload_key;
    $assets = $baseurl_short . 'lib/panoramic-viewer/';
    ?>
    <link rel="stylesheet" href="<?php echo escape($assets . 'viewer.css?' . $css_reload_key); ?>">
    <script defer src="<?php echo escape($assets . 'viewer.js?' . $css_reload_key); ?>"></script>
    <?php
}
