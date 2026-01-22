<?php


$lang["vr_view_configuration"]='Google VR Viewの設定';
$lang["vr_view_google_hosted"]='Googleが提供するVR View JavaScriptライブラリを使用しますか？';
$lang["vr_view_js_url"]='VRビューJavaScriptライブラリへのURL（上記がfalseの場合にのみ必要）。サーバー内の場合は相対パスを使用してください。例：/vrview/build/vrview.js';
$lang["vr_view_restypes"]='VRビューで表示するリソースタイプ';
$lang["vr_view_autopan"]='自動パンを有効にする。';
$lang["vr_view_vr_mode_off"]='VRモードボタンを無効にする';
$lang["vr_view_condition"]='VRビューの状態';
$lang["vr_view_condition_detail"]='もし下のフィールドが選択されている場合、フィールドに設定された値を確認して、VRビュープレビューを表示するかどうかを決定することができます。これにより、メタデータフィールドをマッピングして埋め込まれたEXIFデータに基づいてプラグインを使用するかどうかを決定できます。これが設定されていない場合、互換性のない形式であっても常にプレビューが試みられます。<br /><br />注：Googleは、equirectangular-panoramic形式の画像と動画を必要とします。<br />推奨構成は、exiftoolフィールド「ProjectionType」を「Projection Type」というフィールドにマップし、そのフィールドを使用することです。';
$lang["vr_view_projection_field"]='VRビューのProjectionTypeフィールド';
$lang["vr_view_projection_value"]='VRビューを有効にするために必要な値。';
$lang["vr_view_additional_options"]='追加オプション';
$lang["vr_view_additional_options_detail"]='以下は、メタデータフィールドをマッピングしてVRビューパラメータを制御するために使用することができるように、リソースごとにプラグインを制御することを可能にします。<br />詳細な情報については、<a href =\'https://developers.google.com/vr/concepts/vrview-web\' target=\'+blank\'>https://developers.google.com/vr/concepts/vrview-web</a>を参照してください。';
$lang["vr_view_stereo_field"]='画像/ビデオがステレオかどうかを判断するために使用されるフィールド（オプション、未設定の場合はデフォルトでfalseになります）';
$lang["vr_view_stereo_value"]='チェックする値。ステレオが見つかった場合、trueに設定されます。';
$lang["vr_view_yaw_only_field"]='ロール/ピッチを防止するかどうかを決定するために使用されるフィールド（オプション、未設定の場合はデフォルトでfalse）';
$lang["vr_view_yaw_only_value"]='チェックする値。見つかった場合、is_yaw_onlyオプションがtrueに設定されます。';
$lang["vr_view_orig_image"]='オリジナルのリソースファイルを画像プレビューのソースとして使用しますか？';
$lang["vr_view_orig_video"]='オリジナルのリソースファイルをビデオプレビューのソースとして使用しますか？';
$lang["plugin-vr_view-title"]='VRビュー';
$lang["plugin-vr_view-desc"]='Google VR View - 360度画像およびビデオプレビュー（正距円筒図法形式）';
$lang["page-title_vr_view_download"] = 'VRビュー';
$lang["page-title_vr_view_setup"] = 'VRビュープラグインの設定';