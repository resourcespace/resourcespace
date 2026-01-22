<?php 

$rss_fields=array(8,12);
// Time to live, to do with caching
$rss_ttl=60;
$rss_show_field_titles=false; 

// Add any new vars that specify metadata fields to this array to stop them being deleted if plugin is in use
// These are added in hooks/all.php
$rss_fieldvars = array("rss_fields");
