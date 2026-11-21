<?php

declare(strict_types=1);

include_once dirname(__DIR__) . '/include/definitions.php';

// Load only files that define constants or functions.
include_once RESOURCESPACE_BASE_PATH . '/include/version.php';
include_once RESOURCESPACE_BASE_PATH . '/include/general_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/database_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/search_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/do_search.php';
include_once RESOURCESPACE_BASE_PATH . '/include/resource_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/collections_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/language_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/message_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/node_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/encryption_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/render_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/user_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/debug_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/log_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/file_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/config_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/plugin_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/migration_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/metadata_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/job_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/tab_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/mime_types.php';
include_once RESOURCESPACE_BASE_PATH . '/include/CommandPlaceholderArg.php';
include_once RESOURCESPACE_BASE_PATH . '/include/annotation_functions.php';

// Classes (PSR-4 compliant) get handled by Composer's autoloader
include_once RESOURCESPACE_BASE_PATH . '/vendor/autoload.php';
