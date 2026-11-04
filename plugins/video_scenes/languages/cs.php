<?php


$lang["video_scene_title"] = 'Video scény';
$lang["video_scene_intro"] = 'Detekujte scény lokálně pomocí transnetv2-pytorch po nahrání nebo obnovení náhledu. Před zpracováním nainstalujte Python balíček; váhy modelu jsou zahrnuty. Volitelný OpenAI API klíč umožňuje popisy scén; koláže a zvuk scén jsou pak odesílány do OpenAI.';
$lang["video_scene_python"] = 'Python spustitelný soubor (absolutní cesta)';
$lang["video_scene_threshold"] = 'Prahová hodnota přechodu scény (mezi 0 a 1)';
$lang["video_scene_api_key"] = 'OpenAI API klíč (volitelný)';
$lang["video_scene_model"] = 'OpenAI Realtime model podporující vstup obrazu a zvuku';
$lang["video_scene_audio"] = 'Zahrnout zvuk scény do popisů';
$lang["video_scene_audio_overlap"] = 'Zvukový kontext před a po každé scéně (sekundy, 0 až 30)';
$lang["video_scene_prompt"] = 'Výzva pro popis scény';
$lang["video_scene_search_field"] = 'Pole pro vyhledávání popisů scén (volitelné)';
$lang["video_scene_prompt_help"] = 'Instrukce pro popis každé scény z jejích snímků a zvuku. Maximálně 8 000 bajtů.';
$lang["video_scene_timeout"] = 'Maximální doba zpracování na video (sekundy)';
$lang["video_scene_invalid_config"] = 'Zkontrolujte cestu k Pythonu, práh, model, klíč a časový limit zpracování.';
$lang["video_scene_time"] = 'Časová značka';
$lang["video_scene_thumbnail"] = 'Náhled';
$lang["video_scene_description"] = 'Popis';
$lang["video_scene_edit_description"] = 'Upravit popis scény v %time';
$lang["video_scene_invalid_description"] = 'Zadejte platný popis o délce až 16 000 bajtů.';
$lang["video_scene_description_missing"] = 'Tato scéna již neexistuje. Znovu načtěte zobrazení zdroje.';
$lang["video_scene_description_save_failed"] = 'Popis nelze uložit. Zkuste to znovu nebo znovu načtěte zobrazení zdroje.';
$lang["video_scene_description_saved"] = 'Popis scény byl uložen.';
$lang["video_scene_no_description"] = 'Popis není k dispozici';
$lang["video_scene_play"] = 'Přehrát scénu v %time';
$lang["video_scene_play_unavailable"] = 'Tato scéna je mimo dostupnou video ukázku.';
$lang["video_scene_job_success"] = 'Zpracování video scény dokončeno';
$lang["video_scene_job_failure"] = 'Zpracování video scény selhalo. Podrobnosti naleznete v protokolu úlohy.';