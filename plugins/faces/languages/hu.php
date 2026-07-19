<?php

$lang["faces-detected-faces"] = 'Felfedezett arcok';
$lang["faces-detected-face"] = 'Arcfelismerés';
$lang["faces-confidence"] = 'Bizalom';
$lang["faces-find-matching"] = 'Találd meg a megfelelő arcokat';
$lang["faces-configuration"] = 'AI Arcok Konfiguráció';
$lang["faces-service-endpoint"] = 'Python FastAPI szolgáltatás URL';
$lang["faces-match-threshold"] = 'Arcazonosító küszöb: milyen hasonlósági szintet tekintenek egyezésnek az arcok keresésekor? Javasolt 30%.';
$lang["faces-tag-threshold"] = 'Arc címke küszöb: milyen hasonlósági szintet tekintenek egyezésnek az arcok automatikus címkézésekor? Javasolt 50%.';
$lang["faces-tag-field"] = 'A mező, amely a címkézett egyének neveit tartalmazza. Ennek egy dinamikus legördülő mezőnek kell lennie.';
$lang["faces-name"] = 'Név';
$lang["faces-detect-on-upload"] = 'Arcfelismerés a feltöltés során?';
$lang["faces-tag-on-upload"] = 'Címkézze a felismert arcokat feltöltéskor?';
$lang["faces-detecting"] = 'Arcok keresése az erőforrásban:';
$lang["faces-tagging"] = 'Arcfelismerések címkézése az erőforrásban:';
$lang["faces-confidence-threshold"] = 'Arc arcosság küszöb: Mennyire legyen biztos a modell abban, hogy emberi arcot talált? Javasolt 70% (az ennél alacsonyabb értékek elmosódott arcokat és nem arcokat fognak egyezni)';
$lang["faces-oneface"] = 'Kérjük, válasszon csak egy lehetőséget minden egyes archoz.';
$lang["faces-show-view"] = 'Mutassa az AI Faces funkciót a nézet oldalon.';
$lang["faces_count_faces"] = 'Összes észlelt arc';
$lang["faces_count_missing"] = 'Képek feldolgozásra';
$lang["faces-tag-field-not-set"] = 'A címkéző mező nincs konfigurálva.';

$lang["page-title_faces_setup"] = 'Arcfelismerő bővítmény beállítása';
$lang["faces_insight_faces"] = 'InsightFaces';
$lang["faces_detect_faces"] = 'Arcok felismerése';
$lang["faces_tag_faces"] = 'Arcok címkézése';
$lang["faces_detect_faces_configure"] = 'Feladat konfigurálása arcok felismeréséhez';
$lang["faces_tag_faces_configure"] = 'Feladat konfigurálása arcok címkézéséhez';
$lang["faces_detect_faces_intro"] = 'Hozzon létre egy feladatot az arcok felismerésének megkezdéséhez itt - ez a feladat nem igényel paramétereket, így elindítható, amíg nincs más, ezzel a típussal kapcsolatos függőben lévő feladat.';
$lang["faces_tag_faces_collection_refs_help"] = 'Ennek a beállításnak az aktiválásával csak a felsorolt gyűjtemények erőforrásai frissülnek. Ha nem ad meg gyűjteményeket, akkor az arcok címkézése minden alkalmas erőforrásra vonatkozik. A gyűjteményeket vesszővel elválasztott listaként, illetve tartományként is megadhatja pl. 100,105,110-115';
