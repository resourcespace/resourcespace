<?php

$lang["faces-detected-faces"] = "Detected faces";
$lang["faces-detected-face"] = "Detected face";
$lang["faces-find-matching"] = "Find matching faces";
$lang["faces-configuration"] = "AI Faces Configuration";
$lang["faces-service-endpoint"] = "Python FastAPI service URL";
$lang["faces-match-threshold"] = "Face match threshold: what level of similarity is considered a match when searching for faces? Suggested 30% (raise if there are a lot of incorrect matches in search results)";
$lang["faces-tag-threshold"] = "Face tag threshold: what level of similarity is considered a match when automatically tagging faces? Suggested 50% (raise if there are a large number of incorrect tags)";
$lang["faces-confidence-threshold"] = "Face confidence threshold: How confident should the model be that it has found a human face? Suggested 70% (values below this will match obscured faces and non faces)";
$lang["faces-confidence"] = "Confidence this is a face";
$lang["faces-tag-field"] = "The field containing the names of the tagged individuals. This should be a Dynamic Keyword List field.";
$lang["faces-tag-field-not-set"] = "Tagging field is not configured.";
$lang["faces-name"] = "Name";
$lang["faces-detect-on-upload"] = "Scan for faces on upload?";
$lang["faces-tag-on-upload"] = "Tag recognised faces on upload?";
$lang["faces-detecting"] = "Scanning for faces in resource:";
$lang["faces-tagging"] = "Tagging detected faces in resource:";
$lang["faces-oneface"] = "Please select only one option for each face.";
$lang["faces-show-view"] = "Show the AI Faces functionality on the view page.";
$lang["faces_count_faces"] = "Total faces detected";
$lang["faces_count_missing"] = "Images to process";

$lang["page-title_faces_setup"] = "Setup Faces Plugin";

$lang["faces_insight_faces"] = "InsightFaces";
$lang["faces_detect_faces"] = "Detect faces";
$lang["faces_tag_faces"] = "Tag faces";
$lang["faces_detect_faces_configure"] = "Configure job to detect faces";
$lang["faces_tag_faces_configure"] = "Configure job to tag faces";

$lang["faces_detect_faces_intro"] = "Create a job to start detection of faces here - this job does not require any parameters so can be started as long as there are no other outstanding jobs of this type.";
$lang["faces_tag_faces_collection_refs_help"] = "Setting this option will mean only resources in the listed collections will be updated. If no collections are specified then face tagging will be updated for ALL suitable resources. Collections can be specified using a comma-separated list as well as ranges e.g 100,105,110-115";