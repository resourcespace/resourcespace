<?php


$lang["vr_view_configuration"]='Configurazione di Google VR View';
$lang["vr_view_google_hosted"]='Utilizzare la libreria javascript VR View ospitata da Google?';
$lang["vr_view_js_url"]='URL alla libreria javascript VR View (necessario solo se il precedente è falso). Se locale al server, utilizzare il percorso relativo, ad esempio /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Tipi di risorse da visualizzare utilizzando la visualizzazione VR';
$lang["vr_view_autopan"]='Abilita Autopan';
$lang["vr_view_vr_mode_off"]='Disattiva pulsante modalità VR';
$lang["vr_view_condition"]='Condizione di visualizzazione VR';
$lang["vr_view_condition_detail"]='Se un campo è selezionato di seguito, il valore impostato per il campo può essere controllato e utilizzato per determinare se visualizzare o meno l\'anteprima di VR View. Ciò consente di determinare se utilizzare il plugin in base ai dati EXIF incorporati mappando i campi dei metadati. Se questo non è impostato, l\'anteprima verrà sempre tentata, anche se il formato non è compatibile. <br /><br />NB Google richiede immagini e video formattati equirettangolari-panoramici. <br />La configurazione suggerita consiste nel mappare il campo exiftool \'ProjectionType\' in un campo chiamato \'Tipo di proiezione\' e utilizzare tale campo.';
$lang["vr_view_projection_field"]='Campo Tipo di proiezione vista VR';
$lang["vr_view_projection_value"]='Valore richiesto per abilitare la visualizzazione VR';
$lang["vr_view_additional_options"]='Opzioni aggiuntive';
$lang["vr_view_additional_options_detail"]='La seguente opzione ti consente di controllare il plugin per risorsa mappando i campi di metadati da utilizzare per controllare i parametri di visualizzazione VR.<br />Consulta <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> per informazioni più dettagliate';
$lang["vr_view_stereo_field"]='Campo utilizzato per determinare se l\'immagine/video è stereo (opzionale, predefinito su falso se non impostato)';
$lang["vr_view_stereo_value"]='Valore da controllare. Se trovato, lo stereo verrà impostato su vero';
$lang["vr_view_yaw_only_field"]='Campo utilizzato per determinare se il rollio/pitch dovrebbe essere impedito (opzionale, predefinito su false se non impostato)';
$lang["vr_view_yaw_only_value"]='Valore da controllare. Se trovato, l\'opzione is_yaw_only verrà impostata su true';
$lang["vr_view_orig_image"]='Usare il file originale della risorsa come sorgente per l\'anteprima dell\'immagine?';
$lang["vr_view_orig_video"]='Usare il file originale della risorsa come fonte per l\'anteprima del video?';
$lang["plugin-vr_view-title"]='Vista VR';
$lang["plugin-vr_view-desc"]='Google VR View - Anteprime di immagini e video a 360 gradi (formato equirettangolare)';
$lang["page-title_vr_view_download"] = 'VR View';
$lang["page-title_vr_view_setup"] = 'Imposta il plugin VR View';