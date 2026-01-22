<?php


$lang["museumplus_configuration"]='Configuration de MuseumPlus';
$lang["museumplus_top_menu_title"]='MuseumPlus: associations invalides';
$lang["museumplus_api_settings_header"]='Détails de l\'API';
$lang["museumplus_host"]='Hôte';
$lang["museumplus_host_api"]='Hôte de l\'API (pour les appels d\'API uniquement ; généralement identique à celui ci-dessus)';
$lang["museumplus_application"]='Nom de l\'application';
$lang["user"]='Utilisateur';
$lang["museumplus_api_user"]='Utilisateur';
$lang["password"]='Mot de passe';
$lang["museumplus_api_pass"]='Mot de passe';
$lang["museumplus_RS_settings_header"]='Paramètres de ResourceSpace';
$lang["museumplus_mpid_field"]='Champ de métadonnées utilisé pour stocker l\'identifiant MuseumPlus (MpID)';
$lang["museumplus_module_name_field"]='Champ de métadonnées utilisé pour contenir le nom des modules pour lesquels l\'ID de Mp est valide. Si non défini, le plugin utilisera la configuration du module "Object" par défaut.';
$lang["museumplus_secondary_links_field"]='Champ de métadonnées utilisé pour contenir les liens secondaires vers d\'autres modules. ResourceSpace générera une URL MuseumPlus pour chacun des liens. Les liens auront un format de syntaxe spécial : nom_du_module:ID (par exemple, "Object:1234")';
$lang["museumplus_object_details_title"]='Détails de MuseumPlus';
$lang["museumplus_script_header"]='Paramètres du script';
$lang["museumplus_last_run_date"]='Dernière exécution du script';
$lang["museumplus_enable_script"]='Activer le script MuseumPlus';
$lang["museumplus_interval_run"]='Exécuter le script à l\'intervalle suivant (par exemple, +1 jour, +2 semaines, quinzaine). Laissez vide et il s\'exécutera à chaque fois que cron_copy_hitcount.php s\'exécute';
$lang["museumplus_log_directory"]='Répertoire pour stocker les journaux de script. Si cela est laissé vide ou est invalide, aucun enregistrement ne sera effectué.';
$lang["museumplus_integrity_check_field"]='Vérification d\'intégrité de champ';
$lang["museumplus_modules_configuration_header"]='Configuration des modules';
$lang["museumplus_module"]='Module';
$lang["museumplus_add_new_module"]='Ajouter un nouveau module MuseumPlus';
$lang["museumplus_mplus_field_name"]='Nom de champ MuseumPlus';
$lang["museumplus_rs_field"]='Champ ResourceSpace';
$lang["museumplus_view_in_museumplus"]='Afficher dans MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Êtes-vous sûr(e) de vouloir supprimer cette configuration de module ? Cette action ne peut pas être annulée !';
$lang["museumplus_module_setup"]='Configuration du module';
$lang["museumplus_module_name"]='Nom du module MuseumPlus';
$lang["museumplus_mplus_id_field"]='Nom du champ d\'identifiant MuseumPlus';
$lang["museumplus_mplus_id_field_helptxt"]='Laissez vide pour utiliser l\'ID technique \'__id\' (par défaut)';
$lang["museumplus_rs_uid_field"]='Champ UID de ResourceSpace';
$lang["museumplus_applicable_resource_types"]='Types de ressources applicables';
$lang["museumplus_field_mappings"]='MuseumPlus - Mappages de champs de ResourceSpace';
$lang["museumplus_add_mapping"]='Ajouter une correspondance';
$lang["museumplus_error_bad_conn_data"]='Données de connexion MuseumPlus invalides';
$lang["museumplus_error_unexpected_response"]='Code de réponse inattendu reçu de MuseumPlus - %code';
$lang["museumplus_error_no_data_found"]='Aucune donnée trouvée dans MuseumPlus pour cet identifiant MpID - %mpid';
$lang["museumplus_warning_script_not_completed"]='ATTENTION : Le script MuseumPlus n\'a pas été complété depuis \'%script_last_ran\'.
Vous pouvez ignorer cette alerte uniquement si vous avez reçu une notification de réussite de l\'exécution du script.';
$lang["museumplus_error_script_failed"]='Le script MuseumPlus n\'a pas pu s\'exécuter car un verrou de processus était en place. Cela indique que l\'exécution précédente n\'a pas été terminée
Si vous devez effacer le verrou après une exécution échouée, exécutez le script comme suit:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='L\'option de configuration $php_path DOIT être définie pour que la fonctionnalité cron fonctionne correctement !';
$lang["museumplus_error_not_deleted_module_conf"]='Impossible de supprimer la configuration du module demandé.';
$lang["museumplus_error_unknown_type_saved_config"]='Le \'museumplus_modules_saved_config\' est d\'un type inconnu !';
$lang["museumplus_error_invalid_association"]='Association de module(s) invalide(s). Veuillez vous assurer que le module et/ou l\'identifiant d\'enregistrement correct(s) ont été saisis !';
$lang["museumplus_id_returns_multiple_records"]='Plusieurs enregistrements trouvés - veuillez entrer l\'identifiant technique à la place';
$lang["museumplus_error_module_no_field_maps"]='Impossible de synchroniser les données de MuseumPlus. Raison : le module \'%name\' n\'a pas de mappages de champs configurés.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='[Avancé] Permet d\'extraire les métadonnées des ressources de MuseumPlus en utilisant son API REST (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'Détails de l\'objet MuseumPlus';
$lang["page-title_museumplus_setup_module"] = 'Configurer le module MuseumPlus';
$lang["page-title_museumplus_setup"] = 'Configurer le plugin MuseumPlus';