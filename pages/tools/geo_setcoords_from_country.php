<?php

include_once dirname(__DIR__, 2) . "/include/boot.php";

$output_newline = PHP_EOL;
if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    $output_newline = '<br>';
}

# A script to set the geo coordinates based on a country field (if available) for resources with no geolocation information set.

$coords = build_coords();
$codes = build_codes();

# Find country field
$country_field = ps_query("SELECT ref,type FROM resource_type_field WHERE name='country'", [], "schema");
if (count($country_field) == 0 || $country_field[0]["ref"] == "") {
    echo " - Country field not found. Must have a field with shorthand name set to 'country'." . $output_newline;
} else {
    $country_ref = $country_field[0]["ref"];
    $country_type = $country_field[0]["type"];
    # Build array of resource+country combinations where the resource has missing latitude or longitude coordinates

    # Build array for country metadata which is node based
    $resource_countries = ps_query(
        "SELECT DISTINCT rn.resource, UPPER(n.name) name
                                             FROM resource_node rn
                                             JOIN node n ON n.ref=rn.node
                                             JOIN resource r ON r.ref=rn.resource
                                            WHERE n.resource_type_field = ?
                                              AND (r.geo_lat IS NULL OR r.geo_lat IS NULL)",
        ['i', $country_ref]
    );

    $rc_array = array_column($resource_countries, "name", "resource");

    # Sort the resource countries into country (value) sequence and then apply the latlong coordinates on change
    asort($rc_array);
    $last_country = "";
    $refs = array();
    foreach ($rc_array as $rckey => $rcvalue) {
        if ($rcvalue != $last_country) {
            if ($last_country != "") {
                $coord_latlong = fetch_country_coords($last_country, $codes, $coords);

                echo escape(" - Country=" . $last_country . "; Refs=" . join(",", $refs)) . $output_newline;

                update_country_coords($refs, $coord_latlong);
            }
            $last_country = $rcvalue;
            unset($refs);
        }
        $refs[] = $rckey;
    }
    if ($last_country != "") {
        $coord_latlong = fetch_country_coords($last_country, $codes, $coords);

        echo escape(" - Country=" . $last_country . "; Refs=" . join(",", $refs)) . $output_newline;

        update_country_coords($refs, $coord_latlong);
    }
}

function update_country_coords($refs, $latlong)
{
    if (count($latlong) == 2) {
        $chunks = array_chunk($refs, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
        foreach ($chunks as $chunk) {
            ps_query(
                "UPDATE resource SET geo_lat= ?, geo_long= ? WHERE ref IN (" . ps_param_insert(count($chunk)) . ")",
                array_merge(['d', $latlong[0], 'd', $latlong[1]], ps_param_fill($chunk, 'i'))
            );
        }
    }
}

function fetch_country_coords($country_name, $codes, $coords)
{
    # Resolve the country code for the given country name
    $latlong = array();
    $found = false;
    reset($codes);
    foreach ($codes as $code) {
        $s = explode(",", $code);
        if (
            count($s) == 2
            && ((strtoupper($s[0]) == $country_name) || (strpos($country_name, "~EN:" . strtoupper($s[0])) !== false))
        ) {
            $found = true;
            $code = $s[1];
            break;
        }
    }
    if ($found) {
        # Resolve the coordinates for the country code
        reset($coords);
        foreach ($coords as $coord) {
            # Each coord is country code, latitude, longitude
            $s = explode(",", $coord);
            if ($s[0] == trim($code)) {
                $latlong[0] = $s[1];
                $latlong[1] = $s[2];
                break;
            }
        }
    }
    return $latlong;
}

function build_coords()
{
    return explode("\n", "AD,42.5000,1.5000
    AE,24.0000,54.0000
    AF,33.0000,65.0000
    AG,17.0500,-61.8000
    AI,18.2300,-63.0500
    AL,41.0000,20.0000
    AM,40.0000,45.0000
    AN,12.2500,-68.7500
    AO,-12.5000,18.5000
    AP,35.0000,105.0000
    AQ,-90.0000,0.0000
    AR,-34.0000,-64.0000
    AS,-14.3000,-170.0000
    AT,47.5000,14.0000
    AU,-27.0000,133.0000
    AW,12.5000,-69.9670
    AZ,40.5000,47.5000
    BA,44.0000,18.0000
    BB,13.1600,-59.5600
    BD,24.0000,90.0000
    BE,50.7000,4.6000
    BF,12.8000,-2.0000
    BG,43.0000,25.0000
    BH,26.0800,50.5300
    BI,-3.5000,30.0000
    BJ,9.5000,2.2500
    BM,32.3100,-64.7500
    BN,4.5000,114.7000
    BO,-17.0000,-65.0000
    BR,-10.0000,-55.0000
    BS,24.7000,-76.2000
    BT,27.5000,90.5000
    BV,-54.4300,3.4000
    BW,-22.0000,24.0000
    BY,53.0000,28.0000
    BZ,17.2000,-88.7000
    CA,60.0000,-95.0000
    CC,-12.1400,96.8700
    CD,-2.0000,24.0000
    CF,7.0000,21.0000
    CG,-1.0000,15.0000
    CH,46.9000,8.4000
    CI,8.0000,-5.0000
    CK,-21.2300,-159.7700
    CL,-30.0000,-71.0000
    CM,5.0000,12.0000
    CN,35.0000,105.0000
    CO,4.0000,-72.0000
    CR,10.0000,-83.9000
    CU,21.6000,-79.4000
    CV,15.9000,-24.0000
    CX,-10.4800,105.6300
    CY,35.0000,33.1000
    CZ,49.7000,15.5000
    DE,51.0000,9.0000
    DJ,11.7000,42.7000
    DK,56.0000,10.0000
    DM,15.4000,-61.3000
    DO,19.0000,-70.7000
    DZ,28.0000,3.0000
    EC,-1.0000,-78.0000
    EE,58.7000,26.0000
    EG,27.0000,30.0000
    EH,25.0000,-13.0000
    ER,15.6000,38.5000
    ES,40.0000,-3.0000
    ET,8.0000,40.0000
    EU,47.0000,8.0000
    FI,64.0000,26.0000
    FJ,-17.5000,179.1000
    FK,-51.8000,-59.3000
    FM,6.8900,158.2300
    FO,62.1000,-6.9000
    FR,46.0000,3.0000
    GA,-0.4000,12.1000
    GB,54.0000,-2.0000
    GD,12.1200,-61.6700
    GE,42.0000,43.5000
    GF,4.0000,-53.2000
    GH,8.0000,-2.0000
    GI,36.1400,-5.3500
    GL,72.0000,-40.0000
    GM,13.4000,-16.0000
    GN,11.0000,-10.0000
    GP,16.2000,-61.6000
    GQ,1.6000,10.5000
    GR,39.0000,22.0000
    GS,-54.4000,-36.6000
    GT,15.5000,-90.3000
    GU,13.4500,144.7700
    GW,12.0000,-14.9000
    GY,4.9000,-59.0000
    HK,22.2600,114.1900
    HM,-53.1000,73.5000
    HN,14.8000,-87.4000
    HR,45.4000,16.0000
    HT,19.0000,-72.4000
    HU,47.0000,19.3000
    ID,-5.0000,120.0000
    IE,53.0000,-8.0000
    IL,31.0000,34.9000
    IN,24.0000,79.0000
    IO,-7.3300,72.4300
    IQ,33.0000,44.0000
    IR,32.0000,53.0000
    IS,65.0000,-18.0000
    IT,43.0000,12.0000
    JM,18.2000,-77.3000
    JO,31.1000,36.6000
    JP,37.0000,140.0000
    KE,1.0000,38.0000
    KG,41.5000,74.6000
    KH,12.9000,104.9000
    KI,1.4000,173.0000
    KM,-12.2000,44.3000
    KN,17.4000,-62.8000
    KP,40.1000,126.7000
    KR,36.3000,127.8000
    KW,29.3000,47.7000
    KY,19.3000,-81.2000
    KZ,48.0000,68.0000
    LA,19.7000,102.5000
    LB,34.1000,35.9000
    LC,13.8800,-60.9600
    LI,47.1700,9.5300
    LK,7.7000,80.7000
    LR,6.5000,-9.5000
    LS,-29.5000,28.5000
    LT,55.4000,23.9000
    LU,49.7000,6.1000
    LV,57.0000,24.9000
    LY,28.0000,18.0000
    MA,32.0000,-5.0000
    MC,43.7300,7.4200
    MD,47.1000,28.6000
    ME,42.8000,19.2000
    MG,-20.0000,47.0000
    MH,9.0000,168.2000
    MK,41.7000,21.7000
    ML,17.0000,-4.0000
    MM,22.0000,98.0000
    MN,46.0000,105.0000
    MO,22.200,113.5500
    MP,15.1200,145.7100
    MQ,14.6700,-61.0000
    MR,20.0000,-10.0000
    MS,16.7500,-62.2000
    MT,35.8900,14.4400
    MU,-20.2500,57.5800
    MV,3.2000,73.0000
    MW,-13.5000,34.0000
    MX,23.0000,-102.0000
    MY,2.8000,113.5000
    MZ,-18.0000,35.0000
    NA,-22.0000,17.0000
    NC,-21.5000,165.5000
    NE,16.0000,8.0000
    NF,-29.0400,167.9600
    NG,10.0000,8.0000
    NI,13.0000,-85.0000
    NL,52.4000,5.8000
    NO,62.0000,10.0000
    NP,28.0000,84.0000
    NR,-0.5300,166.9300
    NU,-19.0500,-169.8500
    NZ,-41.0000,174.0000
    OM,21.0000,57.0000
    PA,8.9000,-80.0000
    PE,-10.0000,-76.0000
    PF,-17.6000,-149.4000
    PG,-6.0000,147.0000
    PH,13.0000,122.0000
    PK,30.0000,70.0000
    PL,52.0000,20.0000
    PM,46.9500,-56.3200
    PR,18.2000,-66.5000
    PS,32.0000,35.3000
    PT,39.5000,-8.0000
    PW,7.5000,134.5700
    PY,-23.0000,-58.0000
    QA,25.4000,51.3000
    RE,-21.1000,55.6000
    RO,46.0000,25.0000
    RS,44.0000,20.8000
    RU,60.0000,100.0000
    RW,-1.9000,29.9000
    SA,25.0000,45.0000
    SB,-8.3000,158.7000
    SC,-4.5800,55.6700
    SD,15.0000,30.0000
    SE,62.0000,15.0000
    SG,1.3700,103.8000
    SH,-15.9600,-5.7000
    SI,46.0000,14.8000
    SJ,78.0000,20.0000
    SK,48.7000,19.5000
    SL,8.5000,-11.5000
    SM,43.9400,12.4700
    SN,14.5000,-14.5000
    SO,10.0000,49.0000
    SR,4.1000,-55.8000
    SS,7.2400,30.00
    ST,0.2400,6.5900
    SV,13.8000,-88.9000
    SY,35.2000,38.8000
    SZ,-26.5000,31.5000
    TC,21.7400,-71.8000
    TD,15.0000,19.0000
    TF,-49.3000,69.5000
    TG,8.6000,1.1000
    TH,15.3000,101.2000
    TJ,38.8000,70.9000
    TK,-9.2000,-171.8000
    TM,40.0000,60.0000
    TN,34.3000,9.7000
    TO,-21.1400,-175.2000
    TR,39.0000,35.0000
    TT,10.5000,-61.2000
    TV,-8.5000,179.1200
    TW,23.5000,121.0000
    TZ,-6.0000,35.0000
    UA,49.0000,32.0000
    UG,1.7000,32.5000
    UM,19.3000,166.6300
    US,38.0000,-97.0000
    UY,-33.0000,-56.0000
    UZ,41.0000,64.0000
    VA,41.9040,12.4530
    VC,13.2500,-61.2000
    VE,8.0000,-66.0000
    VG,18.5000,-64.5000
    VI,18.3400,-64.7600
    VN,16.2000,107.7000
    VU,-15.1000,167.0000
    WF,-13.2900,-176.2100
    WS,-13.6000,-172.4000
    YE,15.0000,48.0000
    YT,-12.8300,45.1700
    ZA,-29.0000,24.0000
    ZM,-13.3000,27.9000
    ZW,-18.7000,29.9000"); // $coords
}

function build_codes()
{
    return explode("\n", "
    AFGHANISTAN,AF
    ALAND ISLANDS,AX
    ALBANIA,AL
    ALGERIA,DZ
    AMERICAN SAMOA,AS
    ANDORRA,AD
    ANGOLA,AO
    ANGUILLA,AI
    ANTARCTICA,AQ
    ANTIGUA AND BARBUDA,AG
    ARGENTINA,AR
    ARMENIA,AM
    ARUBA,AW
    AUSTRALIA,AU
    AUSTRIA,AT
    AZERBAIJAN,AZ
    BAHAMAS,BS
    BAHRAIN,BH
    BANGLADESH,BD
    BARBADOS,BB
    BELARUS,BY
    BELGIUM,BE
    BELIZE,BZ
    BENIN,BJ
    BERMUDA,BM
    BHUTAN,BT
    BOLIVIA PLURINATIONAL STATE OF,BO
    BOLIVIA,BO
    BONAIRE SAINT EUSTATIUS AND SABA,BQ
    BOSNIA AND HERZEGOVINA,BA
    HERZEGOVINA,BA
    BOSNIA,BA
    BOTSWANA,BW
    BOUVET ISLAND,BV
    BRAZIL,BR
    BRITISH INDIAN OCEAN TERRITORY,IO
    BRUNEI DARUSSALAM,BN
    BULGARIA,BG
    BURKINA FASO,BF
    BURUNDI,BI
    CAMBODIA,KH
    CAMEROON,CM
    CANADA,CA
    CAPE VERDE,CV
    CAYMAN ISLANDS,KY
    AFRICA,CF
    CENTRAL AFRICAN REPUBLIC,CF
    CHAD,TD
    CHILE,CL
    CHINA,CN
    CHRISTMAS ISLAND,CX
    COCOS (KEELING) ISLANDS,CC
    COLOMBIA,CO
    COMOROS,KM
    CONGO,CG
    CONGO THE DEMOCRATIC REPUBLIC OF THE,CD
    DEMOCRATIC REPUBLIC OF CONGO,CD
    D.R. CONGO,CD
    COOK ISLANDS,CK
    COSTA RICA,CR
    COTE D'IVOIRE,CI
    CÔTE D'IVOIRE,CI
    CROATIA,HR
    CUBA,CU
    CURACAO,CW
    CYPRUS,CY
    CZECH REPUBLIC,CZ
    DENMARK,DK
    DJIBOUTI,DJ
    DOMINICA,DM
    DOMINICAN REPUBLIC,DO
    ECUADOR,EC
    EGYPT,EG
    EL SALVADOR,SV
    EQUATORIAL GUINEA,GQ
    ERITREA,ER
    ESTONIA,EE
    ETHIOPIA,ET
    FALKLAND ISLANDS (MALVINAS),FK
    FAROE ISLANDS,FO
    FIJI,FJ
    FINLAND,FI
    FRANCE,FR
    FRENCH GUIANA,GF
    FRENCH POLYNESIA,PF
    FRENCH SOUTHERN TERRITORIES,TF
    GABON,GA
    GAMBIA,GM
    THE GAMBIA,GM
    GEORGIA,GE
    GERMANY,DE
    GHANA,GH
    GIBRALTAR,GI
    GREECE,GR
    GREENLAND,GL
    GRENADA,GD
    GUADELOUPE,GP
    GUAM,GU
    GUATEMALA,GT
    GUERNSEY,GG
    GUINEA,GN
    GUINEA-BISSAU,GW
    GUYANA,GY
    HAITI,HT
    HEARD ISLAND AND MCDONALD ISLANDS,HM
    HOLY SEE (VATICAN CITY STATE),VA
    HONDURAS,HN
    HONG KONG,HK
    HUNGARY,HU
    ICELAND,IS
    INDIA,IN
    INDONESIA,ID
    IRAN ISLAMIC REPUBLIC OF,IR
    IRAQ,IQ
    IRELAND,IE
    ISLE OF MAN,IM
    ISRAEL,IL
    israel and palestinian territories,IL
    ITALY,IT
    JAMAICA,JM
    JAPAN,JP
    JERSEY,JE
    JORDAN,JO
    KAZAKHSTAN,KZ
    KENYA,KE
    KIRIBATI,KI
    KOREA DEMOCRATIC PEOPLE'S REPUBLIC OF,KP
    NORTH KOREA,KP
    KOREA REPUBLIC OF,KR
    SOUTH KOREA,KR
    KUWAIT,KW
    KYRGYZSTAN,KG
    LAO PEOPLE'S DEMOCRATIC REPUBLIC,LA
    LATVIA,LV
    LEBANON,LB
    LESOTHO,LS
    LIBERIA,LR
    LIBYAN ARAB JAMAHIRIYA,LY
    LIECHTENSTEIN,LI
    LITHUANIA,LT
    LUXEMBOURG,LU
    MACAO,MO
    MACEDONIA THE FORMER YUGOSLAV REPUBLIC OF,MK
    MADAGASCAR,MG
    MALAWI,MW
    MALAYSIA,MY
    MALDIVES,MV
    MALI,ML
    MALTA,MT
    MARSHALL ISLANDS,MH
    MARTINIQUE,MQ
    MAURITANIA,MR
    MAURITIUS,MU
    MAYOTTE,YT
    MEXICO,MX
    MICRONESIA FEDERATED STATES OF,FM
    MOLDOVA REPUBLIC OF,MD
    MONACO,MC
    MONGOLIA,MN
    MONTENEGRO,ME
    MONTSERRAT,MS
    MOROCCO,MA
    MOZAMBIQUE,MZ
    MYANMAR,MM
    BURMA,MM
    MYANMAR BURMA,MM
    NAMIBIA,NA
    NAURU,NR
    NEPAL,NP
    NETHERLANDS,NL
    NETHERLANDS ANTILLES,BQ
    NEW CALEDONIA,NC
    NEW ZEALAND,NZ
    NICARAGUA,NI
    NIGER,NE
    NIGERIA,NG
    NIUE,NU
    NORFOLK ISLAND,NF
    NORTHERN MARIANA ISLANDS,MP
    NORWAY,NO
    OMAN,OM
    PAKISTAN,PK
    PALAU,PW
    PALESTINIAN TERRITORY OCCUPIED,PS
    PALESTINE,PS
    GAZA STRIP,PS
    OCCUPIED PALESTINIAN TERRITORY,PS
    PANAMA,PA
    PAPUA NEW GUINEA,PG
    PARAGUAY,PY
    PERU,PE
    PHILIPPINES,PH
    PITCAIRN,PN
    POLAND,PL
    PORTUGAL,PT
    PUERTO RICO,PR
    QATAR,QA
    REUNION,RE
    ROMANIA,RO
    RUSSIAN FEDERATION,RU
    RUSSIA,RU
    RWANDA,RW
    RÉUNION,RE
    SAINT BARTHELEMY,BL
    SAINT BARTHÉLEMY,BL
    SAINT HELENA,SH
    SAINT HELENA ASCENSION AND TRISTAN DA CUNHA,SH
    SAINT KITTS AND NEVIS,KN
    SAINT LUCIA,LC
    SAINT MARTIN,MF
    SAINT MARTIN (FRENCH PART),MF
    SAINT PIERRE AND MIQUELON,PM
    SAINT VINCENT AND THE GRENADINES,VC
    SAMOA,WS
    SAN MARINO,SM
    SAO TOME AND PRINCIPE,ST
    SAUDI ARABIA,SA
    SENEGAL,SN
    SERBIA,RS
    SEYCHELLES,SC
    SIERRA LEONE,SL
    SINGAPORE,SG
    SINT MAARTEN (DUTCH PART),SX
    SLOVAKIA,SK
    SLOVENIA,SI
    SOLOMON ISLANDS,SB
    SOMALIA,SO
    SOMALILAND,SO
    SOUTH AFRICA,ZA
    SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS,GS
    SPAIN,ES
    SRI LANKA,LK
    SUDAN,SD
    SOUTHERN SUDAN,SS
    SURINAME,SR
    SVALBARD AND JAN MAYEN,SJ
    SWAZILAND,SZ
    SWEDEN,SE
    SWITZERLAND,CH
    SYRIAN ARAB REPUBLIC,SY
    TAIWAN PROVINCE OF CHINA,TW
    TAJIKISTAN,TJ
    TANZANIA UNITED REPUBLIC OF,TZ
    TANZANIA,TZ
    THAILAND,TH
    TIMOR-LESTE,TL
    EAST TIMOR,TL
    TOGO,TG
    TOKELAU,TK
    TONGA,TO
    TRINIDAD AND TOBAGO,TT
    TUNISIA,TN
    TURKEY,TR
    TURKMENISTAN,TM
    TURKS AND CAICOS ISLANDS,TC
    TUVALU,TV
    UGANDA,UG
    UKRAINE,UA
    UNITED ARAB EMIRATES,AE
    UNITED KINGDOM,GB
    GREAT BRITAIN,GB
    UK,GB
    ENGLAND,GB
    SCOTLAND,GB
    WALES,GB
    NORTHERN IRELAND,GB
    UNITED STATES,US
    USA,US
    UNITED STATES MINOR OUTLYING ISLANDS,UM
    URUGUAY,UY
    UZBEKISTAN,UZ
    VANUATU,VU
    VATICAN CITY STATE,VA
    VENEZUELA BOLIVARIAN REPUBLIC OF,VE
    VIET NAM,VN
    VIETNAM,VN
    VIRGIN ISLANDS BRITISH,VG
    VIRGIN ISLANDS U.S.,VI
    WALLIS AND FUTUNA,WF
    WESTERN SAHARA,EH
    YEMEN,YE
    ZAMBIA,ZM
    ZIMBABWE,ZW");  // $codes
}
