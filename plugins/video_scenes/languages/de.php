<?php


$lang["video_scene_title"] = 'Videoszenen';
$lang["video_scene_intro"] = 'Erkennen Sie Szenen lokal mit transnetv2-pytorch nach dem Hochladen oder der Vorschau-Neuerstellung. Installieren Sie das Python-Paket vor der Verarbeitung; Modellgewichte sind enthalten. Ein optionaler OpenAI API-Schlüssel ermöglicht Szenenbeschreibungen; Collagen und Szenen-Audio werden dann an OpenAI gesendet.';
$lang["video_scene_python"] = 'Python ausführbare Datei (absoluter Pfad)';
$lang["video_scene_threshold"] = 'Schwellenwert für Szenenübergänge (zwischen 0 und 1)';
$lang["video_scene_api_key"] = 'OpenAI API-Schlüssel (optional)';
$lang["video_scene_model"] = 'OpenAI Echtzeitmodell, das Bild- und Audioeingaben unterstützt';
$lang["video_scene_audio"] = 'Szenen-Audio in Beschreibungen einbeziehen';
$lang["video_scene_audio_overlap"] = 'Audio-Kontext vor und nach jeder Szene (Sekunden, 0 bis 30)';
$lang["video_scene_prompt"] = 'Szenenbeschreibung Aufforderung';
$lang["video_scene_search_field"] = 'Durchsuchbares Feld für Szenenbeschreibungen (optional)';
$lang["video_scene_prompt_help"] = 'Anweisungen zur Beschreibung jeder Szene anhand ihrer Frames und Audios. Maximal 8.000 Bytes.';
$lang["video_scene_timeout"] = 'Maximale Verarbeitungszeit pro Video (Sekunden)';
$lang["video_scene_invalid_config"] = 'Überprüfen Sie den Python-Pfad, Schwellenwert, Modell, Schlüssel und die Verarbeitungszeitüberschreitung.';
$lang["video_scene_time"] = 'Zeitstempel';
$lang["video_scene_thumbnail"] = 'Vorschaubild';
$lang["video_scene_description"] = 'Beschreibung';
$lang["video_scene_edit_description"] = 'Szenenbeschreibung bei %time bearbeiten';
$lang["video_scene_invalid_description"] = 'Geben Sie eine gültige Beschreibung mit bis zu 16.000 Bytes ein.';
$lang["video_scene_description_missing"] = 'Diese Szene existiert nicht mehr. Laden Sie die Ressourcenansicht neu.';
$lang["video_scene_description_save_failed"] = 'Die Beschreibung konnte nicht gespeichert werden. Versuchen Sie es erneut oder laden Sie die Ressourcenansicht neu.';
$lang["video_scene_description_saved"] = 'Szenenbeschreibung gespeichert.';
$lang["video_scene_no_description"] = 'Keine Beschreibung verfügbar';
$lang["video_scene_play"] = 'Szene bei %time abspielen';
$lang["video_scene_play_unavailable"] = 'Diese Szene liegt außerhalb der verfügbaren Video-Vorschau.';
$lang["video_scene_job_success"] = 'Video-Szenenverarbeitung abgeschlossen';
$lang["video_scene_job_failure"] = 'Video-Szenenverarbeitung fehlgeschlagen. Details im Job-Protokoll einsehen.';