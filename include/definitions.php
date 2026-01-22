<?php

define('RESOURCESPACE_BASE_PATH', dirname(__DIR__));

// current upgrade level of ResourceSpace (used for migration scripts, will set sysvar using this if not already defined)
define('SYSTEM_UPGRADE_LEVEL', 29);

// PHP VERSION AND MINIMUM SUPPORTED
if (!defined('PHP_VERSION_ID')) {
    // Only needed PHP versions < 5.2.7 - we don't support those versions in the rest of the code but this is the one place where we need to (to tell them to upgrade).
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}
define('PHP_VERSION_SUPPORTED', 70400); // 7.4.0 is the minimum version supported

// ------------------------- FIELD TYPES -------------------------

define('FIELD_TYPE_TEXT_BOX_SINGLE_LINE', 0);
define('FIELD_TYPE_TEXT_BOX_MULTI_LINE', 1);
define('FIELD_TYPE_CHECK_BOX_LIST', 2);
define('FIELD_TYPE_DROP_DOWN_LIST', 3);
define('FIELD_TYPE_DATE_AND_OPTIONAL_TIME', 4);
define('FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE', 5);
define('FIELD_TYPE_EXPIRY_DATE', 6);
define('FIELD_TYPE_CATEGORY_TREE', 7);
define('FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE', 8);
define('FIELD_TYPE_DYNAMIC_KEYWORDS_LIST', 9);
define('FIELD_TYPE_DATE', 10);
define('FIELD_TYPE_RADIO_BUTTONS', 12);
define('FIELD_TYPE_WARNING_MESSAGE', 13);
define('FIELD_TYPE_DATE_RANGE', 14);

$field_types = array(
    FIELD_TYPE_TEXT_BOX_SINGLE_LINE             => "fieldtype-text_box_single_line",
    FIELD_TYPE_TEXT_BOX_MULTI_LINE              => "fieldtype-text_box_multi-line",
    FIELD_TYPE_CHECK_BOX_LIST                   => "fieldtype-check_box_list",
    FIELD_TYPE_DROP_DOWN_LIST                   => "fieldtype-drop_down_list",
    FIELD_TYPE_DATE_AND_OPTIONAL_TIME           => "fieldtype-date_and_optional_time",
    FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE        => "fieldtype-text_box_large_multi-line",
    FIELD_TYPE_EXPIRY_DATE                      => "fieldtype-expiry_date",
    FIELD_TYPE_CATEGORY_TREE                    => "fieldtype-category_tree",
    FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE   => "fieldtype-text_box_formatted_and_tinymce",
    FIELD_TYPE_DYNAMIC_KEYWORDS_LIST            => "fieldtype-dynamic_keywords_list",
    FIELD_TYPE_DATE                             => "fieldtype-date",
    FIELD_TYPE_RADIO_BUTTONS                    => "fieldtype-radio_buttons",
    FIELD_TYPE_WARNING_MESSAGE                  => "fieldtype-warning_message",
    FIELD_TYPE_DATE_RANGE                       => "fieldtype-date_range"
);

$FIXED_LIST_FIELD_TYPES = array(
    FIELD_TYPE_CHECK_BOX_LIST,
    FIELD_TYPE_DROP_DOWN_LIST,
    FIELD_TYPE_CATEGORY_TREE,
    FIELD_TYPE_DYNAMIC_KEYWORDS_LIST,
    FIELD_TYPE_RADIO_BUTTONS
);

$TEXT_FIELD_TYPES = array(
    FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
    FIELD_TYPE_TEXT_BOX_MULTI_LINE,
    FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE,
    FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE,
    FIELD_TYPE_WARNING_MESSAGE
);

$DATE_FIELD_TYPES = array(
    FIELD_TYPE_DATE_AND_OPTIONAL_TIME,
    FIELD_TYPE_EXPIRY_DATE,
    FIELD_TYPE_DATE,
    FIELD_TYPE_DATE_RANGE
);

/*
From version 10, ResourceSpace converted all non-fixed list types (e.g text & date fields) to use nodes. Resources
with values in these field types, will always have only one node associated. The field may have multiple nodes in order
to handle changes to a resource value.

Note: date ranges were already using nodes so they've been excluded from having this behaviour. In addition, for date ranges,
a resource will have up to 2 nodes associated (the start and/or end dates).
*/
define('NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES', array_merge($TEXT_FIELD_TYPES, array_diff($DATE_FIELD_TYPES, [FIELD_TYPE_DATE_RANGE])));

$NODE_FIELDS = array_merge($FIXED_LIST_FIELD_TYPES, [FIELD_TYPE_DATE_RANGE], NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES);

// ------------------------- LOG_CODE_ -------------------------

// codes used for log entries (including resource, collection and activity logs)

define('LOG_CODE_ACCESS_CHANGED', 'a');
define('LOG_CODE_ALTERNATIVE_CREATED', 'b');
define('LOG_CODE_CREATED', 'c');
define('LOG_CODE_COPIED', 'C');
define('LOG_CODE_DOWNLOADED', 'd');
define('LOG_CODE_EDITED', 'e');
define('LOG_CODE_STATICSYNC_FILE_MODIFIED', 'sf');
define('LOG_CODE_EMAILED', 'E');
define('LOG_CODE_LOGGED_IN', 'l');
define('LOG_CODE_MULTI_EDITED', 'm');
define('LOG_CODE_NODE_REVERT', 'N');
define('LOG_CODE_CREATED_BY_CHANGED', 'o');
define('LOG_CODE_USER_OPT_IN', 'O');
define('LOG_CODE_REVERTED_REUPLOADED', 'r');
define('LOG_CODE_REPLACED', 'f');
define('LOG_CODE_REORDERED', 'R');
define('LOG_CODE_STATUS_CHANGED', 's');
define('LOG_CODE_SYSTEM', 'S');
define('LOG_CODE_TRANSFORMED', 't');
define('LOG_CODE_UPLOADED', 'u');
define('LOG_CODE_UPLOADED_PREVIEW', 'up');
define('LOG_CODE_UNSPECIFIED', 'U');
define('LOG_CODE_VIEWED', 'v');
define('LOG_CODE_DELETED', 'x');
define('LOG_CODE_DELETED_PERMANENTLY', 'xx');
define('LOG_CODE_DELETED_ALTERNATIVE', 'y');
define('LOG_CODE_ENABLED', '+');
define('LOG_CODE_DISABLED', '-');
define('LOG_CODE_LOCKED', 'X');
define('LOG_CODE_UNLOCKED', 'Y');
define('LOG_CODE_DELETED_ACCESS_KEY', 'XK');
define('LOG_CODE_FAILED_LOGIN_ATTEMPT', 'Xl');
define('LOG_CODE_EXTERNAL_UPLOAD', 'EUP');
define('LOG_CODE_EDITED_RESOURCE', 'EDT');
define('LOG_CODE_SET_NO_FILE', 'NOFIL');
define('LOG_CODE_UNSET_NO_FILE', 'FIL');
define('LOG_CODE_COLLECTION_REMOVED_RESOURCE', 'r');
define('LOG_CODE_COLLECTION_REMOVED_ALL_RESOURCES', 'R');
define('LOG_CODE_COLLECTION_DELETED_ALL_RESOURCES', 'D');
define('LOG_CODE_COLLECTION_DELETED_RESOURCE', 'd');
define('LOG_CODE_COLLECTION_ADDED_RESOURCE', 'a');
define('LOG_CODE_COLLECTION_ADDED_RESOURCE_COPIED', 'c');
define('LOG_CODE_COLLECTION_ADDED_RESOURCE_COMMENT', 'm');
define('LOG_CODE_COLLECTION_ADDED_RESOURCE_RATING', '*');
define('LOG_CODE_COLLECTION_SHARED_COLLECTION', 'S');
define('LOG_CODE_COLLECTION_EMAILED_COLLECTION', 'E');
define('LOG_CODE_COLLECTION_SHARED_RESOURCE_WITH', 's');
define('LOG_CODE_COLLECTION_STOPPED_SHARING_COLLECTION', 'T');
define('LOG_CODE_COLLECTION_STOPPED_RESOURCE_ACCESS', 't');
define('LOG_CODE_COLLECTION_DELETED_COLLECTION', 'X');
define('LOG_CODE_COLLECTION_BATCH_TRANSFORMED', 'b');
define('LOG_CODE_COLLECTION_ACCESS_CHANGED', 'A');
define('LOG_CODE_COLLECTION_COLLECTION_DOWNLOADED', 'Z');
define('LOG_CODE_COLLECTION_SHARED_UPLOAD', 'SEU');
define('LOG_CODE_COLLECTION_EDIT_UPLOAD_SHARE', 'EEU');
define('LOG_CODE_JOB_ADDED', 'J');
define('LOG_CODE_JOB_COMPLETED', 'JC');
define('LOG_CODE_JOB_DELETED', 'JD');

// validates LOG_CODE is legal
function LOG_CODE_validate($log_code)
{
    return in_array($log_code, LOG_CODE_get_all());
}

// returns all allowable LOG_CODEs
function LOG_CODE_get_all()
{
    return definitions_get_by_prefix('LOG_CODE');
}

// ------------------------- SYSTEM NOTIFICATION TYPES -------------------------
define('MANAGED_REQUEST', 1);
define('COLLECTION_REQUEST', 2);
define('USER_REQUEST', 3);
define('SUBMITTED_RESOURCE', 4);
define('SUBMITTED_COLLECTION', 5);

// Advanced search mappings. Used to translate field names to !properties special search codes
$advanced_search_properties = array("media_heightmin" => "hmin",
                                  "media_heightmax" => "hmax",
                                  "media_widthmin" => "wmin",
                                  "media_widthmax" => "wmax",
                                  "media_filesizemin" => "fmin",
                                  "media_filesizemax" => "fmax",
                                  "media_fileextension" => "fext",
                                  "properties_haspreviewimage" => "pi",
                                  "properties_contributor" => "cu",
                                  "properties_orientation" => "orientation"
                                  );


// ------------------------- JOB STATUS / GENERIC STATUS CODES -------------------------
define('STATUS_DISABLED', 0);
define('STATUS_ACTIVE', 1);
define('STATUS_COMPLETE', 2);
define('STATUS_INPROGRESS', 3);
define('STATUS_ERROR', 5);

// ------------------------- JOB PRIORITY CODES -------------------------
define('JOB_PRIORITY_IMMEDIATE', 0);
define('JOB_PRIORITY_USER', 1);
define('JOB_PRIORITY_SYSTEM', 2);
define('JOB_PRIORITY_COMPLETED', 9);

// -------------------- General definitions --------------------
define('RESOURCE_LOG_APPEND_PREVIOUS', -1);    // used to specify that we want to append the previous resource_log entry

// Global definition of link bullet carets - easy to change link caret style in the future.
define('LINK_CARET', '<i aria-hidden="true" class="icon-chevron-right"></i>&nbsp;');
define('LINK_CARET_BACK', '<i aria-hidden="true" class="icon-chevron-left"></i>&nbsp;');
define('LINK_PLUS', '<i aria-hidden="true" class="icon-plus"></i>&nbsp;');
define('LINK_PLUS_CIRCLE', '<i aria-hidden="true" class="icon-circle-plus"></i>&nbsp;');
define('LINK_CHEVRON_RIGHT', '<i aria-hidden="true" class="icon-chevron-right"></i>&nbsp;');
define('UPLOAD_ICON', '<i aria-hidden="true" class="icon-upload"></i>&nbsp;');
define('CONTRIBUTIONS_ICON', '<i aria-hidden="true" class="icon-user-round-plus"></i>&nbsp;');
define('DASH_ICON', '<i aria-hidden="true" class="icon-layout-dashboard"></i>&nbsp;');
define('FEATURED_COLLECTION_ICON', '<i aria-hidden="true" class="icon-folder"></i>&nbsp;');
define('RECENT_ICON', '<i aria-hidden="true" class="icon-clock"></i>&nbsp;');
define('HELP_ICON', '<i aria-hidden="true" class="icon-book-marked"></i>&nbsp;');
define('HOME_ICON', '<i aria-hidden="true" class="icon-house"></i>&nbsp;');
define('SEARCH_ICON', '<i class="icon-search" aria-hidden="true"></i>&nbsp;');
define('ICON_EDIT', '<i class="icon-pencil" aria-hidden="true"></i>&nbsp;');
define('ICON_REMOVE', '<i class="icon-circle-minus" aria-hidden="true"></i>&nbsp;');
define('ICON_FOLDER', '<i class="icon-folder" aria-hidden="true"></i>&nbsp;');
define('ICON_CUBE', '<i class="icon-box" aria-hidden="true"></i>&nbsp;');

define('NODE_TOKEN_PREFIX', '@@');
define('NODE_TOKEN_OR', '|');
define('NODE_TOKEN_NOT', '!');
define('NODE_NAME_STRING_SEPARATOR', '@@|@@');

// Full text search prefix
define('FULLTEXT_SEARCH_PREFIX', '@FULL_TEXT');
define('FULLTEXT_SEARCH_QUOTES_PLACEHOLDER', '[QUOTES]');

// Simple Search pills' delimiter
define('TAG_EDITOR_DELIMITER', '~');

// Facial recognition
define('FACIAL_RECOGNITION_CROP_SIZE_PREFIX', '_facial_recognition_crop_');
define('FACIAL_RECOGNITION_PREPARED_IMAGE_EXT', 'pgm');
// --------------------------------------------------------------------------------

// ------------------------- FILTER (SEARCH, EDIT) LOGICAL OPERATORS -------------------------
define('RS_FILTER_ALL', 1);
define('RS_FILTER_NONE', 2);
define('RS_FILTER_ANY', 3);

// Related node operators
define('RS_FILTER_NODE_NOT_IN', 0);
define('RS_FILTER_NODE_IN', 1);

// used internally within this file

function definitions_get_by_prefix($prefix)
{
    $return_definitions = array();
    foreach (get_defined_constants() as $key => $value) {
        if (preg_match('/^' . $prefix . '/', $key)) {
            $return_definitions[$key] = $value;
        }
    }
    return $return_definitions;
}

$h264_profiles = array(
    "Baseline" => "242E0",
    "Main" => "4D40",
    "High" => "6400",
    "Extended" => "58A0"
    );

// Array of default html tags that are permitted in field data
$permitted_html_tags =  array(
    'html',
    'body',
    'div',
    'span',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'br',
    'em',
    'strong',
    'ol',
    'ul',
    'li',
    'small',
    'sub',
    'sup',
    'code',
    'blockquote',
    'pre',
    's',
    'ins',
    'del',
    'mark',
    'b',
    'u',
    'p',
    'i',
    'big',
    'cite',
    'q',
    'var',
    'samp',
    'kbd',
    'tt'
);

// Array of default html attributes that are permitted in field data
$permitted_html_attributes = array('id', 'class', 'style');

// Standard paths (e.g libraries)
$jquery_path = "/lib/js/jquery-3.6.0.min.js";
$jquery_ui_path = "/lib/js/jquery-ui-1.13.2.min.js";
define('LIB_OPENSEADRAGON', '/lib/openseadragon_2.4.2');

// Define dropdown action categories
define('ACTIONGROUP_RESOURCE', 1);
define('ACTIONGROUP_COLLECTION', 2);
define('ACTIONGROUP_EDIT', 3);
define('ACTIONGROUP_SHARE', 4);
define('ACTIONGROUP_RESEARCH', 5);
define('ACTIONGROUP_ADVANCED', 6);

// Global variable that contains variable names that reference metadata fields considered to be core to ResourceSpace
// and shouldn't be deleted. Plugins can register their own with config_register_core_fieldvars()
// IMPORTANT - not an actual definition/constant, the value will change when using the config_register_core_fieldvars().
$corefields = array(
    "BASE" => array(
        'filename_field',
        'view_title_field',
        'date_field',
        'reset_date_field',
        'download_filename_field',
        'extracted_text_field',
        'facial_recognition_tag_field',
        'staticsync_filepath_to_field',
        'staticsync_extension_mapping_append_values_fields',
        'portrait_landscape_field',
        'metadata_template_title_field',
        'thumbs_display_fields',
        'list_display_fields',
        'sort_fields',
        'config_sheetlist_fields',
        'config_sheetthumb_fields',
        'config_sheetsingle_fields',
        'related_search_searchcrumb_field',
        'warn_field_request_approval',
        'rating_field',
        'iiif_identifier_field',
        'iiif_description_field',
        'iiif_license_field',
        'iiif_sequence_field',
        'join_fields',
        'annotate_fields',
    )
);

// Similar to $corefields but holds list of field refs we want the system to prevent from deleting. Mostly plugins will want
// to register these IF the plugin is configured to use certain metadata fields.
// IMPORTANT - not an actual definition/constant, the value will change when using the config_register_core_field_refs().
$core_field_refs = [];

// ----------------------------------------------
// COLLECTIONS
// ----------------------------------------------
define("COLLECTION_TYPE_STANDARD", 0);
define("COLLECTION_TYPE_UPLOAD", 1); # for collections used in upload then edit mode
define("COLLECTION_TYPE_SELECTION", 2); # selecting resources to be edited in batch for the active user (allowed only one per user)
define("COLLECTION_TYPE_FEATURED", 3); # featured collections (used for both parents and children featured collections)
define("COLLECTION_TYPE_PUBLIC", 4); # public collections
define("COLLECTION_TYPE_SHARE_UPLOAD", 5); # External upload share
define("COLLECTION_TYPE_REQUEST", 6); # Resource requests - can't be edited

$FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS = array(
    "no_image" => 0,
    "most_popular_image" => 1,
    "most_popular_images" => 10,
    "most_recent_image" => 20,
    "manual" => 100,
);
$COLLECTION_PUBLIC_TYPES = array(COLLECTION_TYPE_PUBLIC, COLLECTION_TYPE_FEATURED);

// ----------------------------------------------
// RESOURCE ACCESS TYPES
// ----------------------------------------------
define("RESOURCE_ACCESS_FULL", 0); # Full Access (download all sizes)
define("RESOURCE_ACCESS_RESTRICTED", 1); # 1 = Restricted Access (download only those sizes that are set to allow restricted downloads)
define("RESOURCE_ACCESS_CONFIDENTIAL", 2); # Confidential (no access)
define("RESOURCE_ACCESS_CUSTOM_GROUP", 3); # custom group access
define("RESOURCE_ACCESS_INVALID_REQUEST", 99); # invalid resource request eg. invalid resource ref
const RESOURCE_ACCESS_TYPES = [
    RESOURCE_ACCESS_FULL,
    RESOURCE_ACCESS_RESTRICTED,
    RESOURCE_ACCESS_CONFIDENTIAL,
    RESOURCE_ACCESS_CUSTOM_GROUP,
];

// ----------------------------------------------
// MESSAGES
// ----------------------------------------------
// Enumerated types of message.  Note the base two offset for binary combination.
DEFINE("MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN", 1);
DEFINE("MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL", 2);
DEFINE("MESSAGE_ENUM_NOTIFICATION_TYPE_USER_MESSAGE", 4);
DEFINE("MESSAGE_ENUM_NOTIFICATION_TYPE_RESERVED_2", 8);
DEFINE("MESSAGE_ENUM_NOTIFICATION_TYPE_RESERVED_3", 16);

DEFINE("MESSAGE_DEFAULT_TTL_SECONDS", 60 * 60 * 24 * 7);        // 7 days

// ----------------------------------------------
// MIGRATIONS
// ----------------------------------------------
define('MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX', '!deprecated');
define('MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX_CATEGORY_TREE', "-1,,!deprecated\n");


// ----------------------------------------------
// DEPRECATED PARAMETERS
// ----------------------------------------------
define('DEPRECATED_STARSEARCH', 0);

# Keyboard control codes
# Previous/next resource: left/right arrows
$keyboard_navigation_prev = 37;
$keyboard_navigation_next = 39;
# add resource to collection, 'a'
$keyboard_navigation_add_resource = 65;
# previous page in document preview, ','
$keyboard_navigation_prev_page = 188;
# next page in document preview, '.'
$keyboard_navigation_next_page = 190;
# view all results, '/'
$keyboard_navigation_all_results = 191;
# toggle thumbnails in collections frame, 't'
$keyboard_navigation_toggle_thumbnails = 84;
# view all resources from current collection, 'v'
$keyboard_navigation_view_all = 86;
# zoom to/from preview, default 'z'
$keyboard_navigation_zoom = 90;
# close modal, escape
$keyboard_navigation_close = 27;
$keyboard_navigation_video_search_backwards = 74;
# play/pause - 'k'
$keyboard_navigation_video_search_play_pause = 75;
# play forwards - 'l'
$keyboard_navigation_video_search_forwards = 76;

# Array of valid utilities (as used by get_utility_path() function) used to create files used in offline job handlers e.g. create_alt_file. create_download_file. Plugins can extend this
$offline_job_prefixes = array("ffmpeg","im-convert","im-mogrify","ghostscript","im-composite","archiver");

# Regular expression defining e-mail format
# Currently exclusively used for comments functionality - checking of valid (anonymous) email addresses entered in JS and in back-end PHP
$regex_email = "[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}";

// LEAFLET MAPS - AVAILABLE COLOURS
$MARKER_COLORS = array(
    0 => 'Blue',
    1 => 'Red',
    2 => 'Green',
    3 => 'Orange',
    4 => 'Yellow',
    5 => 'Black',
    6 => 'Grey',
    7 => 'Violet',
    8 => 'Gold'
);

// Reports
const REPORT_PLACEHOLDER_NON_CORRELATED_SQL = '[non_correlated_sql]';

// ----------------------------------------------
// SYSTEM - GENERAL
// ----------------------------------------------
const SYSTEM_REQUIRED_PHP_MODULES = [
    'curl' => 'curl_init',
    'gd' => 'imagecrop',
    'xml' => 'xml_parser_create',
    'mbstring' => 'mb_strtoupper',
    'intl' => 'locale_get_default',
    'json' => 'json_decode',
    'zip' => 'zip_open',
    'apcu' => 'apcu_fetch',
    'dom' => 'dom_import_simplexml',
    'mysqli' => 'mysqli_init',
];

// Chunking a list of IDs (with the highest ID length) in batches of this size should be well within the default max_allowed_packet size
const SYSTEM_DATABASE_IDS_CHUNK_SIZE = 500;

// How many times should we retry our previous action before giving up?
// NOTE: don't set too high or your script may sit and wait for minutes depending on database configuration.
const SYSTEM_DATABASE_MAX_RETRIES = 2;

/*
List of ResourceSpace system utilities (core and optional). If adding a new entry, make sure get_utility_path() is updated
as well to handle the new entry.

A system utility structure will have the following keys/properties:-
- required = is this utility core to ResourceSpace?
- path_var_name = the variable name holding the utility path {@see get_utility_path()}
- display_name = self explanatory
- show_on_check_page = when we use multiple components of a bigger "package" (e.g IM has convert, identify, composite and mogrify)
- version_check - argument = the argument used to get the version out (NB: some utilities do this on STDERR). Default: -version
- version_check - callback = function used to verify we got the expected version. It should return an array as expected
    by the check.php page:
    * - utility - Updated utility structure (if required to do so)
    * - found - PHP bool representing whether we've found what we were expecting in the version output.

Example:
'utilityname' => [
    'required' => false,
    'path_var_name' => 'utilityname_path',
    'display_name' => 'utilityname',
    'show_on_check_page' => true,
    'version_check' => [
        'argument' => '',
        'callback' => [
            'fct_name' => 'check_utility_cli_version_found_by_name',
            'args' => [['utilityname', 'anyOtherRelevantString']],
        ],
    ],
],
*/
const RS_SYSTEM_UTILITIES = [
    'im-convert' => [
        'required' => true,
        'path_var_name' => 'imagemagick_path',
        'display_name' => 'ImageMagick/GraphicsMagick - convert',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_imagemagick_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'im-identify' => [
        'required' => true,
        'path_var_name' => 'imagemagick_path',
        'display_name' => 'ImageMagick/GraphicsMagick - identify',
        'show_on_check_page' => false,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_imagemagick_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'im-composite' => [
        'required' => true,
        'path_var_name' => 'imagemagick_path',
        'display_name' => 'ImageMagick/GraphicsMagick - composite',
        'show_on_check_page' => false,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_imagemagick_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'im-mogrify' => [
        'required' => true,
        'path_var_name' => 'imagemagick_path',
        'display_name' => 'ImageMagick/GraphicsMagick - mogrify',
        'show_on_check_page' => false,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_imagemagick_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'ghostscript' => [
        'required' => true,
        'path_var_name' => 'ghostscript_path',
        'display_name' => 'Ghostscript',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['ghostscript']],
            ],
        ],
    ],
    'ffmpeg' => [
        'required' => true,
        'path_var_name' => 'ffmpeg_path',
        'display_name' => 'FFmpeg',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['ffmpeg', 'avconv']],
            ],
        ],
    ],
    'ffprobe' => [
        'required' => true,
        'path_var_name' => 'ffmpeg_path',
        'display_name' => 'ffprobe',
        'show_on_check_page' => false,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['ffprobe', 'avprobe']],
            ],
        ],
    ],
    'exiftool' => [
        'required' => true,
        'path_var_name' => 'exiftool_path',
        'display_name' => 'ExifTool',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '-ver',
            'callback' => [
                'fct_name' => 'check_exiftool_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'php' => [
        'required' => false,
        'path_var_name' => 'php_path',
        'display_name' => 'PHP',
        'show_on_check_page' => false,
        'version_check' => [
            'argument' => '',
            'callback' => [
                'fct_name' => '',
                'args' => [],
            ],
        ],
    ],
    'python' => [
        'required' => false,
        'path_var_name' => 'python_path',
        'display_name' => 'Python',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '--version',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['Python']],
            ],
        ],
    ],
    'opencv' => [
        'required' => false,
        'path_var_name' => 'python_path',
        'display_name' => 'OpenCV (Python module)',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '-c "import cv2; print(cv2.__version__)"',
            'callback' => [
                'fct_name' => 'check_numeric_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'archiver' => [
        'required' => false,
        'path_var_name' => 'archiver_path',
        'display_name' => 'Archiver',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '--help',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['zip', '7z', 'tar']],
            ],
        ],
    ],
    'fits' => [
        'required' => false,
        'path_var_name' => 'fits_path',
        'display_name' => 'File Information Tool Set (FITS)',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '-v',
            'callback' => [
                'fct_name' => 'check_numeric_cli_version_found',
                'args' => [],
            ],
        ],
    ],
    'pdftotext' => [
        'required' => false,
        'path_var_name' => 'pdftotext_path',
        'display_name' => 'pdftotext',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '-v',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['pdftotext']],
            ],
        ],
    ],
    'blender' => [
        'required' => false,
        'path_var_name' => 'blender_path',
        'display_name' => 'Blender',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '-v',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['blender']],
            ],
        ],
    ],
    'unoconvert' => [
        'required' => false,
        'path_var_name' => 'unoconv_path',
        'display_name' => 'Unoserver',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '--version',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['unoconvert']],
            ],
        ],
    ],
    'calibre' => [
        'required' => false,
        'path_var_name' => 'calibre_path',
        'display_name' => 'Calibre',
        'show_on_check_page' => true,
        'version_check' => [
            'argument' => '--version',
            'callback' => [
                'fct_name' => 'check_utility_cli_version_found_by_name',
                'args' => [['calibre']],
            ],
        ],
    ],
];

const SENSITIVE_VARIABLE_NAMES = [
    'mysql_server',
    'mysql_username',
    'mysql_password',
    'mysql_db',
    'mysql_log_location',
    'mysqli_ssl_server_cert',
    'mysqli_ssl_ca_cert',
    'read_only_db_username',
    'read_only_db_password',
    'storagedir',
    'storageurl',
    'email_notify',
    'scramble_key',
    'scramble_key_old',
    'api_scramble_key',
    'smtp_username',
    'smtp_password',
    'homeanim_folder',
    'remote_config_url',
    'remote_config_key',
    'syncdir',
    'debug_log_location',
    'log_error_messages_url',
    'CORS_whitelist',
    'facial_recognition_face_recognizer_models_location',
    'fstemplate_alt_scramblekey',

    // Plugins
    'ldapauth_rootdn',
    'ldapauth_rootpass',
    'ldapauth',
    'tms_link_user',
    'tms_link_password',
    'wordpress_sso_secret',
    'youtube_publish_username',
    'youtube_publish_password',
    'vimeo_publish_client_id',
    'vimeo_publish_client_secret',
    'vimeo_publish_access_token',
    'museumplus_api_user',
    'museumplus_api_pass',
    'emu_email_notify',
];

const WORKFLOW_DEFAULT_ICON = "cog";
const WORKFLOW_DEFAULT_ICONS = [
    '-2'    => 'file-input',
    '-1'    => 'eye',
    '0'     => 'check',
    '1'     => 'clock',
    '2'     => 'archive',
    '3'     => 'trash-2',
    ];

// File extensions that can be natively viewed in the browser
const VIEW_IN_BROWSER_EXTENSIONS = ['pdf', 'mp3'];


// Array of file extensions and their corresponding MIME types supported for browser inline viewing
const INLINE_VIEWABLE_TYPES = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'txt' => 'text/plain',
];


// PHP stream wrappers that will be blocked when attempting uploads by URL via the API.
const BLOCKED_STREAM_WRAPPERS = ['php', 'file'];

// Separator to use when rendering date range field values
define('DATE_RANGE_SEPARATOR', " / ");

// Maximum age in hours of the actions that will be included in user action notification emails
define('ACTIONS_EMAIL_MAX_AGE', 168);

// Array of permitted api calls that can be made using native mode authentication
// Only those required for browser access must be added.
const API_NATIVE_WHITELIST = [
    'add_resource_to_collection',
    'collection_add_resources',
    'collection_remove_resources',
    'create_collection',
    'delete_access_keys',
    'delete_alternative_file',
    'delete_resource',
    'delete_tabs',
    'do_search',
    'get_collections_resource_count',
    'get_dash_search_data',
    'get_field_options',
    'get_users',
    'get_user_message',
    'relate_all_resources',
    'remove_resource_from_collection',
    'reorder_featured_collections',
    'reorder_tabs',
    'save_tab',
    'send_collection_to_admin',
    'send_user_message',
    'update_related_resource',
    'toggle_active_state_for_nodes',
    'put_resource_data',
    'get_processing_message',
    'delete_resources_in_collection',
    'checkperm',
    'faces_set_node',
    'update_field'
];

const DEFAULT_DOWNLOAD_FILENAME_FORMAT = 'RS%resource_%filename%size.%extension';

// get_system_status() severity types
const SEVERITY_CRITICAL = 0;
const SEVERITY_WARNING = 1;
const SEVERITY_NOTICE = 2;

// get_system_status() debug log warning config
// Trigger a warning if debug log has been enabled over this duration
// Default: 3 days (3 * 24 * 60 * 60 seconds)
$debug_log_warning_duration = 3 * 24 * 60 * 60; 
// Trigger a warning if debug log has exceeded this file size
// Default: 50MB (50 * 1024 * 1024 bytes)
$debug_log_warning_size = 50 * 1024 * 1024;

// ------------ PREVIEW IMAGE STATUS CODES (the resource table has_image  column)  ----------
const RESOURCE_PREVIEWS_NONE = 0;
const RESOURCE_PREVIEWS_ALL = 1;
const RESOURCE_PREVIEWS_MINIMAL = 2;

# Set Normalizer form based on user language
# Options need to be in language_code=>option.
# i.e. to use Canonical Decomposition followed by Canonical Composition: "en" => Normalizer::NFD
# Possible options are
# Normalizer::NFD;  Normalization Form D (NFD) - Canonical Decomposition
# Normalizer::NFKD; Normalization Form KD (NFKD) - Compatibility Decomposition
# Normalizer::NFC;  Normalization Form C (NFC) - Canonical Decomposition followed by Canonical Composition
# Normalizer::NFKC; Normalization Form KC (NFKC) - Compatibility Decomposition, followed by Canonical Composition
# See https://www.php.net/manual/en/class.normalizer.php for more information
$language_normalize_mapping = [
    "en" => Normalizer::NFD,
    "fr" => Normalizer::NFD,
    "sv" => Normalizer::NFC,
];

const API_ISSUE_VALID_DESTINATIONS = [
    "linkrui" => [
        "name" => "LinkrUI",
        "url" => "https://resourcespace.linkrui.com/saml",
        "stateparam" => "state",
    ],
];

// Stream wrappers not needed by ResourceSpace, to unregister during boot.php to improve security.
const UNREGISTER_WRAPPERS = ['ftp','ftps', 'phar'];

const COLLECTION_FRAME_HEIGHT = 153;

// Default lifetime in days of a temporary download file created by the job queue. After this time it will be deleted by another job
const DOWNLOAD_FILE_LIFETIME = 14;

$max_collection_thumbs = 150;

// Define languages available
$languages["en"] = "International English";
$languages["en-US"] = "American English";
$languages["ar"] = "العربية";
$languages["id"] = "Bahasa Indonesia"; # Indonesian
$languages["be"] = "беларуская мова"; # Belarusian
$languages["bn"] = "বাংলা"; # Bengali
$languages["ca"] = "Català"; # Catalan
$languages["cs"] = "čeština"; # Czech
$languages["cy"] = "Cymraeg"; # Welsh
$languages["da"] = "Dansk"; # Danish
$languages["de"] = "Deutsch"; # German
$languages["el"] = "Ελληνικά"; # Greek
$languages["es"] = "Español"; # Spanish
$languages["es-AR"] = "Español (Argentina)";
$languages["fi"] = "Suomi"; # Finnish
$languages["fr"] = "Français"; # French
$languages["hi"] = "आधुनिक मानक हिन्दी"; # Hindi
$languages["hu"] = "Magyar"; # Hungarian
$languages["hr"] = "Hrvatski"; # Croatian
$languages["it"] = "Italiano"; # Italian
$languages["jp"] = "日本語"; # Japanese
$languages["ko"] = "한국어"; # Korean
$languages["ms"] = "Bahasa Melayu"; # Malay
$languages["nl"] = "Nederlands"; # Dutch
$languages["no"] = "Norsk"; # Norwegian
$languages["fa"] = "فارسی"; # Persian
$languages["pa"] = "ਪੰਜਾਬੀ"; # Punjabi
$languages["pl"] = "Polski"; # Polish
$languages["pt"] = "Português"; # Portuguese
$languages["pt-BR"] = "Português do Brasil"; # Brazilian Portuguese
$languages["ro"] = "Limba română"; # Romanian
$languages["ru"] = "Русский язык"; # Russian
$languages["sk"] = "Slovenčina"; # Slovak
$languages["sv"] = "Svenska"; # Swedish
$languages["sw"] = "Kiswahili"; # Swahili
$languages["th"] = "ภาษาไทย"; # Thai
$languages["tl"] = "Filipino"; # Filipino
$languages["tr"] = "Türkçe"; # Turkish
$languages["uk"] = "українська мова"; # Ukranian
$languages["ur"] = "اُردُو"; # Urdu
$languages["vi"] = "Tiếng Việt"; # Vietnamese
$languages["zh-CN"] = "简体字"; # Simplified Chinese

# English stop words
$noadd = array("", "a","the","this","then","another","is","with","in","and","where","how","on","of","to", "from", "at", "for", "-", "by", "be");

// Lucide classes for the icon to display when there's no preview image.
const LUCIDE_EXTENSIONS = [
    // Images
    'png' => 'image',
    'jpg' => 'image',
    'jpeg' => 'image',
    'gif' => 'image',
    'svg' => 'image',
    'svgz' => 'image',
    'tif' => 'image',
    'tiff' => 'image',
    'bmp' => 'image',
    'ico' => 'image',
    'psd' => 'image',
    'raw' => 'image',
    'dng' => 'image',
    'cr2' => 'image',
    'nef' => 'image',
    'orf' => 'image',
    'rw2' => 'image',

    // Video
    'mp4' => 'video',
    'mov' => 'video',
    'avi' => 'video',
    'mkv' => 'video',
    'flv' => 'video',
    'fla' => 'video',
    'wmv' => 'video',
    '3gp' => 'video',
    'webm' => 'video',
    'vob' => 'video',
    'm4v' => 'video',
    'mxf' => 'video',

    // Audio
    'mp3' => 'music',
    'wav' => 'music',
    'aac' => 'music',
    'flac' => 'music',
    'm4a' => 'music',
    'wma' => 'music',
    'ogg' => 'music',
    'aif' => 'music',
    'aiff' => 'music',

    // Documents
    'pdf' => 'file-text',
    'doc' => 'file-text',
    'docx' => 'file-text',
    'odt' => 'file-text',
    'xls' => 'file-spreadsheet',
    'xlsx' => 'file-spreadsheet',
    'ods' => 'file-spreadsheet',
    'ppt' => 'file-chart-pie',
    'pptx' => 'file-chart-pie',
    'odp' => 'file-chart-pie',
    'rtf' => 'file-text',
    'txt' => 'file-text',
    'md' => 'file-text',

    // Code
    'php' => 'file-braces',
    'html' => 'file-braces',
    'htm' => 'file-braces',
    'css' => 'file-braces',
    'js' => 'file-braces',
    'json' => 'file-braces',
    'xml' => 'file-braces',
    'sql' => 'file-braces',
    'sh' => 'file-braces',
    'py' => 'file-braces',
    'java' => 'file-braces',
    'c' => 'file-braces',
    'cpp' => 'file-braces',
    'rb' => 'file-braces',
    'go' => 'file-braces',

    // Compressed
    'zip' => 'file-archive',
    'rar' => 'file-archive',
    '7z' => 'file-archive',
    'tar' => 'file-archive',
    'gz' => 'file-archive',
    'bz2' => 'file-archive',
    'xz' => 'file-archive',
    'iso' => 'file-archive',

    // Miscellaneous
    'log' => 'file-text',
    'cfg' => 'file-text',
    'ini' => 'file-text',
    'csv' => 'file-spreadsheet',

    // Fonts
    'ttf' => 'file-type',
    'otf' => 'file-type',
    'woff' => 'file-type',
    'woff2' => 'file-type',

    // Default to 'file' for unrecognized extensions
    'default' => 'file',
];

const TINYMCE_VALID_PLUGINS = [
    'autolink'  => 'Autolink',
    'autoresize' => 'Autoresize',
    'charmap' => 'Character Map',
    'code' => 'Code',
    'directionality' => 'Directionality',
    'emoticons' => 'Emoticons',
    'fullscreen' => 'Full Screen',
    'help' => 'Help',
    'insertdatetime' => 'Insert Date/Time',
    'link' => 'Link',
    'lists' => 'Lists',
    'advlist' => 'List Styles',
    'nonbreaking' => 'Nonbreaking Space',
    'preview' => 'Preview',
    'quickbars' => 'Quick Toolbars',
    'searchreplace' => 'Search and Replace',
    'visualblocks' => 'Visual Blocks',    
    'visualchars' => 'Visual Characters',
    'wordcount' => 'Word Count',
];

const FIELD_SORT_METHODS = [
    'default' => 0,
    'dot-notation' => 1,
];

// How many times should the system attempt to generate previews? 
// The preview attempts for a resource are reset if preview generation is manually initiated
const SYSTEM_MAX_PREVIEW_ATTEMPTS = 5;

const NON_PREVIEW_EXTENSIONS = ['zip'];

$offline_job_list = [
    0 => ['name' => 'Core',
          'lang_string' => 'job_list_core',
          'type' => 'group_start'],
    1 => ['name' => 'Recreate Previews',
          'lang_string' => 'job_list_recreate_previews',
          'script_name' => 'recreate_previews',
          'plugin' => null],
    2 => ['name' => 'Regenerate EXIF data',
          'lang_string' => 'job_list_exif_data',
          'script_name' => 'update_exiftool_field',
          'plugin' => null],
    3 => ['name' => 'Purge duplicate resources',
          'lang_string' => 'job_list_purge_duplicates',
          'script_name' => 'purge_duplicates',
          'plugin' => null],
    4 => ['name' => 'Re-index data',
          'lang_string' => 'job_list_reindex',
          'script_name' => 'reindex',
          'plugin' => null],
    5 => ['name' => 'Update checksums',
          'lang_string' => 'job_list_checksums',
          'script_name' => 'update_checksums',
          'plugin' => null],
    6 => ['name' => 'Update extracted text',
          'lang_string' => 'job_list_extracted_text',
          'script_name' => 'update_extracted_text',
          'plugin' => null],
    7 => ['name' => 'Core',
          'type' => 'group_end'],
];