<?php


$lang["video_scene_title"] = 'صحنه‌های ویدئو';
$lang["video_scene_intro"] = 'شناسایی صحنه‌ها به صورت محلی با transnetv2-pytorch پس از بارگذاری یا بازسازی پیش‌نمایش. قبل از پردازش بسته پایتون را نصب کنید؛ وزن‌های مدل شامل شده‌اند. کلید API اختیاری OpenAI امکان توصیف صحنه‌ها را فراهم می‌کند؛ سپس کلاژها و صدای صحنه به OpenAI ارسال می‌شوند.';
$lang["video_scene_python"] = 'اجرای پایتون (مسیر مطلق)';
$lang["video_scene_threshold"] = 'آستانه انتقال صحنه (بین ۰ و ۱)';
$lang["video_scene_api_key"] = 'کلید API OpenAI (اختیاری)';
$lang["video_scene_model"] = 'مدل بلادرنگ OpenAI که ورودی تصویر و صدا را پشتیبانی می‌کند';
$lang["video_scene_audio"] = 'شامل کردن صدای صحنه در توصیفات';
$lang["video_scene_audio_overlap"] = 'زمینه صوتی قبل و بعد از هر صحنه (ثانیه، ۰ تا ۳۰)';
$lang["video_scene_prompt"] = 'دستور توصیف صحنه';
$lang["video_scene_search_field"] = 'فیلد توصیفات قابل جستجو برای صحنه‌ها (اختیاری)';
$lang["video_scene_prompt_help"] = 'دستورالعمل‌هایی برای توصیف هر صحنه از فریم‌ها و صدای آن. حداکثر ۸۰۰۰ بایت.';
$lang["video_scene_timeout"] = 'حداکثر زمان پردازش برای هر ویدئو (ثانیه)';
$lang["video_scene_invalid_config"] = 'مسیر پایتون، آستانه، مدل، کلید و زمان پردازش را بررسی کنید.';
$lang["video_scene_time"] = 'زمان‌نشانه';
$lang["video_scene_thumbnail"] = 'تصویر بندانگشتی';
$lang["video_scene_description"] = 'توضیحات';
$lang["video_scene_edit_description"] = 'ویرایش توضیحات صحنه در %time';
$lang["video_scene_invalid_description"] = 'یک توضیح معتبر تا سقف ۱۶٬۰۰۰ بایت وارد کنید.';
$lang["video_scene_description_missing"] = 'این صحنه دیگر وجود ندارد. نمای منبع را دوباره بارگذاری کنید.';
$lang["video_scene_description_save_failed"] = 'امکان ذخیره توضیحات وجود ندارد. دوباره تلاش کنید یا نمای منبع را دوباره بارگذاری کنید.';
$lang["video_scene_description_saved"] = 'توضیحات صحنه ذخیره شد.';
$lang["video_scene_no_description"] = 'توضیحی موجود نیست';
$lang["video_scene_play"] = 'پخش صحنه در %time';
$lang["video_scene_play_unavailable"] = 'این صحنه خارج از پیش‌نمایش ویدیوی موجود است.';
$lang["video_scene_job_success"] = 'پردازش صحنه ویدیو با موفقیت انجام شد';
$lang["video_scene_job_failure"] = 'پردازش صحنه ویدیو ناموفق بود. برای جزئیات به گزارش کار مراجعه کنید.';