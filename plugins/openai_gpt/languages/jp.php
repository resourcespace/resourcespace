<?php


$lang["openai_gpt_title"]='OpenAIの統合';
$lang["property-openai_gpt_prompt"]='GPT プロンプト';
$lang["property-openai_gpt_input_field"]='GPT入力フィールド';
$lang["openai_gpt_model"]='使用するAPIモデルの名前（例： \'text-davinci-003\'）';
$lang["openai_gpt_temperature"]='0から1の間で温度をサンプリングします（値が高いほど、モデルはより多くのリスクを取ります）。';
$lang["openai_gpt_max_tokens"]='最大トークン数';
$lang["openai_gpt_advanced"]='警告 - このセクションはテスト目的でのみ使用され、ライブシステムで変更しないでください。ここでプラグインオプションを変更すると、構成されたすべてのメタデータフィールドの動作に影響を与えます。注意して変更してください！';
$lang["openai_gpt_system_message"]='初期システムメッセージテキスト。プレースホルダー %%IN_TYPE%% と %%OUT_TYPE%% は、ソース/ターゲットフィールドのタイプに応じて「text」または「json」に置き換えられます';
$lang["openai_gpt_intro"]='既存のデータをカスタマイズ可能なプロンプトでOpenAI APIに渡して生成されたメタデータを追加します。詳細については、<a href=\'https://platform.openai.com/docs/introduction\' target=\'_blank\'>https://platform.openai.com/docs/introduction</a>を参照してください。';
$lang["openai_gpt_api_key"]='OpenAI APIキー。APIキーは<a href=\'https://openai.com/api\' target=\'_blank\' >https://openai.com/api</a>から取得してください';
$lang["plugin-openai_gpt-title"]='OpenAI API GPT 統合';
$lang["plugin-openai_gpt-desc"]='OpenAI生成のメタデータ。設定されたフィールドデータをOpenAI APIに渡し、返された情報を保存します。';
$lang["openai_gpt_model_override"]='モデルはグローバル設定でロックされています: [model]';
$lang["openai_gpt_processing_multiple_resources"]='複数のリソース';
$lang["openai_gpt_processing_resource"]='リソース [resource]';
$lang["openai_gpt_processing_field"]='フィールド \'[field]\' のAI処理';
$lang["property-gpt_source"]='GPTソース';
$lang["openai_gpt_language"] = '出力言語';
$lang["openai_gpt_language_user"] = '現在のユーザーの言語';
$lang["openai_gpt_overwrite_data"] = '設定されたフィールドの既存データを上書きしますか？';
$lang["page-title_openai_gpt_setup"] = 'OpenAI GPTプラグインの設定';