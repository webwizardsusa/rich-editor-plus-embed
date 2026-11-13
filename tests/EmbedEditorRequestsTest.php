<?php

use Illuminate\Support\Facades\Http;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\EditorPreview;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

beforeEach(fn (): mixed => PluginRegistry::flush());

function embedField(?EmbedPlugin $plugin = null): RichEditorPlus
{
    return RichEditorPlus::make('body')->addPlugins([$plugin ?? EmbedPlugin::make()]);
}

function fakeProviders(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'www.youtube.com/oembed*' => Http::response([
            'type' => 'video', 'title' => 'A video', 'thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg',
            'width' => 640, 'height' => 360, 'html' => '<iframe src="https://www.youtube.com/embed/abc"></iframe>',
        ]),
        'publish.x.com/oembed*' => Http::response([
            'type' => 'rich', 'html' => '<blockquote class="twitter-tweet"><p>Hi</p></blockquote>',
        ]),
        'vimeo.com/api/oembed.json*' => Http::response([], 404),
    ]);
}

it('resolves a URL to the attributes of its embed', function (): void {
    fakeProviders();

    expect(embedField()->answerPluginRequestForJs(EmbedPlugin::ID, 'resolve', ['url' => ' https://youtu.be/abc ']))->toBe(['embed' => [
        'url' => 'https://youtu.be/abc',
        'provider' => 'youtube',
        'title' => 'A video',
        'thumbnail' => 'https://i.ytimg.com/vi/abc/hq.jpg',
    ]]);
});

it('answers an error for a URL it cannot embed, without asking a provider about other sites', function (mixed $url, string $error): void {
    fakeProviders();

    expect(embedField()->answerPluginRequestForJs(EmbedPlugin::ID, 'resolve', ['url' => $url]))->toBe(['error' => __($error)]);
})->with([
    'a site no provider takes' => ['https://example.com/page', 'rich-editor-plus-embed::plugins.embed.modal.url_unsupported'],
    'a URL that is not a string' => [['https://youtu.be/abc'], 'rich-editor-plus-embed::plugins.embed.modal.url_unsupported'],
    'a URL that is too long' => ['https://youtu.be/'.str_repeat('a', 3000), 'rich-editor-plus-embed::plugins.embed.modal.url_unsupported'],
    'a page the provider has nothing for' => ['https://vimeo.com/1', 'rich-editor-plus-embed::plugins.embed.modal.url_unavailable'],
]);

it('resolves URLs for a disabled field, which still shows its embeds', function (): void {
    fakeProviders();

    expect(embedField()->disabled()->answerPluginRequestForJs(EmbedPlugin::ID, 'resolve', ['url' => 'https://youtu.be/abc']))
        ->toHaveKey('embed.provider', 'youtube');
});

it('answers previews of the rendered embeds when the editor shows embeds, but no provider scripts', function (): void {
    fakeProviders();

    $answer = embedField(EmbedPlugin::make()->editorPreview(EditorPreview::Embed))->answerPluginRequestForJs(EmbedPlugin::ID, 'previews', ['urls' => [
        'https://youtu.be/abc',
        'https://x.com/jack/status/20',
        'https://vimeo.com/1',
        'javascript:alert(1)',
        ['not a url'],
    ]]);

    expect($answer['previews'])->toHaveKeys(['https://youtu.be/abc', 'https://x.com/jack/status/20'])->toHaveCount(2)
        ->and($answer['previews']['https://youtu.be/abc'])->toBe([
            'html' => '<div class="rep-embed-frame" style="aspect-ratio: 640 / 360"><iframe src="https://www.youtube.com/embed/abc" width="640" height="360" title="A video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
        ])
        ->and($answer['previews']['https://x.com/jack/status/20'])->toBe(['html' => null]);
});

it('answers no previews when the editor shows links or cards', function (EditorPreview $preview): void {
    Http::preventStrayRequests();
    Http::fake();

    expect(embedField(EmbedPlugin::make()->editorPreview($preview))->answerPluginRequestForJs(EmbedPlugin::ID, 'previews', ['urls' => ['https://youtu.be/abc']]))
        ->toBe(['previews' => []]);

    Http::assertNothingSent();
})->with([EditorPreview::Link, EditorPreview::Card]);

it('previews at most a request\'s worth of embeds', function (): void {
    Http::preventStrayRequests();
    Http::fake(['www.youtube.com/oembed*' => Http::response([], 404)]);

    $urls = array_map(static fn (int $index): string => "https://youtu.be/{$index}", range(1, EmbedPlugin::MAX_PREVIEWS + 5));

    embedField(EmbedPlugin::make()->editorPreview(EditorPreview::Embed))->answerPluginRequestForJs(EmbedPlugin::ID, 'previews', ['urls' => $urls]);

    Http::assertSentCount(EmbedPlugin::MAX_PREVIEWS);
});

it('tells the editor how to preview embeds and which hosts to look for in pasted URLs', function (): void {
    $options = EmbedPlugin::make()->editorPreview(EditorPreview::Link)->withoutProviders(['flickr'])->getJsExtension()->getOptions();

    expect($options['preview'])->toBe('link')
        ->and($options['hosts'])->toContain('*.youtube.com', 'youtu.be', '*.x.com')->not->toContain('*.flickr.com')
        ->and($options['providers'])->toMatchArray(['youtube' => 'YouTube', 'x' => 'X'])->not->toHaveKey('flickr')
        ->and(EmbedPlugin::make()->getJsExtension()->getOptions()['preview'])->toBe('card');
});
