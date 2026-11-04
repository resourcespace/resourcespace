# Video scenes

Videos are analysed by the published [`transnetv2-pytorch`](https://pypi.org/project/transnetv2-pytorch/)
package and its supported command-line interface. The wheel includes the model
weights. The plugin has no model downloader or neural-network inference implementation.

Upload and preview recreation hooks queue a `video_scene` job. Its worker creates
scene thumbnails and optional audiovisual descriptions, then replaces the video's
`resource_scene` rows. The resource view supports full pages and modals, clickable
96x54 thumbnails and timestamp buttons, and a 600px scrolling table with sticky headings.
Resource, preview, watermark and external-share restrictions still apply.

## Installation (Ubuntu)

Use one existing Python environment if it is suitable. On an Ubuntu installation
with externally managed Python packages, create one isolated environment for this
plugin; the detector and optional description worker use the same interpreter.
Python 3.10 or later and a compatible PyTorch wheel are required.

```sh
sudo apt update
sudo apt install -y python3-venv ffmpeg
sudo python3 -m venv /opt/resourcespace-video-scenes/venv
sudo /opt/resourcespace-video-scenes/venv/bin/python -m pip install --upgrade pip
sudo /opt/resourcespace-video-scenes/venv/bin/python -m pip install torch --index-url https://download.pytorch.org/whl/cpu
sudo /opt/resourcespace-video-scenes/venv/bin/python -m pip install -r /var/www/html/trunk/plugins/video_scenes/scripts/requirements.txt
```

The explicit PyTorch step selects a prebuilt CPU wheel and avoids installing GPU
runtime dependencies. An existing supported PyTorch installation can be reused.
No Git checkout, Git LFS, separate weights or GPU configuration is needed. Change
the ResourceSpace path above if your installation is elsewhere.

Verify the installed CLI before enabling processing:

```sh
/opt/resourcespace-video-scenes/venv/bin/python -m transnetv2_pytorch --help
/opt/resourcespace-video-scenes/venv/bin/python -m transnetv2_pytorch /path/to/video.mp4 --output /tmp/scenes.json --format json --threshold 0.5 --quiet
```

Choose a new output filename: the package can overwrite an existing output.
The direct executable `transnetv2_pytorch` supports the same arguments. This plugin
uses `python -m transnetv2_pytorch` so the configured Python also selects the package.

## Configuration and processing

Enable **Video scenes** in Manage plugins. The Python executable path is
backend-only: set `$video_scene_python` in
`plugins/video_scenes/config/config.php` to
`/opt/resourcespace-video-scenes/venv/bin/python` if using the documented virtual
environment. It is intentionally not exposed in the setup form. The remaining
setup defaults are. After upgrading from a version that exposed the Python path,
save the plugin setup once to remove its old saved value and use the backend path:

| Setting | Default |
| --- | --- |
| Scene transition threshold | 0.5 (must be greater than 0 and less than 1) |
| OpenAI API key | Empty: detection and thumbnails are local |
| OpenAI Realtime model | gpt-realtime-2.1 |
| Include scene audio | Yes |
| Audio context overlap | 1 second before and after each scene (configurable from 0 to 30 seconds) |
| Scene description prompt | Combines the frames with audio context. Editable in setup. |
| Maximum processing time | 7200 seconds (allowed range: 60-86400) |

The service account must be able to execute the backend-configured Python, FFmpeg and FFprobe, read the
package, bundled weights and original videos, and write to OS temporary storage
and ResourceSpace job storage. The package's CLI finds FFmpeg and FFprobe through
PATH; the plugin adds the configured utility directories to the child process PATH.

In this revision the offline worker requires `$offline_job_queue = true` in the
installation configuration. Configure `$ffmpeg_path`, normally `/usr/bin` on Ubuntu.
Enabling the queue also affects other ResourceSpace operations supporting background
processing. Reuse the installation's existing offline worker schedule, or add this
to the service account's own crontab:

```cron
* * * * * /usr/bin/php /var/www/html/trunk/pages/tools/offline_jobs.php --max-jobs=1
```

Run the same worker manually to inspect its output, using the installation's PHP
CLI and service account. For example:

```sh
sudo -u www-data /usr/bin/php /var/www/html/trunk/pages/tools/offline_jobs.php --max-jobs=1
```

Upload a short video or recreate an existing video's previews, wait for the job,
then reopen the resource view. There is no automatic scan of existing resources.
Alternatives, audio-only extensions and negative upload templates are ignored.

## Scene output and timing

Version 1.0.5 of the package supports `--output`, `--format json`, `--threshold`,
`--quiet` and `--device`. Its JSON output is a list of records such as:

```json
[{"shot_id": 1, "start_frame": 0, "end_frame": 24, "probability": 0.87, "start_time": "0.000", "end_time": "0.960"}]
```

`shot_id` starts at 1. Frames start at 0 and both frame endpoints are inclusive.
`probability` is the maximum predicted transition probability within that scene,
not a rating of description quality. The CLI's time strings are rounded to
milliseconds and its end time identifies the start of the last frame.

The plugin retains its existing timing contract: numeric source presentation
times with an **exclusive** end time. The example above ends at 1.0 seconds in
ResourceSpace for a 25fps video. Shot IDs and probabilities are retained unchanged.
For variable frame rates, the plugin supplies the CLI with a temporary 48x27
lossless proxy containing one frame per original frame on a regular timeline.
Detected frame numbers map back to original presentation timestamps; it does not
approximate source timestamps by dividing by nominal frame rate. Constant-rate
videos are supplied directly to the CLI.

The `resource_scene` table stores the six detection fields and description.
Each small JPEG thumbnail is stored beside the original resource as
`scene_<resource_scene ref>.jpg`, keeping image data out of the database. Its nullable `shot_id` and `probability` columns are added
through `dbstruct` for existing installations without deleting previous scenes.
Recreating previews populates those fields on regenerated results. Consumers are
the view table, protected thumbnail endpoint and optional collage/audio processing.

## Optional descriptions

Entering an OpenAI key opts into sending nine-frame collages and, when enabled,
matching scene audio to OpenAI's Realtime API. One text response describes each
scene; no separate transcription pass or Whisper installation is required.
Videos without audio use visual input alone. The configured model needs image and
audio input support, and the server needs outbound TLS WebSockets to api.openai.com.

Audio is limited to ten minutes per scene. Description failures leave detected
scenes and thumbnails available and mark the job as failed; recreating previews
retries processing. Local processing failures preserve existing rows. CLI failure
messages include the exit status and bounded diagnostic output with credentials
and source paths removed. Temporary detection output, proxies and collages are
created in a private workspace and removed after processing.

Play buttons and thumbnails seek the existing player. A shortened preview cannot
play later scenes; provide a full-length preview or use permitted original-video
playback to reach them. Thumbnails are omitted for viewers requiring watermarks.
Descriptions are independent of individual metadata field permissions.

Users with edit access to the resource can select **Edit** beside a scene's
description, change the text and select **Save**. **Cancel** discards unsaved
changes. No administrator permission is required. External-share views are
read-only. Saves are protected by CSRF validation and recorded in the resource
log; a description may also be cleared or added without an OpenAI key.
Recreating previews runs fresh scene analysis and replaces manual descriptions.

## Verification

```sh
php plugins/video_scenes/tests/unit/run.php
php plugins/video_scenes/tests/setup/run.php
/opt/resourcespace-video-scenes/venv/bin/python -B -m unittest discover -s plugins/video_scenes/tests/python -v
node plugins/video_scenes/tests/browser/run.cjs
php plugins/video_scenes/tests/integration/run.php /opt/resourcespace-video-scenes/venv/bin/python
```

The integration test uses real CLI inference and FFmpeg against its own temporary
storage and `rs_test_db`. It refuses to overwrite an existing test database and
removes only the database it creates; do not run another database suite concurrently.
Python tests cover CLI output adaptation, errors, VFR and single-frame timing,
collages, audio, and mocked OpenAI responses. HTTP fixtures test setup and scene
editing, including CSRF, permission checks, validation and resource ownership.
Browser tests cover access, XSS, seeking, thumbnail size/clicks, panel spacing,
header contrast, sticky scrolling and editing/saving/cancelling descriptions.
No test makes a paid OpenAI call.

[Knowledge Base setup article](knowledge_base.html)
