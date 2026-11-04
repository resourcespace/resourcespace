<?php


$lang["video_scene_title"] = 'Escenas de video';
$lang["video_scene_intro"] = 'Detecta escenas localmente con transnetv2-pytorch después de la carga o recreación de la vista previa. Instale el paquete de Python antes de procesar; los pesos del modelo están incluidos. Una clave API opcional de OpenAI permite descripciones de escenas; los collages y el audio de la escena se envían luego a OpenAI.';
$lang["video_scene_python"] = 'Ejecutable de Python (ruta absoluta)';
$lang["video_scene_threshold"] = 'Umbral de transición de escena (entre 0 y 1)';
$lang["video_scene_api_key"] = 'Clave API de OpenAI (opcional)';
$lang["video_scene_model"] = 'Modelo en tiempo real de OpenAI que soporta entrada de imagen y audio';
$lang["video_scene_audio"] = 'Incluir audio de la escena en las descripciones';
$lang["video_scene_audio_overlap"] = 'Contexto de audio antes y después de cada escena (segundos, 0 a 30)';
$lang["video_scene_prompt"] = 'Indicador para descripción de escenas';
$lang["video_scene_search_field"] = 'Campo de descripciones de escenas buscable (opcional)';
$lang["video_scene_prompt_help"] = 'Instrucciones para describir cada escena a partir de sus cuadros y audio. Máximo 8.000 bytes.';
$lang["video_scene_timeout"] = 'Tiempo máximo de procesamiento por video (segundos)';
$lang["video_scene_invalid_config"] = 'Verifique la ruta de Python, el umbral, el modelo, la clave y el tiempo de procesamiento.';
$lang["video_scene_time"] = 'Marca de tiempo';
$lang["video_scene_thumbnail"] = 'Miniatura';
$lang["video_scene_description"] = 'Descripción';
$lang["video_scene_edit_description"] = 'Editar descripción de la escena en %time';
$lang["video_scene_invalid_description"] = 'Ingrese una descripción válida de hasta 16,000 bytes.';
$lang["video_scene_description_missing"] = 'Esta escena ya no existe. Recargue la vista del recurso.';
$lang["video_scene_description_save_failed"] = 'No se pudo guardar la descripción. Intente nuevamente o recargue la vista del recurso.';
$lang["video_scene_description_saved"] = 'Descripción de la escena guardada.';
$lang["video_scene_no_description"] = 'No hay descripción disponible';
$lang["video_scene_play"] = 'Reproducir escena en %time';
$lang["video_scene_play_unavailable"] = 'Esta escena está fuera de la vista previa de video disponible.';
$lang["video_scene_job_success"] = 'Procesamiento de la escena de video completado';
$lang["video_scene_job_failure"] = 'El procesamiento de la escena de video falló. Consulte el registro del trabajo para más detalles.';