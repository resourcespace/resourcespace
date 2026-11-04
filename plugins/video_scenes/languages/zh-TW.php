<?php


$lang["video_scene_title"] = '影片場景';
$lang["video_scene_intro"] = '上傳或重新預覽後，使用 transnetv2-pytorch 在本地偵測場景。處理前請先安裝 Python 套件；模型權重已包含其中。可選的 OpenAI API 金鑰可啟用場景描述；拼貼圖和場景音訊將傳送至 OpenAI。';
$lang["video_scene_python"] = 'Python 執行檔（絕對路徑）';
$lang["video_scene_threshold"] = '場景轉換閾值（介於 0 到 1 之間）';
$lang["video_scene_api_key"] = 'OpenAI API 金鑰（可選）';
$lang["video_scene_model"] = '支援影像與音訊輸入的 OpenAI 即時模型';
$lang["video_scene_audio"] = '在描述中包含場景音訊';
$lang["video_scene_audio_overlap"] = '每個場景前後的音訊上下文（秒，0 到 30）';
$lang["video_scene_prompt"] = '場景描述提示詞';
$lang["video_scene_search_field"] = '可搜尋的場景描述欄位（可選）';
$lang["video_scene_prompt_help"] = '從每個場景的影格與音訊描述場景的指示。最大 8,000 位元組。';
$lang["video_scene_timeout"] = '每部影片的最大處理時間（秒）';
$lang["video_scene_invalid_config"] = '請檢查 Python 路徑、閾值、模型、金鑰及處理逾時設定。';
$lang["video_scene_time"] = '時間戳記';
$lang["video_scene_thumbnail"] = '縮圖';
$lang["video_scene_description"] = '描述';
$lang["video_scene_edit_description"] = '編輯 %time 的場景描述';
$lang["video_scene_invalid_description"] = '請輸入最多16,000字節的有效描述。';
$lang["video_scene_description_missing"] = '此場景已不存在。請重新載入資源檢視。';
$lang["video_scene_description_save_failed"] = '無法儲存描述。請重試或重新載入資源檢視。';
$lang["video_scene_description_saved"] = '場景描述已儲存。';
$lang["video_scene_no_description"] = '無可用描述';
$lang["video_scene_play"] = '播放 %time 的場景';
$lang["video_scene_play_unavailable"] = '此場景超出可用的影片預覽範圍。';
$lang["video_scene_job_success"] = '影片場景處理完成';
$lang["video_scene_job_failure"] = '影片場景處理失敗。詳情請參閱工作日誌。';