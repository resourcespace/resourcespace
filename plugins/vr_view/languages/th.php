<?php


$lang["vr_view_configuration"]='การตั้งค่า Google VR View';
$lang["vr_view_google_hosted"]='ใช้ไลบรารี JavaScript VR View ที่โฮสต์โดย Google หรือไม่?';
$lang["vr_view_restypes"]='ประเภททรัพยากรที่จะแสดงโดยใช้ VR View';
$lang["vr_view_autopan"]='เปิดใช้งาน Autopan';
$lang["vr_view_vr_mode_off"]='ปิดการใช้งานปุ่มโหมด VR';
$lang["vr_view_condition"]='เงื่อนไขการดู VR';
$lang["vr_view_condition_detail"]='หากมีการเลือกฟิลด์ด้านล่าง ค่าที่ตั้งไว้สำหรับฟิลด์นั้นสามารถตรวจสอบและใช้เพื่อกำหนดว่าจะแสดงตัวอย่าง VR View หรือไม่ ซึ่งช่วยให้คุณสามารถกำหนดว่าจะใช้ปลั๊กอินตามข้อมูล EXIF ที่ฝังอยู่โดยการแมพฟิลด์เมตาดาต้า หากไม่ตั้งค่านี้ ตัวอย่างจะพยายามแสดงเสมอ แม้ว่าฟอร์แมตจะไม่เข้ากัน <br /><br />หมายเหตุ Google ต้องการภาพและวิดีโอที่มีรูปแบบ equirectangular-panoramic<br />การกำหนดค่าที่แนะนำคือการแมพฟิลด์ exiftool \'ProjectionType\' ไปยังฟิลด์ที่เรียกว่า \'Projection Type\' และใช้ฟิลด์นั้น';
$lang["vr_view_projection_value"]='ค่าที่จำเป็นสำหรับการเปิดใช้งาน VR View';
$lang["vr_view_additional_options"]='ตัวเลือกเพิ่มเติม';
$lang["vr_view_additional_options_detail"]='การตั้งค่าดังต่อไปนี้ช่วยให้คุณควบคุมปลั๊กอินต่อทรัพยากรโดยการแมพฟิลด์เมตาดาต้าเพื่อใช้ควบคุมพารามิเตอร์ VR View<br />ดู <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> สำหรับข้อมูลที่ละเอียดมากขึ้น';
$lang["vr_view_stereo_field"]='ฟิลด์ที่ใช้ในการกำหนดว่า รูปภาพ/วิดีโอ เป็นสเตอริโอหรือไม่ (ไม่บังคับ, ค่าเริ่มต้นเป็นเท็จหากไม่ได้ตั้งค่า)';
$lang["vr_view_stereo_value"]='ค่าที่จะตรวจสอบ หากพบจะตั้งค่า stereo เป็น true';
$lang["vr_view_yaw_only_field"]='ฟิลด์ที่ใช้ในการกำหนดว่าควรป้องกันการหมุน/เอียงหรือไม่ (ไม่บังคับ ค่าเริ่มต้นเป็น false หากไม่ได้ตั้งค่า)';
$lang["vr_view_yaw_only_value"]='ค่าที่จะตรวจสอบ หากพบตัวเลือก is_yaw_only จะถูกตั้งค่าเป็นจริง';
$lang["vr_view_orig_image"]='ใช้ไฟล์ทรัพยากรต้นฉบับเป็นแหล่งสำหรับการแสดงตัวอย่างภาพหรือไม่?';
$lang["vr_view_orig_video"]='ใช้ไฟล์ทรัพยากรต้นฉบับเป็นแหล่งสำหรับตัวอย่างวิดีโอหรือไม่?';
$lang["vr_view_js_url"]='URL ไปยังไลบรารี javascript ของ VR View (จำเป็นเฉพาะเมื่อข้างต้นเป็นเท็จ) หากอยู่ในเซิร์ฟเวอร์เดียวกันให้ใช้เส้นทางสัมพัทธ์ เช่น /vrview/build/vrview.js';
$lang["vr_view_projection_field"]='ฟิลด์ประเภทการฉายภาพ VR View';
$lang["page-title_vr_view_download"] = 'การดู VR';
$lang["page-title_vr_view_setup"] = 'ตั้งค่า VR View Plugin';