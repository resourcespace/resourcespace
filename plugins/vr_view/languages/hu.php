<?php


$lang["vr_view_configuration"] = 'Google VR View konfiguráció';
$lang["vr_view_google_hosted"] = 'Használja a Google által hosztolt VR View javascript könyvtárat?';
$lang["vr_view_js_url"] = 'URL a VR View javascript könyvtárhoz (csak akkor szükséges, ha a fenti hamis). Ha a szerverhez helyi, használjon relatív elérési utat pl. /vrview/build/vrview.js';
$lang["vr_view_restypes"] = 'Erőforrás típusok megjelenítése VR nézetben';
$lang["vr_view_autopan"] = 'Autopan engedélyezése';
$lang["vr_view_vr_mode_off"] = 'VR mód letiltása gomb';
$lang["vr_view_condition"] = 'VR Nézet feltétel';
$lang["vr_view_condition_detail"] = 'Ha az alábbi mező közül egy ki van választva, a mezőhöz beállított érték ellenőrizhető és felhasználható annak meghatározására, hogy megjelenítse-e a VR View előnézetet. Ez lehetővé teszi, hogy a beágyazott EXIF adatok alapján döntsön a plugin használatáról a metaadat mezők leképezésével. Ha ez nincs beállítva, az előnézet mindig megkísérli a megjelenítést, még akkor is, ha a formátum inkompatibilis <br /><br />NB A Google equirectangular-panoráma formátumú képeket és videókat igényel.<br />Javasolt konfiguráció a \'ProjectionType\' exiftool mező leképezése egy \'Projection Type\' nevű mezőre, és annak a mezőnek a használata.';
$lang["vr_view_projection_field"] = 'VR Nézet VetítésiTípus mező';
$lang["vr_view_projection_value"] = 'Szükséges érték a VR nézet engedélyezéséhez';
$lang["vr_view_additional_options"] = 'További lehetőségek';
$lang["vr_view_additional_options_detail"] = 'A következő lehetőséget ad arra, hogy erőforrásonként vezérelje a plugint azáltal, hogy a VR View paraméterek vezérléséhez használt metaadat mezőket hozzárendeli<br />További részletes információkért lásd <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a>';
$lang["vr_view_stereo_field"] = 'Mező, amelyet arra használnak, hogy meghatározzák, hogy a kép/videó sztereó-e (opcionális, alapértelmezés szerint hamis, ha nincs beállítva)';
$lang["vr_view_stereo_value"] = 'Érték, amelyet ellenőrizni kell. Ha megtalálható, a sztereó értéke igazra lesz állítva';
$lang["vr_view_yaw_only_field"] = 'Mező, amelyet arra használnak, hogy meghatározzák, hogy meg kell-e akadályozni a dőlést/fordulást (opcionális, alapértelmezés szerint hamis, ha nincs beállítva)';
$lang["vr_view_yaw_only_value"] = 'Érték, amelyet ellenőrizni kell. Ha megtalálható, az is_yaw_only opció igazra lesz állítva';
$lang["vr_view_orig_image"] = 'Eredeti erőforrás fájl használata képkivonat forrásaként?';
$lang["vr_view_orig_video"] = 'Használja az eredeti erőforrás fájlt a videó előnézet forrásaként?';
$lang["page-title_vr_view_download"] = 'VR Nézet';
$lang["page-title_vr_view_setup"] = 'VR Nézet Plugin Beállítása';