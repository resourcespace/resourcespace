<?php

$falcon_base_url = "https://api.falcon.io";
$falcon_link_template_url = "https://app.falcon.io/#/publish/content-pool/card/preview/stock/[id]";
$falcon_link_restypes = array(1);
$falcon_link_text_field = 8;
$falcon_link_default_tag = "";
$falcon_link_tag_fields = array('1'); # needs to be array so can add multiple tags field
$falcon_link_id_field = false;
$falcon_link_api_key = "";
$falcon_link_permitted_extensions = array("jpg","jpeg","png","gif","tiff");
$falcon_link_usergroups = array(3);
$falcon_link_filter = "";
$falcon_link_share_user = "falcon.io";

// Add any new vars that specify metadata fields to this array to stop them being deleted if plugin is in use
// These are added in hooks/all.php
$falcon_link_fieldvars = array("falcon_link_tag_fields","falcon_link_text_field");
