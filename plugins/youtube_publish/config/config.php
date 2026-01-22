<?php

$youtube_publish_restypes=array(3);
$youtube_publish_title_field=8;
$youtube_publish_descriptionfields=array('18');
$youtube_publish_keywords_fields=array('1'); # needs to be array so can add multiple tags field
$youtube_publish_url_field=false;
$youtube_publish_allow_multiple=false;

$youtube_publish_oauth2=true;
$youtube_publish_developer_key="";

#if not using OAuth2.0
$youtube_publish_username="";
$youtube_publish_password="";

# If using Oauth 2.0
# Required values can obtained from https://code.google.com/apis 
$youtube_publish_client_id="";
$youtube_publish_client_secret="";
$youtube_publish_callback_url="/plugins/youtube_publish/pages/youtube_upload.php";

$youtube_publish_add_anchor=false;

#Ability to configure chunk size used when uploading to YouTube (in MB)
$youtube_chunk_size=10;

// Add any new vars that specify metadata fields to this array to stop them being deleted if plugin is in use
// These are added in hooks/all.php
$youtube_publish_fieldvars = array("youtube_publish_title_field","youtube_publish_descriptionfields","youtube_publish_url_field");
