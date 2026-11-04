<?php


$lang["video_scene_title"] = 'مشاهد الفيديو';
$lang["video_scene_intro"] = 'اكتشاف المشاهد محليًا باستخدام transnetv2-pytorch بعد التحميل أو إعادة إنشاء المعاينة. قم بتثبيت حزمة Python قبل المعالجة؛ أوزان النموذج مرفقة. مفتاح API اختياري من OpenAI يتيح وصف المشاهد؛ يتم بعد ذلك إرسال الكولاجات وصوت المشهد إلى OpenAI.';
$lang["video_scene_python"] = 'تنفيذ Python (المسار المطلق)';
$lang["video_scene_threshold"] = 'عتبة انتقال المشهد (بين 0 و 1)';
$lang["video_scene_api_key"] = 'مفتاح API الخاص بـ OpenAI (اختياري)';
$lang["video_scene_model"] = 'نموذج OpenAI Realtime يدعم إدخال الصورة والصوت';
$lang["video_scene_audio"] = 'تضمين صوت المشهد في الأوصاف';
$lang["video_scene_audio_overlap"] = 'سياق الصوت قبل وبعد كل مشهد (بالثواني، من 0 إلى 30)';
$lang["video_scene_prompt"] = 'موجه وصف المشهد';
$lang["video_scene_search_field"] = 'حقل أوصاف المشاهد القابل للبحث (اختياري)';
$lang["video_scene_prompt_help"] = 'تعليمات لوصف كل مشهد من إطاراته وصوته. الحد الأقصى 8000 بايت.';
$lang["video_scene_timeout"] = 'الحد الأقصى لوقت المعالجة لكل فيديو (بالثواني)';
$lang["video_scene_invalid_config"] = 'تحقق من مسار Python، العتبة، النموذج، المفتاح ووقت المعالجة.';
$lang["video_scene_time"] = 'الطابع الزمني';
$lang["video_scene_thumbnail"] = 'الصورة المصغرة';
$lang["video_scene_description"] = 'الوصف';
$lang["video_scene_edit_description"] = 'تعديل وصف المشهد عند %time';
$lang["video_scene_invalid_description"] = 'أدخل وصفًا صالحًا يصل إلى 16,000 بايت.';
$lang["video_scene_description_missing"] = 'هذا المشهد لم يعد موجودًا. أعد تحميل عرض المورد.';
$lang["video_scene_description_save_failed"] = 'تعذر حفظ الوصف. حاول مرة أخرى أو أعد تحميل عرض المورد.';
$lang["video_scene_description_saved"] = 'تم حفظ وصف المشهد.';
$lang["video_scene_no_description"] = 'لا يوجد وصف متاح';
$lang["video_scene_play"] = 'تشغيل المشهد عند %time';
$lang["video_scene_play_unavailable"] = 'هذا المشهد خارج معاينة الفيديو المتاحة.';
$lang["video_scene_job_success"] = 'اكتمل معالجة مشهد الفيديو';
$lang["video_scene_job_failure"] = 'فشل معالجة مشهد الفيديو. راجع سجل الوظيفة للتفاصيل.';