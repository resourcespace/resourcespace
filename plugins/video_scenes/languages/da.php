<?php


$lang["video_scene_title"] = 'Videoscener';
$lang["video_scene_intro"] = 'Registrer scener lokalt med transnetv2-pytorch efter upload eller forhåndsvisningsgengivelse. Installer Python-pakken før behandling; modelvægte er inkluderet. En valgfri OpenAI API-nøgle muliggør scene beskrivelser; collager og scene lyd sendes derefter til OpenAI.';
$lang["video_scene_python"] = 'Python eksekverbar fil (absolut sti)';
$lang["video_scene_threshold"] = 'Sceneovergangstærskel (mellem 0 og 1)';
$lang["video_scene_api_key"] = 'OpenAI API-nøgle (valgfri)';
$lang["video_scene_model"] = 'OpenAI Realtime-model, der understøtter billede- og lydinput';
$lang["video_scene_audio"] = 'Inkluder scene lyd i beskrivelser';
$lang["video_scene_audio_overlap"] = 'Lydkontekst før og efter hver scene (sekunder, 0 til 30)';
$lang["video_scene_prompt"] = 'Scene beskrivelsesprompt';
$lang["video_scene_search_field"] = 'Søgbart felt for scene beskrivelser (valgfri)';
$lang["video_scene_prompt_help"] = 'Instruktioner til at beskrive hver scene ud fra dens billeder og lyd. Maksimalt 8.000 bytes.';
$lang["video_scene_timeout"] = 'Maksimal behandlingstid pr. video (sekunder)';
$lang["video_scene_invalid_config"] = 'Kontroller Python-sti, tærskel, model, nøgle og behandlingstimeout.';
$lang["video_scene_time"] = 'Tidsstempel';
$lang["video_scene_thumbnail"] = 'Miniaturebillede';
$lang["video_scene_description"] = 'Beskrivelse';
$lang["video_scene_edit_description"] = 'Rediger scene beskrivelse ved %time';
$lang["video_scene_invalid_description"] = 'Indtast en gyldig beskrivelse på op til 16.000 bytes.';
$lang["video_scene_description_missing"] = 'Denne scene findes ikke længere. Genindlæs ressourcens visning.';
$lang["video_scene_description_save_failed"] = 'Beskrivelsen kunne ikke gemmes. Prøv igen eller genindlæs ressourcens visning.';
$lang["video_scene_description_saved"] = 'Scenebeskrivelse gemt.';
$lang["video_scene_no_description"] = 'Ingen beskrivelse tilgængelig';
$lang["video_scene_play"] = 'Afspil scene ved %time';
$lang["video_scene_play_unavailable"] = 'Denne scene er uden for den tilgængelige video preview.';
$lang["video_scene_job_success"] = 'Videoscene behandling fuldført';
$lang["video_scene_job_failure"] = 'Videoscene behandling mislykkedes. Se jobloggen for detaljer.';