<?php


$lang["video_scene_title"] = '비디오 장면';
$lang["video_scene_intro"] = '업로드 또는 미리보기 재생성 후 transnetv2-pytorch로 로컬에서 장면을 감지합니다. 처리 전에 Python 패키지를 설치하세요; 모델 가중치가 포함되어 있습니다. 선택적 OpenAI API 키를 사용하면 장면 설명이 가능하며, 콜라주와 장면 오디오가 OpenAI로 전송됩니다.';
$lang["video_scene_python"] = 'Python 실행 파일 (절대 경로)';
$lang["video_scene_threshold"] = '장면 전환 임계값 (0과 1 사이)';
$lang["video_scene_api_key"] = 'OpenAI API 키 (선택 사항)';
$lang["video_scene_model"] = '이미지 및 오디오 입력을 지원하는 OpenAI 실시간 모델';
$lang["video_scene_audio"] = '설명에 장면 오디오 포함';
$lang["video_scene_audio_overlap"] = '각 장면 전후 오디오 컨텍스트 (초, 0~30)';
$lang["video_scene_prompt"] = '장면 설명 프롬프트';
$lang["video_scene_search_field"] = '검색 가능한 장면 설명 필드 (선택 사항)';
$lang["video_scene_prompt_help"] = '프레임과 오디오를 기반으로 각 장면을 설명하는 지침입니다. 최대 8,000 바이트.';
$lang["video_scene_timeout"] = '비디오당 최대 처리 시간 (초)';
$lang["video_scene_invalid_config"] = 'Python 경로, 임계값, 모델, 키 및 처리 시간 초과를 확인하세요.';
$lang["video_scene_time"] = '타임스탬프';
$lang["video_scene_thumbnail"] = '썸네일';
$lang["video_scene_description"] = '설명';
$lang["video_scene_edit_description"] = '%time 에 장면 설명 편집';
$lang["video_scene_invalid_description"] = '최대 16,000 바이트까지 유효한 설명을 입력하세요.';
$lang["video_scene_description_missing"] = '이 장면은 더 이상 존재하지 않습니다. 리소스 보기를 새로 고치세요.';
$lang["video_scene_description_save_failed"] = '설명을 저장할 수 없습니다. 다시 시도하거나 리소스 보기를 새로 고치세요.';
$lang["video_scene_description_saved"] = '장면 설명이 저장되었습니다.';
$lang["video_scene_no_description"] = '설명이 없습니다';
$lang["video_scene_play"] = '%time 에 장면 재생';
$lang["video_scene_play_unavailable"] = '이 장면은 사용 가능한 비디오 미리보기 범위를 벗어났습니다.';
$lang["video_scene_job_success"] = '비디오 장면 처리가 완료되었습니다';
$lang["video_scene_job_failure"] = '비디오 장면 처리에 실패했습니다. 자세한 내용은 작업 로그를 확인하세요.';