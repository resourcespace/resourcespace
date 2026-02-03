<?php

$lang["clip-natural-language-search"] = "Search by description";
$lang["clip-natural-language-search-help"] = "Enter a description of the image e.g. 'A red sports car'.";
$lang["clip-configuration"] = "CLIP configuration";
$lang["clip-ai-smart-search"] = "AI Smart Search";
$lang["clip-visually-similar-images"] = "Visually similar images";
$lang["clip-duplicate-images"] = "Duplicate images";
$lang["clip-duplicate-images-all"] = "View all images with duplicates";
$lang["clip-search-upload-image"] = "Search by providing an image";
$lang["clip_search_cutoff"] = "Natural Language Search vector distance cutoff (suggested 25%; increase to focus search, decrease to expand search)";
$lang["clip_similar_cutoff"] = "Similar Images vector distance cutoff (suggested 50%; increase to focus search, decrease to expand search)";
$lang["clip_duplicate_cutoff"] = "Duplicate Images vector distance cutoff (suggested 90%; increase to focus search, decrease to expand search)";
$lang["clip_results_limit_search"] = "Number of search results to display";
$lang["clip_results_limit_similar"] = "Number of similar resources to display";
$lang["clip_service_url"] = "CLIP service URL";
$lang["clip_text_search_fields"] = "Metadata fields to combine for the text vector. Select only those fields that will help to structure a short meaningful description. Excessive fields will dilute meaning. Suggestion: title only. Do not include fields containing codes.";
$lang["clip-vector-on-upload"] = "Generate CLIP vector on file upload";
$lang["clip-generating"] = "CLIP is Generating CLIP vectors for resource: ";
$lang["clip-tagging"] = "CLIP is automatically tagging resource: ";
$lang["clip-automatic-tagging"] = "Automatic Tagging";
$lang["clip-title-field"] = "Field for the automatically generated title based on the closest match in the external vector database";
$lang["clip-title-url"] = "External vector database for titles";
$lang["clip-keyword-field"] = "Field for the closest matching keywords in the external vector database";
$lang["clip-keyword-url"] = "External vector database for keywords";
$lang["clip-keyword-count"] = "Number of keywords to set (x nearest keywords by cosine similarity)";
$lang["clip_show_on_searchbar"] = "Show CLIP features on the search bar";
$lang["clip_show_on_view"] = "Show CLIP features on the resource view page";
$lang["clip_resource_types"] = "Create vectors (enable searching of) these resource types";
$lang["clip_vector-statistics"] = "Vector statistics";
$lang["clip_count_vectors"] = "Vector count";
$lang["clip_missing_vectors"] = "Vectors missing";
$lang["clip-vector-generation"] = "Vector generation";
$lang["clip-vector-cleanup"] = "Delete orphaned vectors";
$lang["clip-vector-cleanup-description"] = "Remove vectors that belong to resources that no longer exist or are not one of the above selected resource types";
$lang["clip_enable_full_duplicate_search"] = "BETA: Enable the 'Duplicate images' Search option in AI Smart Search";

$lang["page-title_clip_search"] = "AI Smart Search";
$lang["page-title_clip_setup"]  = "Setup CLIP Plugin";
$lang["page-title_clip_webcam"] = "Webcam Tag Generator";

$lang["clip-ai_smart_search"] = "CLIP AI Smart Search";
$lang["clip-generate_vectors"] = "Generate CLIP vectors";
$lang["clip-configure_job"] = "Configure job to generate CLIP vectors";
$lang["clip-job_limit"] = "Batch size limit";
$lang["clip-job_limit_help"] = "Setting this option will limit the number of resources processed in one run.";
$lang["clip-job_limit_error"] = "Value must be between 1 and 100000 or blank for no limit";