<?php


$lang["vr_view_configuration"] = 'Google VR View 設定';
$lang["vr_view_google_hosted"] = '使用 Google 托管的 VR View JavaScript 库？';
$lang["vr_view_js_url"] = 'VR View JavaScript 库的 URL（僅在上述為 false 時需要）。如果在本地伺服器，請使用相對路徑，例如 /vrview/build/vrview.js';
$lang["vr_view_restypes"] = '使用 VR View 顯示的資源類型';
$lang["vr_view_autopan"] = '啟用自動平移';
$lang["vr_view_vr_mode_off"] = '禁用 VR 模式按鈕';
$lang["vr_view_condition"] = 'VR View 條件';
$lang["vr_view_condition_detail"] = '如果在下方選擇了欄位，則可以檢查並使用該欄位設定的值來判斷是否顯示 VR View 預覽。這允許你根據嵌入的 EXIF 資料來決定是否使用插件，方法是映射元資料欄位。如果未設定，預覽將始終嘗試顯示，即使格式不相容。<br \\/><br \\/>NB Google 需要等角全景格式的圖像和影片。<br \\/>建議的設定是將 exiftool 欄位 \'ProjectionType\' 映射到一個名為 \'Projection Type\' 的欄位，並使用該欄位。';
$lang["vr_view_projection_field"] = 'VR View 投影類型欄位';
$lang["vr_view_projection_value"] = '啟用 VR View 所需的值';
$lang["vr_view_additional_options"] = '其他選項';
$lang["vr_view_additional_options_detail"] = '以下設定允許你透過映射元資料欄位來控制每個資源的插件，進而控制 VR View 的參數<br \\/>請參閱 <a href =\'https:\\/\\/developers.google.com\\/vr\\/concepts\\/vrview-web\' target=\'+blank\'>https:\\/\\/developers.google.com\\/vr\\/concepts\\/vrview-web<\\/a> 以獲取更詳細的資訊';
$lang["vr_view_stereo_field"] = '用於判斷圖像\\/影片是否為立體聲的欄位（可選，未設定時預設為 false）';
$lang["vr_view_stereo_value"] = '用於檢查的值。若找到，立體聲將設定為 true';
$lang["vr_view_yaw_only_field"] = '用於判斷是否應阻止滾轉／俯仰的欄位（可選，未設定時預設為 false）';
$lang["vr_view_yaw_only_value"] = '要檢查的值。如果找到，is_yaw_only 選項將設置為 true';
$lang["vr_view_orig_image"] = '使用原始資源檔案作為圖像預覽的來源？';
$lang["vr_view_orig_video"] = '使用原始資源檔案作為影片預覽的來源？';
$lang["page-title_vr_view_download"] = 'VR 觀看';
$lang["page-title_vr_view_setup"] = '設定 VR 觀看插件';
