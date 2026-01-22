<?php


$lang["museumplus_configuration"]='MuseumPlus Yapılandırması';
$lang["museumplus_top_menu_title"]='MuseumPlus: geçersiz ilişkiler';
$lang["museumplus_api_settings_header"]='API ayrıntıları';
$lang["museumplus_host"]='Sunucu';
$lang["museumplus_host_api"]='API Ana Bilgisayarı (yalnızca API çağrıları için; genellikle yukarıdaki ile aynı)';
$lang["museumplus_application"]='Uygulama adı';
$lang["user"]='Kullanıcı';
$lang["museumplus_api_user"]='Kullanıcı';
$lang["password"]='Şifre';
$lang["museumplus_api_pass"]='Şifre';
$lang["museumplus_RS_settings_header"]='ResourceSpace ayarları';
$lang["museumplus_mpid_field"]='MüzePlus tanımlayıcısını (MpID) saklamak için kullanılan metadata alanı';
$lang["museumplus_module_name_field"]='MpID\'nin geçerli olduğu modüllerin adını tutmak için kullanılan metadata alanı. Ayarlanmazsa, eklenti "Nesne" modülü yapılandırmasına geri dönecektir.';
$lang["museumplus_secondary_links_field"]='Diğer modüllere ikincil bağlantıları tutmak için kullanılan metadata alanı. ResourceSpace, her bağlantı için bir MuseumPlus URL\'si oluşturacaktır. Bağlantılar özel bir sözdizimi formatına sahip olacaktır: modül_adı:ID (örneğin "Object:1234")';
$lang["museumplus_object_details_title"]='MuseumPlus ayrıntıları';
$lang["museumplus_script_header"]='Betik ayarları';
$lang["museumplus_last_run_date"]='Son çalıştırma tarihi';
$lang["museumplus_enable_script"]='MuseumPlus betiğini etkinleştir';
$lang["museumplus_interval_run"]='Aşağıdaki aralıkta komut dosyasını çalıştır (örneğin, +1 gün, +2 hafta, iki hafta). Boş bırakın ve cron_copy_hitcount.php her çalıştığında çalışacaktır)';
$lang["museumplus_log_directory"]='Betik günlüklerini depolamak için dizin. Bu boş bırakılırsa veya geçersizse günlük kaydı yapılmaz.';
$lang["museumplus_integrity_check_field"]='Bütünlük kontrol alanı';
$lang["museumplus_modules_configuration_header"]='Modül yapılandırması';
$lang["museumplus_module"]='Modül';
$lang["museumplus_add_new_module"]='Yeni MuseumPlus modülü ekle';
$lang["museumplus_mplus_field_name"]='MuseumPlus alan adı';
$lang["museumplus_rs_field"]='ResourceSpace alanı';
$lang["museumplus_view_in_museumplus"]='MuseumPlus\'ta Görüntüle';
$lang["museumplus_confirm_delete_module_config"]='Bu modül yapılandırmasını silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!';
$lang["museumplus_module_setup"]='Modül kurulumu';
$lang["museumplus_module_name"]='MuseumPlus modül adı';
$lang["museumplus_mplus_id_field"]='MuseumPlus ID alan adı';
$lang["museumplus_mplus_id_field_helptxt"]='Boş bırakın, teknik kimlik \'__id\' (varsayılan) kullanılsın';
$lang["museumplus_rs_uid_field"]='ResourceSpace UID alanı';
$lang["museumplus_applicable_resource_types"]='Uygulanabilir kaynak tür(ler)i';
$lang["museumplus_field_mappings"]='MuseumPlus - ResourceSpace alan eşlemeleri';
$lang["museumplus_add_mapping"]='Eşleme ekle';
$lang["museumplus_error_bad_conn_data"]='MuseumPlus Bağlantı Verileri geçersiz';
$lang["museumplus_error_unexpected_response"]='Beklenmeyen MuseumPlus yanıt kodu alındı - %code';
$lang["museumplus_error_no_data_found"]='Bu MpID için MuseumPlus\'ta veri bulunamadı - %mpid';
$lang["museumplus_warning_script_not_completed"]='UYARI: MuseumPlus betiği \'%script_last_ran\' tarihinden beri tamamlanmadı.
Bu uyarıyı yalnızca daha sonra başarılı bir betik tamamlanması bildirimi aldıysanız güvenle göz ardı edebilirsiniz.';
$lang["museumplus_error_script_failed"]='MuseumPlus betiği, bir işlem kilidi olduğu için çalıştırılamadı. Bu, önceki çalıştırmanın tamamlanmadığını gösterir.
Başarısız bir çalıştırmadan sonra kilidi temizlemeniz gerekiyorsa, betiği şu şekilde çalıştırın:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='$php_path yapılandırma seçeneği, cron işlevselliğinin başarılı bir şekilde çalışabilmesi için AYARLANMALIDIR!';
$lang["museumplus_error_not_deleted_module_conf"]='İstenen modül yapılandırması silinemiyor.';
$lang["museumplus_error_unknown_type_saved_config"]='\'museumplus_modules_saved_config\' bilinmeyen bir türde!';
$lang["museumplus_error_invalid_association"]='Geçersiz modül(ler) ilişkisi. Lütfen doğru Modül ve/veya Kayıt Kimliğinin girildiğinden emin olun!';
$lang["museumplus_id_returns_multiple_records"]='Birden fazla kayıt bulundu - lütfen teknik kimliği girin';
$lang["museumplus_error_module_no_field_maps"]='MuseumPlus\'tan veri senkronize edilemiyor. Sebep: \'%name\' modülünde alan eşlemeleri yapılandırılmamış.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='[Gelişmiş] Kaynak meta verilerinin MuseumPlus\'tan REST API\'si (MpRIA) kullanılarak çıkarılmasına izin verir.';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Nesne Ayrıntıları';
$lang["page-title_museumplus_setup_module"] = 'MüzePlus Modülünü Kurun';
$lang["page-title_museumplus_setup"] = 'MuseumPlus Eklentisini Kurun';