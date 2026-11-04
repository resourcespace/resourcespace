<?php


$lang["video_scene_title"] = 'Adegan video';
$lang["video_scene_intro"] = 'Deteksi adegan secara lokal dengan transnetv2-pytorch setelah unggahan atau pembuatan ulang pratinjau. Instal paket Python sebelum memproses; bobot model sudah termasuk. Kunci API OpenAI opsional memungkinkan deskripsi adegan; kolase dan audio adegan kemudian dikirim ke OpenAI.';
$lang["video_scene_python"] = 'Eksekusi Python (jalur absolut)';
$lang["video_scene_threshold"] = 'Ambang transisi adegan (antara 0 dan 1)';
$lang["video_scene_api_key"] = 'Kunci API OpenAI (opsional)';
$lang["video_scene_model"] = 'Model OpenAI Realtime yang mendukung input gambar dan audio';
$lang["video_scene_audio"] = 'Sertakan audio adegan dalam deskripsi';
$lang["video_scene_audio_overlap"] = 'Konteks audio sebelum dan sesudah setiap adegan (detik, 0 sampai 30)';
$lang["video_scene_prompt"] = 'Prompt deskripsi adegan';
$lang["video_scene_search_field"] = 'Bidang deskripsi adegan yang dapat dicari (opsional)';
$lang["video_scene_prompt_help"] = 'Instruksi untuk mendeskripsikan setiap adegan dari bingkai dan audio. Maksimal 8.000 byte.';
$lang["video_scene_timeout"] = 'Waktu pemrosesan maksimum per video (detik)';
$lang["video_scene_invalid_config"] = 'Periksa jalur Python, ambang, model, kunci, dan waktu habis pemrosesan.';
$lang["video_scene_time"] = 'Cap waktu';
$lang["video_scene_thumbnail"] = 'Gambar mini';
$lang["video_scene_description"] = 'Deskripsi';
$lang["video_scene_edit_description"] = 'Edit deskripsi adegan pada %time';
$lang["video_scene_invalid_description"] = 'Masukkan deskripsi yang valid hingga 16.000 byte.';
$lang["video_scene_description_missing"] = 'Adegan ini tidak lagi ada. Muat ulang tampilan sumber daya.';
$lang["video_scene_description_save_failed"] = 'Deskripsi tidak dapat disimpan. Coba lagi atau muat ulang tampilan sumber daya.';
$lang["video_scene_description_saved"] = 'Deskripsi adegan disimpan.';
$lang["video_scene_no_description"] = 'Tidak ada deskripsi tersedia';
$lang["video_scene_play"] = 'Putar adegan pada %time';
$lang["video_scene_play_unavailable"] = 'Adegan ini berada di luar pratinjau video yang tersedia.';
$lang["video_scene_job_success"] = 'Pemrosesan adegan video selesai';
$lang["video_scene_job_failure"] = 'Pemrosesan adegan video gagal. Lihat log pekerjaan untuk detail.';
