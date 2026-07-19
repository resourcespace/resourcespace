<?php


$lang["museumplus_configuration"] = 'Конфигурация на MuseumPlus';
$lang["museumplus_top_menu_title"] = 'MuseumPlus: невалидни асоциации';
$lang["museumplus_api_settings_header"] = 'Детайли за API';
$lang["museumplus_host"] = 'Хост';
$lang["museumplus_host_api"] = 'API хост (само за API повиквания; обикновено същият като горния)';
$lang["museumplus_application"] = 'Име на приложението (не е задължително за по-новите URL адреси на M+ хост)';
$lang["user"] = 'Потребител';
$lang["museumplus_api_user"] = 'Потребител';
$lang["password"] = 'Парола';
$lang["museumplus_api_pass"] = 'Парола';
$lang["museumplus_RS_settings_header"] = 'Настройки на ResourceSpace';
$lang["museumplus_mpid_field"] = 'Поле за метаданни, използвано за съхраняване на идентификатора MuseumPlus (MpID)';
$lang["museumplus_module_name_field"] = 'Поле за метаданни, използвано за съхраняване на името на модулите, за които е валиден MpID. АКО не е зададено, приставката ще използва конфигурацията на модула "Обект".';
$lang["museumplus_secondary_links_field"] = 'Поле за метаданни, използвано за съхраняване на вторичните връзки към други модули. ResourceSpace ще генерира URL адрес на MuseumPlus за всяка от връзките. Връзките ще имат специален синтаксис: име_на_модул:ID (например "Обект:1234")';
$lang["museumplus_object_details_title"] = 'Детайли за MuseumPlus';
$lang["museumplus_script_header"] = 'Настройки на скрипта';
$lang["museumplus_last_run_date"] = 'Последен път изпълнение на скрипта';
$lang["museumplus_enable_script"] = 'Активирай скрипта MuseumPlus';
$lang["museumplus_interval_run"] = 'Изпълнявай скрипта на следния интервал (напр. +1 ден, +2 седмици, две седмици). Оставете празно и той ще се изпълнява всеки път, когато се стартира cron_copy_hitcount.php)';
$lang["museumplus_log_directory"] = 'Директория за съхранение на логовете на скрипта. Ако е оставена празна или невалидна, няма да се създават логове.';
$lang["museumplus_integrity_check_field"] = 'Поле за проверка на цялостта';
$lang["museumplus_modules_configuration_header"] = 'Конфигурация на модулите';
$lang["museumplus_module"] = 'Модул';
$lang["museumplus_add_new_module"] = 'Добави нов модул MuseumPlus';
$lang["museumplus_mplus_field_name"] = 'Име на полето в MuseumPlus';
$lang["museumplus_rs_field"] = 'Поле ResourceSpace';
$lang["museumplus_view_in_museumplus"] = 'Виж в MuseumPlus';
$lang["museumplus_confirm_delete_module_config"] = 'Наистина ли искате да изтриете тази конфигурация на модул? Тази операция не може да бъде отменена!';
$lang["museumplus_module_setup"] = 'Настройка на модула';
$lang["museumplus_module_name"] = 'Име на модула MuseumPlus';
$lang["museumplus_mplus_id_field"] = 'Име на полето MuseumPlus ID';
$lang["museumplus_mplus_id_field_helptxt"] = 'Оставете празно, за да използвате техническия ID \'__id\' (по подразбиране)';
$lang["museumplus_rs_uid_field"] = 'Поле ResourceSpace UID';
$lang["museumplus_applicable_resource_types"] = 'Приложим тип(ове) ресурс';
$lang["museumplus_field_mappings"] = 'Мапинг на полета между MuseumPlus и ResourceSpace';
$lang["museumplus_add_mapping"] = 'Добави мапинг';
$lang["museumplus_error_bad_conn_data"] = 'Невалидни данни за връзка с MuseumPlus';
$lang["museumplus_error_unexpected_response"] = 'Получен неочакван код на отговор от MuseumPlus - %code';
$lang["museumplus_error_no_data_found"] = 'Не са намерени данни в MuseumPlus за този MpID - %mpid';
$lang["museumplus_warning_script_not_completed"] = 'ВНИМАНИЕ: Скриптът MuseumPlus не е завършил изпълнението си от \'%script_last_ran\'.
Можете безопасно да игнорирате това предупреждение, само ако сте получили известие за успешно завършване на скрипта.';
$lang["museumplus_error_script_failed"] = 'Скриптът MuseumPlus не успя да се изпълни, защото имаше заключване на процес. Това означава, че предишното изпълнение не е завършило.
Ако трябва да изчистите заключването след неуспешно изпълнение, стартирайте скрипта по следния начин:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"] = 'Настройката $php_path трябва да бъде зададена, за да може cron да работи успешно!';
$lang["museumplus_error_not_deleted_module_conf"] = 'Неуспешно изтриване на конфигурацията на модула.';
$lang["museumplus_error_unknown_type_saved_config"] = 'Типът \'museumplus_modules_saved_config\' е непознат!';
$lang["museumplus_error_invalid_association"] = 'Невалидна асоциация на модул(и). Моля, уверете се, че е въведен правилният Модул и/или ID на запис!';
$lang["museumplus_id_returns_multiple_records"] = 'Намерен е повече от един запис - моля, въведете техническия идентификатор вместо това';
$lang["museumplus_error_module_no_field_maps"] = 'Не може да се синхронизира данни от MuseumPlus. Причина: модулът \'%name\' няма конфигурирани съответствия на полета.';
$lang["page-title_museumplus_museumplus_object_details"] = 'Детайли за обекта в MuseumPlus';
$lang["page-title_museumplus_setup_module"] = 'Настройка на модула MuseumPlus';
$lang["page-title_museumplus_setup"] = 'Настройка на плъгина MuseumPlus';
