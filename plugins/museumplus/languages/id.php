<?php


$lang["museumplus_configuration"]='Konfigurasi MuseumPlus';
$lang["museumplus_top_menu_title"]='MuseumPlus: asosiasi tidak valid';
$lang["museumplus_api_settings_header"]='Rincian API';
$lang["museumplus_host"]='Menginap (for verb) / Host (for noun)';
$lang["museumplus_host_api"]='Host API (hanya untuk panggilan API; biasanya sama dengan yang di atas)';
$lang["museumplus_application"]='Nama aplikasi';
$lang["user"]='Pengguna';
$lang["museumplus_api_user"]='Pengguna';
$lang["password"]='Kata sandi';
$lang["museumplus_api_pass"]='Kata sandi';
$lang["museumplus_RS_settings_header"]='Pengaturan ResourceSpace';
$lang["museumplus_mpid_field"]='Bidang metadata yang digunakan untuk menyimpan pengenal MuseumPlus (MpID)';
$lang["museumplus_module_name_field"]='Bidang metadata yang digunakan untuk menyimpan nama modul yang sesuai dengan MpID. Jika tidak diatur, plugin akan kembali ke konfigurasi modul "Objek".';
$lang["museumplus_secondary_links_field"]='Bidang metadata yang digunakan untuk menyimpan tautan sekunder ke modul lain. ResourceSpace akan menghasilkan URL MuseumPlus untuk setiap tautan. Tautan akan memiliki format sintaks khusus: nama_modul:ID (misalnya "Object:1234")';
$lang["museumplus_object_details_title"]='Rincian MuseumPlus';
$lang["museumplus_script_header"]='Pengaturan skrip';
$lang["museumplus_last_run_date"]='Terakhir kali skrip dijalankan';
$lang["museumplus_enable_script"]='Aktifkan skrip MuseumPlus';
$lang["museumplus_interval_run"]='Jalankan skrip pada interval berikut (misalnya +1 hari, +2 minggu, dua minggu). Biarkan kosong dan skrip akan dijalankan setiap kali cron_copy_hitcount.php dijalankan';
$lang["museumplus_log_directory"]='Direktori untuk menyimpan log skrip. Jika ini dikosongkan atau tidak valid, maka tidak akan ada pencatatan log.';
$lang["museumplus_integrity_check_field"]='Memeriksa integritas bidang';
$lang["museumplus_modules_configuration_header"]='Konfigurasi Modul';
$lang["museumplus_module"]='Modul';
$lang["museumplus_add_new_module"]='Tambahkan modul MuseumPlus baru';
$lang["museumplus_mplus_field_name"]='Nama bidang MuseumPlus';
$lang["museumplus_rs_field"]='Kolom ResourceSpace';
$lang["museumplus_view_in_museumplus"]='Lihat di MuseumPlus';
$lang["museumplus_confirm_delete_module_config"]='Apakah Anda yakin ingin menghapus konfigurasi modul ini? Tindakan ini tidak dapat dibatalkan!';
$lang["museumplus_module_setup"]='Penyiapan Modul';
$lang["museumplus_module_name"]='Nama modul MuseumPlus';
$lang["museumplus_mplus_id_field"]='Nama bidang ID MuseumPlus';
$lang["museumplus_mplus_id_field_helptxt"]='Biarkan kosong untuk menggunakan ID teknis \'__id\' (default)';
$lang["museumplus_rs_uid_field"]='Kolom UID ResourceSpace';
$lang["museumplus_applicable_resource_types"]='Jenis sumber daya yang berlaku';
$lang["museumplus_field_mappings"]='Pemetaan bidang MuseumPlus - ResourceSpace';
$lang["museumplus_add_mapping"]='Tambahkan pemetaan';
$lang["museumplus_error_bad_conn_data"]='Data Koneksi MuseumPlus tidak valid';
$lang["museumplus_error_unexpected_response"]='Kode respons MuseumPlus yang tidak terduga diterima - %code';
$lang["museumplus_error_no_data_found"]='Tidak ditemukan data di MuseumPlus untuk MpID ini - %mpid';
$lang["museumplus_warning_script_not_completed"]='PERINGATAN: Skrip MuseumPlus belum selesai sejak \'%script_last_ran\'.
Anda dapat mengabaikan peringatan ini hanya jika Anda kemudian menerima pemberitahuan bahwa skrip telah selesai dengan sukses.';
$lang["museumplus_error_script_failed"]='Skrip MuseumPlus gagal dijalankan karena terdapat kunci proses yang sedang berjalan. Hal ini menunjukkan bahwa proses sebelumnya tidak selesai
Jika Anda perlu membersihkan kunci setelah proses gagal, jalankan skrip sebagai berikut:
php museumplus_script.php --clear-lock';
$lang["museumplus_php_utility_not_found"]='Opsi konfigurasi $php_path HARUS diatur agar fungsi cron dapat berjalan dengan sukses!';
$lang["museumplus_error_not_deleted_module_conf"]='Tidak dapat menghapus konfigurasi modul yang diminta.';
$lang["museumplus_error_unknown_type_saved_config"]='"museumplus_modules_saved_config" adalah tipe yang tidak diketahui!';
$lang["museumplus_error_invalid_association"]='Asosiasi modul yang tidak valid. Pastikan bahwa Modul dan/atau ID Rekaman yang benar telah dimasukkan!';
$lang["museumplus_id_returns_multiple_records"]='Beberapa catatan ditemukan - silakan masukkan ID teknisnya';
$lang["museumplus_error_module_no_field_maps"]='Tidak dapat menyinkronkan data dari MuseumPlus. Alasan: modul \'%name\' tidak memiliki pemetaan bidang yang dikonfigurasi.';
$lang["plugin-museumplus-title"]='MuseumPlus';
$lang["plugin-museumplus-desc"]='Memungkinkan metadata sumber daya diekstraksi dari MuseumPlus menggunakan REST API-nya (MpRIA).';
$lang["page-title_museumplus_museumplus_object_details"] = 'MuseumPlus Detail Objek';
$lang["page-title_museumplus_setup_module"] = 'Setup MuseumPlus Module';
$lang["page-title_museumplus_setup"] = 'Setup Plugin MuseumPlus';