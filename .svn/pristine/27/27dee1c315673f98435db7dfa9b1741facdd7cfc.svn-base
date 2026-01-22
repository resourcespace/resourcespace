<?php
define('TMS_LINK_MODULES_MIGRATED', 'tms_link_modules_migrated');


$tms_link_test_mode=false;
$tms_link_email_notify="";
$tms_link_test_count=500;
// Number of resources to retrieve from TMS in each query - can be tweaked for performance
$tms_link_query_chunk_size=50;

// SQL Server connection settings
$tms_link_dsn_name='TMS SQL Server';
$tms_link_user='';
$tms_link_password='';

$tms_link_enable_update_script=true;
$tms_link_script_failure_notify_days=3;

// $tms_link_log_directory=""; // This must now must be set in the primary config file
$tms_link_log_expiry=7;
$tms_link_write_to_debug_log = false;

// Additional options for bidirectional syncing (ResourceSpace -> TMS)
$tms_link_push_image=false;
$tms_link_push_image_sizes=array("pre","thm","col");
$tms_link_push_condition="";
$tms_link_tms_loginid="ResourceSpace";
$tms_link_mediatypeid=1;
$tms_link_formatid=2;
$tms_link_colordepthid=0;
$tms_link_media_path="\\\\SERVERNAME\\filestore\\";


// TODO: migrate existing configs to $tms_link_modules_saved_mappings as a new entry
$tms_link_table_name='';
$tms_link_resource_types=array(12);
$tms_link_checksum_field=0; # Field to use for storing checksum values 
$tms_link_object_id_field=0; # Field that is used to store TMS object ID
$tms_link_field_mappings_saved=base64_encode(serialize(array()));
$tms_link_text_columns=array("ObjectStatus","Department","Classification","Curator","Cataloguer","ObjectName","SubjectKeywords","Creators","Titles","StylePeriod","CulturalContext","Medium","Geography","CreditLine","Description","RelatedObjects","Inscription","Provenance","CurrLocDisplay","Copyright","Dimensions","Restrictions","CreditLineRepro","ObjRightsType");
$tms_link_numeric_columns=array("ObjectID","ObjectNumber","CuratorRevISODate","Dated","RowChecksum");

$tms_link_mediapaths_resource_reference_column = ""; # Column to store resource ID in for TMS media ID creation


$tms_link_modules_saved_mappings = base64_encode(
    serialize(
        array(
            '5be2e8c2d0616' => array(
                'module_name' => '',
                'tms_uid_field' => 'ObjectID',
                'tms_uid_field_int' => true,
                'rs_uid_field' => 0,
                'checksum_field' => 0,
                'applicable_resource_types' => array(),
                'tms_rs_mappings' => array(
                    array(
                        'tms_column' => 'Dimensions',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'ObjectStatus',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Department',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Classification',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Curator',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Cataloguer',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'ObjectName',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'SubjectKeywords',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Creators',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Titles',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'StylePeriod',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'CulturalContext',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Medium',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Geography',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'CreditLine',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Description',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'RelatedObjects',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Inscription',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Provenance',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'CurrLocDisplay',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Copyright',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'Restrictions',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'CreditLineRepro',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'ObjRightsType',
                        'rs_field' => 0,
                        'encoding' => 'UTF-16'
                    ),
                    array(
                        'tms_column' => 'ObjectNumber',
                        'rs_field' => 0,
                        'encoding' => 'UTF-8'
                    ),
                    array(
                        'tms_column' => 'CuratorRevISODate',
                        'rs_field' => 0,
                        'encoding' => 'UTF-8'
                    ),
                    array(
                        'tms_column' => 'Dated',
                        'rs_field' => 0,
                        'encoding' => 'UTF-8'
                    ),
                    array(
                        'tms_column' => 'RowChecksum',
                        'rs_field' => 0,
                        'encoding' => 'UTF-8'
                    ),
                )
            ),
        )
    )
);