<?php

$clip_search_cutoff = 0.25;
$clip_similar_cutoff = 0.5;
$clip_duplicate_cutoff = 0.9;
$clip_results_limit_search = 120;
$clip_results_limit_similar = 120;
$clip_service_url = "http://127.0.0.1:8000";
$clip_text_search_fields = [8];
$clip_vector_on_upload = true;

$clip_title_field = 0;
$clip_title_url = "https://www.resourcespace.com/downloads/clip/titles_textonly.tagdb";

$clip_keyword_field = 0;
$clip_keyword_url = "https://www.resourcespace.com/downloads/clip/taggable_nouns.tagdb";
$clip_keyword_count = 5;
$clip_resource_types = array(1,3); // Default resource types to index

$clip_cron_generate_batch = 10000; // Vectors to generate each batch run.

$clip_enable_full_duplicate_search = false; // EXPERIMENTAL: Enable "all duplicate images" on AI Smart Search page
