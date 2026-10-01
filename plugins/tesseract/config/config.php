<?php

$tesseract_field = 0;
$tesseract_extensions = "pdf,jpg,jpeg,png,tiff,tif";

# Maximum number of resources to be processed with each run of cron_copy_hitcount.php
# Run plugins/tesseract/scripts/process.php to process a large number of resources e.g. after the plugin is first enabled.
$cron_tesseract_resources_per_batch = 2500;
