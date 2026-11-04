<?php


$lang["video_scene_title"] = 'Videoscener';
$lang["video_scene_intro"] = 'Oppdag scener lokalt med transnetv2-pytorch etter opplasting eller forhåndsvisningsrekreasjon. Installer Python-pakken før behandling; modellvekter er inkludert. En valgfri OpenAI API-nøkkel muliggjør scene beskrivelser; kollasjer og scene lyd sendes deretter til OpenAI.';
$lang["video_scene_python"] = 'Python-eksekverbar fil (absolutt sti)';
$lang["video_scene_threshold"] = 'Grense for sceneskifte (mellom 0 og 1)';
$lang["video_scene_api_key"] = 'OpenAI API-nøkkel (valgfritt)';
$lang["video_scene_model"] = 'OpenAI Realtime-modell som støtter bilde- og lydinngang';
$lang["video_scene_audio"] = 'Inkluder scene lyd i beskrivelser';
$lang["video_scene_audio_overlap"] = 'Lydkontekst før og etter hver scene (sekunder, 0 til 30)';
$lang["video_scene_prompt"] = 'Prompt for scene beskrivelse';
$lang["video_scene_search_field"] = 'Søkbart felt for scene beskrivelser (valgfritt)';
$lang["video_scene_prompt_help"] = 'Instruksjoner for å beskrive hver scene ut fra dens bilder og lyd. Maksimalt 8 000 byte.';
$lang["video_scene_timeout"] = 'Maksimal behandlingstid per video (sekunder)';
$lang["video_scene_invalid_config"] = 'Sjekk Python-sti, grense, modell, nøkkel og behandlingstidsavbrudd.';
$lang["video_scene_time"] = 'Tidsstempel';
$lang["video_scene_thumbnail"] = 'Miniatyrbilde';
$lang["video_scene_description"] = 'Beskrivelse';
$lang["video_scene_edit_description"] = 'Rediger scenebeskrivelse ved %time';
$lang["video_scene_invalid_description"] = 'Skriv inn en gyldig beskrivelse på opptil 16 000 byte.';
$lang["video_scene_description_missing"] = 'Denne scenen finnes ikke lenger. Last inn ressursvisningen på nytt.';
$lang["video_scene_description_save_failed"] = 'Beskrivelsen kunne ikke lagres. Prøv igjen eller last inn ressursvisningen på nytt.';
$lang["video_scene_description_saved"] = 'Scenebeskrivelse lagret.';
$lang["video_scene_no_description"] = 'Ingen beskrivelse tilgjengelig';
$lang["video_scene_play"] = 'Spill av scene ved %time';
$lang["video_scene_play_unavailable"] = 'Denne scenen er utenfor tilgjengelig videoforhåndsvisning.';
$lang["video_scene_job_success"] = 'Videosceneprosessering fullført';
$lang["video_scene_job_failure"] = 'Videosceneprosessering mislyktes. Se jobbloggen for detaljer.';