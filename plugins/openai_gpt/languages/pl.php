<?php


$lang["openai_gpt_temperature"]='Próbkowanie temperatury między 0 a 1 (wyższe wartości oznaczają, że model podejmie większe ryzyko)';
$lang["openai_gpt_max_tokens"]='Maksymalna liczba tokenów';
$lang["openai_gpt_advanced"]='OSTRZEŻENIE - Ta sekcja służy wyłącznie do celów testowych i nie powinna być zmieniana na żywych systemach. Zmiana jakiejkolwiek opcji wtyczki tutaj wpłynie na zachowanie wszystkich pól metadanych, które zostały skonfigurowane. Zmieniaj ostrożnie!';
$lang["openai_gpt_system_message"]='Początkowy tekst wiadomości systemowej. Miejsca %%IN_TYPE%% i %%OUT_TYPE%% zostaną zastąpione przez \'text\' lub \'json\' w zależności od typów pól źródłowych/docelowych';
$lang["openai_gpt_api_key"]='Klucz API OpenAI. Uzyskaj swój klucz API z <a href=\'https://openai.com/api\' target=\'_blank\' >https://openai.com/api</a>';
$lang["plugin-openai_gpt-title"]='Integracja z API GPT OpenAI';
$lang["plugin-openai_gpt-desc"]='OpenAI wygenerowane metadane. Przekazuje skonfigurowane dane pola do API OpenAI i przechowuje zwrócone informacje.';
$lang["openai_gpt_model_override"]='Model został zablokowany w globalnej konfiguracji na: [model]';
$lang["openai_gpt_processing_multiple_resources"]='Wiele zasobów';
$lang["openai_gpt_processing_resource"]='Zasób [resource]';
$lang["openai_gpt_processing_field"]='Przetwarzanie AI dla pola \'[field]\'';
$lang["openai_gpt_language"] = 'Język wyjściowy';
$lang["openai_gpt_language_user"] = 'Język bieżącego użytkownika';
$lang["openai_gpt_overwrite_data"] = 'Czy nadpisać istniejące dane w skonfigurowanych polach?';
$lang["openai_gpt_title"] = 'Przetwarzanie metadanych OpenAI/Ollama';
$lang["openai_gpt_intro"] = 'Dodaje metadane generowane przez przesyłanie istniejących danych lub podglądu zasobu do API OpenAI (lub kompatybilnego, takiego jak Ollama) z konfigurowalnym zapytaniem. Odwiedź <a href=\'https://platform.openai.com/docs/introduction\' target=\'_blank\'>dokumentację OpenAI</a>, aby uzyskać bardziej szczegółowe informacje.';
$lang["property-openai_gpt_prompt"] = 'Przetwarzanie AI';
$lang["property-openai_gpt_input_field"] = 'Przetwarzanie wejścia AI';
$lang["openai_gpt_model"] = 'Model OpenAI do użycia (np. \'gpt-4o\')';
$lang["property-gpt_source"] = 'GPT Source';
$lang["openai_gpt"] = 'OpenAI GPT';
$lang["openai_gpt_process_existing"] = 'Przetwórz istniejące pola AI';
$lang["openai_gpt_process_existing_configure"] = 'Skonfiguruj zadanie do przetwarzania istniejących pól AI';
$lang["openai_gpt_process_existing_field_ref"] = 'Pole AI';
$lang["openai_gpt_process_existing_overwrite"] = 'Nadpisz';
$lang["openai_gpt_process_existing_field_ref_help"] = 'To jest pole docelowe do zaktualizowania.';
$lang["openai_gpt_process_existing_collection_refs_help"] = 'Ustawienie tej opcji oznacza, że tylko zasoby w wymienionych kolekcjach będą przetwarzane. Jeśli nie określono kolekcji, to WSZYSTKIE odpowiednie zasoby będą przetwarzane. Kolekcje można określić za pomocą listy oddzielonej przecinkami, a także zakresów, np. 100,105,110-115';
$lang["openai_gpt_process_existing_overwrite_help"] = 'Ustawienie tej opcji spowoduje, że wszelkie istniejące dane w polu docelowym zostaną nadpisane. Należy pamiętać, że jeśli nadpisywanie jest włączone, a pole wejściowe nie zawiera danych, pole docelowe zostanie wyczyszczone.';
$lang["openai_gpt_limit_warning"] = 'OSTRZEŻENIE - Przekroczono limit tokenów, więc dalsze wywołania API OpenAI nie będą działać. Pola GPT nie będą przetwarzane.';
$lang["openai_gpt_limit_warning_short"] = 'OSTRZEŻENIE - Przekroczono limit tokenów, więc pola GPT nie będą przetwarzane.';
$lang["openai_gpt_usage_days"] = 'Użycie tokenów w ciągu ostatnich %%DAYS%% dni';
$lang["openai_gpt_token_limit"] = 'Limit tokenów';
$lang["openai_gpt_no_token_limit"] = 'Brak skonfigurowanego limitu tokenów';
$lang["openai_gpt_configured_limit"] = '%%TOKEN_LIMIT%% na %%DAYS%% dni';
$lang["openai_gpt_token_count"] = '%%TOKEN_COUNT%% tokenów';
$lang["openai_gpt_provider"] = 'Dostawca AI';
$lang["openai_gpt_provider_override"] = 'Dostawca został zablokowany w konfiguracji globalnej na: [provider]';
$lang["ollama_name"] = 'Ollama';
$lang["ollama_model"] = 'Model Ollama do użycia (np. \'gemma3:12b\')';
$lang["ollama_endpoint"] = 'Proszę przetłumaczyć: Ollama endpoint to use (np. http://[IP]:11434/v1/chat/completions

Ollama endpoint do użycia (np. http://[IP]:11434/v1/chat/completions';