<?php


$lang["museumplus_configuration"]='Konfigurace MuseumPlus';
$lang["museumplus_top_menu_title"]='MuseumPlus: neplatné asociace';
$lang["museumplus_api_settings_header"]='Podrobnosti API';
$lang["museumplus_host"]='Hostitel';
$lang["museumplus_host_api"]='API Host (pouze pro API volání; obvykle stejný jako výše)';
$lang["museumplus_application"]='Název aplikace';
$lang["user"]='Uživatel';
$lang["museumplus_api_user"]='Uživatel';
$lang["password"]='Heslo';
$lang["museumplus_api_pass"]='Heslo';
$lang["museumplus_RS_settings_header"]='Nastavení ResourceSpace';
$lang["museumplus_mpid_field"]='Pole metadat používané k uložení identifikátoru MuseumPlus (MpID)';
$lang["museumplus_module_name_field"]='Pole metadat používané k uchování názvu modulů, pro které je MpID platný. Pokud není nastaveno, plugin se vrátí k nastavení modulu "Object".';
$lang["museumplus_secondary_links_field"]='Pole metadat používané k uchování sekundárních odkazů na jiné moduly. ResourceSpace vygeneruje URL MuseumPlus pro každý z odkazů. Odkazy budou mít speciální syntaxi: module_name:ID (např. "Object:1234")';
$lang["museumplus_object_details_title"]='Podrobnosti MuseumPlus';
$lang["museumplus_script_header"]='Nastavení skriptu';
$lang["museumplus_last_run_date"]='Skript naposledy spuštěn';
$lang["museumplus_enable_script"]='Povolit skript MuseumPlus';
$lang["museumplus_interval_run"]='Spusťte skript v následujícím intervalu (např. +1 den, +2 týdny, čtrnáct dní). Nechte prázdné a bude se spouštět pokaždé, když se spustí cron_copy_hitcount.php';
$lang["museumplus_log_directory"]='Adresář pro ukládání protokolů skriptů. Pokud je toto pole prázdné nebo neplatné, nebude probíhat žádné protokolování.';
$lang["museumplus_integrity_check_field"]='Pole kontroly integrity';
$lang["museumplus_modules_configuration_header"]='Konfigurace modulů';
$lang["museumplus_module"]='Modul';
$lang["museumplus_add_new_module"]='Přidat nový modul MuseumPlus';
$lang["museumplus_mplus_field_name"]='Název pole MuseumPlus';
$lang["museumplus_rs_field"]='Pole ResourceSpace';
$lang["museumplus_view_in_museumplus"]='Zobrazit v MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Opravdu chcete smazat tuto konfiguraci modulu? Tuto akci nelze vrátit zpět!';
$lang["museumplus_module_setup"]='Nastavení modulu';
$lang["museumplus_module_name"]='Název modulu MuseumPlus';
$lang["museumplus_mplus_id_field"]='Název pole MuseumPlus ID';
$lang["museumplus_mplus_id_field_helptxt"]='Nechte prázdné pro použití technického ID \'__id\' (výchozí)';
$lang["museumplus_rs_uid_field"]='Pole UID ResourceSpace';
$lang["museumplus_applicable_resource_types"]='Příslušný typ(y) zdroje';
$lang["museumplus_field_mappings"]='MuseumPlus - ResourceSpace mapování polí';
$lang["museumplus_add_mapping"]='Přidat mapování';
$lang["museumplus_error_bad_conn_data"]='Data připojení k MuseumPlus je neplatná';
$lang["museumplus_error_unexpected_response"]='Neočekávaný kód odpovědi MuseumPlus přijat - %code';
$lang["museumplus_error_no_data_found"]='Nebyly nalezeny žádné údaje v MuseumPlus pro tento MpID - %mpid';
$lang["museumplus_warning_script_not_completed"]='UPOZORNĚNÍ: Skript MuseumPlus nebyl dokončen od \'%script_last_ran\'.
Toto upozornění můžete bezpečně ignorovat pouze v případě, že jste následně obdrželi oznámení o úspěšném dokončení skriptu.';
$lang["museumplus_error_script_failed"]='Skript MuseumPlus se nepodařilo spustit, protože byl aktivní zámek procesu. To znamená, že předchozí spuštění nebylo dokončeno.
Pokud potřebujete po neúspěšném spuštění zámek odstranit, spusťte skript následujícím způsobem:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='Možnost konfigurace $php_path MUSÍ být nastavena, aby funkce cron mohla úspěšně běžet!';
$lang["museumplus_error_not_deleted_module_conf"]='Nelze odstranit požadovanou konfiguraci modulu.';
$lang["museumplus_error_unknown_type_saved_config"]='Typ \'museumplus_modules_saved_config\' je neznámý!';
$lang["museumplus_error_invalid_association"]='Neplatné přiřazení modulů. Ujistěte se, že byl zadán správný modul a/nebo ID záznamu!';
$lang["museumplus_id_returns_multiple_records"]='Nalezeno více záznamů - prosím, zadejte technické ID místo toho';
$lang["museumplus_error_module_no_field_maps"]='Nelze synchronizovat data z MuseumPlus. Důvod: modul \'%name\' nemá nakonfigurované mapování polí.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='[Pokročilé] Umožňuje extrahovat metadata zdrojů z MuseumPlus pomocí jeho REST API (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Podrobnosti objektu';
$lang["page-title_museumplus_setup_module"] = 'Nastavení modulu MuseumPlus';
$lang["page-title_museumplus_setup"] = 'Nastavení pluginu MuseumPlus';