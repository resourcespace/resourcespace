<?php


$lang["video_scene_title"] = 'ビデオシーン';
$lang["video_scene_intro"] = 'アップロード後またはプレビュー再作成時にtransnetv2-pytorchでローカルにシーンを検出します。処理前にPythonパッケージをインストールしてください。モデルの重みは含まれています。オプションのOpenAI APIキーを使用するとシーンの説明が可能になり、コラージュやシーンの音声がOpenAIに送信されます。';
$lang["video_scene_python"] = 'Python実行ファイル（絶対パス）';
$lang["video_scene_threshold"] = 'シーン遷移の閾値（0から1の間）';
$lang["video_scene_api_key"] = 'OpenAI APIキー（オプション）';
$lang["video_scene_model"] = '画像と音声入力をサポートするOpenAIリアルタイムモデル';
$lang["video_scene_audio"] = '説明にシーンの音声を含める';
$lang["video_scene_audio_overlap"] = '各シーンの前後の音声コンテキスト（秒、0から30）';
$lang["video_scene_prompt"] = 'シーン説明のプロンプト';
$lang["video_scene_search_field"] = '検索可能なシーン説明フィールド（オプション）';
$lang["video_scene_prompt_help"] = '各シーンのフレームと音声から説明するための指示。最大8,000バイト。';
$lang["video_scene_timeout"] = '動画ごとの最大処理時間（秒）';
$lang["video_scene_invalid_config"] = 'Pythonパス、閾値、モデル、キー、処理タイムアウトを確認してください。';
$lang["video_scene_time"] = 'タイムスタンプ';
$lang["video_scene_thumbnail"] = 'サムネイル';
$lang["video_scene_description"] = '説明';
$lang["video_scene_edit_description"] = '%time のシーン説明を編集';
$lang["video_scene_invalid_description"] = '最大16,000バイトまでの有効な説明を入力してください。';
$lang["video_scene_description_missing"] = 'このシーンは存在しません。リソースビューを再読み込みしてください。';
$lang["video_scene_description_save_failed"] = '説明を保存できませんでした。再試行するかリソースビューを再読み込みしてください。';
$lang["video_scene_description_saved"] = 'シーンの説明が保存されました。';
$lang["video_scene_no_description"] = '説明はありません';
$lang["video_scene_play"] = '%time のシーンを再生';
$lang["video_scene_play_unavailable"] = 'このシーンは利用可能なビデオプレビューの範囲外です。';
$lang["video_scene_job_success"] = 'ビデオシーンの処理が完了しました';
$lang["video_scene_job_failure"] = 'ビデオシーンの処理に失敗しました。詳細はジョブログを参照してください。';