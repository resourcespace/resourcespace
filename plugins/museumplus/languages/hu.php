<?php


$lang["museumplus_configuration"] = 'MuseumPlus konfiguráció';
$lang["museumplus_top_menu_title"] = 'MuseumPlus: érvénytelen kapcsolatok';
$lang["museumplus_api_settings_header"] = 'API részletek';
$lang["museumplus_host"] = 'Gazda';
$lang["museumplus_host_api"] = 'API gazda (csak API hívásokhoz; általában megegyezik a fentiekkel)';
$lang["museumplus_application"] = 'Alkalmazás neve (nem szükséges újabb M+ Host URL-ekhez)';
$lang["user"] = 'Felhasználó';
$lang["museumplus_api_user"] = 'Felhasználó';
$lang["password"] = 'Jelszó';
$lang["museumplus_api_pass"] = 'Jelszó';
$lang["museumplus_RS_settings_header"] = 'ResourceSpace beállítások';
$lang["museumplus_mpid_field"] = 'A metaadatmező, amely a MuseumPlus azonosítót (MpID) tárolja';
$lang["museumplus_module_name_field"] = 'A metaadatmező, amely a modulok nevét tárolja, amelyekre a MpID érvényes. Ha nincs beállítva, a plugin az "Object" modul konfigurációjára fog visszaállni.';
$lang["museumplus_secondary_links_field"] = 'Metaadatmező, amely a másodlagos hivatkozásokat tartalmazza más modulokhoz. A ResourceSpace minden hivatkozáshoz generál egy MuseumPlus URL-t. A hivatkozásoknak különleges szintaxisformátuma lesz: modul_név:ID (pl. "Objektum:1234")';
$lang["museumplus_object_details_title"] = 'MuseumPlus részletek';
$lang["museumplus_script_header"] = 'Script beállítások';
$lang["museumplus_last_run_date"] = 'A szkript utolsó futása';
$lang["museumplus_enable_script"] = 'MuseumPlus szkript engedélyezése';
$lang["museumplus_interval_run"] = 'Futtassa a szkriptet a következő időközönként (pl. +1 nap, +2 hét, kéthetes). Hagyja üresen, és minden alkalommal futni fog, amikor a cron_copy_hitcount.php fut)';
$lang["museumplus_log_directory"] = 'A könyvtár, ahol a szkript naplófájljait tárolják. Ha ez üresen marad vagy érvénytelen, akkor nem történik naplózás.';
$lang["museumplus_integrity_check_field"] = 'Integritás ellenőrző mező';
$lang["museumplus_modules_configuration_header"] = 'Modulok konfigurációja';
$lang["museumplus_module"] = 'Modul';
$lang["museumplus_add_new_module"] = 'Új MuseumPlus modul hozzáadása';
$lang["museumplus_mplus_field_name"] = 'MuseumPlus mező neve';
$lang["museumplus_rs_field"] = 'ResourceSpace mező';
$lang["museumplus_view_in_museumplus"] = 'Nézet a MuseumPlus-ban';
$lang["museumplus_confirm_delete_module_config"] = 'Biztos benne, hogy törölni szeretné ezt a modul konfigurációt? Ez a művelet nem vonható vissza!';
$lang["museumplus_module_setup"] = 'Modul beállítás';
$lang["museumplus_module_name"] = 'MuseumPlus modulnév';
$lang["museumplus_mplus_id_field"] = 'MuseumPlus azonosító mező neve';
$lang["museumplus_mplus_id_field_helptxt"] = 'Hagyja üresen a \'__id\' (alapértelmezett) technikai azonosító használatához';
$lang["museumplus_rs_uid_field"] = 'ResourceSpace UID mező';
$lang["museumplus_applicable_resource_types"] = 'Alkalmazható erőforrástípus(ok)';
$lang["museumplus_field_mappings"] = 'MuseumPlus - ResourceSpace mezőleképezések';
$lang["museumplus_add_mapping"] = 'Hozzáadás térképezés';
$lang["museumplus_error_bad_conn_data"] = 'MuseumPlus kapcsolat adatok érvénytelenek';
$lang["museumplus_error_unexpected_response"] = 'Váratlan MuseumPlus válaszkód érkezett - %code';
$lang["museumplus_error_no_data_found"] = 'Nincs adat a MuseumPlus-ban ehhez az MpID-hez - %mpid';
$lang["museumplus_warning_script_not_completed"] = 'FIGYELEM: A MuseumPlus szkript nem fejeződött be a(z) \'%script_last_ran\' időpont óta. Ezt a figyelmeztetést biztonságosan figyelmen kívül hagyhatja, ha ezt követően értesítést kapott a szkript sikeres befejezéséről.';
$lang["museumplus_error_script_failed"] = 'A MuseumPlus szkript futása meghiúsult, mert egy folyamatzár érvényben volt. Ez azt jelzi, hogy az előző futás nem fejeződött be.  
Ha a meghiúsult futás után szeretné eltávolítani a zárat, futtassa a szkriptet az alábbiak szerint:  
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"] = 'A $php_path konfigurációs opciót be kell állítani ahhoz, hogy a cron funkció sikeresen működjön!';
$lang["museumplus_error_not_deleted_module_conf"] = 'Nem lehet törölni a kért modul konfigurációt.';
$lang["museumplus_error_unknown_type_saved_config"] = 'A \'museumplus_modules_saved_config\' ismeretlen típusú!';
$lang["museumplus_error_invalid_association"] = 'Érvénytelen modul(ok) társítása. Kérjük, győződjön meg arról, hogy a megfelelő modul és/vagy rekordazonosító került beírásra!';
$lang["museumplus_id_returns_multiple_records"] = 'Több rekord található - kérjük, adja meg a technikai azonosítót helyette';
$lang["museumplus_error_module_no_field_maps"] = 'Nem sikerült szinkronizálni az adatokat a MuseumPlus-ból. Ok: a \'%name\' modulhoz nincs konfigurálva mezőleképezés.';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Objekt Részletek';
$lang["page-title_museumplus_setup_module"] = 'MúzeumPlus Modul Beállítása';
$lang["page-title_museumplus_setup"] = 'Állítsa be a MuseumPlus bővítményt';