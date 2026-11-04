<?php


$lang["video_scene_title"] = 'Видео сцени';
$lang["video_scene_intro"] = 'Откривайте сцени локално с transnetv2-pytorch след качване или възстановяване на визуализация. Инсталирайте Python пакета преди обработка; включени са тежести на модела. Опционален OpenAI API ключ позволява описания на сцени; колажи и аудио от сцените се изпращат към OpenAI.';
$lang["video_scene_python"] = 'Изпълним Python файл (абсолютен път)';
$lang["video_scene_threshold"] = 'Праг за преход на сцена (между 0 и 1)';
$lang["video_scene_api_key"] = 'OpenAI API ключ (по избор)';
$lang["video_scene_model"] = 'OpenAI Realtime модел, поддържащ вход от изображения и аудио';
$lang["video_scene_audio"] = 'Включване на аудио от сцената в описанията';
$lang["video_scene_audio_overlap"] = 'Аудио контекст преди и след всяка сцена (секунди, от 0 до 30)';
$lang["video_scene_prompt"] = 'Подканващ текст за описание на сцена';
$lang["video_scene_search_field"] = 'Поле за търсене на описания на сцени (по избор)';
$lang["video_scene_prompt_help"] = 'Инструкции за описание на всяка сцена от нейните кадри и аудио. Максимум 8 000 байта.';
$lang["video_scene_timeout"] = 'Максимално време за обработка на видео (секунди)';
$lang["video_scene_invalid_config"] = 'Проверете пътя до Python, прага, модела, ключа и времето за обработка.';
$lang["video_scene_time"] = 'Отметка на времето';
$lang["video_scene_thumbnail"] = 'Миниатюра';
$lang["video_scene_description"] = 'Описание';
$lang["video_scene_edit_description"] = 'Редактиране на описанието на сцената в %time';
$lang["video_scene_invalid_description"] = 'Въведете валидно описание с дължина до 16 000 байта.';
$lang["video_scene_description_missing"] = 'Тази сцена вече не съществува. Презаредете изгледа на ресурса.';
$lang["video_scene_description_save_failed"] = 'Описание не можа да бъде запазено. Опитайте отново или презаредете изгледа на ресурса.';
$lang["video_scene_description_saved"] = 'Описанието на сцената е запазено.';
$lang["video_scene_no_description"] = 'Няма налично описание';
$lang["video_scene_play"] = 'Възпроизвеждане на сцена в %time';
$lang["video_scene_play_unavailable"] = 'Тази сцена е извън наличния видео преглед.';
$lang["video_scene_job_success"] = 'Обработката на видео сцената е завършена';
$lang["video_scene_job_failure"] = 'Обработката на видео сцената не бе успешна. Вижте дневника на задачата за подробности.';