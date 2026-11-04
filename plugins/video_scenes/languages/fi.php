<?php


$lang["video_scene_title"] = 'Videokohtaukset';
$lang["video_scene_intro"] = 'Tunnista kohtaukset paikallisesti transnetv2-pytorchilla latauksen tai esikatselun uudelleenluonnin jälkeen. Asenna Python-paketti ennen käsittelyä; mallipainot sisältyvät. Valinnainen OpenAI API-avain mahdollistaa kohtauskuvaukset; kollaasit ja kohtauksen ääni lähetetään sitten OpenAI:lle.';
$lang["video_scene_python"] = 'Python-suoritustiedosto (absoluuttinen polku)';
$lang["video_scene_threshold"] = 'Kohtauksen siirtymän kynnysarvo (0 ja 1 välillä)';
$lang["video_scene_api_key"] = 'OpenAI API-avain (valinnainen)';
$lang["video_scene_model"] = 'OpenAI Realtime -malli, joka tukee kuva- ja ääniosuutta';
$lang["video_scene_audio"] = 'Sisällytä kohtauksen ääni kuvauksiin';
$lang["video_scene_audio_overlap"] = 'Äänikonteksti ennen ja jälkeen jokaisen kohtauksen (sekunteina, 0–30)';
$lang["video_scene_prompt"] = 'Kohtauksen kuvauskehotus';
$lang["video_scene_search_field"] = 'Haettavissa oleva kohtauskuvauskenttä (valinnainen)';
$lang["video_scene_prompt_help"] = 'Ohjeet kunkin kohtauksen kuvaamiseen sen kehyksistä ja äänestä. Enintään 8 000 tavua.';
$lang["video_scene_timeout"] = 'Suurin käsittelyaika per video (sekunteina)';
$lang["video_scene_invalid_config"] = 'Tarkista Python-polku, kynnysarvo, malli, avain ja käsittelyaikaraja.';
$lang["video_scene_time"] = 'Aikaleima';
$lang["video_scene_thumbnail"] = 'Esikatselukuva';
$lang["video_scene_description"] = 'Kuvaus';
$lang["video_scene_edit_description"] = 'Muokkaa kohtauksen kuvausta kohdassa %time';
$lang["video_scene_invalid_description"] = 'Anna kelvollinen kuvaus, enintään 16 000 tavua.';
$lang["video_scene_description_missing"] = 'Tätä kohtausta ei enää ole. Lataa resurssinäkymä uudelleen.';
$lang["video_scene_description_save_failed"] = 'Kuvausta ei voitu tallentaa. Yritä uudelleen tai lataa resurssinäkymä uudelleen.';
$lang["video_scene_description_saved"] = 'Kohtauksen kuvaus tallennettu.';
$lang["video_scene_no_description"] = 'Kuvausta ei ole saatavilla';
$lang["video_scene_play"] = 'Toista kohtaus kohdassa %time';
$lang["video_scene_play_unavailable"] = 'Tämä kohtaus on videon esikatselun ulkopuolella.';
$lang["video_scene_job_success"] = 'Videokohtauksen käsittely valmis';
$lang["video_scene_job_failure"] = 'Videokohtauksen käsittely epäonnistui. Katso työn loki lisätietoja varten.';