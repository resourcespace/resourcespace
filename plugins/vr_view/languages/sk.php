<?php


$lang["vr_view_configuration"]='Konfigurácia Google VR View';
$lang["vr_view_google_hosted"]='Použiť JavaScript knižnicu VR View hostovanú na Google?';
$lang["vr_view_js_url"]='URL k JavaScript knižnici VR View (potrebno le, če zgoraj ni res). Če je knjižnica na strežniku, uporabite relativno pot, npr. /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Typy zdrojov na zobrazenie pomocou VR zobrazenia';
$lang["vr_view_autopan"]='Povoliť automatické posúvanie';
$lang["vr_view_vr_mode_off"]='Zakleni gumb za način VR';
$lang["vr_view_condition"]='Stav VR zobrazenia';
$lang["vr_view_condition_detail"]='Ak je nižšie vybrané pole, hodnota nastavená pre pole môže byť skontrolovaná a použitá na určenie, či zobraziť alebo nezobraziť náhľad VR View. To vám umožní určiť, či použiť plugin na základe vložených EXIF dát mapovaním metadátových polí. Ak nie je nastavené, náhľad sa vždy pokúsi zobraziť, aj keď formát nie je kompatibilný. <br /><br />Poznámka: Google vyžaduje obrázky a videá vo formáte equirectangular-panoramic. <br />Navrhovaná konfigurácia je mapovať pole exiftool \'ProjectionType\' na pole s názvom \'Projection Type\' a použiť toto pole.';
$lang["vr_view_projection_field"]='Pole "ProjectionType" zobrazenia VR pohľadu';
$lang["vr_view_projection_value"]='Požadovaná hodnota pre povolenie VR zobrazenia';
$lang["vr_view_additional_options"]='Dodatečné možnosti';
$lang["vr_view_additional_options_detail"]='Naslednje vam omogoča nadzorovanje vtičnika na viru z dodeljevanjem polj metapodatkov za uporabo pri nadzoru parametrov VR View.<br />Za podrobnejše informacije si oglejte <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a>';
$lang["vr_view_stereo_field"]='Pole, ki se uporablja za določanje, ali je slika/video stereo (neobvezno, privzeto je nastavljeno na napačno, če ni nastavljeno)';
$lang["vr_view_stereo_value"]='Hodnota, ktorá sa má skontrolovať. Ak sa nájde, stereo bude nastavené na true';
$lang["vr_view_yaw_only_field"]='Pole, ki se uporablja za določanje ali naj se prepreči nagibanje/valovanje (neobvezno, privzeto je nastavljeno na false, če ni nastavljeno)';
$lang["vr_view_yaw_only_value"]='Hodnota, ktorá sa má skontrolovať. Ak sa nájde, nastaví sa možnosť is_yaw_only na true';
$lang["vr_view_orig_image"]='Použiť pôvodný súbor zdroja ako zdroj pre náhľad obrázka?';
$lang["vr_view_orig_video"]='Použiť pôvodný súbor zdroja ako zdroj pre náhľad videa?';
$lang["plugin-vr_view-title"]='VR Pogled';
$lang["plugin-vr_view-desc"]='Google VR View - 360-stopinjski predogled slik in videoposnetkov (equirektangularni format)';
$lang["page-title_vr_view_download"] = 'VR pohľad';
$lang["page-title_vr_view_setup"] = 'Nastavenie pluginu VR View';