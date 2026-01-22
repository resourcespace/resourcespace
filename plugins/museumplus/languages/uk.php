<?php


$lang["museumplus_configuration"]='Конфігурація MuseumPlus';
$lang["museumplus_top_menu_title"]='MuseumPlus: недійсні асоціації';
$lang["museumplus_api_settings_header"]='Деталі API';
$lang["museumplus_host"]='Хост';
$lang["museumplus_host_api"]='Хост API (тільки для викликів API; зазвичай такий самий, як вищезазначений)';
$lang["museumplus_application"]='Назва застосунку';
$lang["user"]='Користувач';
$lang["museumplus_api_user"]='Користувач';
$lang["password"]='Пароль';
$lang["museumplus_api_pass"]='Пароль';
$lang["museumplus_RS_settings_header"]='Налаштування ResourceSpace';
$lang["museumplus_mpid_field"]='Поле метаданих, яке використовується для зберігання ідентифікатора MuseumPlus (MpID)';
$lang["museumplus_module_name_field"]='Поле метаданих використовується для зберігання імені модуля, для якого дійсний MpID. Якщо не встановлено, плагін повернеться до конфігурації модуля "Object".';
$lang["museumplus_secondary_links_field"]='Поле метаданих використовується для зберігання вторинних посилань на інші модулі. ResourceSpace згенерує URL-адресу MuseumPlus для кожного з посилань. Посилання матимуть спеціальний синтаксис: module_name:ID (наприклад, "Object:1234")';
$lang["museumplus_object_details_title"]='Деталі MuseumPlus';
$lang["museumplus_script_header"]='Налаштування скрипту';
$lang["museumplus_last_run_date"]='Скрипт останній раз запущений';
$lang["museumplus_enable_script"]='Увімкнути скрипт MuseumPlus';
$lang["museumplus_interval_run"]='Запустіть скрипт з наступним інтервалом (наприклад, +1 день, +2 тижні, два тижні). Залиште порожнім, і він буде запускатися кожного разу, коли запускається cron_copy_hitcount.php)';
$lang["museumplus_log_directory"]='Каталог для зберігання журналів скриптів. Якщо це поле залишити порожнім або вказати недійсний каталог, то журналювання не відбуватиметься.';
$lang["museumplus_integrity_check_field"]='Поле перевірки цілісності';
$lang["museumplus_modules_configuration_header"]='Конфігурація модулів';
$lang["museumplus_module"]='Модуль';
$lang["museumplus_add_new_module"]='Додати новий модуль MuseumPlus';
$lang["museumplus_mplus_field_name"]='Назва поля MuseumPlus';
$lang["museumplus_rs_field"]='Поле ResourceSpace';
$lang["museumplus_view_in_museumplus"]='Переглянути в MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Ви впевнені, що хочете видалити цю конфігурацію модуля? Цю дію не можна скасувати!';
$lang["museumplus_module_setup"]='Налаштування модуля';
$lang["museumplus_module_name"]='Назва модуля MuseumPlus';
$lang["museumplus_mplus_id_field"]='Назва поля MuseumPlus ID';
$lang["museumplus_mplus_id_field_helptxt"]='Залиште порожнім, щоб використовувати технічний ID \'__id\' (за замовчуванням)';
$lang["museumplus_rs_uid_field"]='Поле UID ResourceSpace';
$lang["museumplus_applicable_resource_types"]='Застосовуваний тип(и) ресурсу(ів)';
$lang["museumplus_field_mappings"]='MuseumPlus - відповідність полів ResourceSpace';
$lang["museumplus_add_mapping"]='Додати відображення';
$lang["museumplus_error_bad_conn_data"]='Дані підключення MuseumPlus недійсні';
$lang["museumplus_error_unexpected_response"]='Отримано неочікуваний код відповіді MuseumPlus - %code';
$lang["museumplus_error_no_data_found"]='Дані для цього MpID - %mpid не знайдено в MuseumPlus';
$lang["museumplus_warning_script_not_completed"]='УВАГА: Скрипт MuseumPlus не завершився з \'%script_last_ran\'.
Ви можете безпечно ігнорувати це попередження, лише якщо згодом отримали повідомлення про успішне завершення скрипту.';
$lang["museumplus_error_script_failed"]='Скрипт MuseumPlus не вдалося запустити через наявність блокування процесу. Це вказує на те, що попередній запуск не завершився.
Якщо вам потрібно зняти блокування після невдалого запуску, запустіть скрипт наступним чином:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='Параметр конфігурації $php_path ПОВИНЕН бути встановлений, щоб функціональність cron успішно працювала!';
$lang["museumplus_error_not_deleted_module_conf"]='Неможливо видалити запитану конфігурацію модуля.';
$lang["museumplus_error_unknown_type_saved_config"]='\'Тип \'museumplus_modules_saved_config\' невідомий!';
$lang["museumplus_error_invalid_association"]='Неправильна асоціація модуля(ів). Будь ласка, переконайтеся, що введено правильний модуль та/або ідентифікатор запису!';
$lang["museumplus_id_returns_multiple_records"]='Знайдено кілька записів - будь ласка, введіть технічний ID замість цього';
$lang["museumplus_error_module_no_field_maps"]='Неможливо синхронізувати дані з MuseumPlus. Причина: модуль \'%name\' не має налаштованих відповідностей полів.';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Деталі об\'єкта';
$lang["page-title_museumplus_setup_module"] = 'Налаштування модуля MuseumPlus';
$lang["page-title_museumplus_setup"] = 'Налаштування плагіна MuseumPlus';