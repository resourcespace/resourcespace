// Local, database-free browser regression tests for the deployed viewer bundle.
// Run from the repository root: node plugins/panoramic_viewer/tests/browser/run.cjs
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../../../..');
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/') {
        res.setHeader('Content-Type', 'text/html');
        res.end('<!doctype html><html><head><link rel="stylesheet" href="/lib/panoramic-viewer/viewer.css">'
            + '<script defer src="/lib/panoramic-viewer/viewer.js"></script></head>'
            + '<body><div id="CentralSpace"></div><div id="modal"></div></body></html>');
        return;
    }
    if (url.pathname === '/packed-stereo.svg') {
        res.setHeader('Content-Type', 'image/svg+xml');
        res.end('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512">'
            + '<path fill="red" d="M0 0H512V256H0Z"/><path fill="blue" d="M0 256H512V512H0Z"/></svg>');
        return;
    }
    const file = path.resolve(root, '.' + url.pathname);
    if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) {
        res.writeHead(404).end();
        return;
    }
    res.setHeader('Content-Type', ({'.js': 'text/javascript', '.css': 'text/css', '.jpg': 'image/jpeg',
        '.webm': 'video/webm', '.mp4': 'video/mp4'})[path.extname(file)] || 'application/octet-stream');
    fs.createReadStream(file).pipe(res);
});

(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = `http://127.0.0.1:${server.address().port}`;
    const browser = await chromium.launch({ headless: true,
        args: ['--no-sandbox', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        const externalRequests = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('request', request => {
            if (!request.url().startsWith(origin) && !request.url().startsWith('blob:')) {
                externalRequests.push(request.url());
            }
        });
        await page.goto(origin);
        await page.waitForFunction(() => window.resourceSpacePanoramicViewerLoaded);
        async function show(options = {}, target = 'CentralSpace') {
            await page.evaluate(({options, target}) => {
                const config = {
                    source: '/plugins/panoramic_viewer/samples/venice-sunset.jpg',
                    autopan: false, stereo: false, yawOnly: false, vrModeOff: false, video: false,
                    error: 'Panorama loading failed', lang: {}, ...options,
                };
                document.getElementById(target).innerHTML = '<table><tr><td class="panoramic-viewer-wrapper">'
                    + '<div class="panoramic-viewer"></div></td></tr></table>';
                document.querySelector(`#${target} .panoramic-viewer`).dataset.panoramicViewer = JSON.stringify(config);
                // Attribute is set in the same task, before MutationObserver processes the new DOM.
            }, {options, target});
        }
        async function ready(target = 'CentralSpace') {
            await page.waitForFunction(target => document.querySelector(`#${target} .panoramic-viewer`)
                ?.photoSphereViewer?.state.ready, target);
        }
        await show();
        await ready();
        assert.equal(await page.locator('#CentralSpace canvas').count(), 1);
        const position = await page.evaluate(() => document.querySelector('.panoramic-viewer').photoSphereViewer.getPosition());
        const box = await page.locator('.panoramic-viewer').boundingBox();
        await page.mouse.move(box.x + 200, box.y + 200);
        await page.mouse.down();
        await page.mouse.move(box.x + 350, box.y + 240, {steps: 10});
        await page.mouse.up();
        assert.notEqual(await page.evaluate(() => document.querySelector('.panoramic-viewer')
            .photoSphereViewer.getPosition().yaw), position.yaw, 'Mouse drag must pan');
        await page.evaluate(() => { window.oldViewer = document.querySelector('.panoramic-viewer').photoSphereViewer; });
        await show({autopan: true, yawOnly: true});
        await ready();
        await page.waitForFunction(() => window.oldViewer.children.length === 0);
        await page.evaluate(() => document.querySelector('.panoramic-viewer').photoSphereViewer.rotate({yaw: 1, pitch: 0.6}));
        assert.equal(await page.evaluate(() => document.querySelector('.panoramic-viewer').photoSphereViewer.getPosition().pitch), 0);
        await page.waitForFunction(() => document.querySelector('.panoramic-viewer')
            .photoSphereViewer.getPlugin('autorotate').isEnabled());
        console.log('PASS: image, mouse drag, yaw-only movement, autopan, replacement cleanup');

        await show({stereo: true, source: '/packed-stereo.svg'});
        await ready();
        const screenshot = await page.locator('.panoramic-viewer').screenshot();
        const pixel = await page.evaluate(async base64 => {
            const image = new Image();
            image.src = 'data:image/png;base64,' + base64;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            const context = canvas.getContext('2d');
            context.drawImage(image, 0, 0);
            return Array.from(context.getImageData(image.width / 2, image.height / 2, 1, 1).data);
        }, screenshot.toString('base64'));
        assert.ok(pixel[0] > 240 && pixel[2] < 15, 'Stereo source must display the upper (left-eye) image');
        console.log('PASS: packed stereo left-eye crop');

        await show({video: true, source: '/plugins/panoramic_viewer/samples/ess-na-crub-waterfall.webm'}, 'modal');
        await ready('modal');
        await page.evaluate(async () => {
            window.oldVideoViewer = document.querySelector('#modal .panoramic-viewer').photoSphereViewer;
            await window.oldVideoViewer.getPlugin('video').video.play();
        });
        await page.waitForFunction(() => window.oldVideoViewer.getPlugin('video').video.currentTime > 0);
        await page.evaluate(() => {
            window.playingVideo = window.oldVideoViewer.getPlugin('video').video;
            document.getElementById('modal').replaceChildren();
        });
        await page.waitForFunction(() => window.oldVideoViewer.children.length === 0 && window.playingVideo.paused
            && !window.playingVideo.getAttribute('src'));
        console.log('PASS: 360 WebM playback and modal media cleanup');

        await show({video: true, source: '/plugins/panoramic_viewer/samples/ess-na-crub-waterfall.mp4'});
        await ready();
        console.log('PASS: H.264 MP4 loading');
        // Capture/restore markup as ResourceSpace browser history does; stale canvases must be replaced.
        const history = await page.locator('#CentralSpace').innerHTML();
        await page.evaluate(() => document.getElementById('CentralSpace').replaceChildren());
        await page.evaluate(html => {document.getElementById('CentralSpace').innerHTML = html;}, history);
        await ready();
        assert.equal(await page.locator('#CentralSpace canvas').count(), 1);
        await page.addScriptTag({url: origin + '/lib/panoramic-viewer/viewer.js'});
        assert.equal(await page.locator('#CentralSpace canvas').count(), 1);
        console.log('PASS: browser history restoration and repeated bundle loading');

        await show({source: '/missing.jpg'});
        await page.waitForFunction(() => document.querySelector('.psv-overlay')?.textContent.includes('Panorama loading failed'));
        await show({video: true, source: '/missing.webm'});
        await page.waitForFunction(() => document.querySelector('.psv-overlay')?.textContent.includes('Panorama loading failed'));
        console.log('PASS: missing image/video error feedback');
        for (let i = 0; i < 5; i++) {
            await show({video: true, source: '/plugins/panoramic_viewer/samples/ess-na-crub-waterfall.webm'});
            await page.evaluate(() => document.getElementById('CentralSpace').replaceChildren());
        }
        await show();
        await ready();
        await page.waitForTimeout(300);
        assert.deepEqual(errors, [], 'Uncaught browser errors');
        assert.deepEqual(externalRequests, [], 'Runtime requests must stay local');
        console.log('PASS: rapid navigation and local-only runtime requests');
        await page.setViewportSize({width: 390, height: 844});
        assert.ok((await page.locator('.panoramic-viewer').boundingBox()).width <= 390);
        console.log('PASS: mobile viewport sizing');
        const mobile = await browser.newPage({viewport: {width: 390, height: 844}, hasTouch: true, isMobile: true});
        mobile.on('pageerror', error => errors.push(error.message));
        await mobile.goto(origin);
        await mobile.evaluate(() => {
            const container = document.createElement('div');
            container.className = 'panoramic-viewer';
            container.dataset.panoramicViewer = JSON.stringify({
                source: '/plugins/panoramic_viewer/samples/venice-sunset.jpg', video: false,
                autopan: false, yawOnly: false, stereo: false, vrModeOff: true, error: 'Failed', lang: {},
            });
            document.getElementById('CentralSpace').append(container);
        });
        await mobile.waitForFunction(() => document.querySelector('.panoramic-viewer')?.photoSphereViewer?.state.ready);
        const beforeTouch = await mobile.evaluate(() => document.querySelector('.panoramic-viewer')
            .photoSphereViewer.getPosition().yaw);
        const touch = await mobile.context().newCDPSession(mobile);
        await touch.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x: 180, y: 160}]});
        for (let x = 180; x < 280; x += 10) {
            await touch.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: 160}]});
        }
        await touch.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
        assert.notEqual(await mobile.evaluate(() => document.querySelector('.panoramic-viewer')
            .photoSphereViewer.getPosition().yaw), beforeTouch, 'Touch drag must pan');
        await mobile.close();
        assert.deepEqual(errors, [], 'Uncaught browser errors after mobile interaction');
        console.log('PASS: emulated mobile touch drag');

    } finally {
        await browser.close();
        server.close();
    }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
