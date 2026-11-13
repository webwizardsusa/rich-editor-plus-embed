<?php

use Illuminate\Support\Facades\Http;
use Webwizardsusa\RichEditorPlus\Plugins\Core\BoldPlugin;
use Webwizardsusa\RichEditorPlus\Plugins\Core\LinkPlugin;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusBatchRenderer;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlusEmbed\BareUrlEmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Providers\Provider;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

function renderWithBareUrls(string $html, ?EmbedPlugin $embeds = null): string
{
    $embeds ??= EmbedPlugin::make();

    return RichContentPlusRenderer::make($html)
        ->plugins([$embeds, BareUrlEmbedPlugin::make()->using($embeds), LinkPlugin::make(), BoldPlugin::make()])
        ->toHtml();
}

function fakeBareUrlProviders(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'www.youtube.com/oembed*' => Http::response([
            'type' => 'video', 'title' => 'A video', 'thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg',
            'html' => '<iframe src="https://www.youtube.com/embed/abc"></iframe>',
        ]),
        'vimeo.com/api/oembed.json*' => Http::response([], 404),
    ]);
}

it('shows a paragraph holding only a provider\'s URL as its embed', function (string $paragraph): void {
    fakeBareUrlProviders();

    expect(renderWithBareUrls("<p>Before</p>{$paragraph}<p>After</p>"))->toBe(
        '<p>Before</p>'
        .'<figure class="rep-embed" data-type="embed" data-url="https://www.youtube.com/watch?v=abc&amp;list=x" data-provider="youtube" data-title="A video" data-thumbnail="https://i.ytimg.com/vi/abc/hq.jpg" data-kind="video">'
        .'<div class="rep-embed-frame"><iframe src="https://www.youtube.com/embed/abc" title="A video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div></figure>'
        .'<p>After</p>',
    );
})->with([
    'as text' => '<p> https://www.youtube.com/watch?v=abc&amp;list=x </p>',
    'as a link to itself' => '<p><a href="https://www.youtube.com/watch?v=abc&amp;list=x" target="_blank" rel="noopener noreferrer nofollow">https://www.youtube.com/watch?v=abc&amp;list=x</a></p>',
]);

it('leaves paragraphs that hold more than a provider\'s URL as they are', function (string $paragraph): void {
    fakeBareUrlProviders();

    expect(renderWithBareUrls($paragraph))->not->toContain('rep-embed');
})->with([
    'a URL in a sentence' => '<p>Watch https://youtu.be/abc now</p>',
    'a link with other text' => '<p><a href="https://youtu.be/abc">this video</a></p>',
    'URL text linking elsewhere' => '<p><a href="https://evil.example/">https://youtu.be/abc</a></p>',
    'a formatted URL' => '<p><strong>https://youtu.be/abc</strong></p>',
    'a site no provider takes' => '<p>https://example.com/page</p>',
]);

it('leaves the paragraph as it is when the provider has nothing to embed', function (): void {
    fakeBareUrlProviders();

    expect(renderWithBareUrls('<p>https://vimeo.com/1</p>'))->toBe('<p>https://vimeo.com/1</p>');
});

it('only changes rendered content, never what is stored', function (): void {
    fakeBareUrlProviders();

    $embeds = EmbedPlugin::make();

    expect(RichContentPlusRenderer::make('<p>https://youtu.be/abc</p>')->plugins([$embeds, BareUrlEmbedPlugin::make()->using($embeds)])->toStorableHtml())
        ->toBe('<p>https://youtu.be/abc</p>');
});

it('looks bare URLs up together with the embeds already stored, once per URL', function (): void {
    fakeBareUrlProviders();
    $embeds = EmbedPlugin::make();

    $rendered = RichContentPlusBatchRenderer::make([
        'first' => '<p>https://youtu.be/abc</p><figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc"></figure>',
        'second' => '<p>https://youtu.be/abc</p><p>https://vimeo.com/1</p>',
    ])->plugins([$embeds, BareUrlEmbedPlugin::make()->using($embeds)])->toHtml();

    expect(substr_count($rendered['first'], '<iframe'))->toBe(2)
        ->and(substr_count($rendered['second'], '<iframe'))->toBe(1);

    Http::assertSentCount(2);
});

it('uses the providers of the embed plugin it is given', function (): void {
    Http::preventStrayRequests();
    Http::fake(['videos.example.com/oembed*' => Http::response(['type' => 'video', 'html' => '<iframe src="https://videos.example.com/embed/1"></iframe>'])]);

    $embeds = EmbedPlugin::make()->providers([
        Provider::make('videos')->urls(['videos.example.com/*'])->endpoint('https://videos.example.com/oembed')->iframe(['videos.example.com']),
    ]);

    expect(renderWithBareUrls('<p>https://videos.example.com/1</p>', $embeds))->toContain('<iframe src="https://videos.example.com/embed/1"');
});

it('needs the embed plugin', function (): void {
    RichContentPlusRenderer::make('<p>https://youtu.be/abc</p>')->plugins([BareUrlEmbedPlugin::make()])->toHtml();
})->throws(LogicException::class, EmbedPlugin::ID);
