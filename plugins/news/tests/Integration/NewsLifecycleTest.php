<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once dirname(__DIR__, 2) . '/inc/news_functions.php';
require_once dirname(__DIR__, 2) . '/hooks/home.php';

beforeAll(function (): void {
    CheckDBStruct(dirname(__DIR__, 2) . '/dbstruct');
});

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['lang']);
    register_plugin_language('news');
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('lists a published headline on the Dashboard and reflects edits and deletion', function () use (&$fixtures): void {
    $title = 'News <update> ' . generateRandomTestID(14);
    $body = 'Original story';
    $date = '2099-03-01 00:00:00';
    add_news($date, $title, $body);
    $ref = sql_insert_id();
    $fixtures->onCleanup(static fn () => delete_news($ref));
    expect(get_news($ref))->toBe([['ref' => $ref, 'date' => $date, 'title' => $title, 'body' => $body]]);

    $render = static fn (): string => cast_echo_to_string(HookNewsHomeHomebeforepanels(...));
    $html = $render();
    expect($html)->toContain('id="NewsPanel"', 'news.php?ref=' . $ref, escape($title))
        ->not->toContain($title);

    $edited = $title . ' revised';
    update_news($ref, $date, $edited, 'Updated story');
    expect(get_news($ref)[0])->toMatchArray(['title' => $edited, 'body' => 'Updated story'])
        ->and($render())->toContain(escape($edited));

    delete_news($ref);
    expect(get_news($ref))->toBeEmpty()
        ->and($render())->not->toContain('news.php?ref=' . $ref);
});
