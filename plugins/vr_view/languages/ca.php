<?php


$lang["vr_view_configuration"]='Configuració de Google VR View';
$lang["vr_view_google_hosted"]='Utilitzar la biblioteca de JavaScript VR View allotjada per Google?';
$lang["vr_view_js_url"]='URL a la llibreria de javascript VR View (només necessari si el camp anterior és fals). Si és local al servidor, utilitzeu la ruta relativa, per exemple /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Tipus de recursos per mostrar amb la visualització VR';
$lang["vr_view_autopan"]='Habilitar l\'autopan';
$lang["vr_view_vr_mode_off"]='Desactivar el botó del mode VR';
$lang["vr_view_condition"]='Condicions de visualització de VR';
$lang["vr_view_condition_detail"]='Si es selecciona un camp a continuació, el valor establert per al camp es pot comprovar i utilitzar per determinar si es mostra o no la vista prèvia de VR View. Això us permet determinar si heu de fer servir el connector basat en les dades EXIF incrustades mitjançant la creació de camps de metadades. Si això no està configurat, sempre s\'intentarà mostrar la vista prèvia, fins i tot si el format no és compatible. <br /><br />Nota: Google requereix imatges i vídeos amb format panoràmic equirectangular. <br />La configuració suggerida és mapejar el camp \'ProjectionType\' d\'exiftool a un camp anomenat \'Tipus de projecció\' i utilitzar aquest camp.';
$lang["vr_view_projection_field"]='Camp de tipus de projecció de visualització VR';
$lang["vr_view_projection_value"]='Valor requerit per habilitar la visualització de VR';
$lang["vr_view_additional_options"]='Opcions addicionals';
$lang["vr_view_additional_options_detail"]='El següent permet controlar el connector per recurs mitjançant la vinculació dels camps de metadades per utilitzar per controlar els paràmetres de visualització VR.<br />Consulteu <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> per obtenir informació més detallada';
$lang["vr_view_stereo_field"]='Camp utilitzat per determinar si la imatge/vídeo és estereoscòpica (opcional, per defecte és fals si no s\'estableix)';
$lang["vr_view_stereo_value"]='Valor a comprovar. Si es troba, l\'estèreo s\'establirà com a verdader';
$lang["vr_view_yaw_only_field"]='Camp utilitzat per determinar si s\'ha de prevenir el gir/inclinació (opcional, per defecte és fals si no està definit)';
$lang["vr_view_yaw_only_value"]='Valor a comprovar. Si es troba, l\'opció is_yaw_only es configurarà com a true';
$lang["vr_view_orig_image"]='Utilitzar el fitxer original del recurs com a font per a la vista prèvia de la imatge?';
$lang["vr_view_orig_video"]='Utilitzar el fitxer original de recurs com a font per a la vista prèvia del vídeo?';
$lang["plugin-vr_view-title"]='Vista VR';
$lang["plugin-vr_view-desc"]='Google VR View - Previsualitzacions d\'imatges i vídeos de 360 graus (format equirectangular)';
$lang["page-title_vr_view_download"] = 'Visió VR';
$lang["page-title_vr_view_setup"] = 'Configura el complement de vista VR';