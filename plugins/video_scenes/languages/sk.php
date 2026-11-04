<?php


$lang["video_scene_title"] = 'Video scény';
$lang["video_scene_intro"] = 'Detekujte scény lokálne pomocou transnetv2-pytorch po nahraní alebo obnovení náhľadu. Pred spracovaním nainštalujte Python balík; váhy modelu sú zahrnuté. Voliteľný OpenAI API kľúč umožňuje popisy scén; koláže a zvuk scén sa potom odosielajú do OpenAI.';
$lang["video_scene_python"] = 'Python spustiteľný súbor (absolútna cesta)';
$lang["video_scene_threshold"] = 'Práh prechodov scén (medzi 0 a 1)';
$lang["video_scene_api_key"] = 'OpenAI API kľúč (voliteľné)';
$lang["video_scene_model"] = 'OpenAI Realtime model podporujúci vstup obrazu a zvuku';
$lang["video_scene_audio"] = 'Zahrnúť zvuk scény do popisov';
$lang["video_scene_audio_overlap"] = 'Zvukový kontext pred a po každej scéne (sekundy, 0 až 30)';
$lang["video_scene_prompt"] = 'Výzva na popis scény';
$lang["video_scene_search_field"] = 'Pole pre vyhľadávanie popisov scén (voliteľné)';
$lang["video_scene_prompt_help"] = 'Pokyny na popis každej scény z jej snímok a zvuku. Maximálne 8 000 bajtov.';
$lang["video_scene_timeout"] = 'Maximálny čas spracovania na video (sekundy)';
$lang["video_scene_invalid_config"] = 'Skontrolujte cestu k Pythonu, prah, model, kľúč a časový limit spracovania.';
$lang["video_scene_time"] = 'Časová značka';
$lang["video_scene_thumbnail"] = 'Náhľad';
$lang["video_scene_description"] = 'Popis';
$lang["video_scene_edit_description"] = 'Upraviť popis scény v čase %time';
$lang["video_scene_invalid_description"] = 'Zadajte platný popis s maximálnou veľkosťou 16 000 bajtov.';
$lang["video_scene_description_missing"] = 'Táto scéna už neexistuje. Znova načítajte zobrazenie zdroja.';
$lang["video_scene_description_save_failed"] = 'Popis sa nepodarilo uložiť. Skúste to znova alebo znovu načítajte zobrazenie zdroja.';
$lang["video_scene_description_saved"] = 'Popis scény bol uložený.';
$lang["video_scene_no_description"] = 'Popis nie je k dispozícii';
$lang["video_scene_play"] = 'Prehrať scénu v čase %time';
$lang["video_scene_play_unavailable"] = 'Táto scéna je mimo dostupného náhľadu videa.';
$lang["video_scene_job_success"] = 'Spracovanie video scény bolo dokončené';
$lang["video_scene_job_failure"] = 'Spracovanie video scény zlyhalo. Podrobnosti nájdete v protokole úlohy.';