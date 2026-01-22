<?php


$lang["museumplus_configuration"]='MuseumPlus Konfiguration';
$lang["museumplus_top_menu_title"]='MuseumPlus: ugyldige associationer';
$lang["museumplus_api_settings_header"]='API detaljer';
$lang["museumplus_host"]='Vært';
$lang["museumplus_host_api"]='API Vært (kun til API-opkald; normalt det samme som ovenfor)';
$lang["museumplus_application"]='Ansøgningsnavn';
$lang["user"]='Bruger';
$lang["museumplus_api_user"]='Bruger';
$lang["password"]='Adgangskode';
$lang["museumplus_api_pass"]='Adgangskode';
$lang["museumplus_RS_settings_header"]='ResourceSpace indstillinger';
$lang["museumplus_mpid_field"]='Metadatafelt brugt til at gemme MuseumPlus identifikator (MpID)';
$lang["museumplus_module_name_field"]='Metadatafelt brugt til at indeholde modulnavnene, for hvilke MpID\'en er gyldig. Hvis det ikke er angivet, vil pluginnet falde tilbage til "Object" modulkonfigurationen.';
$lang["museumplus_secondary_links_field"]='Metadatafelt brugt til at indeholde sekundære links til andre moduler. ResourceSpace vil generere en MuseumPlus URL til hver af disse links. Links vil have en speciel syntaksformat: modul_navn:ID (f.eks. "Object:1234")';
$lang["museumplus_object_details_title"]='MuseumPlus detaljer';
$lang["museumplus_script_header"]='Skriptindstillinger';
$lang["museumplus_last_run_date"]='Script sidst kørt';
$lang["museumplus_enable_script"]='Aktivér MuseumPlus-scriptet';
$lang["museumplus_interval_run"]='Kør scriptet med følgende interval (f.eks. +1 dag, +2 uger, fjorten dage). Lad feltet stå tomt, og det vil køre hver gang, cron_copy_hitcount.php køres';
$lang["museumplus_log_directory"]='Mappe til at gemme scriptlogs i. Hvis dette efterlades tomt eller er ugyldigt, vil der ikke blive logget noget.';
$lang["museumplus_integrity_check_field"]='Integritetskontrolfelt';
$lang["museumplus_modules_configuration_header"]='Modulkonfiguration';
$lang["museumplus_module"]='Modul';
$lang["museumplus_add_new_module"]='Tilføj nyt MuseumPlus-modul';
$lang["museumplus_mplus_field_name"]='MuseumPlus feltnavn';
$lang["museumplus_rs_field"]='ResourceSpace felt';
$lang["museumplus_view_in_museumplus"]='Vis i MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Er du sikker på, at du vil slette denne modulkonfiguration? Denne handling kan ikke fortrydes!';
$lang["museumplus_module_setup"]='Modulopsætning';
$lang["museumplus_module_name"]='MuseumPlus modulnavn';
$lang["museumplus_mplus_id_field"]='MuseumPlus ID felt navn';
$lang["museumplus_mplus_id_field_helptxt"]='Efterlad tom for at bruge den tekniske ID \'__id\' (standard)';
$lang["museumplus_rs_uid_field"]='ResourceSpace UID felt';
$lang["museumplus_applicable_resource_types"]='Anvendelige ressourcetyper';
$lang["museumplus_field_mappings"]='MuseumPlus - ResourceSpace feltmappings';
$lang["museumplus_add_mapping"]='Tilføj kortlægning';
$lang["museumplus_error_bad_conn_data"]='MuseumPlus Forbindelsesdata er ugyldige';
$lang["museumplus_error_unexpected_response"]='Modtaget uventet MuseumPlus svarkode - %code';
$lang["museumplus_error_no_data_found"]='Ingen data fundet i MuseumPlus for dette MpID - %mpid';
$lang["museumplus_warning_script_not_completed"]='ADVARSEL: MuseumPlus-scriptet er ikke fuldført siden \'%script_last_ran\'.
Du kan sikkert ignorere denne advarsel, kun hvis du efterfølgende har modtaget meddelelse om en vellykket script-afslutning.';
$lang["museumplus_error_script_failed"]='MuseumPlus-scriptet kunne ikke køres, fordi der var en proceslås i gang. Dette indikerer, at den tidligere kørsel ikke blev fuldført
Hvis du skal fjerne låsen efter en mislykket kørsel, skal du køre scriptet som følger:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='$php_path konfigurationsindstilling SKAL være sat for at cron-funktionaliteten kan køre succesfuldt!';
$lang["museumplus_error_not_deleted_module_conf"]='Kan ikke slette den ønskede modulkonfiguration.';
$lang["museumplus_error_unknown_type_saved_config"]='"museumplus_modules_saved_config" er af en ukendt type!';
$lang["museumplus_error_invalid_association"]='Ugyldig modul(er) tilknytning. Sørg venligst for, at den korrekte modul og/eller post-ID er blevet indtastet!';
$lang["museumplus_id_returns_multiple_records"]='Flere poster fundet - indtast venligst den tekniske ID i stedet';
$lang["museumplus_error_module_no_field_maps"]='Kan ikke synkronisere data fra MuseumPlus. Årsag: modulet \'%name\' har ingen konfigurerede feltmappings.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='Tillader udtrækning af ressource-metadata fra MuseumPlus ved hjælp af dets REST API (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Objekt Detaljer';
$lang["page-title_museumplus_setup_module"] = 'Opsæt MuseumPlus Modul';
$lang["page-title_museumplus_setup"] = 'Opsæt MuseumPlus-plugin';