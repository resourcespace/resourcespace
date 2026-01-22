<?php


$lang["museumplus_configuration"]='MuseumPlus Konfiguration';
$lang["museumplus_top_menu_title"]='MuseumPlus: ogiltiga sammankopplingar';
$lang["museumplus_api_settings_header"]='API-detaljer';
$lang["museumplus_host"]='Värd';
$lang["museumplus_host_api"]='API-värd (endast för API-anrop; vanligtvis samma som ovan)';
$lang["museumplus_application"]='Ansökningsnamn';
$lang["user"]='Användare';
$lang["museumplus_api_user"]='Användare';
$lang["password"]='Lösenord';
$lang["museumplus_api_pass"]='Lösenord';
$lang["museumplus_RS_settings_header"]='ResourceSpace inställningar';
$lang["museumplus_mpid_field"]='Metadatafält som används för att lagra MuseumPlus-identifierare (MpID)';
$lang["museumplus_module_name_field"]='Metadatafält som används för att hålla modulnamnen för vilka MpID är giltigt. Om det inte är inställt kommer tillägget att använda "Object"-modulkonfigurationen som reserv.';
$lang["museumplus_secondary_links_field"]='Metadatafält som används för att hålla sekundära länkar till andra moduler. ResourceSpace kommer att generera en MuseumPlus URL för var och en av länkarna. Länkarna kommer att ha en speciell syntaxformat: modulnamn:ID (t.ex. "Object:1234")';
$lang["museumplus_object_details_title"]='MuseumPlus detaljer';
$lang["museumplus_script_header"]='Skriptinställningar';
$lang["museumplus_last_run_date"]='Senaste körning av skript';
$lang["museumplus_enable_script"]='Aktivera MuseumPlus-skriptet';
$lang["museumplus_interval_run"]='Kör skriptet med följande intervall (t.ex. +1 dag, +2 veckor, fjorton dagar). Lämna tomt så körs det varje gång cron_copy_hitcount.php körs';
$lang["museumplus_log_directory"]='Katalog för att lagra skriptloggar. Om detta lämnas tomt eller är ogiltigt kommer ingen loggning att ske.';
$lang["museumplus_integrity_check_field"]='Fält för integritetskontroll';
$lang["museumplus_modules_configuration_header"]='Modulkonfiguration';
$lang["museumplus_module"]='Modul';
$lang["museumplus_add_new_module"]='Lägg till nytt MuseumPlus-modul';
$lang["museumplus_mplus_field_name"]='MuseumPlus fältnamn';
$lang["museumplus_rs_field"]='ResourceSpace-fält';
$lang["museumplus_view_in_museumplus"]='Visa i MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Är du säker på att du vill ta bort denna modulkonfiguration? Denna åtgärd kan inte ångras!';
$lang["museumplus_module_setup"]='Modulinstallation';
$lang["museumplus_module_name"]='Modulnamn för MuseumPlus';
$lang["museumplus_mplus_id_field"]='MuseumPlus ID fältnamn';
$lang["museumplus_mplus_id_field_helptxt"]='Lämna tomt för att använda det tekniska ID:et \'__id\' (standard)';
$lang["museumplus_rs_uid_field"]='ResourceSpace UID-fält';
$lang["museumplus_applicable_resource_types"]='Tillämplig resurstyp(er)';
$lang["museumplus_field_mappings"]='MuseumPlus - Fältmappningar för ResourceSpace';
$lang["museumplus_add_mapping"]='Lägg till kartläggning';
$lang["museumplus_error_bad_conn_data"]='MuseumPlus anslutningsdata ogiltig';
$lang["museumplus_error_unexpected_response"]='Mottog oväntad MuseumPlus svars-kod - %code';
$lang["museumplus_error_no_data_found"]='Ingen data hittades i MuseumPlus för detta MpID - %mpid';
$lang["museumplus_warning_script_not_completed"]='VARNING: MuseumPlus-skriptet har inte slutförts sedan \'%script_last_ran\'.
Du kan ignorera denna varning endast om du senare har fått meddelande om att skriptet har slutförts framgångsrikt.';
$lang["museumplus_error_script_failed"]='MuseumPlus-skriptet misslyckades med att köras eftersom en processlås var på plats. Detta indikerar att föregående körning inte slutfördes
Om du behöver rensa låset efter en misslyckad körning, kör skriptet enligt följande:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='$php_path konfigurationsalternativet MÅSTE ställas in för att cron-funktionaliteten ska kunna köras framgångsrikt!';
$lang["museumplus_error_not_deleted_module_conf"]='Det går inte att ta bort den begärda modulkonfigurationen.';
$lang["museumplus_error_unknown_type_saved_config"]='\'museumplus_modules_saved_config\' är av en okänd typ!';
$lang["museumplus_error_invalid_association"]='Ogiltig modulassociation. Se till att rätt modul och/eller post-ID har angetts!';
$lang["museumplus_id_returns_multiple_records"]='Flera poster hittades - ange tekniskt ID istället';
$lang["museumplus_error_module_no_field_maps"]='Kan inte synkronisera data från MuseumPlus. Anledning: modulen \'%name\' har inga fältmappningar konfigurerade.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='Tillåter att resursmetadata extraheras från MuseumPlus med hjälp av dess REST API (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Objekt Detaljer';
$lang["page-title_museumplus_setup_module"] = 'Installera MuseumPlus-modul';
$lang["page-title_museumplus_setup"] = 'Installera MuseumPlus-pluginet';