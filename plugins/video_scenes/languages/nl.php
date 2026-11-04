<?php


$lang["video_scene_title"] = 'Videoscènes';
$lang["video_scene_intro"] = 'Detecteer scènes lokaal met transnetv2-pytorch na upload of previewrecreatie. Installeer het Python-pakket voordat u begint met verwerken; modelgewichten zijn inbegrepen. Een optionele OpenAI API-sleutel maakt scènebeschrijvingen mogelijk; collages en scène-audio worden vervolgens naar OpenAI gestuurd.';
$lang["video_scene_python"] = 'Python uitvoerbaar bestand (absoluut pad)';
$lang["video_scene_threshold"] = 'Drempel voor scèneovergang (tussen 0 en 1)';
$lang["video_scene_api_key"] = 'OpenAI API-sleutel (optioneel)';
$lang["video_scene_model"] = 'OpenAI Realtime-model dat beeld- en audio-invoer ondersteunt';
$lang["video_scene_audio"] = 'Neem scène-audio op in beschrijvingen';
$lang["video_scene_audio_overlap"] = 'Audiocontext voor en na elke scène (seconden, 0 tot 30)';
$lang["video_scene_prompt"] = 'Prompt voor scènebeschrijving';
$lang["video_scene_search_field"] = 'Doorzoekbaar veld voor scènebeschrijvingen (optioneel)';
$lang["video_scene_prompt_help"] = 'Instructies voor het beschrijven van elke scène op basis van de frames en audio. Maximaal 8.000 bytes.';
$lang["video_scene_timeout"] = 'Maximale verwerkingstijd per video (seconden)';
$lang["video_scene_invalid_config"] = 'Controleer het Python-pad, drempel, model, sleutel en verwerkingstijd.';
$lang["video_scene_time"] = 'Tijdstempel';
$lang["video_scene_thumbnail"] = 'Miniatuur';
$lang["video_scene_description"] = 'Beschrijving';
$lang["video_scene_edit_description"] = 'Bewerk scènebeschrijving op %time';
$lang["video_scene_invalid_description"] = 'Voer een geldige beschrijving in van maximaal 16.000 bytes.';
$lang["video_scene_description_missing"] = 'Deze scène bestaat niet meer. Herlaad de resource-weergave.';
$lang["video_scene_description_save_failed"] = 'De beschrijving kon niet worden opgeslagen. Probeer het opnieuw of herlaad de resource-weergave.';
$lang["video_scene_description_saved"] = 'Scènebeschrijving opgeslagen.';
$lang["video_scene_no_description"] = 'Geen beschrijving beschikbaar';
$lang["video_scene_play"] = 'Speel scène af op %time';
$lang["video_scene_play_unavailable"] = 'Deze scène valt buiten de beschikbare videovoorvertoning.';
$lang["video_scene_job_success"] = 'Verwerking van videoscène voltooid';
$lang["video_scene_job_failure"] = 'Verwerking van videoscène mislukt. Zie het taaklogboek voor details.';