<?php


$lang["video_scene_title"] = 'Escenes de vídeo';
$lang["video_scene_intro"] = 'Detecta escenes localment amb transnetv2-pytorch després de la càrrega o la recreació de la previsualització. Instal·la el paquet Python abans de processar; els pesos del model estan inclosos. Una clau API d\'OpenAI opcional permet descripcions d\'escenes; els collages i l\'àudio de l\'escena es envien a OpenAI.';
$lang["video_scene_python"] = 'Executables Python (camí absolut)';
$lang["video_scene_threshold"] = 'Llindar de transició d\'escena (entre 0 i 1)';
$lang["video_scene_api_key"] = 'Clau API d\'OpenAI (opcional)';
$lang["video_scene_model"] = 'Model OpenAI en temps real que suporta entrada d\'imatge i àudio';
$lang["video_scene_audio"] = 'Inclou l\'àudio de l\'escena a les descripcions';
$lang["video_scene_audio_overlap"] = 'Context d\'àudio abans i després de cada escena (segons, de 0 a 30)';
$lang["video_scene_prompt"] = 'Indicació per a la descripció de l\'escena';
$lang["video_scene_search_field"] = 'Camp de descripcions d\'escenes cercables (opcional)';
$lang["video_scene_prompt_help"] = 'Instruccions per descriure cada escena a partir dels seus fotogrames i àudio. Màxim 8.000 bytes.';
$lang["video_scene_timeout"] = 'Temps màxim de processament per vídeo (segons)';
$lang["video_scene_invalid_config"] = 'Comprova el camí de Python, el llindar, el model, la clau i el temps d\'espera de processament.';
$lang["video_scene_time"] = 'Marca temporal';
$lang["video_scene_thumbnail"] = 'Miniatura';
$lang["video_scene_description"] = 'Descripció';
$lang["video_scene_edit_description"] = 'Edita la descripció de l\'escena a %time';
$lang["video_scene_invalid_description"] = 'Introdueix una descripció vàlida de fins a 16.000 bytes.';
$lang["video_scene_description_missing"] = 'Aquesta escena ja no existeix. Torna a carregar la vista del recurs.';
$lang["video_scene_description_save_failed"] = 'No s\'ha pogut desar la descripció. Torna-ho a provar o torna a carregar la vista del recurs.';
$lang["video_scene_description_saved"] = 'Descripció de l\'escena desada.';
$lang["video_scene_no_description"] = 'No hi ha cap descripció disponible';
$lang["video_scene_play"] = 'Reprodueix l\'escena a %time';
$lang["video_scene_play_unavailable"] = 'Aquesta escena està fora de la previsualització de vídeo disponible.';
$lang["video_scene_job_success"] = 'Processament de l\'escena de vídeo completat';
$lang["video_scene_job_failure"] = 'El processament de l\'escena de vídeo ha fallat. Consulta el registre de la feina per a més detalls.';