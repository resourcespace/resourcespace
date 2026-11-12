Panoramic Viewer sample media
============================
These samples are free, openly licensed media, distributed locally for testing.
They are not automatically imported into ResourceSpace or used by the viewer.
Upload them normally and configure the resource type / projection condition.

venice-sunset.jpg
-----------------
Venice Sunset by Greg Zaal / Poly Haven.
Source: https://polyhaven.com/a/venice_sunset
Download: https://dl.polyhaven.org/file/ph-assets/HDRIs/extra/Tonemapped%20JPG/venice_sunset.jpg
Licence: CC0 1.0 Universal (full text: CC0-1.0.txt).
https://creativecommons.org/publicdomain/zero/1.0/
Changes: tonemapped JPEG resized from 8192x4096 to 1024x512 using FFmpeg.
This is a monoscopic, full-sphere equirectangular photograph.
Poly Haven makes this asset available free and in the public domain.

Repository preparation command:
ffmpeg -i source.jpg -vf scale=1024:512 -frames:v 1 -q:v 4 venice-sunset.jpg

ess-na-crub-waterfall.webm and ess-na-crub-waterfall.mp4
------------------------------------------------------
20180407 132600 Ess-Na-Crub Waterfall by Bernard Ladenthin (7 April 2018).
Source: https://commons.wikimedia.org/wiki/File:20180407_132600_Ess-Na-Crub_Waterfall.webm
Original: https://upload.wikimedia.org/wikipedia/commons/0/03/20180407_132600_Ess-Na-Crub_Waterfall.webm
Downloaded WebM: https://upload.wikimedia.org/wikipedia/commons/transcoded/0/03/20180407_132600_Ess-Na-Crub_Waterfall.webm/20180407_132600_Ess-Na-Crub_Waterfall.webm.360p.vp9.webm
Licence: Creative Commons Attribution 4.0 International (CC-BY-4.0.txt).
https://creativecommons.org/licenses/by/4.0/
WebM: Wikimedia's 640x320 VP9/Opus transcode, approximately 10 seconds,
redistributed without further changes. Monoscopic equirectangular video.
MP4: first 5 seconds of that transcode, encoded at 640x320 as H.264,
without audio, with faststart, for testing browser video compatibility.
Attribution must accompany redistribution. No endorsement is implied.

Repository preparation command:
ffmpeg -i source.webm -t 5 -vf scale=640:320 -c:v libx264 -pix_fmt yuv420p -crf 30 -an -movflags +faststart ess-na-crub-waterfall.mp4

Downloaded 11 November 2026. Both full licence texts are included alongside
these files. Their licences apply to the samples, independently of the code.
