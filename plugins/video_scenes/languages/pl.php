<?php


$lang["video_scene_title"] = 'Sceny wideo';
$lang["video_scene_intro"] = 'Wykrywaj sceny lokalnie za pomocą transnetv2-pytorch po przesłaniu lub odtworzeniu podglądu. Przed przetwarzaniem zainstaluj pakiet Python; wagi modelu są dołączone. Opcjonalny klucz API OpenAI umożliwia opisy scen; kolaże i dźwięk sceny są następnie wysyłane do OpenAI.';
$lang["video_scene_python"] = 'Wykonywalny plik Pythona (pełna ścieżka)';
$lang["video_scene_threshold"] = 'Próg przejścia sceny (od 0 do 1)';
$lang["video_scene_api_key"] = 'Klucz API OpenAI (opcjonalnie)';
$lang["video_scene_model"] = 'Model OpenAI Realtime obsługujący obraz i dźwięk';
$lang["video_scene_audio"] = 'Uwzględnij dźwięk sceny w opisach';
$lang["video_scene_audio_overlap"] = 'Kontekst dźwiękowy przed i po każdej scenie (sekundy, 0 do 30)';
$lang["video_scene_prompt"] = 'Polecenie opisu sceny';
$lang["video_scene_search_field"] = 'Pole z opisami scen do wyszukiwania (opcjonalnie)';
$lang["video_scene_prompt_help"] = 'Instrukcje dotyczące opisywania każdej sceny na podstawie jej klatek i dźwięku. Maksymalnie 8 000 bajtów.';
$lang["video_scene_timeout"] = 'Maksymalny czas przetwarzania na wideo (sekundy)';
$lang["video_scene_invalid_config"] = 'Sprawdź ścieżkę Pythona, próg, model, klucz i limit czasu przetwarzania.';
$lang["video_scene_time"] = 'Znacznik czasu';
$lang["video_scene_thumbnail"] = 'Miniatura';
$lang["video_scene_description"] = 'Opis';
$lang["video_scene_edit_description"] = 'Edytuj opis sceny o %time';
$lang["video_scene_invalid_description"] = 'Wprowadź poprawny opis do 16 000 bajtów.';
$lang["video_scene_description_missing"] = 'Ta scena już nie istnieje. Przeładuj widok zasobu.';
$lang["video_scene_description_save_failed"] = 'Nie udało się zapisać opisu. Spróbuj ponownie lub przeładuj widok zasobu.';
$lang["video_scene_description_saved"] = 'Opis sceny zapisany.';
$lang["video_scene_no_description"] = 'Brak dostępnego opisu';
$lang["video_scene_play"] = 'Odtwórz scenę o %time';
$lang["video_scene_play_unavailable"] = 'Ta scena znajduje się poza dostępnym podglądem wideo.';
$lang["video_scene_job_success"] = 'Przetwarzanie sceny wideo zakończone pomyślnie';
$lang["video_scene_job_failure"] = 'Przetwarzanie sceny wideo nie powiodło się. Zobacz dziennik zadań, aby uzyskać szczegóły.';