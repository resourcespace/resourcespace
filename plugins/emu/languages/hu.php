<?php

$lang["emu_configuration"] = 'EMu konfiguráció';
$lang["emu_api_settings"] = 'API szerver beállítások';
$lang["emu_api_server"] = 'Szerver cím (pl. http://[server.address])';
$lang["emu_api_server_port"] = 'Szerver port';
$lang["emu_resource_types"] = 'Válassza ki az EMu-hoz kapcsolódó erőforrástípusokat';
$lang["emu_email_notify"] = 'E-mail cím, ahová a szkript értesítéseket küld. Hagyja üresen, hogy az alapértelmezett rendszerértesítési címre álljon vissza';
$lang["emu_script_failure_notify_days"] = 'A napok száma, amely után figyelmeztetést kell megjeleníteni és e-mailt küldeni, ha a szkript nem fejeződött be';
$lang["emu_script_header"] = 'Engedélyezze a szkriptet, amely automatikusan frissíti az EMu adatokat, amikor a ResourceSpace végrehajtja a tervezett feladatát (cron_copy_hitcount.php)';
$lang["emu_last_run_date"] = 'A szkript utolsó futása';
$lang["emu_script_mode"] = 'Szkript mód';
$lang["emu_script_mode_option_1"] = 'Importálja a metaadatokat az EMu-ból';
$lang["emu_script_mode_option_2"] = 'Húzza be az összes EMu rekordot és tartsa szinkronban az RS-t és az EMu-t';
$lang["emu_enable_script"] = 'EMu szkript engedélyezése';
$lang["emu_test_mode"] = 'Teszt üzemmód - Állítsa igazra, és a szkript futni fog, de nem frissíti az erőforrásokat';
$lang["emu_interval_run"] = 'Futtassa a szkriptet a következő időközönként (pl. +1 nap, +2 hét, kéthetes). Hagyja üresen, és minden alkalommal futni fog, amikor a cron_copy_hitcount.php fut)';
$lang["emu_log_directory"] = 'A könyvtár, ahol a szkriptnaplókat tárolják. Ha ez üresen marad vagy érvénytelen, akkor nem történik naplózás.';
$lang["emu_created_by_script_field"] = 'Metaadatmező, amelyet arra használnak, hogy tárolják, hogy egy erőforrást EMu szkript hozott-e létre';
$lang["emu_settings_header"] = 'EMu beállítások';
$lang["emu_irn_field"] = 'Metaadatmező az EMu azonosító (IRN) tárolására';
$lang["emu_search_criteria"] = 'Szinkronizálási keresési feltételek az EMu és a ResourceSpace között';
$lang["emu_rs_mappings_header"] = 'EMu - ResourceSpace térképezési szabályok';
$lang["emu_module"] = 'EMu module';
$lang["emu_column_name"] = 'EMu modul oszlop';
$lang["emu_rs_field"] = 'ResourceSpace mező';
$lang["emu_add_mapping"] = 'Hozzáadás térképezés';
$lang["emu_confirm_upload_nodata"] = 'Kérjük, jelölje be a négyzetet, hogy megerősítse, hogy folytatni kívánja a feltöltést';
$lang["emu_test_script_title"] = 'Teszt/ Futás szkript';
$lang["emu_run_script"] = 'Folyamat';
$lang["emu_script_problem"] = 'FIGYELEM - az EMu szkript az utolsó %days% napon belül nem fejeződött be sikeresen. Utolsó futási idő:';
$lang["emu_no_resource"] = 'Nincs megadva erőforrás-azonosító!';
$lang["emu_upload_nodata"] = 'Nem található EMu adat ehhez az IRN-hez:';
$lang["emu_nodata_returned"] = 'Nem található EMu adat a megadott IRN-hez.';
$lang["emu_createdfromemu"] = 'EMU bővítményből készült';

$lang["page-title_emu_emu_object_details"] = 'EMu Objekt Részletek';
$lang["page-title_emu_setup"] = 'Beállítás Plugin';