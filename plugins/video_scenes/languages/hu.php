<?php


$lang["video_scene_title"] = 'Videójelenetek';
$lang["video_scene_intro"] = 'Jelenetek helyi felismerése a transnetv2-pytorch segítségével feltöltés vagy előnézet újragenerálása után. A feldolgozás előtt telepítse a Python csomagot; a modell súlyai tartalmazva vannak. Egy opcionális OpenAI API kulcs jelenetleírásokat tesz lehetővé; kollázsok és jelenethangok ezután az OpenAI-hoz kerülnek.';
$lang["video_scene_python"] = 'Python futtatható fájl (abszolút elérési út)';
$lang["video_scene_threshold"] = 'Jelenetváltás küszöbértéke (0 és 1 között)';
$lang["video_scene_api_key"] = 'OpenAI API kulcs (opcionális)';
$lang["video_scene_model"] = 'OpenAI valós idejű modell, amely támogatja a kép- és hangbemenetet';
$lang["video_scene_audio"] = 'Jelenethangok bevonása a leírásokba';
$lang["video_scene_audio_overlap"] = 'Hangkörnyezet minden jelenet előtt és után (másodperc, 0-tól 30-ig)';
$lang["video_scene_prompt"] = 'Jelenetleírási utasítás';
$lang["video_scene_search_field"] = 'Kereshető jelenetleírás mező (opcionális)';
$lang["video_scene_prompt_help"] = 'Utasítások minden jelenet leírásához a képkockák és hang alapján. Maximum 8 000 bájt.';
$lang["video_scene_timeout"] = 'Maximális feldolgozási idő videónként (másodperc)';
$lang["video_scene_invalid_config"] = 'Ellenőrizze a Python elérési útját, a küszöbértéket, a modellt, a kulcsot és a feldolgozási időkorlátot.';
$lang["video_scene_time"] = 'Időbélyeg';
$lang["video_scene_thumbnail"] = 'Bélyegkép';
$lang["video_scene_description"] = 'Leírás';
$lang["video_scene_edit_description"] = 'Jelenet leírásának szerkesztése %time időpontban';
$lang["video_scene_invalid_description"] = 'Adjon meg egy érvényes, legfeljebb 16 000 bájtos leírást.';
$lang["video_scene_description_missing"] = 'Ez a jelenet már nem létezik. Töltse újra az erőforrás nézetet.';
$lang["video_scene_description_save_failed"] = 'A leírás mentése sikertelen volt. Próbálja újra vagy töltse újra az erőforrás nézetet.';
$lang["video_scene_description_saved"] = 'Jelenet leírása elmentve.';
$lang["video_scene_no_description"] = 'Nincs elérhető leírás';
$lang["video_scene_play"] = 'Jelenet lejátszása %time időpontban';
$lang["video_scene_play_unavailable"] = 'Ez a jelenet kívül esik az elérhető videó előnézeten.';
$lang["video_scene_job_success"] = 'A videójelenet feldolgozása befejeződött';
$lang["video_scene_job_failure"] = 'A videójelenet feldolgozása sikertelen volt. Részletekért tekintse meg a feladat naplóját.';