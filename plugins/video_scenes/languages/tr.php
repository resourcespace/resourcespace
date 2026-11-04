<?php


$lang["video_scene_title"] = 'Video sahneleri';
$lang["video_scene_intro"] = 'Yükleme veya önizleme yeniden oluşturma sonrası transnetv2-pytorch ile sahneleri yerel olarak algılayın. İşlemden önce Python paketini kurun; model ağırlıkları dahildir. İsteğe bağlı bir OpenAI API anahtarı sahne açıklamalarını etkinleştirir; kolajlar ve sahne sesi daha sonra OpenAI\'ye gönderilir.';
$lang["video_scene_python"] = 'Python yürütülebilir dosyası (mutlak yol)';
$lang["video_scene_threshold"] = 'Sahne geçiş eşiği (0 ile 1 arasında)';
$lang["video_scene_api_key"] = 'OpenAI API anahtarı (isteğe bağlı)';
$lang["video_scene_model"] = 'Görüntü ve ses girişini destekleyen OpenAI Gerçek Zamanlı modeli';
$lang["video_scene_audio"] = 'Açıklamalara sahne sesini dahil et';
$lang["video_scene_audio_overlap"] = 'Her sahneden önce ve sonra ses bağlamı (saniye, 0 ile 30 arasında)';
$lang["video_scene_prompt"] = 'Sahne açıklama istemi';
$lang["video_scene_search_field"] = 'Aranabilir sahne açıklamaları alanı (isteğe bağlı)';
$lang["video_scene_prompt_help"] = 'Her sahneyi kareleri ve sesiyle tanımlamak için talimatlar. Maksimum 8.000 bayt.';
$lang["video_scene_timeout"] = 'Video başına maksimum işlem süresi (saniye)';
$lang["video_scene_invalid_config"] = 'Python yolu, eşik, model, anahtar ve işlem zaman aşımını kontrol edin.';
$lang["video_scene_time"] = 'Zaman damgası';
$lang["video_scene_thumbnail"] = 'Küçük resim';
$lang["video_scene_description"] = 'Açıklama';
$lang["video_scene_edit_description"] = '%time konumundaki sahne açıklamasını düzenle';
$lang["video_scene_invalid_description"] = 'Geçerli, en fazla 16.000 bayt uzunluğunda bir açıklama girin.';
$lang["video_scene_description_missing"] = 'Bu sahne artık mevcut değil. Kaynak görünümünü yeniden yükleyin.';
$lang["video_scene_description_save_failed"] = 'Açıklama kaydedilemedi. Tekrar deneyin veya kaynak görünümünü yeniden yükleyin.';
$lang["video_scene_description_saved"] = 'Sahne açıklaması kaydedildi.';
$lang["video_scene_no_description"] = 'Açıklama yok';
$lang["video_scene_play"] = '%time konumundaki sahneyi oynat';
$lang["video_scene_play_unavailable"] = 'Bu sahne mevcut video önizlemesinin dışında.';
$lang["video_scene_job_success"] = 'Video sahne işleme tamamlandı';
$lang["video_scene_job_failure"] = 'Video sahne işleme başarısız oldu. Ayrıntılar için iş günlüğüne bakın.';