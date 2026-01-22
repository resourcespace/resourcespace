<?php

$lang["csv_user_import_batch_user_import"] = 'Importation groupée d\'utilisateurs';
$lang["csv_user_import_import"] = 'Importer';
$lang["csv_user_import"] = 'Importation d\'utilisateurs CSV';
$lang["csv_user_import_intro"] = 'Utilisez cette fonctionnalité pour importer un lot d\'utilisateurs dans ResourceSpace. Veuillez prêter une attention particulière au format de votre fichier CSV et suivre les normes ci-dessous :';
$lang["csv_user_import_upload_file"] = 'Sélectionner le fichier';
$lang["csv_user_import_processing_file"] = 'TRAITEMENT DU FICHIER...';
$lang["csv_user_import_error_found"] = 'Erreur(s) trouvée(s) - annulation en cours';
$lang["csv_user_import_move_upload_file_failure"] = 'Il y a eu une erreur lors du déplacement du fichier téléchargé. Veuillez réessayer ou contacter les administrateurs.';
$lang["csv_user_import_condition1"] = 'Assurez-vous que le fichier CSV est encodé en utilisant UTF-8';
$lang["csv_user_import_condition2"] = 'Le fichier CSV doit avoir une ligne d\'en-tête';
$lang["csv_user_import_condition3"] = 'Colonnes qui contiendront des valeurs contenant des virgules (,), assurez-vous de les formater en tant que type texte afin de ne pas avoir à ajouter des guillemets (""). Lors de l\'enregistrement en tant que fichier .csv, assurez-vous de cocher l\'option de citation des cellules de type texte';
$lang["csv_user_import_condition4"] = 'Colonnes autorisées : *nom d\'utilisateur, *e-mail, mot de passe, nom complet, expiration du compte, commentaires, restriction IP, langue. Remarque : les champs obligatoires sont marqués d\'une astérisque (*)';
$lang["csv_user_import_condition5"] = 'La langue de l\'utilisateur reviendra par défaut à celle définie à l\'aide de l\'option de configuration "$defaultlanguage" si la colonne de langue n\'est pas trouvée ou si elle n\'a pas de valeur';
$lang["plugin-csv_user_import-title"] = 'Importation d\'utilisateurs CSV';
$lang["plugin-csv_user_import-desc"] = '[Avancé] Permet d\'importer un lot d\'utilisateurs à partir d\'un fichier CSV pré-formaté';

$lang["page-title_csv_user_import_csv_user_import"] = 'Importation d\'utilisateurs CSV';