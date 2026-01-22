<?php

use function ImageBanks\autoloadProviders;

/**
 * Defines the maximum number of instances a Provider with multi-instance support can have.
 * @var int
 */
const IMAGE_BANKS_MAX_INSTANCE_COUNT = 100;

include_once dirname(__DIR__) . '/include/image_banks_functions.php';

$image_banks_loaded_providers = autoloadProviders();

/* 
Note: multi-instance Providers (e.g. ResourceSpace) will have to be selected by the user after they've 
configured at least one instance since in that case the instance represents the provider.
*/
$image_banks_selected_providers = ['Pixabay', 'Shutterstock'];