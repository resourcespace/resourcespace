<?php


$lang["vr_view_configuration"]='Google VR View konfigurasjon';
$lang["vr_view_google_hosted"]='Vil du bruke Google-hostet VR View JavaScript-bibliotek?';
$lang["vr_view_js_url"]='Nettadresse til VR View JavaScript-biblioteket (kun nødvendig hvis overstående er usant). Hvis det er lokalt på serveren, bruk relativ bane, f.eks. /vrview/build/vrview.js';
$lang["vr_view_restypes"]='Ressurstyper som skal vises ved hjelp av VR-visning';
$lang["vr_view_autopan"]='Aktiver Autopanering';
$lang["vr_view_vr_mode_off"]='Deaktiver VR-modus-knapp';
$lang["vr_view_condition"]='VR-visningsbetingelse';
$lang["vr_view_condition_detail"]='Hvis et felt er valgt nedenfor, kan verdien som er satt for feltet sjekkes og brukes til å bestemme om VR-visningsforhåndsvisningen skal vises eller ikke. Dette lar deg bestemme om du skal bruke tillegget basert på innebygd EXIF-data ved å kartlegge metadatafelt. Hvis dette ikke er satt, vil forhåndsvisningen alltid bli forsøkt, selv om formatet er uforenlig. <br /><br />NB Google krever bilder og videoer i equirectangular-panoramisk format. <br />Foreslått konfigurasjon er å kartlegge exiftool-feltet \'ProjectionType\' til et felt kalt \'Projection Type\' og bruke det feltet.';
$lang["vr_view_projection_field"]='VR Visningsprojeksjonstype felt';
$lang["vr_view_projection_value"]='Nødvendig verdi for at VR-visning skal være aktivert';
$lang["vr_view_additional_options"]='Tilleggsalternativer';
$lang["vr_view_additional_options_detail"]='Følgende lar deg kontrollere tillegget per ressurs ved å kartlegge metadatafelt som skal brukes til å kontrollere VR View-parametere. Se <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> for mer detaljert informasjon';
$lang["vr_view_stereo_field"]='Felt brukt til å bestemme om bilde/video er stereoskopisk (valgfritt, standardverdi er falsk hvis ikke satt)';
$lang["vr_view_stereo_value"]='Verdi å sjekke for. Hvis den blir funnet, vil stereo bli satt til sann';
$lang["vr_view_yaw_only_field"]='Felt brukt til å bestemme om rulling/helling skal forhindres (valgfritt, standardverdi er falsk hvis ikke satt)';
$lang["vr_view_yaw_only_value"]='Verdi å sjekke for. Hvis funnet, vil is_yaw_only-alternativet bli satt til sant';
$lang["vr_view_orig_image"]='Bruk original ressursfil som kilde for bildevisning?';
$lang["vr_view_orig_video"]='Bruk original ressursfil som kilde for video forhåndsvisning?';
$lang["plugin-vr_view-title"]='VR-visning';
$lang["plugin-vr_view-desc"]='Google VR View - 360 graders bilde- og videoforhåndsvisninger (equirektangulært format)';
$lang["page-title_vr_view_download"] = 'VR Visning';
$lang["page-title_vr_view_setup"] = 'Sett opp VR View-plugin';