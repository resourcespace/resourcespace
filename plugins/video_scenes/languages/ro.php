<?php


$lang["video_scene_title"] = 'Scene video';
$lang["video_scene_intro"] = 'Detectați scenele local cu transnetv2-pytorch după încărcare sau recrearea previzualizării. Instalați pachetul Python înainte de procesare; greutățile modelului sunt incluse. O cheie API OpenAI opțională permite descrieri ale scenelor; colajele și audio-ul scenei sunt apoi trimise către OpenAI.';
$lang["video_scene_python"] = 'Executabil Python (cale absolută)';
$lang["video_scene_threshold"] = 'Pragul de tranziție a scenei (între 0 și 1)';
$lang["video_scene_api_key"] = 'Cheie API OpenAI (opțional)';
$lang["video_scene_model"] = 'Model OpenAI în timp real care suportă intrare imagine și audio';
$lang["video_scene_audio"] = 'Include audio-ul scenei în descrieri';
$lang["video_scene_audio_overlap"] = 'Context audio înainte și după fiecare scenă (secunde, 0 până la 30)';
$lang["video_scene_prompt"] = 'Instrucțiune pentru descrierea scenei';
$lang["video_scene_search_field"] = 'Câmp pentru descrieri ale scenelor căutabile (opțional)';
$lang["video_scene_prompt_help"] = 'Instrucțiuni pentru descrierea fiecărei scene din cadrele și audio-ul său. Maximum 8.000 de octeți.';
$lang["video_scene_timeout"] = 'Timp maxim de procesare per video (secunde)';
$lang["video_scene_invalid_config"] = 'Verificați calea Python, pragul, modelul, cheia și timpul de procesare.';
$lang["video_scene_time"] = 'Marcaj temporal';
$lang["video_scene_thumbnail"] = 'Miniatură';
$lang["video_scene_description"] = 'Descriere';
$lang["video_scene_edit_description"] = 'Editează descrierea scenei la %time';
$lang["video_scene_invalid_description"] = 'Introduceți o descriere validă de până la 16.000 de octeți.';
$lang["video_scene_description_missing"] = 'Această scenă nu mai există. Reîncarcă vizualizarea resursei.';
$lang["video_scene_description_save_failed"] = 'Descrierea nu a putut fi salvată. Încearcă din nou sau reîncarcă vizualizarea resursei.';
$lang["video_scene_description_saved"] = 'Descrierea scenei a fost salvată.';
$lang["video_scene_no_description"] = 'Nicio descriere disponibilă';
$lang["video_scene_play"] = 'Redă scena la %time';
$lang["video_scene_play_unavailable"] = 'Această scenă este în afara previzualizării video disponibile.';
$lang["video_scene_job_success"] = 'Procesarea scenei video a fost finalizată';
$lang["video_scene_job_failure"] = 'Procesarea scenei video a eșuat. Vezi jurnalul de activități pentru detalii.';