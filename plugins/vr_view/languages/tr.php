<?php


$lang["vr_view_configuration"]='Google VR View yapılandırması';
$lang["vr_view_google_hosted"]='Google barındırılan VR View javascript kütüphanesini kullan?';
$lang["vr_view_js_url"]='VR Görünüm javascript kütüphanesinin URL\'si (yukarıdaki yanlışsa gereklidir). Sunucuya yerel ise göreli yolu kullanın, örn. /vrview/build/vrview.js';
$lang["vr_view_restypes"]='VR Görünümünde görüntülenecek kaynak türleri';
$lang["vr_view_autopan"]='Otomatik Kaydırmayı Etkinleştir';
$lang["vr_view_vr_mode_off"]='VR modu düğmesini devre dışı bırak';
$lang["vr_view_condition"]='VR Görüntüleme koşulu';
$lang["vr_view_condition_detail"]='Eğer aşağıda bir alan seçilirse, alan için ayarlanan değer kontrol edilebilir ve VR Görünüm önizlemesinin gösterilip gösterilmeyeceğini belirlemek için kullanılabilir. Bu, eklentiyi gömülü EXIF verilerine dayalı olarak kullanıp kullanmayacağınızı, meta veri alanlarını eşleştirerek belirlemenizi sağlar. Bu ayar yapılmazsa, format uyumsuz olsa bile önizleme her zaman denenecektir <br /><br />NB Google, equirectangular-panoramik formatlı görüntüler ve videolar gerektirir.<br />Önerilen yapılandırma, exiftool alanı \'ProjectionType\'ı \'Projection Type\' adlı bir alana eşlemek ve bu alanı kullanmaktır.';
$lang["vr_view_projection_field"]='VR Görüntüleme ProjeksiyonTürü alanı';
$lang["vr_view_projection_value"]='VR Görünümünün etkinleştirilmesi için gerekli değer';
$lang["vr_view_additional_options"]='Ek seçenekler';
$lang["vr_view_additional_options_detail"]='Aşağıdaki, VR Görünüm parametrelerini kontrol etmek için kullanılacak meta veri alanlarını eşleştirerek eklentiyi kaynak başına kontrol etmenizi sağlar<br />Daha ayrıntılı bilgi için <a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a> adresine bakın';
$lang["vr_view_stereo_field"]='Görüntü/video\'nun stereo olup olmadığını belirlemek için kullanılan alan (isteğe bağlı, ayarlanmazsa varsayılan olarak yanlış)';
$lang["vr_view_stereo_value"]='Kontrol edilecek değer. Bulunursa stereo doğru olarak ayarlanacak';
$lang["vr_view_yaw_only_field"]='Alan, yuvarlanma/eğimin önlenip önlenmeyeceğini belirlemek için kullanılır (isteğe bağlı, ayarlanmazsa varsayılan olarak yanlış)';
$lang["vr_view_yaw_only_value"]='Kontrol edilecek değer. Bulunursa, is_yaw_only seçeneği true olarak ayarlanacaktır';
$lang["vr_view_orig_image"]='Önizleme görüntüsü için orijinal kaynak dosyasını kaynak olarak kullan?';
$lang["vr_view_orig_video"]='Video önizlemesi için orijinal kaynak dosyasını kaynak olarak kullan?';
$lang["plugin-vr_view-title"]='VR Görünümü';
$lang["plugin-vr_view-desc"]='Google VR Görünümü - 360 derece görüntü ve video önizlemeleri (equirectangular format)';
$lang["page-title_vr_view_download"] = 'VR Görünümü';
$lang["page-title_vr_view_setup"] = 'VR Görünüm Eklentisini Kurun';