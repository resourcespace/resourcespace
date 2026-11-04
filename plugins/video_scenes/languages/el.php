<?php


$lang["video_scene_title"] = 'Σκηνές βίντεο';
$lang["video_scene_intro"] = 'Ανίχνευση σκηνών τοπικά με transnetv2-pytorch μετά τη μεταφόρτωση ή την αναδημιουργία προεπισκόπησης. Εγκαταστήστε το πακέτο Python πριν από την επεξεργασία· τα βάρη του μοντέλου περιλαμβάνονται. Ένα προαιρετικό κλειδί API OpenAI επιτρέπει περιγραφές σκηνών· κολάζ και ήχος σκηνής αποστέλλονται τότε στο OpenAI.';
$lang["video_scene_python"] = 'Εκτελέσιμο Python (απόλυτη διαδρομή)';
$lang["video_scene_threshold"] = 'Κατώφλι μετάβασης σκηνής (μεταξύ 0 και 1)';
$lang["video_scene_api_key"] = 'Κλειδί API OpenAI (προαιρετικό)';
$lang["video_scene_model"] = 'Μοντέλο OpenAI σε πραγματικό χρόνο που υποστηρίζει εικόνα και ήχο';
$lang["video_scene_audio"] = 'Συμπερίληψη ήχου σκηνής στις περιγραφές';
$lang["video_scene_audio_overlap"] = 'Πλαίσιο ήχου πριν και μετά από κάθε σκηνή (δευτερόλεπτα, 0 έως 30)';
$lang["video_scene_prompt"] = 'Πρότροπο περιγραφής σκηνής';
$lang["video_scene_search_field"] = 'Πεδίο αναζητήσιμων περιγραφών σκηνών (προαιρετικό)';
$lang["video_scene_prompt_help"] = 'Οδηγίες για την περιγραφή κάθε σκηνής από τα καρέ και τον ήχο της. Μέγιστο 8.000 bytes.';
$lang["video_scene_timeout"] = 'Μέγιστος χρόνος επεξεργασίας ανά βίντεο (δευτερόλεπτα)';
$lang["video_scene_invalid_config"] = 'Ελέγξτε τη διαδρομή Python, το κατώφλι, το μοντέλο, το κλειδί και το χρόνο επεξεργασίας.';
$lang["video_scene_time"] = 'Χρονική σήμανση';
$lang["video_scene_thumbnail"] = 'Μικρογραφία';
$lang["video_scene_description"] = 'Περιγραφή';
$lang["video_scene_edit_description"] = 'Επεξεργασία περιγραφής σκηνής στο %time';
$lang["video_scene_invalid_description"] = 'Εισάγετε μια έγκυρη περιγραφή έως 16.000 bytes.';
$lang["video_scene_description_missing"] = 'Αυτή η σκηνή δεν υπάρχει πλέον. Φορτώστε ξανά την προβολή πόρου.';
$lang["video_scene_description_save_failed"] = 'Η περιγραφή δεν αποθηκεύτηκε. Δοκιμάστε ξανά ή φορτώστε ξανά την προβολή πόρου.';
$lang["video_scene_description_saved"] = 'Η περιγραφή της σκηνής αποθηκεύτηκε.';
$lang["video_scene_no_description"] = 'Δεν υπάρχει διαθέσιμη περιγραφή';
$lang["video_scene_play"] = 'Αναπαραγωγή σκηνής στο %time';
$lang["video_scene_play_unavailable"] = 'Αυτή η σκηνή βρίσκεται εκτός της διαθέσιμης προεπισκόπησης βίντεο.';
$lang["video_scene_job_success"] = 'Η επεξεργασία της σκηνής βίντεο ολοκληρώθηκε';
$lang["video_scene_job_failure"] = 'Η επεξεργασία της σκηνής βίντεο απέτυχε. Δείτε το αρχείο καταγραφής εργασιών για λεπτομέρειες.';