<?php


$lang["vr_view_configuration"]='Google VR View Konfiguration';
$lang["vr_view_google_hosted"]='Möchten Sie die VR View JavaScript-Bibliothek von Google verwenden?';
$lang["vr_view_js_url"]='URL zur VR View JavaScript-Bibliothek (nur erforderlich, wenn das Obige falsch ist). Wenn lokal auf dem Server, verwenden Sie den relativen Pfad, z.B. /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Anzuzeigende Ressourcentypen mit VR-Ansicht';
$lang["vr_view_autopan"]='Aktiviere Autopan';
$lang["vr_view_vr_mode_off"]='Deaktiviere VR-Modus Schaltfläche';
$lang["vr_view_condition"]='VR-Ansichtsbedingung';
$lang["vr_view_condition_detail"]='Wenn ein Feld unten ausgewählt wird, kann der für das Feld festgelegte Wert überprüft und verwendet werden, um zu bestimmen, ob die VR View-Vorschau angezeigt werden soll oder nicht. Dies ermöglicht es Ihnen, zu bestimmen, ob das Plugin auf der Grundlage von eingebetteten EXIF-Daten unter Verwendung von Metadatenfeldern verwendet werden soll. Wenn dies nicht gesetzt ist, wird die Vorschau immer versucht, auch wenn das Format nicht kompatibel ist. <br /><br />NB Google erfordert equirectangular-panoramatische formatierte Bilder und Videos.<br />Die vorgeschlagene Konfiguration besteht darin, das exiftool-Feld \'ProjectionType\' auf ein Feld namens \'Projection Type\' abzubilden und dieses Feld zu verwenden.';
$lang["vr_view_projection_field"]='VR Ansicht Projektionstyp Feld';
$lang["vr_view_projection_value"]='Erforderlicher Wert, um die VR-Ansicht zu aktivieren';
$lang["vr_view_additional_options"]='Zusätzliche Optionen';
$lang["vr_view_additional_options_detail"]='Folgendes ermöglicht es Ihnen, das Plugin pro Ressource zu steuern, indem Sie Metadatenfelder zuordnen, um die VR View-Parameter zu steuern.<br />Weitere detaillierte Informationen finden Sie unter <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a>';
$lang["vr_view_stereo_field"]='Feld, das verwendet wird, um zu bestimmen, ob ein Bild/Video stereoskopisch ist (optional, Standardwert ist false, wenn nicht gesetzt)';
$lang["vr_view_stereo_value"]='Zu überprüfender Wert. Wenn gefunden, wird Stereo auf "true" gesetzt';
$lang["vr_view_yaw_only_field"]='Feld, das verwendet wird, um zu bestimmen, ob Roll-/Pitch-Bewegungen verhindert werden sollen (optional, Standardwert ist false, wenn nicht festgelegt)';
$lang["vr_view_yaw_only_value"]='Zu überprüfender Wert. Wenn er gefunden wird, wird die Option is_yaw_only auf true gesetzt';
$lang["vr_view_orig_image"]='Verwenden Sie die Originalressourcendatei als Quelle für die Bildvorschau?';
$lang["vr_view_orig_video"]='Verwenden Sie die Originalressourcendatei als Quelle für die Video-Vorschau?';
$lang["plugin-vr_view-title"]='VR-Ansicht';
$lang["plugin-vr_view-desc"]='Google VR View - 360-Grad-Bild- und Video-Vorschauen (equirectangulares Format)';
$lang["page-title_vr_view_download"] = 'VR Ansicht';
$lang["page-title_vr_view_setup"] = 'VR-Ansicht Plugin einrichten';