<?php


$lang["museumplus_configuration"]='MuseumPlus 配置';
$lang["museumplus_top_menu_title"]='MuseumPlus：无效的关联。';
$lang["museumplus_api_settings_header"]='API细节';
$lang["museumplus_host"]='主机';
$lang["museumplus_host_api"]='API主机（仅用于API调用；通常与上面相同）';
$lang["museumplus_application"]='应用程序名称';
$lang["user"]='用户';
$lang["museumplus_api_user"]='用户';
$lang["password"]='密码';
$lang["museumplus_api_pass"]='密码';
$lang["museumplus_RS_settings_header"]='ResourceSpace设置';
$lang["museumplus_mpid_field"]='用于存储MuseumPlus标识符（MpID）的元数据字段。';
$lang["museumplus_module_name_field"]='用于保存MpID有效的模块名称的元数据字段。如果未设置，则插件将回退到“Object”模块配置。';
$lang["museumplus_secondary_links_field"]='用于保存指向其他模块的次要链接的元数据字段。ResourceSpace将为每个链接生成一个MuseumPlus URL。链接将具有特殊的语法格式：module_name:ID（例如，“Object:1234”）。';
$lang["museumplus_object_details_title"]='MuseumPlus 详细信息';
$lang["museumplus_script_header"]='脚本设置';
$lang["museumplus_last_run_date"]='脚本上次运行时间';
$lang["museumplus_enable_script"]='启用MuseumPlus脚本';
$lang["museumplus_log_directory"]='存储脚本日志的目录。如果留空或无效，则不会发生记录日志。';
$lang["museumplus_integrity_check_field"]='完整性检查字段';
$lang["museumplus_modules_configuration_header"]='模块配置';
$lang["museumplus_module"]='模块';
$lang["museumplus_add_new_module"]='添加新的MuseumPlus模块';
$lang["museumplus_mplus_field_name"]='MuseumPlus字段名称';
$lang["museumplus_rs_field"]='ResourceSpace字段';
$lang["museumplus_view_in_museumplus"]='在MuseumPlus中查看';
$lang["museumplus_confirm_delete_module_config"]='您确定要删除此模块配置吗？此操作无法撤销！';
$lang["museumplus_module_setup"]='模块设置';
$lang["museumplus_module_name"]='MuseumPlus 模块名称';
$lang["museumplus_mplus_id_field"]='MuseumPlus ID字段名称';
$lang["museumplus_mplus_id_field_helptxt"]='留空以使用技术ID \'__id\'（默认）。';
$lang["museumplus_rs_uid_field"]='ResourceSpace UID字段';
$lang["museumplus_applicable_resource_types"]='适用资源类型';
$lang["museumplus_field_mappings"]='MuseumPlus - ResourceSpace字段映射';
$lang["museumplus_add_mapping"]='添加映射';
$lang["museumplus_error_bad_conn_data"]='MuseumPlus连接数据无效。';
$lang["museumplus_error_unexpected_response"]='收到了意外的 MuseumPlus 响应代码 - %code';
$lang["museumplus_error_no_data_found"]='在MuseumPlus中没有找到与此MpID（%mpid）相关的数据。';
$lang["museumplus_warning_script_not_completed"]='警告：MuseumPlus脚本自\'%script_last_ran\'以来尚未完成。
只有在随后收到成功脚本完成的通知时，您才可以安全地忽略此警告。';
$lang["museumplus_error_script_failed"]='MuseumPlus脚本无法运行，因为有一个进程锁定。这表明上一次运行没有完成。
如果需要在运行失败后清除锁定，请按以下方式运行脚本：
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='$php_path配置选项必须设置，以便cron功能成功运行！';
$lang["museumplus_error_not_deleted_module_conf"]='无法删除所请求的模块配置。';
$lang["museumplus_error_unknown_type_saved_config"]='\'museumplus_modules_saved_config\' 是未知类型！';
$lang["museumplus_error_invalid_association"]='无效的模块关联。请确保正确的模块和/或记录ID已输入！';
$lang["museumplus_id_returns_multiple_records"]='找到多个记录 - 请输入技术ID。';
$lang["museumplus_error_module_no_field_maps"]='无法从MuseumPlus同步数据。原因：模块“%name”未配置字段映射。';
$lang["museumplus_interval_run"]='在以下间隔运行脚本（例如：+1天，+2周，双周）。留空则每次 cron_copy_hitcount.php 运行时都会执行';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='[高级] 允许使用其 REST API (MpRIA) 从 MuseumPlus 提取资源元数据。';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus 对象详情';
$lang["page-title_museumplus_setup_module"] = '设置 MuseumPlus 模块';
$lang["page-title_museumplus_setup"] = '设置 MuseumPlus 插件';