<?php


$lang["emu_configuration"] = 'EMu 設定';
$lang["emu_api_settings"] = 'API 伺服器設定';
$lang["emu_api_server"] = '伺服器位址（例如 http://[server.address]）';
$lang["emu_api_server_port"] = '伺服器埠號';
$lang["emu_resource_types"] = '選擇與 EMu 相關聯的資源類型';
$lang["emu_email_notify"] = '腳本將傳送通知的電子郵件地址。留空則預設為系統通知地址';
$lang["emu_script_failure_notify_days"] = '在此天數後若腳本未完成，將顯示警示並發送電子郵件';
$lang["emu_script_header"] = '啟用腳本，當 ResourceSpace 執行排程任務（cron_copy_hitcount.php）時，會自動更新 EMu 資料';
$lang["emu_last_run_date"] = '腳本上次執行時間';
$lang["emu_script_mode"] = '腳本模式';
$lang["emu_script_mode_option_1"] = '從 EMu 匯入元資料';
$lang["emu_script_mode_option_2"] = '拉取所有 EMu 記錄並保持 RS 與 EMu 同步';
$lang["emu_enable_script"] = '啟用 EMu 腳本';
$lang["emu_test_mode"] = '測試模式 - 設為 true，腳本將執行但不更新資源';
$lang["emu_interval_run"] = '以以下間隔執行腳本（例如 +1 天、+2 週、兩週）。留空則每次執行 cron_copy_hitcount.php 時都會運行';
$lang["emu_log_directory"] = '腳本日誌存放目錄。若此為空或無效，則不會記錄日誌。';
$lang["emu_created_by_script_field"] = '用於存儲資源是否由 EMu 腳本建立的元數據欄位';
$lang["emu_settings_header"] = 'EMu 設定';
$lang["emu_irn_field"] = '用於存儲 EMu 識別碼 (IRN) 的元數據欄位';
$lang["emu_search_criteria"] = '用於同步 EMu 與 ResourceSpace 的搜尋條件';
$lang["emu_rs_mappings_header"] = 'EMu - ResourceSpace 映射規則';
$lang["emu_module"] = 'EMu 模組';
$lang["emu_column_name"] = 'EMu 模組欄位';
$lang["emu_rs_field"] = 'ResourceSpace 欄位';
$lang["emu_add_mapping"] = '新增映射';
$lang["emu_confirm_upload_nodata"] = '請勾選以確認您希望繼續上傳';
$lang["emu_test_script_title"] = '測試／執行腳本';
$lang["emu_run_script"] = '處理';
$lang["emu_script_problem"] = '警告 - EMu 腳本在過去 %days% 天內未成功完成。上次執行時間：';
$lang["emu_no_resource"] = '未指定資源ID！';
$lang["emu_upload_nodata"] = '未找到此 IRN 的 EMu 資料：';
$lang["emu_nodata_returned"] = '未找到指定 IRN 的 EMu 資料。';
$lang["emu_createdfromemu"] = '由 EMU 插件建立';
$lang["page-title_emu_emu_object_details"] = 'EMu 物件詳情';
$lang["page-title_emu_setup"] = '設定 EMu 插件';
