<?php

use Webwizardsusa\RichEditorPlus\Plugins\Markdown\MarkdownFormat;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\BareUrlEmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

beforeEach(fn (): mixed => PluginRegistry::flush());

function markdownRegistry(): PluginRegistry
{
    return PluginRegistry::make([...PluginRegistry::defaultPlugins(), EmbedPlugin::make()]);
}

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function embedNode(array $attrs): array
{
    return ['type' => 'embed', 'attrs' => [
        'url' => null, 'provider' => null, 'title' => null, 'thumbnail' => null, 'caption' => '', ...$attrs,
    ]];
}

function paragraphNode(string $text): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
}

it('writes an embed as its stored figure, an HTML block on its own line', function (): void {
    $document = ['type' => 'doc', 'content' => [
        paragraphNode('Before'),
        embedNode(['url' => 'https://youtu.be/abc', 'provider' => 'youtube', 'title' => 'A video', 'caption' => 'Watch this']),
        paragraphNode('After'),
    ]];

    expect(MarkdownFormat::make()->fromDocument($document, markdownRegistry()))->toBe(
        "Before\n\n"
        .'<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube" data-title="A video"><a href="https://youtu.be/abc">A video</a><figcaption>Watch this</figcaption></figure>'
        ."\n\nAfter\n",
    );
});

it('reads its Markdown back as the same embeds, in a quote too', function (): void {
    $registry = markdownRegistry();
    $document = ['type' => 'doc', 'content' => [
        embedNode(['url' => 'https://www.youtube.com/watch?v=abc&list=x', 'provider' => 'youtube', 'title' => 'A "video" & more', 'thumbnail' => 'https://i.ytimg.com/vi/abc/hq.jpg', 'caption' => 'Watch <this>']),
        ['type' => 'blockquote', 'content' => [
            paragraphNode('Quoted'),
            embedNode(['url' => 'https://vimeo.com/1', 'provider' => 'vimeo']),
        ]],
    ]];

    $markdown = MarkdownFormat::make()->fromDocument($document, $registry);

    expect(MarkdownFormat::make()->toDocument($markdown, $registry))->toEqual($document)
        ->and(MarkdownFormat::make()->fromDocument(MarkdownFormat::make()->toDocument($markdown, $registry), $registry))->toBe($markdown);
});

it('keeps a caption with blank lines and markup inside the HTML block, escaped', function (): void {
    $registry = markdownRegistry();
    $document = ['type' => 'doc', 'content' => [
        embedNode(['url' => 'https://youtu.be/abc', 'provider' => 'youtube', 'caption' => "First line\n\n<script>alert(1)</script>"]),
    ]];

    $markdown = MarkdownFormat::make()->fromDocument($document, $registry);

    expect($markdown)->toBe('<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube"><a href="https://youtu.be/abc">https://youtu.be/abc</a><figcaption>First line &lt;script&gt;alert(1)&lt;/script&gt;</figcaption></figure>'."\n")
        ->and(MarkdownFormat::make()->toDocument($markdown, $registry)['content'][0]['attrs']['caption'])->toBe('First line <script>alert(1)</script>');
});

it('writes nothing for an embed without a usable URL, and never reads one from Markdown', function (): void {
    $registry = markdownRegistry();

    expect(MarkdownFormat::make()->fromDocument(['type' => 'doc', 'content' => [embedNode(['url' => 'javascript:alert(1)'])]], $registry))->toBe('')
        ->and(MarkdownFormat::make()->toDocument('<figure data-type="embed" data-url="javascript:alert(1)"></figure>', $registry)['content'] ?? [])
        ->each->not->toMatchArray(['type' => 'embed']);
});

it('reads an embed figure written into Markdown by hand', function (): void {
    expect(MarkdownFormat::make()->toDocument("Intro\n\n<figure data-type=\"embed\" data-url=\"https://vimeo.com/1\"></figure>\n", markdownRegistry())['content'][1])
        ->toBe(embedNode(['url' => 'https://vimeo.com/1', 'provider' => 'vimeo']));
});

it('can be used on a Markdown field, bare URLs included', function (): void {
    $embeds = EmbedPlugin::make();
    $plugins = [$embeds, BareUrlEmbedPlugin::make()->using($embeds)];

    MarkdownFormat::make()->assertSupports($plugins);

    expect(MarkdownFormat::make()->unsupportedPlugins($plugins))->toBe([]);
});
