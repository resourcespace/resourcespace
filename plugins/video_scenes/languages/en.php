<?php

$lang['video_scene_title'] = 'Video scenes';
$lang['video_scene_intro'] = 'Detect scenes locally with transnetv2-pytorch after upload or preview recreation. '
    . 'Install the Python package before processing; model weights are included. '
    . 'An optional OpenAI API key enables scene descriptions; collages and scene audio are then sent to OpenAI.';
$lang['video_scene_python'] = 'Python executable (absolute path)';
$lang['video_scene_threshold'] = 'Scene transition threshold (between 0 and 1)';
$lang['video_scene_api_key'] = 'OpenAI API key (optional)';
$lang['video_scene_model'] = 'OpenAI Realtime model supporting image and audio input';
$lang['video_scene_audio'] = 'Include scene audio in descriptions';
$lang['video_scene_audio_overlap'] = 'Audio context before and after each scene (seconds, 0 to 30)';
$lang['video_scene_prompt'] = 'Scene description prompt';
$lang['video_scene_search_field'] = 'Searchable scene descriptions field (optional)';
$lang['video_scene_prompt_help'] = 'Instructions for describing each scene from its frames and audio. Maximum 8,000 bytes.';
$lang['video_scene_timeout'] = 'Maximum processing time per video (seconds)';
$lang['video_scene_invalid_config'] = 'Check the Python path, threshold, model, key '
    . 'and processing timeout.';
$lang['video_scene_time'] = 'Timestamp';
$lang['video_scene_thumbnail'] = 'Thumbnail';
$lang['video_scene_description'] = 'Description';
$lang['video_scene_edit_description'] = 'Edit scene description at %time';
$lang['video_scene_invalid_description'] = 'Enter a valid description of up to 16,000 bytes.';
$lang['video_scene_description_missing'] = 'This scene no longer exists. Reload the resource view.';
$lang['video_scene_description_save_failed'] = 'The description could not be saved. '
    . 'Try again or reload the resource view.';
$lang['video_scene_description_saved'] = 'Scene description saved.';
$lang['video_scene_no_description'] = 'No description available';
$lang['video_scene_play'] = 'Play scene at %time';
$lang['video_scene_play_unavailable'] = 'This scene is outside the available video preview.';
$lang['video_scene_job_success'] = 'Video scene processing completed';
$lang['video_scene_job_failure'] = 'Video scene processing failed. See the job log for details.';
