<?php


$lang["video_scene_title"] = 'Відеосцени';
$lang["video_scene_intro"] = 'Виявляйте сцени локально за допомогою transnetv2-pytorch після завантаження або відтворення попереднього перегляду. Встановіть пакет Python перед обробкою; ваги моделі включені. Опціональний ключ API OpenAI дозволяє отримувати описи сцен; колажі та аудіо сцен потім надсилаються до OpenAI.';
$lang["video_scene_python"] = 'Виконуваний файл Python (абсолютний шлях)';
$lang["video_scene_threshold"] = 'Поріг переходу сцени (від 0 до 1)';
$lang["video_scene_api_key"] = 'Ключ API OpenAI (опціонально)';
$lang["video_scene_model"] = 'Модель OpenAI Realtime, що підтримує введення зображень та аудіо';
$lang["video_scene_audio"] = 'Включати аудіо сцени в описи';
$lang["video_scene_audio_overlap"] = 'Аудіоконтекст до та після кожної сцени (секунди, від 0 до 30)';
$lang["video_scene_prompt"] = 'Підказка для опису сцени';
$lang["video_scene_search_field"] = 'Поле для пошуку описів сцен (опціонально)';
$lang["video_scene_prompt_help"] = 'Інструкції для опису кожної сцени на основі її кадрів та аудіо. Максимум 8 000 байт.';
$lang["video_scene_timeout"] = 'Максимальний час обробки на відео (секунди)';
$lang["video_scene_invalid_config"] = 'Перевірте шлях до Python, поріг, модель, ключ та час обробки.';
$lang["video_scene_time"] = 'Позначка часу';
$lang["video_scene_thumbnail"] = 'Мініатюра';
$lang["video_scene_description"] = 'Опис';
$lang["video_scene_edit_description"] = 'Редагувати опис сцени на %time';
$lang["video_scene_invalid_description"] = 'Введіть дійсний опис довжиною до 16 000 байт.';
$lang["video_scene_description_missing"] = 'Ця сцена більше не існує. Перезавантажте перегляд ресурсу.';
$lang["video_scene_description_save_failed"] = 'Не вдалося зберегти опис. Спробуйте ще раз або перезавантажте перегляд ресурсу.';
$lang["video_scene_description_saved"] = 'Опис сцени збережено.';
$lang["video_scene_no_description"] = 'Опис відсутній';
$lang["video_scene_play"] = 'Відтворити сцену на %time';
$lang["video_scene_play_unavailable"] = 'Ця сцена знаходиться поза межами доступного відеоперегляду.';
$lang["video_scene_job_success"] = 'Обробка відеосцени завершена';
$lang["video_scene_job_failure"] = 'Обробка відеосцени не вдалася. Деталі див. у журналі завдань.';