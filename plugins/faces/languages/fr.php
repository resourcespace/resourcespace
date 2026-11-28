<?php

$lang["faces-detected-faces"] = 'Visages détectés';
$lang["faces-detected-face"] = 'Visage détecté';
$lang["faces-confidence"] = 'Confiance';
$lang["faces-find-matching"] = 'Trouver des visages correspondants';
$lang["faces-configuration"] = 'Configuration des visages IA';
$lang["faces-tag-field"] = 'Le champ contenant les noms des individus étiquetés. Cela devrait être un champ de liste déroulante dynamique.';
$lang["faces-name"] = 'Nom';
$lang["faces-detect-on-upload"] = 'Scanner les visages lors du téléchargement ?';
$lang["faces-tag-on-upload"] = 'Taguer les visages reconnus lors du téléchargement ?';
$lang["faces-detecting"] = 'Recherche de visages dans la ressource :';
$lang["faces-tagging"] = 'Étiquetage des visages détectés dans la ressource :';
$lang["faces-oneface"] = 'Veuillez sélectionner une seule option pour chaque face.';
$lang["faces-show-view"] = 'Afficher la fonctionnalité AI Faces sur la page de visualisation.';
$lang["faces_count_faces"] = 'Total des visages détectés';
$lang["faces_count_missing"] = 'Images à traiter';
$lang["faces-tag-field-not-set"] = 'Le champ de balisage n\'est pas configuré.';

$lang["page-title_faces_setup"] = 'Configurer le plugin Faces';
$lang["faces_insight_faces"] = 'Faces Insight';
$lang["faces_detect_faces"] = 'Détecter les visages';
$lang["faces_tag_faces"] = 'Étiqueter les visages';
$lang["faces_detect_faces_configure"] = 'Configurer le travail pour détecter les visages';
$lang["faces_tag_faces_configure"] = 'Configurer le travail pour étiqueter les visages';
$lang["faces_detect_faces_intro"] = 'Créez un travail pour démarrer la détection des visages ici - ce travail ne nécessite aucun paramètre et peut être lancé tant qu\'il n\'y a pas d\'autres travaux en cours de ce type.';
$lang["faces_tag_faces_collection_refs_help"] = 'La configuration de cette option signifie que seules les ressources dans les collections listées seront mises à jour. Si aucune collection n\'est spécifiée, l\'étiquetage des visages sera mis à jour pour TOUTES les ressources appropriées. Les collections peuvent être spécifiées à l\'aide d\'une liste séparée par des virgules ainsi que des plages, par exemple 100,105,110-115.';

$lang['faces-service-endpoint'] = 'URL du service Python FastAPI';
$lang['plugin-faces-title'] = 'Visages IA - InsightFace';
$lang['plugin-faces-desc'] = 'Détection et étiquetage automatique des visages à la pointe de la technologie. Recherche par visage.';

$lang['faces-match-threshold'] = 'Seuil de correspondance faciale : quel niveau de similarité est considéré comme une correspondance lors de la recherche de visages ? Suggestion : 30 % (augmentez si de nombreuses correspondances incorrectes apparaissent dans les résultats)';
$lang['faces-tag-threshold'] = 'Seuil d’étiquetage facial : quel niveau de similarité est considéré comme une correspondance lors de l’étiquetage automatique des visages ? Suggestion : 50 % (augmentez si de nombreux tags incorrects apparaissent)';
$lang['faces-confidence-threshold'] = 'Seuil de confiance faciale : à quel point le modèle doit-il être sûr d’avoir détecté un visage humain ? Suggestion : 70 % (les valeurs inférieures correspondent à des visages partiellement cachés ou à des non-visages)';
