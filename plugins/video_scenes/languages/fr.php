<?php


$lang["video_scene_title"] = 'Scènes vidéo';
$lang["video_scene_intro"] = 'Détectez les scènes localement avec transnetv2-pytorch après le téléchargement ou la recréation de l\'aperçu. Installez le package Python avant le traitement ; les poids du modèle sont inclus. Une clé API OpenAI optionnelle permet des descriptions de scènes ; les collages et l\'audio des scènes sont ensuite envoyés à OpenAI.';
$lang["video_scene_python"] = 'Exécutable Python (chemin absolu)';
$lang["video_scene_threshold"] = 'Seuil de transition de scène (entre 0 et 1)';
$lang["video_scene_api_key"] = 'Clé API OpenAI (optionnelle)';
$lang["video_scene_model"] = 'Modèle OpenAI en temps réel supportant l\'entrée image et audio';
$lang["video_scene_audio"] = 'Inclure l\'audio de la scène dans les descriptions';
$lang["video_scene_audio_overlap"] = 'Contexte audio avant et après chaque scène (secondes, 0 à 30)';
$lang["video_scene_prompt"] = 'Invite de description de scène';
$lang["video_scene_search_field"] = 'Champ des descriptions de scènes consultables (optionnel)';
$lang["video_scene_prompt_help"] = 'Instructions pour décrire chaque scène à partir de ses images et de son audio. Maximum 8 000 octets.';
$lang["video_scene_timeout"] = 'Temps maximum de traitement par vidéo (secondes)';
$lang["video_scene_invalid_config"] = 'Vérifiez le chemin Python, le seuil, le modèle, la clé et le délai de traitement.';
$lang["video_scene_time"] = 'Horodatage';
$lang["video_scene_thumbnail"] = 'Vignette';
$lang["video_scene_description"] = 'Description';
$lang["video_scene_edit_description"] = 'Modifier la description de la scène à %time';
$lang["video_scene_invalid_description"] = 'Entrez une description valide de jusqu\'à 16 000 octets.';
$lang["video_scene_description_missing"] = 'Cette scène n\'existe plus. Rechargez la vue de la ressource.';
$lang["video_scene_description_save_failed"] = 'La description n\'a pas pu être enregistrée. Réessayez ou rechargez la vue de la ressource.';
$lang["video_scene_description_saved"] = 'Description de la scène enregistrée.';
$lang["video_scene_no_description"] = 'Aucune description disponible';
$lang["video_scene_play"] = 'Lire la scène à %time';
$lang["video_scene_play_unavailable"] = 'Cette scène est en dehors de l\'aperçu vidéo disponible.';
$lang["video_scene_job_success"] = 'Traitement de la scène vidéo terminé';
$lang["video_scene_job_failure"] = 'Le traitement de la scène vidéo a échoué. Consultez le journal des tâches pour plus de détails.';