Panoramic Viewer
================
Panoramic Viewer displays equirectangular 360 degree photographs and videos in
ResourceSpace's full preview page and preview modal using Photo Sphere Viewer.
The resource view keeps its still thumbnail; click it to open the panorama.
Mouse drag, touch drag, zoom, fullscreen and automatic panning are supported.
All runtime JavaScript, CSS and icons are served locally. WebGL is required.

Configuration
-------------
Enable Panoramic Viewer in Manage Plugins and open its configuration page.
All setting names below have the prefix panoramic_viewer_.

restypes (default []): resource types to enable. None are selected by default.
projection_field (0) and projection_value ('equirectangular'): optional exact
metadata match. Supports fixed-list nodes and comma-separated text values.
Map ExifTool's ProjectionType to your chosen field for EXIF-driven activation.
Without a projection field condition, all selected types are treated as panoramas.

autopan (true): start panning automatically after two seconds of inactivity.
The automatic panning button also allows users to start/stop panning manually.
vr_mode_off (false): hide the mobile stereo view button. The gyroscope button
remains available on supported devices. Sensor permission is requested on use.

orig_image (true): use original JPEG, PNG, GIF or WebP images. Otherwise use the
ResourceSpace large JPEG preview (lpr). GIFs display as still images. Other source
formats can use generated JPEG previews. Previews must retain the full panorama.
orig_video (true): use the original video by default. Set false to use the
transcoded video preview (pre). This requires an existing browser-playable preview. Supported containers: MP4, WebM, Ogg/OGV, M4V and MOV,
subject to browser codec support. MP4/H.264 and WebM/VP9 are recommended; MOV
support varies. Non-browser formats must be transcoded. Playback starts with the
play button, with seek, volume and time controls. The media loops. Large videos
may exceed mobile device texture limits; keep mobile video at 4096 pixels wide
or less. Missing files fall back to ResourceSpace's normal preview. Failed loads,
unsupported codecs and unavailable WebGL produce an error within the viewer.

stereo_field (0), stereo_value (''): identify legacy top/bottom stereo media.
The upper half contains the left-eye equirectangular image; the lower half the
right-eye image. These files display the upper half as a monoscopic panorama,
with a visible notice. They do not retain stereoscopic depth. Side-by-side and
VR180 files are not supported. Use unmodified original sources where previews
have altered the top/bottom layout.
yaw_only_field (0), yaw_only_value (''): exact metadata condition restricting
movement to the horizontal axis. Sensor/stereo buttons are omitted for these
resources because they conflict with preventing pitch and roll.

Device orientation and VR limitations
------------------------------------
The official Gyroscope and Stereo plugins provide sensor movement and mobile
split-screen viewing where the browser exposes device orientation. HTTPS and
user permission are required on applicable devices. Browser/device support and
sensor accuracy vary. Desktop browsers usually hide these buttons. Stereo view
renders the same monoscopic panorama for both eyes; it does not decode separate
left/right source views. Native Cardboard, WebXR headsets and headset controllers
are not provided. No custom headset implementation is included.

Permissions and lifecycle
-------------------------
Only resources with full effective access and permission to download the selected
source size are rendered. The standard ResourceSpace download endpoint handles
media delivery and external share keys. Restricted resources retain normal still
previews. Planar annotations and lightbox previews are bypassed for panoramas.
A single observer initialises newly inserted preview content and destroys removed
viewers, releasing controls, WebGL resources and video playback. This covers AJAX
navigation, modal closure and browser history restoration. Viewers resize with
their containers. No external library hosts or CORS exceptions are needed.

Migration from Google VR View
-----------------------------
In this revision the former plugin is registered as vr_view (its help page used
the name googlevrview). System upgrade script 032 renames the database registration
to panoramic_viewer and renames supported settings, preserving enabled/disabled
state, group restrictions and priority. Serialized-only legacy settings are also
migrated. If the replacement already has a registration, its state and settings
win; missing settings are filled from the legacy configuration. Obsolete records
are removed. Google-hosted/custom JavaScript URL options are discarded. All
replacement dependencies are local. The new plugin version starts at 1.0.

After the normal ResourceSpace upgrade, reload the page so the new hooks and
assets are loaded. No settings need to be recreated for database-configured sites.
Sites that explicitly list vr_view or googlevrview in include/config.php must
change that entry to panoramic_viewer. Rename any corresponding site-specific
vr_view_* configuration overrides to panoramic_viewer_*. Remove google_hosted
and js_url overrides. The upgrade does not edit installation configuration files;
leaving the old plugin in the config.php list can recreate its obsolete database
registration. Apply these changes as part of deploying the replacement files.
Do not retain the old plugin directory in a deployed release.

Changes from the previous viewer: packed stereo depth is unavailable; mobile
stereo view is supported only through the official browser sensor plugin; yaw-only
mode omits conflicting sensor controls; video starts on user action; local assets
replace externally hosted JavaScript. Activation and image selection keep their previous defaults. New configurations
use original video by default; migrated configurations retain their saved choice. The back-to-resource navigation remains available.

Dependencies and updates
------------------------
Photo Sphere Viewer 5.15.1: core, Autorotate, Gyroscope, Stereo, Video, Visible
Range plugins and Equirectangular Video adapter. Three.js 0.185.1. MIT licensed;
notices are in this plugin's licenses/photo-sphere-viewer.txt and licenses/three.txt.

Developers use package.json and pnpm-lock.yaml, pinned to exact package versions.
Use pnpm install --frozen-lockfile with the repository's pinned pnpm version.
The normal postinstall/build:dependencies task copies existing dependencies and
runs js/build_panoramic_viewer.js through js/build_js_dependencies.js. The new
script uses pinned esbuild (development only) to produce viewer.js and viewer.css
in lib/panoramic-viewer and copy licence texts. To rebuild only this viewer:
node js/build_panoramic_viewer.js
Commit/distribute those built assets and licences with the application. Deployment
requires neither npm/pnpm nor internet access. Keep all official plugin versions
aligned when updating. No externally hosted fonts, images or scripts are needed.

Verification and samples
------------------------
Samples and their independent content licences are in samples/README.txt.
Browser regression: node plugins/panoramic_viewer/tests/browser/run.cjs
The browser harness uses Chromium/SwiftShader and a temporary localhost server;
it exercises the built assets without changing any installed site data.
Physical touch devices, iOS sensor permission, Android sensors, mobile split-screen
view and a complete installed-site AJAX/share workflow should also be checked.
