<?php


$lang["vr_view_configuration"] = 'Конфигурация на Google VR View';
$lang["vr_view_google_hosted"] = 'Използвайте Google хоствана библиотека за VR View javascript?';
$lang["vr_view_js_url"] = 'URL към библиотеката за VR View javascript (не е необходима, ако горното е лъжа). Ако е локално на сървъра, използвайте относителен път, например \\/vrview\\/build\\/vrview.js';
$lang["vr_view_restypes"] = 'Типове ресурси за показване с VR View';
$lang["vr_view_autopan"] = 'Активиране на автоматично пътуване';
$lang["vr_view_vr_mode_off"] = 'Деактивирайте бутона за VR режим';
$lang["vr_view_condition"] = 'Условие за VR View';
$lang["vr_view_condition_detail"] = 'Ако е избрано поле по-долу, стойността, зададена за полето, може да бъде проверена и използвана за определяне дали да се показва прегледът VR View. Това ви позволява да решите дали да използвате плъгина въз основа на вградени EXIF данни чрез картографиране на метаданни полета. Ако това не е зададено, прегледът винаги ще бъде опитван, дори ако форматът е несъвместим <br \\/><br \\/>NB Google изисква изображения и видеа с екиретрографска панорамна форма.<br \\/>Предложената конфигурация е да се картографира полето \'ProjectionType\' от exiftool към поле, наречено \'Projection Type\', и да се използва това поле.';
$lang["vr_view_projection_field"] = 'Поле за ProjectionType за VR View';
$lang["vr_view_projection_value"] = 'Задължителна стойност за активиране на VR View';
$lang["vr_view_additional_options"] = 'Допълнителни опции';
$lang["vr_view_additional_options_detail"] = 'Следното ви позволява да контролирате плъгина за всяко ресурс чрез картографиране на метаданни полета за управление на параметрите на VR View<br \\/>Вижте <a href =\'https:\\/\\/developers.google.com\\/vr\\/concepts\\/vrview-web\' target=\'+blank\'>https:\\/\\/developers.google.com\\/vr\\/concepts\\/vrview-web<\\/a> за по-подробна информация';
$lang["vr_view_stereo_field"] = 'Поле, използвано за определяне дали изображението\\/видеото е стерео (по избор, по подразбиране е false ако не е зададено)';
$lang["vr_view_stereo_value"] = 'Стойност за проверка. Ако е намерена, стереото ще бъде зададено на true';
$lang["vr_view_yaw_only_field"] = 'Поле, използвано за определяне дали да се предотврати рол-наклон (по избор, по подразбиране е false ако не е зададено)';
$lang["vr_view_yaw_only_value"] = 'Стойност за проверка. Ако е намерена, опцията is_yaw_only ще бъде зададена на true';
$lang["vr_view_orig_image"] = 'Използвайте оригиналния файл на ресурса като източник за преглед на изображението?';
$lang["vr_view_orig_video"] = 'Използвайте оригиналния файл на ресурса като източник за преглед на видеото?';
$lang["page-title_vr_view_download"] = 'VR преглед';
$lang["page-title_vr_view_setup"] = 'Настройка на VR преглед';
