<?php


$lang["video_scene_title"] = 'Scene video';
$lang["video_scene_intro"] = 'Rileva le scene localmente con transnetv2-pytorch dopo il caricamento o la ricreazione dell\'anteprima. Installa il pacchetto Python prima dell\'elaborazione; i pesi del modello sono inclusi. Una chiave API OpenAI opzionale consente le descrizioni delle scene; collage e audio delle scene vengono quindi inviati a OpenAI.';
$lang["video_scene_python"] = 'Eseguibile Python (percorso assoluto)';
$lang["video_scene_threshold"] = 'Soglia di transizione della scena (tra 0 e 1)';
$lang["video_scene_api_key"] = 'Chiave API OpenAI (opzionale)';
$lang["video_scene_model"] = 'Modello OpenAI Realtime che supporta input immagine e audio';
$lang["video_scene_audio"] = 'Includi l\'audio della scena nelle descrizioni';
$lang["video_scene_audio_overlap"] = 'Contesto audio prima e dopo ogni scena (secondi, da 0 a 30)';
$lang["video_scene_prompt"] = 'Prompt per la descrizione della scena';
$lang["video_scene_search_field"] = 'Campo descrizioni scene ricercabile (opzionale)';
$lang["video_scene_prompt_help"] = 'Istruzioni per descrivere ogni scena dai suoi fotogrammi e audio. Massimo 8.000 byte.';
$lang["video_scene_timeout"] = 'Tempo massimo di elaborazione per video (secondi)';
$lang["video_scene_invalid_config"] = 'Controlla il percorso Python, la soglia, il modello, la chiave e il timeout di elaborazione.';
$lang["video_scene_time"] = 'Timestamp';
$lang["video_scene_thumbnail"] = 'Miniatura';
$lang["video_scene_description"] = 'Descrizione';
$lang["video_scene_edit_description"] = 'Modifica la descrizione della scena a %time';
$lang["video_scene_invalid_description"] = 'Inserisci una descrizione valida fino a 16.000 byte.';
$lang["video_scene_description_missing"] = 'Questa scena non esiste più. Ricarica la visualizzazione della risorsa.';
$lang["video_scene_description_save_failed"] = 'Impossibile salvare la descrizione. Riprova o ricarica la visualizzazione della risorsa.';
$lang["video_scene_description_saved"] = 'Descrizione della scena salvata.';
$lang["video_scene_no_description"] = 'Nessuna descrizione disponibile';
$lang["video_scene_play"] = 'Riproduci la scena a %time';
$lang["video_scene_play_unavailable"] = 'Questa scena è al di fuori dell\'anteprima video disponibile.';
$lang["video_scene_job_success"] = 'Elaborazione della scena video completata';
$lang["video_scene_job_failure"] = 'Elaborazione della scena video fallita. Consulta il registro del lavoro per i dettagli.';