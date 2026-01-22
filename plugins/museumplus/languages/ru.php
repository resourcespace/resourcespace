<?php


$lang["museumplus_configuration"]='Конфигурация MuseumPlus';
$lang["museumplus_top_menu_title"]='MuseumPlus: недопустимые ассоциации';
$lang["museumplus_api_settings_header"]='Детали API';
$lang["museumplus_host"]='Хост (Host)';
$lang["museumplus_host_api"]='Хост API (только для вызовов API; обычно такой же, как указанный выше)';
$lang["museumplus_application"]='Название приложения';
$lang["user"]='Пользователь';
$lang["museumplus_api_user"]='Пользователь';
$lang["password"]='Пароль';
$lang["museumplus_api_pass"]='Пароль';
$lang["museumplus_RS_settings_header"]='Настройки ResourceSpace';
$lang["museumplus_mpid_field"]='Поле метаданных, используемое для хранения идентификатора MuseumPlus (MpID)';
$lang["museumplus_module_name_field"]='Поле метаданных, используемое для хранения имени модуля, для которого действителен MpID. Если не установлено, плагин будет использовать конфигурацию модуля "Object" по умолчанию.';
$lang["museumplus_secondary_links_field"]='Поле метаданных, используемое для хранения вторичных ссылок на другие модули. ResourceSpace будет генерировать URL MuseumPlus для каждой из ссылок. Ссылки будут иметь специальный формат синтаксиса: module_name:ID (например, "Object:1234")';
$lang["museumplus_object_details_title"]='Детали MuseumPlus';
$lang["museumplus_script_header"]='Настройки скрипта';
$lang["museumplus_last_run_date"]='Время последнего запуска скрипта';
$lang["museumplus_enable_script"]='Включить скрипт MuseumPlus';
$lang["museumplus_interval_run"]='Запускать скрипт через указанный интервал (например, +1 день, +2 недели, две недели). Оставьте поле пустым, и он будет запускаться каждый раз, когда запускается cron_copy_hitcount.php';
$lang["museumplus_log_directory"]='Каталог для хранения журналов скриптов. Если это поле оставить пустым или ввести некорректные данные, то журналирование не будет осуществляться.';
$lang["museumplus_integrity_check_field"]='Поле проверки целостности';
$lang["museumplus_modules_configuration_header"]='Конфигурация модулей';
$lang["museumplus_module"]='Модуль';
$lang["museumplus_add_new_module"]='Добавить новый модуль MuseumPlus';
$lang["museumplus_mplus_field_name"]='Имя поля MuseumPlus';
$lang["museumplus_rs_field"]='Поле ResourceSpace';
$lang["museumplus_view_in_museumplus"]='Просмотр в MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Вы уверены, что хотите удалить конфигурацию этого модуля? Это действие нельзя отменить!';
$lang["museumplus_module_setup"]='Настройка модуля';
$lang["museumplus_module_name"]='Название модуля MuseumPlus';
$lang["museumplus_mplus_id_field"]='Название поля идентификатора MuseumPlus';
$lang["museumplus_mplus_id_field_helptxt"]='Оставьте пустым, чтобы использовать технический идентификатор "__id" (по умолчанию)';
$lang["museumplus_rs_uid_field"]='Поле UID в ResourceSpace';
$lang["museumplus_applicable_resource_types"]='Применимый тип(ы) ресурса(ов)';
$lang["museumplus_field_mappings"]='Сопоставления полей MuseumPlus и ResourceSpace в РесурсСпейс';
$lang["museumplus_add_mapping"]='Добавить отображение';
$lang["museumplus_error_bad_conn_data"]='Неверные данные подключения к MuseumPlus';
$lang["museumplus_error_unexpected_response"]='Получен неожиданный код ответа от MuseumPlus - %code';
$lang["museumplus_error_no_data_found"]='Данные не найдены в MuseumPlus для данного MpID - %mpid';
$lang["museumplus_warning_script_not_completed"]='ПРЕДУПРЕЖДЕНИЕ: Скрипт MuseumPlus не был завершен с момента \'%script_last_ran\'.
Вы можете игнорировать это предупреждение только в том случае, если вы впоследствии получили уведомление об успешном завершении скрипта.';
$lang["museumplus_error_script_failed"]='Скрипт MuseumPlus не удалось запустить из-за блокировки процесса. Это указывает на то, что предыдущий запуск не был завершен
Если вам нужно удалить блокировку после неудачного запуска, выполните скрипт следующим образом:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='Опция конфигурации $php_path ДОЛЖНА быть установлена, чтобы функциональность cron успешно работала!';
$lang["museumplus_error_not_deleted_module_conf"]='Невозможно удалить запрошенную конфигурацию модуля.';
$lang["museumplus_error_unknown_type_saved_config"]='"museumplus_modules_saved_config" имеет неизвестный тип!';
$lang["museumplus_error_invalid_association"]='Неверная связь модуля(ей). Пожалуйста, убедитесь, что правильный идентификатор модуля и/или записи был введен!';
$lang["museumplus_id_returns_multiple_records"]='Найдено несколько записей - введите технический идентификатор вместо этого';
$lang["museumplus_error_module_no_field_maps"]='Невозможно синхронизировать данные из MuseumPlus. Причина: модуль \'%name\' не имеет настроенных сопоставлений полей.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='Позволяет извлекать метаданные ресурса из MuseumPlus с использованием его REST API (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Детали объекта';
$lang["page-title_museumplus_setup_module"] = 'Настройка модуля MuseumPlus';
$lang["page-title_museumplus_setup"] = 'Настройка плагина MuseumPlus';