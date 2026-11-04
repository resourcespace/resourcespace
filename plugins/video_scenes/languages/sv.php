<?php


$lang["video_scene_title"] = 'Videoscener';
$lang["video_scene_intro"] = 'Detektera scener lokalt med transnetv2-pytorch efter uppladdning eller förhandsgranskningens återställning. Installera Python-paketet innan bearbetning; modellvikter ingår. En valfri OpenAI API-nyckel möjliggör scenbeskrivningar; kollage och scenaudio skickas då till OpenAI.';
$lang["video_scene_python"] = 'Python-exekverbar fil (absolut sökväg)';
$lang["video_scene_threshold"] = 'Tröskel för scenövergång (mellan 0 och 1)';
$lang["video_scene_api_key"] = 'OpenAI API-nyckel (valfri)';
$lang["video_scene_model"] = 'OpenAI Realtime-modell som stödjer bild- och ljudinmatning';
$lang["video_scene_audio"] = 'Inkludera scenaudio i beskrivningar';
$lang["video_scene_audio_overlap"] = 'Ljudkontext före och efter varje scen (sekunder, 0 till 30)';
$lang["video_scene_prompt"] = 'Prompt för scenbeskrivning';
$lang["video_scene_search_field"] = 'Sökbart fält för scenbeskrivningar (valfritt)';
$lang["video_scene_prompt_help"] = 'Instruktioner för att beskriva varje scen utifrån dess bilder och ljud. Max 8 000 byte.';
$lang["video_scene_timeout"] = 'Maximal bearbetningstid per video (sekunder)';
$lang["video_scene_invalid_config"] = 'Kontrollera Python-sökväg, tröskel, modell, nyckel och bearbetningstidsgräns.';
$lang["video_scene_time"] = 'Tidsstämpel';
$lang["video_scene_thumbnail"] = 'Miniatyrbild';
$lang["video_scene_description"] = 'Beskrivning';
$lang["video_scene_edit_description"] = 'Redigera scenbeskrivning vid %time';
$lang["video_scene_invalid_description"] = 'Ange en giltig beskrivning på upp till 16 000 byte.';
$lang["video_scene_description_missing"] = 'Denna scen finns inte längre. Ladda om resursvyn.';
$lang["video_scene_description_save_failed"] = 'Beskrivningen kunde inte sparas. Försök igen eller ladda om resursvyn.';
$lang["video_scene_description_saved"] = 'Scenbeskrivning sparad.';
$lang["video_scene_no_description"] = 'Ingen beskrivning tillgänglig';
$lang["video_scene_play"] = 'Spela scen vid %time';
$lang["video_scene_play_unavailable"] = 'Denna scen ligger utanför den tillgängliga videoförhandsvisningen.';
$lang["video_scene_job_success"] = 'Videoscensbearbetning slutförd';
$lang["video_scene_job_failure"] = 'Videoscensbearbetning misslyckades. Se jobbloggen för detaljer.';