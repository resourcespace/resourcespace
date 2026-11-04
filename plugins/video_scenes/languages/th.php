<?php


$lang["video_scene_title"] = 'ฉากวิดีโอ';
$lang["video_scene_intro"] = 'ตรวจจับฉากในเครื่องด้วย transnetv2-pytorch หลังจากอัปโหลดหรือสร้างตัวอย่างใหม่ ติดตั้งแพ็กเกจ Python ก่อนการประมวลผล; น้ำหนักโมเดลถูกรวมไว้แล้ว คีย์ API ของ OpenAI เป็นทางเลือกเพื่อเปิดใช้งานคำอธิบายฉาก; ภาพรวมและเสียงฉากจะถูกส่งไปยัง OpenAI';
$lang["video_scene_python"] = 'ไฟล์ปฏิบัติการ Python (เส้นทางแบบสมบูรณ์)';
$lang["video_scene_threshold"] = 'เกณฑ์การเปลี่ยนฉาก (ระหว่าง 0 ถึง 1)';
$lang["video_scene_api_key"] = 'คีย์ API ของ OpenAI (ไม่บังคับ)';
$lang["video_scene_model"] = 'โมเดล OpenAI Realtime ที่รองรับการป้อนภาพและเสียง';
$lang["video_scene_audio"] = 'รวมเสียงฉากในคำอธิบาย';
$lang["video_scene_audio_overlap"] = 'บริบทเสียงก่อนและหลังแต่ละฉาก (วินาที, 0 ถึง 30)';
$lang["video_scene_prompt"] = 'คำสั่งอธิบายฉาก';
$lang["video_scene_search_field"] = 'ฟิลด์คำอธิบายฉากที่ค้นหาได้ (ไม่บังคับ)';
$lang["video_scene_prompt_help"] = 'คำแนะนำสำหรับการอธิบายแต่ละฉากจากเฟรมและเสียงของมัน ขนาดสูงสุด 8,000 ไบต์';
$lang["video_scene_timeout"] = 'เวลาประมวลผลสูงสุดต่อวิดีโอ (วินาที)';
$lang["video_scene_invalid_config"] = 'ตรวจสอบเส้นทาง Python, เกณฑ์, โมเดล, คีย์ และเวลาหมดเวลาการประมวลผล';
$lang["video_scene_time"] = 'เวลาประทับ';
$lang["video_scene_thumbnail"] = 'ภาพขนาดย่อ';
$lang["video_scene_description"] = 'คำอธิบาย';
$lang["video_scene_edit_description"] = 'แก้ไขคำอธิบายฉากที่ %time';
$lang["video_scene_invalid_description"] = 'กรุณาใส่คำอธิบายที่ถูกต้องไม่เกิน 16,000 ไบต์';
$lang["video_scene_description_missing"] = 'ฉากนี้ไม่มีอยู่แล้ว กรุณาโหลดหน้าทรัพยากรใหม่';
$lang["video_scene_description_save_failed"] = 'ไม่สามารถบันทึกคำอธิบายได้ กรุณาลองใหม่หรือลองโหลดหน้าทรัพยากรใหม่';
$lang["video_scene_description_saved"] = 'บันทึกคำอธิบายฉากเรียบร้อยแล้ว';
$lang["video_scene_no_description"] = 'ไม่มีคำอธิบาย';
$lang["video_scene_play"] = 'เล่นฉากที่ %time';
$lang["video_scene_play_unavailable"] = 'ฉากนี้อยู่นอกช่วงตัวอย่างวิดีโอที่มีให้';
$lang["video_scene_job_success"] = 'การประมวลผลฉากวิดีโอเสร็จสมบูรณ์';
$lang["video_scene_job_failure"] = 'การประมวลผลฉากวิดีโอล้มเหลว ดูบันทึกงานสำหรับรายละเอียด';