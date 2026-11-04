/* Browser regressions against the actual rendered view hook and Video.js. No installation data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');

const plugin = path.resolve(__dirname, '../..');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'rs-scene-browser-'));

function render(context, access = 0, preview = '', watermark = '', edit = '') {
    return execFileSync('php', [path.join(__dirname, 'view_fixture.php'), context, access, preview, watermark, edit].map(String),
        {encoding: 'utf8'});
}

(async function () {
    let browser;
    try {
        const clip = path.join(temporary, 'clip.mp4');
        execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=blue:s=160x90:r=10:d=2',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', clip]);
        const data = fs.readFileSync(clip).toString('base64');
        const denied = render('View', 2);
        assert.equal(denied.trim(), '');
        assert.equal(render('View', 0, 'denied').trim(), '');
        assert(!render('View', 1, '', 'watermarked').includes('<img'));
        assert(render('View', 1).includes('test-share-key'));
        assert(!render('View').includes('class="video-scene-edit"'), 'External share offered editing');
        assert(!render('View', 0, '', '', 'readonly').includes('class="video-scene-edit"'), 'Read-only user offered editing');

        browser = await chromium.launch({headless: true});
        const page = await browser.newPage();
        const errors = [];
        const saves = [];
        let denySave = false;
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://scene.test/**', async route => {
            const name = new URL(route.request().url()).pathname;
            if (name.endsWith('edit_description.php')) {
                assert.equal(route.request().method(), 'POST');
                const data = route.request().postData();
                assert(data.includes('test-scene-token'), 'CSRF token missing');
                assert(/name="ref"\r\n\r\n10\r\n/.test(data));
                assert(/name="scene"\r\n\r\n1\r\n/.test(data));
                const description = data.match(/name="description"\r\n\r\n([\s\S]*?)\r\n--/)[1].replace(/\r\n/g, '\n');
                saves.push(description);
                await route.fulfill({status: denySave ? 403 : 200, contentType: 'application/json',
                    body: JSON.stringify(denySave ? {error: 'Permission denied'} : {description})});
            } else if (name.endsWith('.js')) {
                await route.fulfill({contentType: 'text/javascript', body: fs.readFileSync(path.join(plugin, 'js/video-scene.js'))});
            } else if (name.endsWith('.css')) {
                await route.fulfill({contentType: 'text/css', body: fs.readFileSync(path.join(plugin, 'css/video-scene.css'))});
            } else {
                await route.fulfill({contentType: 'image/png',
                    body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF9sAAAAASUVORK5CYII=', 'base64')});
            }
        });
        for (const context of ['View', 'Modal']) {
            await page.goto('about:blank');
            const id = `${context}__introvideo10`;
            await page.setContent(`<div style="height:1100px"></div>
                <video id="${id}" controls preload="auto" src="data:video/mp4;base64,${data}"></video>
                ${render(context, 0, '', '', 'editable')}`);
            await page.addStyleTag({path: path.resolve('css/global.css')});
            await page.addScriptTag({path: path.resolve('lib/videojs/video.min.js')});
            await page.evaluate(id => window.videojs(id), id);
            await page.waitForFunction(id => window.videojs.getPlayer(id).readyState() >= 1, id);
            // Repeated AJAX/modal insertion must not register additional click handlers.
            await page.addScriptTag({path: path.join(plugin, 'js/video-scene.js')});
            assert.equal(await page.locator('.video-scene-table tbody tr').count(), 2);
            assert.equal(await page.locator('.video-scene-description-text').first().textContent(),
                '<img src=x onerror="window.sceneXss=true">');
            assert.equal(await page.evaluate(() => Boolean(window.sceneXss)), false);
            const thumbnail = await page.locator('.video-scene-thumbnail').first().boundingBox();
            assert.equal(thumbnail.width, 96);
            assert.equal(thumbnail.height, 54);
            const spacing = await page.locator('.video-scene-panel .RecordPanel').evaluate(element => ({
                left: getComputedStyle(element).paddingLeft, right: getComputedStyle(element).paddingRight
            }));
            assert.deepEqual(spacing, {left: '20px', right: '20px'});
            await page.locator('.video-scene-time').first().click();
            await page.waitForFunction(id => {
                const player = window.videojs.getPlayer(id);
                return player.currentTime() >= 0.5 && player.currentTime() < 1.5;
            }, id);
            assert.equal(await page.locator('.video-scene-message').isVisible(), false);
            await page.evaluate(id => {
                window.videojs.getPlayer(id).pause();
                window.videojs.getPlayer(id).currentTime(0);
            }, id);
            await page.locator('.video-scene-thumbnail').first().click();
            await page.waitForFunction(id => window.videojs.getPlayer(id).currentTime() >= 0.5, id);
            await page.locator('.video-scene-time').nth(1).click();
            assert.equal(await page.locator('.video-scene-message').textContent(),
                'This scene is outside the available video preview.');

            const edit = page.locator('.video-scene-edit').first();
            const form = page.locator('.video-scene-description-form').first();
            const textarea = form.locator('textarea');
            const saveCount = saves.length;
            await edit.click();
            await textarea.fill('Unsaved draft');
            await form.locator('.video-scene-edit-cancel').click();
            assert.equal(saves.length, saveCount);
            await edit.click();
            assert.equal(await textarea.inputValue(), '<img src=x onerror="window.sceneXss=true">');
            const editedText = '<script>window.sceneXss=true</script>\nEdited scene';
            await textarea.fill(editedText);
            denySave = true;
            await form.locator('button[type="submit"]').click();
            await form.locator('.video-scene-edit-error').waitFor({state: 'visible'});
            assert.equal(await textarea.inputValue(), editedText, 'Failed save lost the draft');
            denySave = false;
            await form.locator('button[type="submit"]').click();
            await form.waitFor({state: 'hidden'});
            assert.equal(await page.locator('.video-scene-description-text').first().textContent(), editedText);
            assert.equal(await page.evaluate(() => Boolean(window.sceneXss)), false);
            await edit.click();
            assert.equal(await textarea.inputValue(), editedText, 'Reopening lost saved content');
            await textarea.fill('');
            await form.locator('button[type="submit"]').click();
            await form.waitFor({state: 'hidden'});
            assert.equal(await page.locator('.video-scene-description-text').first().textContent(), 'No description available');
            assert.equal(saves.length - saveCount, 3, 'Duplicate save handlers were installed');
            const header = page.locator('.video-scene-table th').first();
            const colours = await header.evaluate(element => ({
                text: getComputedStyle(element).color, background: getComputedStyle(element).backgroundColor
            }));
            const luminance = rgb => rgb.match(/\d+/g).slice(0, 3).map(Number).map(value => {
                const s = value / 255;
                return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
            }).reduce((sum, value, i) => sum + value * [0.2126, 0.7152, 0.0722][i], 0);
            const l = [luminance(colours.text), luminance(colours.background)].sort((a, b) => b - a);
            assert((l[0] + 0.05) / (l[1] + 0.05) >= 4.5, 'Header contrast is insufficient');
            await page.evaluate(() => {
                const body = document.querySelector('.video-scene-table tbody');
                for (let row = 0; row < 20; row++) {
                    body.append(body.firstElementChild.cloneNode(true));
                }
            });
            const scroll = page.locator('.video-scene-scroll');
            assert((await scroll.boundingBox()).height <= 600);
            const headerOffset = () => header.evaluate(element => element.getBoundingClientRect().top
                - element.closest('.video-scene-scroll').getBoundingClientRect().top);
            const headerBefore = await headerOffset();
            await scroll.evaluate(element => { element.scrollTop = 400; });
            assert(Math.abs(headerBefore - await headerOffset()) < 1, 'Table heading did not remain visible');
            await page.evaluate(id => window.videojs.getPlayer(id).dispose(), id);
        }
        assert.deepEqual(errors, []);
        console.log('Video scene Chromium checks passed (full-page, modal, permissions, XSS, seeking and description editing).');
    } finally {
        if (browser) {
            await browser.close();
        }
        fs.rmSync(temporary, {recursive: true, force: true});
    }
}()).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
