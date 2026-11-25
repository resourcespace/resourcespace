<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('prepopulates the upload form with the selected metadata template', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $id = generateRandomTestID(10);
        $user = $fixtures->user('uploadtemplate_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);
        $template_type = 2;
        $title_field = (int) $GLOBALS['view_title_field'];
        $source = $fixtures->resource($template_type, 0, $user);
        $field = $fixtures->field('Default catalogue caption ' . $id);
        $heading = 'Exhibition template ' . $id;
        $caption = 'Default caption from template ' . $id;
        $errors = [];
        expect(update_field($source, $title_field, $heading, $errors))->toBeTrue()
            ->and(update_field($source, $field, $caption, $errors))->toBeTrue();
        $upload = $fixtures->trackResource(-$user);

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url, 'session_autologout' => false, 'upload_then_edit' => false,
            'metadata_template_resource_type' => $template_type, 'metadata_template_title_field' => $title_field,
        ]);
        $headers = $server->sessionHeaders($session);
        $path = '/pages/edit.php?' . http_build_query(['ref' => $upload, 'uploader' => 'batch', 'resource_type' => 1]);
        $first = $server->request($path, headers: $headers);
        expect($first['status'])->toBe(200)->and($first['body'])->not->toContain($caption);
        $document = new DOMDocument();
        $document->loadHTML($first['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $options = '//select[@name="metadatatemplate"]/option[@value="' . $source . '"]';
        expect((new DOMXPath($document))->evaluate('normalize-space(' . $options . ')'))->toBe($heading);
        $hidden = LocalTestServer::hiddenFields($first['body'], '//form[@id="mainform"]');
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);

        $selected = $server->request($path, ['metadatatemplate' => $source] + $hidden, $headers);
        expect($selected['status'])->toBe(200);
        $document->loadHTML($selected['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(' . $options . '[@selected])'))->toBe(1.0);
        expect($xpath->evaluate('string(//input[@name="field_' . $field . '"]/@value)'))->toBe($caption);
        expect(get_data_by_field($upload, $field))->toBe($caption);
        expect(get_data_by_field($source, $field))->toBe($caption);
    } finally {
        $fixtures->cleanUp();
    }
});
