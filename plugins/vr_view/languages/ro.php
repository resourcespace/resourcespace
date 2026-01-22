<?php


$lang["vr_view_configuration"]='Configurarea Google VR View';
$lang["vr_view_google_hosted"]='Folosiți biblioteca de cod JavaScript VR View găzduită de Google?';
$lang["vr_view_js_url"]='Adresa URL către biblioteca de JavaScript VR View (necesară doar dacă cea de mai sus este falsă). Dacă este locală pe server, utilizați calea relativă, de exemplu /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Tipuri de resurse de afișat folosind VR View';
$lang["vr_view_autopan"]='Permiteți Autopan';
$lang["vr_view_vr_mode_off"]='Dezactivează butonul modului VR';
$lang["vr_view_condition"]='Stare de vizualizare VR';
$lang["vr_view_condition_detail"]='Dacă un câmp este selectat mai jos, valoarea setată pentru câmp poate fi verificată și utilizată pentru a determina dacă să se afișeze previzualizarea VR View sau nu. Acest lucru vă permite să decideți dacă să utilizați plugin-ul pe baza datelor EXIF încorporate prin maparea câmpurilor de metadate. Dacă acest lucru nu este setat, previzualizarea va fi întotdeauna încercată, chiar dacă formatul este incompatibil. <br /><br />NB Google necesită imagini și videoclipuri formatate equirectangular-panoramic. <br />Configurația sugerată este să mapați câmpul exiftool \'ProjectionType\' la un câmp numit \'Tip de proiecție\' și să utilizați acel câmp.';
$lang["vr_view_projection_field"]='Câmpul TipProiecție pentru vizualizarea VR';
$lang["vr_view_projection_value"]='Valoarea necesară pentru activarea vizualizării VR';
$lang["vr_view_additional_options"]='Opțiuni suplimentare';
$lang["vr_view_additional_options_detail"]='Următoarea opțiune vă permite să controlați modul de utilizare a plugin-ului pentru fiecare resursă prin maparea câmpurilor de metadate pentru a controla parametrii VR View.<br />Consultați <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> pentru informații mai detaliate';
$lang["vr_view_stereo_field"]='Câmp folosit pentru a determina dacă imaginea/video este stereo (opțional, valoarea implicită este falsă dacă nu este setată)';
$lang["vr_view_stereo_value"]='Valoarea de verificat. Dacă este găsită, stereo va fi setat la adevărat';
$lang["vr_view_yaw_only_field"]='Câmp folosit pentru determinarea dacă rotația/înclinarea trebuie prevenită (opțional, valoarea implicită este falsă dacă nu este setată)';
$lang["vr_view_yaw_only_value"]='Valoarea de verificat. Dacă este găsită, opțiunea is_yaw_only va fi setată la adevărat';
$lang["vr_view_orig_image"]='Folosiți fișierul original al resursei ca sursă pentru previzualizarea imaginii?';
$lang["vr_view_orig_video"]='Folosiți fișierul original al resursei ca sursă pentru previzualizarea video?';
$lang["plugin-vr_view-title"]='Vizualizare VR';
$lang["plugin-vr_view-desc"]='Google VR View - previzualizări de imagini și videoclipuri la 360 de grade (format equirectangular)';
$lang["page-title_vr_view_download"] = 'Vizualizare VR';
$lang["page-title_vr_view_setup"] = 'Configurare pluginul VR View';