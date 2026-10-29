<?php

command_line_only();

define('ECHOFEEDBACK', false); # Whether or not to echo progress; for testing of this test script
define('ADVANCED_TRUE', 1);
define('ADVANCED_FALSE', 0);

global $date_field;

$saved_date_field = $date_field;

$fldref = array();

function make_field($fieldname, $resourcetype, $fieldtype, $advanced)
{
    $ref = create_resource_type_field($fieldname, $resourcetype, $fieldtype);
    $sql = "update resource_type_field set advanced_search=?, keywords_index=? where ref=?";
    ps_query($sql, array("i",$advanced,"i",$advanced,"i",$ref));
    return $ref;
}

function verify_field_position($fieldname, $searchfieldarray, $expectedposition)
{
    foreach ($searchfieldarray as $key => $searchfieldentry) {
        if (
            $searchfieldentry['title'] == $fieldname
            && $key == $expectedposition
        ) {
            feedback("FIELD " . $fieldname . " PASSED" . PHP_EOL);
            return true;
        }
    }
    # Error; not located in the expected position
    feedback("FIELD " . $fieldname . " FAILED" . PHP_EOL);
    return false;
}

function feedback($buffer)
{
    if (ECHOFEEDBACK) {
        echo $buffer;
    }
}

# Shipped resource types are as follows
#  1 Photo
#  2 Document
#  3 Video
#  4 Audio

# ############
# TEST 1 SETUP
# Set all metadata fields so that they are not on advanced search
$sql = "update resource_type_field set advanced_search=0";
ps_query($sql);

# Create several resource type text fields with resource type 1 (Photo) which are marked with advanced_search as per list below

# name                      advanced_search   type
# -------------------------------------------------
# PhotoOneNoSearch          0                 Text
# PhotoTwoSearch            1                 Text
# PhotoThreeSearch          1                 Date
# PhotoFourNoSearch         0                 Text
# PhotoFiveSearch           1                 Radio
# PhotoSixNoSearch          0                 Text

$fldref[] = make_field("PhotoOneNoSearch", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_FALSE);
$fldref[] = make_field("PhotoTwoSearch", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_TRUE);
$fldref[] = make_field("PhotoThreeSearch", 1, FIELD_TYPE_DATE, ADVANCED_TRUE);
$fldref[] = make_field("PhotoFourNoSearch", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_FALSE);
$fldref[] = make_field("PhotoFiveSearch", 1, FIELD_TYPE_RADIO_BUTTONS, ADVANCED_TRUE);
$fldref[] = make_field("PhotoSixNoSearch", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_FALSE);

# Create several resource type text fields with resource type 3 (Video) which are marked with advanced_search as per list below

# name                      advanced_search   type
# -------------------------------------------------
# VideoZoneSearch           1                 Checkbox
# VideoNameNoSearch         0                 Text
# VideoTypeSearch           1                 Radio
# VideoDateNoSearch         0                 Date
# VideoAreaSearch           1                 Text

$fldref[] = make_field("VideoZoneSearch", 3, FIELD_TYPE_CHECK_BOX_LIST, ADVANCED_TRUE);
$fldref[] = make_field("VideoNameNoSearch", 3, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_FALSE);
$fldref[] = make_field("VideoTypeSearch", 3, FIELD_TYPE_RADIO_BUTTONS, ADVANCED_TRUE);
$fldref[] = make_field("VideoDateNoSearch", 3, FIELD_TYPE_DATE, ADVANCED_FALSE);
$fldref[] = make_field("VideoAreaSearch", 3, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, ADVANCED_TRUE);

# Note the start and end field reference ids
$ref_first = $fldref[0];
$ref_last = $fldref[count($fldref) - 1];

$sql = "select ref, title from resource_type_field where ref between ? and ?";
$fldset = ps_query($sql, array("i",$ref_first,"i",$ref_last));

feedback(PHP_EOL);
foreach ($fldset as $fldentry) {
    feedback("FIELDENTRY= " . json_encode($fldentry) . PHP_EOL);
}
feedback(PHP_EOL . PHP_EOL);

# TEST 1 EXECUTE
# The config $date_field will be the shipped resource_type_field "Date" (ref 12) which is a Global resource type (0)
feedback(PHP_EOL . "TEST 1 DATE_FIELD= " . $date_field . PHP_EOL);

# Call the function which assembles the searchable fields
$search_fields = get_advanced_search_fields();

feedback(PHP_EOL);
foreach ($search_fields as $search_field) {
    feedback("SEARCHFIELD= " . json_encode($search_field) . PHP_EOL . PHP_EOL);
}
feedback(PHP_EOL . PHP_EOL);

# TEST 1 VERIFY
# Assert that the fields are in the expected sequence
if (!verify_field_position("PhotoTwoSearch", $search_fields, 0)) {
    return false;
}
if (!verify_field_position("PhotoThreeSearch", $search_fields, 1)) {
    return false;
}
if (!verify_field_position("PhotoFiveSearch", $search_fields, 2)) {
    return false;
}
if (!verify_field_position("VideoZoneSearch", $search_fields, 3)) {
    return false;
}
if (!verify_field_position("VideoTypeSearch", $search_fields, 4)) {
    return false;
}
if (!verify_field_position("VideoAreaSearch", $search_fields, 5)) {
    return false;
}
if (!verify_field_position("Date", $search_fields, 6)) {
    return false;
}

# ############
# TEST 2 SETUP
# Set config $date_field to resource_type_field "VideoDateNoSearch"
#   This is one of the fields setup for TEST 1 which is a Video (resource type 3) field
$date_field = $fldref[9];
feedback(PHP_EOL . "TEST 2 DATE_FIELD= " . $date_field . PHP_EOL);

# TEST 2 EXECUTE
# Call the function which assembles the searchable fields
$search_fields = get_advanced_search_fields();

feedback(PHP_EOL);
foreach ($search_fields as $search_field) {
    feedback("SEARCHFIELD= " . json_encode($search_field) . PHP_EOL . PHP_EOL);
}
feedback(PHP_EOL . PHP_EOL);

# TEST 2 VERIFY
# Assert that the fields are in the expected sequence
if (!verify_field_position("PhotoTwoSearch", $search_fields, 0)) {
    return false;
}
if (!verify_field_position("PhotoThreeSearch", $search_fields, 1)) {
    return false;
}
if (!verify_field_position("PhotoFiveSearch", $search_fields, 2)) {
    return false;
}
if (!verify_field_position("VideoDateNoSearch", $search_fields, 3)) {
    return false;
}
if (!verify_field_position("VideoZoneSearch", $search_fields, 4)) {
    return false;
}
if (!verify_field_position("VideoTypeSearch", $search_fields, 5)) {
    return false;
}
if (!verify_field_position("VideoAreaSearch", $search_fields, 6)) {
    return false;
}

feedback("TEST 002300 ADVANCED SEARCHABLE FIELDS - PASSED" . PHP_EOL);

$GLOBALS['date_field'] = $saved_date_field;
feedback("RESTORED DATE_FIELD= " . $date_field . PHP_EOL);

return true;
