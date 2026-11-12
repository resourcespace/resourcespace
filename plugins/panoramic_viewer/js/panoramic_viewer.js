import { Viewer, EquirectangularAdapter } from '@photo-sphere-viewer/core';
import { AutorotatePlugin } from '@photo-sphere-viewer/autorotate-plugin';
import { GyroscopePlugin } from '@photo-sphere-viewer/gyroscope-plugin';
import { StereoPlugin } from '@photo-sphere-viewer/stereo-plugin';
import { VideoPlugin } from '@photo-sphere-viewer/video-plugin';
import { VisibleRangePlugin } from '@photo-sphere-viewer/visible-range-plugin';
import { EquirectangularVideoAdapter } from '@photo-sphere-viewer/equirectangular-video-adapter';
import '@photo-sphere-viewer/core/index.css';
import '@photo-sphere-viewer/video-plugin/index.css';
import '../css/panoramic_viewer.css';

// One observer for full-page, AJAX, modal and browser-history content replacement.
const viewers = new Map();
const selector = '[data-panoramic-viewer]';

/** Adapt legacy top/bottom stereo files to a monoscopic left-eye panorama. */
function sourceAdapter(Adapter, stereo) {
    return class extends Adapter {
        async loadTexture(...args) {
            try {
                const data = await super.loadTexture(...args);
                if (this.disposed) {
                    this.disposeTexture(data);
                    throw new DOMException('Viewer closed', 'AbortError');
                }
                if (stereo) {
                    data.texture.repeat.set(1, 0.5);
                    data.texture.offset.set(0, 0.5);
                    data.panoData = {
                        ...data.panoData,
                        fullWidth: data.panoData.croppedWidth,
                        fullHeight: data.panoData.croppedHeight / 2,
                        croppedHeight: data.panoData.croppedHeight / 2,
                        croppedX: 0,
                        croppedY: 0,
                        posePitch: 0,
                        poseRoll: 0,
                    };
                }
                return data;
            } catch (error) {
                // Video metadata can finish loading after navigation has destroyed its viewer.
                if (this.disposed) {
                    throw new DOMException('Viewer closed', 'AbortError');
                }
                throw error;
            }
        }

        destroy() {
            this.disposed = true;
            super.destroy();
        }
    };
}

/** Dispose WebGL resources, controls, media and their event handlers. */
function dispose(element, entry) {
    viewers.delete(element);
    if (entry.viewer) {
        entry.viewer.destroy();
    }
    if (entry.video) {
        entry.video.pause();
        // Settle an outstanding adapter metadata promise before releasing its source.
        entry.video.dispatchEvent(new Event('error'));
        entry.video.removeAttribute('src');
        entry.video.load();
    }
}

/** Initialise each placeholder once, including fresh DOM restored from history. */
function initialise(element) {
    if (viewers.has(element) || !element.isConnected) {
        return;
    }
    const config = JSON.parse(element.dataset.panoramicViewer);
    const entry = { viewer: null, video: null };
    viewers.set(element, entry);
    element.replaceChildren();
    try {
        const plugins = [[AutorotatePlugin, {
            autostartDelay: config.autopan ? 2000 : null,
            autostartOnIdle: config.autopan,
        }]];
        const navbar = ['autorotate', 'zoom', 'move'];
        // Yaw-only resources cannot follow device pitch; omit conflicting sensor controls.
        if (!config.yawOnly) {
            plugins.push(GyroscopePlugin);
            navbar.push('gyroscope');
            if (!config.vrModeOff) {
                plugins.push(StereoPlugin);
                navbar.push('stereo');
            }
        } else {
            plugins.push([VisibleRangePlugin, { verticalRange: [0, 0] }]);
        }
        if (config.video) {
            entry.video = document.createElement('video');
            entry.video.crossOrigin = 'use-credentials';
            entry.video.playsInline = true;
            entry.video.loop = true;
            entry.video.preload = 'metadata';
            plugins.push(VideoPlugin);
            navbar.push('videoPlay', 'videoVolume', 'videoTime');
        }
        navbar.push('fullscreen');
        entry.viewer = new Viewer({
            container: element,
            adapter: [sourceAdapter(config.video ? EquirectangularVideoAdapter : EquirectangularAdapter, config.stereo), {
                // Texture repeat/offset for packed stereo requires the standard material.
                shader: false,
                useXmpData: !config.stereo && !config.yawOnly,
            }],
            plugins,
            navbar,
            withCredentials: true,
            lang: { ...config.lang, loadError: config.error, webglError: config.error },
        });
        // Explicitly handle the loading promise, including unsupported codecs and cancelled requests.
        if (entry.viewer.adapter) {
            const loading = entry.viewer.setPanorama(config.video ? { source: entry.video } : config.source);
            if (entry.video) {
                entry.video.src = config.source;
            }
            loading.catch(() => {
                // Photo Sphere Viewer displays the translated loadError in its own overlay.
            });
        }
    } catch (error) {
        dispose(element, entry);
        // Keep a failed placeholder registered so observer changes do not repeatedly retry it.
        viewers.set(element, { viewer: null, video: null });
        const message = document.createElement('p');
        message.setAttribute('role', 'alert');
        message.textContent = config.error;
        element.replaceChildren(message);
    }
}

function scan(root) {
    if (root.nodeType !== Node.ELEMENT_NODE) {
        return;
    }
    if (root.matches(selector)) {
        initialise(root);
    }
    root.querySelectorAll(selector).forEach(initialise);
}

if (!window.resourceSpacePanoramicViewerLoaded) {
    window.resourceSpacePanoramicViewerLoaded = true;
    const observer = new MutationObserver((mutations) => {
        viewers.forEach((entry, element) => {
            if (!element.isConnected) {
                dispose(element, entry);
            }
        });
        mutations.forEach((mutation) => mutation.addedNodes.forEach(scan));
    });
    observer.observe(document.body, { childList: true, subtree: true });
    scan(document.body);
    window.addEventListener('pagehide', () => {
        viewers.forEach((entry, element) => dispose(element, entry));
    });
    window.addEventListener('pageshow', () => scan(document.body));
}
