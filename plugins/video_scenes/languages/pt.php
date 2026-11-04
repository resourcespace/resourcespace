<?php


$lang["video_scene_title"] = 'Cenas de vídeo';
$lang["video_scene_intro"] = 'Detecte cenas localmente com transnetv2-pytorch após o upload ou recriação da pré-visualização. Instale o pacote Python antes do processamento; os pesos do modelo estão incluídos. Uma chave de API OpenAI opcional permite descrições das cenas; colagens e áudio das cenas são então enviados para a OpenAI.';
$lang["video_scene_python"] = 'Executável Python (caminho absoluto)';
$lang["video_scene_threshold"] = 'Limite de transição de cena (entre 0 e 1)';
$lang["video_scene_api_key"] = 'Chave de API OpenAI (opcional)';
$lang["video_scene_model"] = 'Modelo OpenAI Realtime que suporta entrada de imagem e áudio';
$lang["video_scene_audio"] = 'Incluir áudio da cena nas descrições';
$lang["video_scene_audio_overlap"] = 'Contexto de áudio antes e depois de cada cena (segundos, 0 a 30)';
$lang["video_scene_prompt"] = 'Prompt para descrição da cena';
$lang["video_scene_search_field"] = 'Campo de descrições de cenas pesquisável (opcional)';
$lang["video_scene_prompt_help"] = 'Instruções para descrever cada cena a partir de seus quadros e áudio. Máximo de 8.000 bytes.';
$lang["video_scene_timeout"] = 'Tempo máximo de processamento por vídeo (segundos)';
$lang["video_scene_invalid_config"] = 'Verifique o caminho do Python, limite, modelo, chave e tempo limite de processamento.';
$lang["video_scene_time"] = 'Carimbo de tempo';
$lang["video_scene_thumbnail"] = 'Miniatura';
$lang["video_scene_description"] = 'Descrição';
$lang["video_scene_edit_description"] = 'Editar descrição da cena em %time';
$lang["video_scene_invalid_description"] = 'Insira uma descrição válida de até 16.000 bytes.';
$lang["video_scene_description_missing"] = 'Esta cena não existe mais. Recarregue a visualização do recurso.';
$lang["video_scene_description_save_failed"] = 'A descrição não pôde ser salva. Tente novamente ou recarregue a visualização do recurso.';
$lang["video_scene_description_saved"] = 'Descrição da cena salva.';
$lang["video_scene_no_description"] = 'Nenhuma descrição disponível';
$lang["video_scene_play"] = 'Reproduzir cena em %time';
$lang["video_scene_play_unavailable"] = 'Esta cena está fora da pré-visualização de vídeo disponível.';
$lang["video_scene_job_success"] = 'Processamento da cena de vídeo concluído';
$lang["video_scene_job_failure"] = 'Falha no processamento da cena de vídeo. Veja o log do trabalho para detalhes.';