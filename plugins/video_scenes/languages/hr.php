<?php


$lang["video_scene_title"] = 'Video scene';
$lang["video_scene_intro"] = 'Otkrivanje scena lokalno pomoću transnetv2-pytorch nakon učitavanja ili ponovnog stvaranja pregleda. Prije obrade instalirajte Python paket; težine modela su uključene. Opcionalni OpenAI API ključ omogućuje opise scena; kolaži i audio scena tada se šalju OpenAI-u.';
$lang["video_scene_python"] = 'Python izvršna datoteka (apsolutna putanja)';
$lang["video_scene_threshold"] = 'Prag prijelaza scene (između 0 i 1)';
$lang["video_scene_api_key"] = 'OpenAI API ključ (opcionalno)';
$lang["video_scene_model"] = 'OpenAI Realtime model koji podržava unos slike i zvuka';
$lang["video_scene_audio"] = 'Uključi audio scene u opise';
$lang["video_scene_audio_overlap"] = 'Audio kontekst prije i poslije svake scene (sekunde, 0 do 30)';
$lang["video_scene_prompt"] = 'Upit za opis scene';
$lang["video_scene_search_field"] = 'Polje za pretražive opise scena (opcionalno)';
$lang["video_scene_prompt_help"] = 'Upute za opisivanje svake scene na temelju njenih okvira i zvuka. Maksimalno 8.000 bajtova.';
$lang["video_scene_timeout"] = 'Maksimalno vrijeme obrade po videu (sekunde)';
$lang["video_scene_invalid_config"] = 'Provjerite Python putanju, prag, model, ključ i vrijeme obrade.';
$lang["video_scene_time"] = 'Vremenska oznaka';
$lang["video_scene_thumbnail"] = 'Sličica';
$lang["video_scene_description"] = 'Opis';
$lang["video_scene_edit_description"] = 'Uredi opis scene u %time';
$lang["video_scene_invalid_description"] = 'Unesite valjani opis do 16.000 bajtova.';
$lang["video_scene_description_missing"] = 'Ova scena više ne postoji. Ponovno učitajte prikaz resursa.';
$lang["video_scene_description_save_failed"] = 'Opis nije moguće spremiti. Pokušajte ponovno ili ponovno učitajte prikaz resursa.';
$lang["video_scene_description_saved"] = 'Opis scene je spremljen.';
$lang["video_scene_no_description"] = 'Nema dostupnog opisa';
$lang["video_scene_play"] = 'Reproduciraj scenu u %time';
$lang["video_scene_play_unavailable"] = 'Ova scena je izvan dostupnog video pregleda.';
$lang["video_scene_job_success"] = 'Obrada video scene je završena';
$lang["video_scene_job_failure"] = 'Obrada video scene nije uspjela. Pogledajte zapisnik zadatka za detalje.';