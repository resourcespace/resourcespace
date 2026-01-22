<?php

# Swedish Language File for the Action Dates Plugin
# Updated by Henrik Frizén 20140312 for svn r5361
# -------
#
#
$lang['action_dates_deletesettings'] = "Inställningar för automatisk borttagning av material – använd med försiktighet";
$lang['action_dates_configuration'] = "Välj de fält som ska användas för att automatiskt utföra de specificerade åtgärderna";
$lang['action_dates_delete'] = "Ta bort material automatiskt när datumet i detta fält nåtts";
$lang['action_dates_restrict'] = "Sätt automatiskt åtkomstnivån till ’Begränsad’ för materialet när datumet i detta fält nåtts. Denna åtgärd utförs endast för material som har åtkomstnivån ’Öppen’.";
$lang['action_dates_delete_logtext'] = " – Automatiskt borttaget av Åtgärdsdatum";
$lang['action_dates_restrict_logtext'] = " – Automatiskt satt till begränsad åtkomst av Åtgärdsdatum";
$lang['action_dates_reallydelete'] = "Ta bort materialet permanent när borttagningsdatumet passerats? Materialet kommer att markeras som borttaget eller få inställd resource_deletion_state om detta alternativ är satt till falskt";
$lang['action_dates_email_admin_days'] = "Skicka ett e-postmeddelande till administratören detta antal dagar innan datumet nås. Lämna blankt om inget meddelande ska skickas.";
$lang['action_dates_email_text'] = "Följande material kommer att få åtkomstnivån ’Begränsad’ om [days] dagar.";
$lang['action_dates_email_subject'] = "Avisering av material som kommer att begränsas";

$lang["action_dates_eligible_states"] = 'Stater som är berättigade till primär automatisk åtgärd. Om inga stater väljs är alla stater berättigade.';
$lang["action_dates_email_text_restrict"] = 'Följande resurser kommer att begränsas om [days] dagar.';
$lang["action_dates_email_text_state"] = 'Följande resurser kommer att ändra status om [days] dagar.';
$lang["action_dates_email_range_restrict"] = 'Följande resurser kommer att begränsas inom [days_min] till [days] dagar.';
$lang["action_dates_email_range_state"] = 'Följande resurser förväntas ändra tillstånd inom [days_min] till [days] dagar.';
$lang["action_dates_email_range"] = 'Följande resurser kommer att begränsas och/eller ändra status inom [days_min] till [days] dagar.';
$lang["action_dates_email_subject_restrict"] = 'Avisering om resurser som kommer att begränsas';
$lang["action_dates_email_subject_state"] = 'Avisering om resurser som ska ändra tillstånd';
$lang["action_dates_new_state"] = 'Ny tillstånd att flytta till (om ovanstående alternativ är inställt på att helt radera resurser kommer detta att ignoreras)';
$lang["action_dates_notification_subject"] = 'Meddelande från pluginet för åtgärdsdatum';
$lang["action_dates_additional_settings"] = 'Ytterligare åtgärder';
$lang["action_dates_additional_settings_info"] = 'Ytterligare, flytta resurser till det valda tillståndet när det angivna fältet har nåtts';
$lang["action_dates_additional_settings_date"] = 'När detta datum har nåtts';
$lang["action_dates_additional_settings_status"] = 'Flytta resurser till detta arkivtillstånd';
$lang["action_dates_remove_from_collection"] = 'Ta bort resurser från alla associerade samlingar när tillståndet ändras?';
$lang["action_dates_email_for_state"] = 'Skicka avisering vid ändring av resursens status. Kräver att fält för statusändringar ovan konfigureras.';
$lang["action_dates_email_for_restrict"] = 'Skicka avisering för att begränsa tillgång till resurser. Kräver att begränsningsfälten ovan konfigureras.';
$lang["action_dates_workflow_actions"] = 'Om Advanced Workflow-tillägget är aktiverat, ska dess meddelanden tillämpas på tillståndsändringar som initieras av detta tillägg?';
$lang["action_dates_weekdays"] = 'Välj veckodagarna när åtgärder ska utföras.';
$lang["weekday-0"] = 'Söndag';
$lang["weekday-1"] = 'Måndag';
$lang["weekday-2"] = 'Tisdag';
$lang["weekday-3"] = 'Onsdag';
$lang["weekday-4"] = 'Torsdag';
$lang["weekday-5"] = 'Fredag';
$lang["weekday-6"] = 'Lördag';
$lang["plugin-action_dates-title"] = 'Åtgärdsdatum';
$lang["plugin-action_dates-desc"] = 'Möjliggör schemalagd radering eller begränsning av resurser baserat på datumfält';

$lang["page-title_action_dates_setup"] = 'Konfigurera åtgärdsdatum-plugin';