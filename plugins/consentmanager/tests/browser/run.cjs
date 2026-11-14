'use strict';
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const https = require('node:https');
const net = require('node:net');
const { spawn, execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../../../..');
const fixturePath = `/tmp/rs-consent-browser-${process.pid}.json`;
const certificatePath = `/tmp/rs-consent-browser-${process.pid}`;
let fixtureProcess, phpServer, proxy, browser;
let fixtureOutput = '';
const assert = (condition, message) => { if (!condition) { throw new Error(message); } };
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const waitFor = async (condition, message, limit = 45000) => {
    const deadline = Date.now() + limit;
    while (Date.now() < deadline) {
        if (condition()) { return; }
        await delay(100);
    }
    throw new Error(message);
};
const freePort = async () => {
    const server = net.createServer();
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    await new Promise((resolve) => server.close(resolve));
    return port;
};
(async () => {
    fixtureProcess = spawn('php', ['plugins/consentmanager/tests/integration/run.php', '--browser', fixturePath], {cwd: root});
    fixtureProcess.stdout.on('data', (chunk) => { fixtureOutput += chunk; });
    fixtureProcess.stderr.on('data', (chunk) => { fixtureOutput += chunk; });
    await waitFor(() => fs.existsSync(fixturePath) || fixtureProcess.exitCode !== null, 'Fixture did not initialise');
    assert(fs.existsSync(fixturePath), 'Fixture failed: ' + fixtureOutput);
    const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=blue:s=160x90:r=10',
        '-t', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-movflags', 'faststart', fixture.video_path]);
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
        '-c:a', 'libmp3lame', fixture.audio_path]);
    execFileSync('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
        '-subj', '/CN=localhost', '-keyout', certificatePath + '.key', '-out', certificatePath + '.crt'], {stdio: 'ignore'});
    const phpPort = await freePort();
    proxy = https.createServer({key: fs.readFileSync(certificatePath + '.key'), cert: fs.readFileSync(certificatePath + '.crt')},
        (request, response) => {
            const upstream = http.request({hostname: '127.0.0.1', port: phpPort, path: request.url,
                method: request.method, headers: request.headers}, (result) => {
                response.writeHead(result.statusCode, result.headers);
                result.pipe(response);
            });
            upstream.on('error', () => { response.writeHead(502); response.end(); });
            request.pipe(upstream);
        });
    await new Promise((resolve) => proxy.listen(0, '127.0.0.1', resolve));
    const origin = `https://127.0.0.1:${proxy.address().port}`;
    phpServer = spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', root,
        'plugins/consentmanager/tests/browser/router.php'], {cwd: root,
        env: {...process.env, RS_CONSENT_FIXTURE: fixturePath, RS_CONSENT_ORIGIN: origin}, stdio: 'ignore'});
    await delay(500);
    browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const admin = await browser.newContext({ignoreHTTPSErrors: true});
    await admin.addCookies([{name: 'user', value: fixture.session, url: origin}]);
    const page = await admin.newPage();
    await page.goto(`${origin}/plugins/consentmanager/pages/templates.php`);
    await page.locator('h1').filter({hasText: 'Consent form templates'}).waitFor({timeout: 15000});
    assert((await page.locator('h1').textContent()).includes('Consent form templates'), 'Template list failed to render: ' + (await page.locator('body').innerText()).slice(0, 500));
    assert(await page.locator('.Listview table').count() === 1, 'Templates do not use a table');
    await page.goto(`${origin}/plugins/consentmanager/pages/template_edit.php`);
    assert(await page.locator('#cm-template-options').count() === 1, 'Tag field missing');
    assert(await page.locator('.select2-selection--multiple').count() === 2, 'Tag helper did not initialise');
    assert((await page.locator('.FormHelp').allTextContents()).join(' ').includes('press Enter'), 'Template field guidance missing');
    const tagInput = page.locator('.select2-search__field').first();
    await tagInput.fill('Web use');
    await page.locator('.select2-results__option').filter({hasText: 'Add: Web use'}).waitFor();
    await tagInput.press('Enter');
    assert(await page.locator('#cm-template-options option:checked').count() === 1, 'Adding an option pill failed');
    await page.locator('.select2-selection__choice__remove').first().click();
    assert(await page.locator('#cm-template-options option:checked').count() === 0, 'Removing an option pill failed');
    await page.goto(`${origin}/plugins/consentmanager/pages/list.php`);
    assert(await page.locator('#consent-request-search').count() === 1, 'Request filters missing');
    assert(await page.locator('#request-status').count() === 1, 'Request status filter missing');
    assert((await page.locator('body').textContent()).includes('To create a new request'), 'Request creation help missing');
    await page.goto(`${origin}/plugins/consentmanager/pages/requests.php?audit=${fixture.request}`);
    assert((await page.locator('body').textContent()).includes(`Consent Request #${fixture.request}`), 'Request breadcrumb missing');
    await page.goto(`${origin}/plugins/consentmanager/pages/recipient_preview.php?request=${fixture.request}`);
    assert(await page.locator('.cm-logo').count() === 1, 'Application logo missing');
    assert(await page.locator('#cm-signature').count() === 1, 'Admin drawing preview missing');
    assert(await page.getByRole('button', {name: 'Next', exact: true}).isEnabled(), 'Preview Next is disabled');
    assert(await page.getByRole('button', {name: 'Decline consent request'}).isDisabled(), 'Preview decline is enabled');
    assert(await page.locator('#cm-method').inputValue() === 'drawn', 'Drawn signature is not the default');
    await page.locator('#cm-signature').scrollIntoViewIfNeeded();
    const bounds = await page.locator('#cm-signature').boundingBox();
    await page.mouse.move(bounds.x + 20, bounds.y + 30);
    await page.mouse.down();
    await page.mouse.move(bounds.x + 100, bounds.y + 70, {steps: 10});
    await page.mouse.up();
    assert((await page.locator('#cm-signature-data').inputValue()).length > 10, 'Admin preview drawing is not interactive');
    await page.locator('#cm-method').selectOption('typed');
    assert(await page.locator('#cm-drawn-controls').isHidden(), 'Preview typed method did not hide drawing box');
    await page.locator('input[name=declarations]').check();
    await page.locator('input[name="fields[0]"]').fill('Preview contact');
    await page.locator('#cm-signatory').fill('Preview signatory');
    const previewPostPromise = page.waitForRequest((request) => request.method() === 'POST');
    await page.getByRole('button', {name: 'Next', exact: true}).click();
    const previewPost = await previewPostPromise;
    assert(previewPost.headers().origin === origin, 'Preview POST lost its same-origin identity');
    assert(previewPost.headers().referer === origin + '/', 'Preview referrer exposes its request path');
    await page.locator('.cm-document').waitFor();
    const previewDocument = await page.locator('.cm-document').getAttribute('src');
    assert(previewDocument.startsWith('data:application/pdf;base64,'), 'Preview did not generate an unsaved PDF');
    const previewText = execFileSync('pdftotext', ['-', '-'],
        {input: Buffer.from(previewDocument.split(',')[1], 'base64')}).toString();
    assert(previewText.includes('Preview signatory'), 'Preview PDF does not include the captured signature');
    assert(await page.getByRole('button', {name: 'Submit signed consent', exact: true}).isDisabled(),
        'Preview can submit real consent');
    await page.getByRole('button', {name: 'Correct details', exact: false}).click();
    await page.locator('#cm-signatory').waitFor();
    assert(await page.locator('input[name="fields[0]"]').inputValue() === 'Preview contact', 'Preview corrections lost answers');
    const recipient = await browser.newContext({ignoreHTTPSErrors: true, viewport: {width: 390, height: 844}});
    const portal = await recipient.newPage();
    const url = `${origin}/plugins/consentmanager/pages/consent.php?token=${fixture.token}`;
    await portal.goto(url);
    await portal.locator('#cm-name').waitFor({timeout: 15000});
    assert(await portal.locator('#cm-name').count() === 1, 'Recipient form unavailable without account');
    assert(await portal.locator('.cm-logo').count() === 1, 'Recipient logo missing');
    assert(await portal.locator('video').count() === 1 && await portal.locator('audio').count() === 1,
        'Mixed media previews missing');
    await portal.locator('video').evaluate((media) => media.load());
    await portal.locator('audio').evaluate((media) => media.load());
    await portal.waitForFunction(() => [...document.querySelectorAll('video,audio')]
        .every((media) => media.readyState >= 2 && media.duration > 0));
    assert(await portal.locator('input[name="options[0]"]:checked').count() === 0, 'Optional permission preselected');
    assert(await portal.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'Mobile portal overflows');
    const badPost = await recipient.request.post(url, {form: {action: 'form', name: 'Bad request'}});
    assert(badPost.status() === 403, 'Missing CSRF token accepted');
    await portal.locator('input[name=declarations]').check();
    await portal.locator('input[name="fields[0]"]').fill('Test person');
    await portal.locator('#cm-method').selectOption('typed');
    assert(await portal.locator('#cm-drawn-controls').isHidden(), 'Typed method did not hide drawing controls');
    await portal.locator('#cm-signatory').fill('Recipient Test');
    const recipientPostPromise = portal.waitForRequest((request) => request.method() === 'POST');
    await portal.getByRole('button', {name: 'Next', exact: true}).click();
    const recipientPost = await recipientPostPromise;
    assert(recipientPost.headers().origin === origin, 'Recipient POST lost its same-origin identity');
    assert(recipientPost.headers().referer === origin + '/', 'Recipient referrer exposes the bearer token');
    await portal.getByText('Your signature has been applied.', {exact: false}).waitFor();
    const signedUrl = await portal.locator('.cm-document').getAttribute('src');
    const signedResponse = await recipient.request.get(signedUrl);
    const signedBytes = await signedResponse.body();
    assert(signedBytes.subarray(0, 5).toString() === '%PDF-', 'Signed document unavailable');
    const pdfText = execFileSync('pdftotext', ['-', '-'], {input: signedBytes}).toString();
    assert(pdfText.includes('Requested by:') && pdfText.includes('Request initiated:'), 'Requester details missing from signed PDF');
    assert(pdfText.includes('Print, Websites'), 'Existing consent usage missing from signed PDF');
    await portal.getByRole('checkbox', {name: 'I have read the complete PDF.'}).check();
    await portal.getByRole('button', {name: 'Submit signed consent', exact: true}).click();
    await portal.getByText('Your signed consent has been submitted successfully.', {exact: false}).waitFor();
    const completedUrl = await portal.getByRole('link', {name: 'Open / download submitted signed PDF'}).getAttribute('href');
    const completedBytes = await (await recipient.request.get(completedUrl)).body();
    assert(signedBytes.equals(completedBytes), 'Completed download differs from reviewed signed PDF');
    await portal.screenshot({path: certificatePath + '-mobile.png', fullPage: true});
    console.log('PASS: browser template list/tags, central filters, breadcrumbs, interactive unsaved preview, drawn/typed visibility, mobile mixed-media portal, CSRF and complete typed-signature submission');
})().catch((error) => { console.error(error.stack); console.error(fixtureOutput.trim()); process.exitCode = 1; }).finally(async () => {
    if (browser) { await browser.close(); }
    if (phpServer) { phpServer.kill(); }
    if (proxy) { await new Promise((resolve) => proxy.close(resolve)); }
    if (fixtureProcess) {
        fs.writeFileSync(fixturePath + '.done', 'done');
        await waitFor(() => fixtureProcess.exitCode !== null, 'Fixture cleanup did not finish', 10000).catch(() => fixtureProcess.kill());
    }
    for (const suffix of ['', '.done']) { if (fs.existsSync(fixturePath + suffix)) { fs.unlinkSync(fixturePath + suffix); } }
    for (const suffix of ['.key', '.crt']) { if (fs.existsSync(certificatePath + suffix)) { fs.unlinkSync(certificatePath + suffix); } }
});
