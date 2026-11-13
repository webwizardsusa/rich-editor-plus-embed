<?php

use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Providers\Provider;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

function renderEmbed(string|array $content): string
{
    return RichContentPlusRenderer::make($content)
        ->plugins([EmbedPlugin::make()])
        ->parseHtml()
        ->toHtml();
}

/**
 * @param  array<string, mixed>  $attrs
 */
function embedDocument(array $attrs): array
{
    return ['type' => 'doc', 'content' => [['type' => 'embed', 'attrs' => $attrs]]];
}

it('keeps an embed, its metadata and caption through parsing and rendering', function (): void {
    $html = '<figure class="rep-embed" data-type="embed" data-url="https://www.youtube.com/watch?v=abc" data-provider="youtube" data-title="A video" data-thumbnail="https://i.ytimg.com/vi/abc/hqdefault.jpg"><a href="https://www.youtube.com/watch?v=abc">A video</a><figcaption>Watch this</figcaption></figure>';

    expect(renderEmbed($html))->toBe($html);
});

it('links to the URL when the embed has no title', function (): void {
    expect(renderEmbed(embedDocument(['url' => 'https://vimeo.com/1'])))
        ->toBe('<figure class="rep-embed" data-type="embed" data-url="https://vimeo.com/1" data-provider="vimeo"><a href="https://vimeo.com/1">https://vimeo.com/1</a></figure>');
});

it('works the provider out from the URL rather than trusting the stored one', function (): void {
    $html = '<figure data-type="embed" data-url="https://vimeo.com/1" data-provider="evil&quot; onload=&quot;x"></figure>';

    expect(renderEmbed($html))->toContain('data-provider="vimeo"')
        ->and(renderEmbed(embedDocument(['url' => 'https://youtu.be/a', 'provider' => 'vimeo'])))->toContain('data-provider="youtube"');
});

it('keeps an embed no provider takes any more as a link', function (): void {
    $html = '<figure data-type="embed" data-url="https://vimeo.com/1" data-provider="vimeo"></figure>';

    $rendered = RichContentPlusRenderer::make($html)
        ->plugins([EmbedPlugin::make()->withoutProviders(['vimeo'])])
        ->parseHtml()
        ->toHtml();

    expect($rendered)->toBe('<figure class="rep-embed" data-type="embed" data-url="https://vimeo.com/1"><a href="https://vimeo.com/1">https://vimeo.com/1</a></figure>');
});

it('embeds from the providers the field is given', function (): void {
    $plugin = EmbedPlugin::make()
        ->providers([Provider::make('videos')->urls(['videos.example.com/*'])])
        ->withProviders([Provider::make('clips')->urls(['clips.example.com/*'])]);

    expect(array_keys($plugin->getProviders()->all()))->toBe(['videos', 'clips']);
});

it('does not treat other figures, or embeds without an http(s) URL, as embeds', function (string $html): void {
    expect(renderEmbed($html))->not->toContain('data-type="embed"');
})->with([
    'a plain figure' => '<figure><a href="https://vimeo.com/1">Video</a></figure>',
    'a javascript URL' => '<figure data-type="embed" data-url="javascript:alert(1)"></figure>',
    'a relative URL' => '<figure data-type="embed" data-url="/videos/1"></figure>',
]);

it('drops a URL or thumbnail from the browser that is not http(s)', function (): void {
    expect(renderEmbed(embedDocument(['url' => ' JaVa script:alert(1)'])))->not->toContain('script')
        ->and(renderEmbed(embedDocument(['url' => 'https://vimeo.com/1', 'thumbnail' => 'data:image/png;base64,AAAA'])))
        ->not->toContain('data-thumbnail');
});

it('escapes the title and caption', function (): void {
    expect(renderEmbed(embedDocument(['url' => 'https://vimeo.com/1', 'title' => '<b>Hi</b>', 'caption' => '<script>x</script>'])))
        ->toContain('data-title="&lt;b&gt;Hi&lt;/b&gt;"')
        ->toContain('<figcaption>&lt;script&gt;x&lt;/script&gt;</figcaption>');
});
