<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once RESOURCESPACE_BASE_PATH . '/include/dash_functions.php';

it('shows personal, all-user and group-specific dashboard tiles only to the intended users', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $id = generateRandomTestID(10);
        $audience = $fixtures->group('Dashboard audience ' . $id, 's,g,f*');
        $outsiders = $fixtures->group('Dashboard outsiders ' . $id, 's,g,f*');
        $users = [
            'owner' => $fixtures->user('dash_owner_' . $id, 3),
            'member' => $fixtures->user('dash_member_' . $id, $audience),
            'outsider' => $fixtures->user('dash_outsider_' . $id, $outsiders),
        ];
        $sessions = [];
        foreach ($users as $role => $user) {
            $sessions[$role] = generateSecureKey(32);
            ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
                's', $sessions[$role], 'i', $user,
            ]);
        }

        $tiles = [];
        foreach (['personal' => [], 'everyone' => [], 'group' => [$audience]] as $scope => $groups) {
            $tile = create_dash_tile(
                'pages/ajax/dash_tile.php?tltype=txt&tlstyle=blank',
                'pages/home.php',
                'Catalogue tile ' . $scope . ' ' . $id,
                0,
                $scope !== 'personal',
                'append',
                false,
                'Tile note for ' . $scope,
                specific_user_groups: $groups,
            );
            expect($tile)->toBeGreaterThan(0);
            $fixtures->onCleanup(static fn () => delete_dash_tile($tile, true, true));
            $tiles[$scope] = $tile;
        }
        add_user_dash_tile($users['owner'], $tiles['personal'], 1000, false);

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url, 'home_dash' => true, 'managed_home_dash' => false,
            'session_autologout' => false,
        ]);
        $expected = [
            'owner' => ['personal', 'everyone'], 'member' => ['everyone', 'group'], 'outsider' => ['everyone'],
        ];
        foreach ($sessions as $role => $session) {
            $home = $server->request('/pages/home.php', headers: $server->sessionHeaders($session));
            expect($home['status'])->toBe(200);
            $document = new DOMDocument();
            $document->loadHTML($home['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            foreach ($tiles as $scope => $ref) {
                $present = $xpath->evaluate('count(//*[@id="HomePanelContainer"]//a[@tile="' . $ref . '"])');
                expect($present)->toBe(in_array($scope, $expected[$role], true) ? 1.0 : 0.0);
            }
        }
    } finally {
        $fixtures->cleanUp();
    }
});
