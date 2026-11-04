<?php


$lang["video_scene_title"] = 'Видео сцены';
$lang["video_scene_intro"] = 'Обнаружение сцен локально с помощью transnetv2-pytorch после загрузки или пересоздания превью. Установите пакет Python перед обработкой; веса модели включены. Необязательный ключ API OpenAI позволяет создавать описания сцен; коллажи и аудио сцен затем отправляются в OpenAI.';
$lang["video_scene_python"] = 'Исполняемый файл Python (абсолютный путь)';
$lang["video_scene_threshold"] = 'Порог перехода сцены (от 0 до 1)';
$lang["video_scene_api_key"] = 'Ключ API OpenAI (необязательно)';
$lang["video_scene_model"] = 'Модель OpenAI Realtime с поддержкой ввода изображения и аудио';
$lang["video_scene_audio"] = 'Включать аудио сцены в описания';
$lang["video_scene_audio_overlap"] = 'Аудиоконтекст до и после каждой сцены (секунды, от 0 до 30)';
$lang["video_scene_prompt"] = 'Подсказка для описания сцены';
$lang["video_scene_search_field"] = 'Поле для поиска описаний сцен (необязательно)';
$lang["video_scene_prompt_help"] = 'Инструкции по описанию каждой сцены на основе её кадров и аудио. Максимум 8000 байт.';
$lang["video_scene_timeout"] = 'Максимальное время обработки на видео (секунды)';
$lang["video_scene_invalid_config"] = 'Проверьте путь к Python, порог, модель, ключ и время обработки.';
$lang["video_scene_time"] = 'Отметка времени';
$lang["video_scene_thumbnail"] = 'Миниатюра';
$lang["video_scene_description"] = 'Описание';
$lang["video_scene_edit_description"] = 'Редактировать описание сцены в %time';
$lang["video_scene_invalid_description"] = 'Введите корректное описание длиной до 16 000 байт.';
$lang["video_scene_description_missing"] = 'Эта сцена больше не существует. Перезагрузите просмотр ресурса.';
$lang["video_scene_description_save_failed"] = 'Не удалось сохранить описание. Попробуйте снова или перезагрузите просмотр ресурса.';
$lang["video_scene_description_saved"] = 'Описание сцены сохранено.';
$lang["video_scene_no_description"] = 'Описание отсутствует';
$lang["video_scene_play"] = 'Воспроизвести сцену в %time';
$lang["video_scene_play_unavailable"] = 'Эта сцена находится за пределами доступного предварительного просмотра видео.';
$lang["video_scene_job_success"] = 'Обработка видео сцены завершена';
$lang["video_scene_job_failure"] = 'Обработка видео сцены не удалась. См. журнал заданий для подробностей.';