<?php

// Populate existing resources gradually
include_once __DIR__ . '/../../include/image_processing.php';
backfill_resource_blurhashes(10000);
set_sysvar('last_blurhash_backfill', date('Y-m-d H:i:s'));
