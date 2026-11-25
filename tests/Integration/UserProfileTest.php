<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('shows a saved JPEG avatar and biography on the profile and honours avatar visibility in comments', function (): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Profile image processing requires ImageMagick.');
    }

    $fixtures = new IntegrationFixtures();
    try {
        $suffix = generateRandomTestID(12);
        $username = 'profileowner' . $suffix;
        $fullname = 'Profile fixture ' . $suffix;
        $owner = $fixtures->user($username, 3);
        // Keep the tagged username distinct from resource and collection link tokens.
        $viewer_name = 'profileviewer' . strtr($suffix, '0123456789', 'klmnopqrst');
        $viewer = $fixtures->user($viewer_name, 3);
        $guest_group = $fixtures->group('Profile comment guests ' . $suffix, 's,g,f*');
        $guest_name = 'profileguest' . $suffix;
        $guest = $fixtures->user($guest_name, $guest_group);
        ps_query('UPDATE user SET approved = 1 WHERE ref = ?', ['i', $guest]);
        $bio = 'Profile biography ' . $suffix;
        $sessions = [$owner => generateSecureKey(32), $viewer => generateSecureKey(32)];
        foreach ($sessions as $user => $session) {
            ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
                's', $session, 'i', $user,
            ]);
        }
        ps_query('UPDATE user SET fullname = ? WHERE ref = ?', ['s', $fullname, 'i', $owner]);
        $fixtures->onCleanup(static fn () => delete_profile_image($owner));

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url,
            'session_autologout' => false,
            'enable_remote_apis' => false,
            'comments_flat_view' => false,
            'anonymous_login' => $guest_name,
        ]);
        $edit = '/pages/user/user_profile_edit.php';
        $owner_headers = $server->sessionHeaders($sessions[$owner]);
        $form = $server->request($edit, headers: $owner_headers);
        expect($form['status'])->toBe(200);
        $form_selector = '//form[contains(@action, "user_profile_edit.php")]';
        $hidden = LocalTestServer::hiddenFields($form['body'], $form_selector);
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        expect(str_contains($form['body'], 'show-hidden-btn'))->toBeFalse();

        $image = testFixture('Images/image_1.jpg');
        $save = $hidden + ['save' => 'yes', 'profile_bio' => '<b>' . $bio . '</b>'];
        $rejected = $server->request(
            $edit,
            $save + ['profile_image' => new CURLFile($image, 'image/jpeg', 'avatar.png')],
            $owner_headers
        );
        expect(str_contains($rejected['body'], $GLOBALS['lang']['error_not_jpeg']))->toBeTrue()
            ->and(get_profile_image($owner))->toBe('');

        $saved = $server->request(
            $edit,
            $save + ['profile_image' => new CURLFile($image, 'image/jpeg', 'avatar.jpeg')],
            $owner_headers
        );
        expect($saved['status'])->toBe(200)->and(get_profile_text($owner))->toBe($bio);
        $avatar = get_profile_image($owner);
        expect($avatar)->not->toBe('');
        $file = $GLOBALS['storagedir'] . '/user_profiles/' . basename($avatar);
        expect($file)->toBeFile();
        $dimensions = getimagesize($file);
        expect([$dimensions[0], $dimensions[1], $dimensions[2]])->toBe([200, 200, IMAGETYPE_JPEG]);
        expect(str_contains($saved['body'], 'id="UserProfileImage"'))->toBeTrue();

        $profile = '/pages/user/user_profile.php?' . http_build_query(['username' => $username]);
        $viewer_headers = $server->sessionHeaders($sessions[$viewer]);
        $visible = $server->request($profile, headers: $viewer_headers);
        expect($visible['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($visible['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $profile_box = '//div[@id="CentralSpace"]//div[contains(@class, "BasicsBox")]';
        $heading = $xpath->evaluate('normalize-space(' . $profile_box . '/h1)');
        expect($heading)->toBe($fullname);
        expect(str_contains($visible['body'], $bio))->toBeTrue();
        expect(str_contains($visible['body'], basename($avatar)))->toBeTrue();

        $resource = $fixtures->resource(1, 0);
        $fixtures->onCleanup(static function () use ($resource, $viewer): void {
            ps_query('DELETE FROM comment WHERE resource_ref = ?', ['i', $resource]);
            foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $viewer]) as $message) {
                message_remove($message);
            }
        });
        $comment_path = '/pages/ajax/comments_handler.php?ref=' . $resource;
        $comment_form = '//div[@id="comment_form"]/form';
        $comment_page = $server->request($comment_path, headers: $owner_headers);
        $comment_fields = LocalTestServer::hiddenFields($comment_page['body'], $comment_form);
        expect($comment_fields)->toHaveKeys([$GLOBALS['CSRF_token_identifier'], 'resource_ref']);
        $comment = 'An update for @' . $viewer_name;
        $submitted = $server->request($comment_path, $comment_fields + ['body' => $comment], $owner_headers);
        expect($submitted['status'])->toBe(200);
        $parent = ps_value('SELECT ref AS value FROM comment WHERE resource_ref = ?', ['i', $resource], 0);
        expect($parent)->toBeGreaterThan(0);
        $parent_selector = '//*[@id="comment' . $parent . '"]';
        $avatar_selector = $parent_selector . '//img[@id="CommentProfileImage"]';
        $document->loadHTML($submitted['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect(basename($xpath->evaluate('string(' . $avatar_selector . '/@src)')))->toBe(basename($avatar));
        $tag = $parent_selector . '//a[text()="@' . $viewer_name . '"]';
        expect($xpath->evaluate('string(' . $tag . '/@href)'))->toEndWith('?username=' . $viewer_name);
        $messages = [];
        expect(message_get($messages, $viewer))->toBeTrue();
        $tag_messages = array_values(array_filter(
            $messages,
            static fn (array $message): bool => str_contains($message['message'], $comment)
        ));
        expect($tag_messages)->toHaveCount(1);
        expect(parse_url($tag_messages[0]['url'], PHP_URL_QUERY))->toBe('r=' . $resource);

        $reply_page = $server->request($comment_path, headers: $viewer_headers);
        $reply_fields = LocalTestServer::hiddenFields($reply_page['body'], $comment_form);
        $reply = 'A reply in the thread';
        $replied = $server->request($comment_path, $reply_fields + ['body' => $reply, 'ref_parent' => $parent], $viewer_headers);
        expect($replied['status'])->toBe(200);
        $document->loadHTML($replied['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('normalize-space(' . $parent_selector . '/following-sibling::div[1]//div[@class="CommentBody"])'))
            ->toBe($reply)
            ->and($xpath->evaluate('string(' . $parent_selector . '/following-sibling::div[1]/@style)'))
            ->toContain('margin-left: 50px');

        $anonymous = $server->request($comment_path, headers: $server->sessionHeaders(''));
        expect($anonymous['status'])->toBe(200)->and($anonymous['body'])->toContain($reply);
        $document->loadHTML($anonymous['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        expect((new DOMXPath($document))->evaluate('count(' . $avatar_selector . ')'))->toBe(0.0);

        $deleted = $server->request($edit, $hidden + ['delete' => 'yes'], $owner_headers);
        expect($deleted['status'])->toBe(200)
            ->and(str_contains($deleted['body'], 'id="UserProfileImage"'))->toBeFalse()
            ->and(file_exists($file))->toBeFalse()
            ->and(get_profile_image($owner))->toBe('')
            ->and(get_profile_text($owner))->toBe($bio);
        expect($server->request($profile, headers: $viewer_headers)['body'])->not->toContain(basename($avatar));
        $without_avatar = $server->request($comment_path, headers: $viewer_headers);
        expect($without_avatar['status'])->toBe(200)->and($without_avatar['body'])->toContain($reply);
        $document->loadHTML($without_avatar['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        expect((new DOMXPath($document))->evaluate('count(' . $avatar_selector . ')'))->toBe(0.0);

        $reuploaded = $server->request(
            $edit,
            $save + ['profile_image' => new CURLFile($image, 'image/jpeg', 'avatar.jpeg')],
            $owner_headers
        );
        $reuploaded_avatar = get_profile_image($owner);
        expect($reuploaded['status'])->toBe(200)->and($reuploaded_avatar)->not->toBeEmpty();
        $reuploaded_file = $GLOBALS['storagedir'] . '/user_profiles/' . basename($reuploaded_avatar);
        expect($reuploaded_file)->toBeFile();
        $account_path = '/pages/team/team_user_edit.php?ref=' . $owner;
        $account_form = $server->request($account_path, headers: $viewer_headers);
        expect($account_form['status'])->toBe(200)->and($account_form['body'])->toContain('name="deleteme"');
        $account_fields = LocalTestServer::hiddenFields($account_form['body'], '//form[.//input[@name="deleteme"]]');
        expect($account_fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $removed_account = $server->request($account_path, $account_fields + [
            'save' => 'yes', 'deleteme' => 'yes',
        ], $viewer_headers);
        expect($removed_account['status'])->toBe(302)
            ->and(file_exists($reuploaded_file))->toBeFalse();
        $remaining_thread = $server->request($comment_path, headers: $viewer_headers);
        expect($remaining_thread['status'])->toBe(200);
        $document->loadHTML($remaining_thread['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('string(' . $parent_selector . ')'))->toContain('An update for')
            ->and($xpath->evaluate('count(' . $avatar_selector . ')'))->toBe(0.0);
    } finally {
        $fixtures->cleanUp();
    }
});
