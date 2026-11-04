<?php


$lang["video_scene_title"] = '视频场景';
$lang["video_scene_intro"] = '上传或预览重建后，使用 transnetv2-pytorch 本地检测场景。处理前请安装 Python 包；模型权重已包含。可选的 OpenAI API 密钥可启用场景描述；拼贴图和场景音频随后发送至 OpenAI。';
$lang["video_scene_python"] = 'Python 可执行文件（绝对路径）';
$lang["video_scene_threshold"] = '场景切换阈值（介于 0 和 1 之间）';
$lang["video_scene_api_key"] = 'OpenAI API 密钥（可选）';
$lang["video_scene_model"] = '支持图像和音频输入的 OpenAI 实时模型';
$lang["video_scene_audio"] = '在描述中包含场景音频';
$lang["video_scene_audio_overlap"] = '每个场景前后音频上下文（秒，0 到 30）';
$lang["video_scene_prompt"] = '场景描述提示';
$lang["video_scene_search_field"] = '可搜索的场景描述字段（可选）';
$lang["video_scene_prompt_help"] = '描述每个场景的帧和音频的说明。最大 8,000 字节。';
$lang["video_scene_timeout"] = '每个视频的最大处理时间（秒）';
$lang["video_scene_invalid_config"] = '请检查 Python 路径、阈值、模型、密钥和处理超时。';
$lang["video_scene_time"] = '时间戳';
$lang["video_scene_thumbnail"] = '缩略图';
$lang["video_scene_description"] = '描述';
$lang["video_scene_edit_description"] = '编辑 %time 的场景描述';
$lang["video_scene_invalid_description"] = '请输入最多16,000字节的有效描述。';
$lang["video_scene_description_missing"] = '此场景已不存在。请重新加载资源视图。';
$lang["video_scene_description_save_failed"] = '描述保存失败。请重试或重新加载资源视图。';
$lang["video_scene_description_saved"] = '场景描述已保存。';
$lang["video_scene_no_description"] = '无可用描述';
$lang["video_scene_play"] = '播放 %time 的场景';
$lang["video_scene_play_unavailable"] = '此场景超出可用视频预览范围。';
$lang["video_scene_job_success"] = '视频场景处理完成';
$lang["video_scene_job_failure"] = '视频场景处理失败。详情请查看作业日志。';