<?php

use Illuminate\Support\Facades\Http;
use Webwizardsusa\RichEditorPlus\Plugins\Core\BlockquotePlugin;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusBatchRenderer;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

function storedEmbed(string $url, string $caption = ''): string
{
    $figcaption = $caption === '' ? '' : '<figcaption>'.e($caption).'</figcaption>';

    return '<figure class="rep-embed" data-type="embed" data-url="'.e($url).'"><a href="'.e($url).'">'.e($url).'</a>'.$figcaption.'</figure>';
}

function displayEmbed(string|array $content, ?EmbedPlugin $plugin = null): string
{
    return RichContentPlusRenderer::make($content)->plugins([$plugin ?? EmbedPlugin::make(), BlockquotePlugin::make()])->toHtml();
}

/**
 * @param  array<string, mixed>  $answer
 */
function fakeYouTube(array $answer): void
{
    Http::preventStrayRequests();
    Http::fake(['www.youtube.com/oembed*' => Http::response($answer)]);
}

function youtubeVideo(string $html): array
{
    return ['type' => 'video', 'title' => 'A video', 'width' => 640, 'height' => 360, 'html' => $html];
}

it('shows a video as the provider\'s player, rebuilt with only the attributes it allows', function (): void {
    fakeYouTube(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc?feature=oembed" width="640" height="360" onload="steal()" style="position:fixed" allow="autoplay; camera; encrypted-media" allowfullscreen></iframe>'));

    expect(displayEmbed(storedEmbed('https://youtu.be/abc', 'Watch this')))->toBe(
        '<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube" data-kind="video">'
        .'<div class="rep-embed-frame" style="aspect-ratio: 640 / 360">'
        .'<iframe src="https://www.youtube.com/embed/abc?feature=oembed" width="640" height="360" title="A video" allow="autoplay; encrypted-media" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>'
        .'</div><figcaption>Watch this</figcaption></figure>',
    );
});

it('plays YouTube from the privacy-enhanced host when asked to', function (): void {
    fakeYouTube(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc"></iframe>'));

    expect(displayEmbed(storedEmbed('https://youtu.be/abc'), EmbedPlugin::make()->privacyEnhancedYouTube()))
        ->toContain('src="https://www.youtube-nocookie.com/embed/abc"');
});

it('keeps a portrait video no wider than it is', function (): void {
    fakeYouTube(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc" width="325" height="578"></iframe>'));

    expect(displayEmbed(storedEmbed('https://youtu.be/abc')))->toContain('style="aspect-ratio: 325 / 578; max-width: 325px"');
});

it('shows the link instead of a player the provider may not load', function (string $html): void {
    fakeYouTube(youtubeVideo($html));

    expect(displayEmbed(storedEmbed('https://youtu.be/abc')))
        ->toBe('<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube"><a href="https://youtu.be/abc">https://youtu.be/abc</a></figure>');
})->with([
    'another host' => '<iframe src="https://evil.example/embed/abc"></iframe>',
    'a host that only starts with the provider\'s' => '<iframe src="https://www.youtube.com.evil.example/embed/abc"></iframe>',
    'credentials before the host' => '<iframe src="https://www.youtube.com@evil.example/embed/abc"></iframe>',
    'http' => '<iframe src="http://www.youtube.com/embed/abc"></iframe>',
    'a javascript URL' => '<iframe src="javascript:alert(1)"></iframe>',
    'a script instead of an iframe' => '<script src="https://www.youtube.com/x.js"></script>',
]);

it('shows a post as the provider\'s blockquote and its own script, never markup or scripts from the answer', function (): void {
    Http::preventStrayRequests();
    Http::fake(['publish.x.com/oembed*' => Http::response([
        'type' => 'rich',
        'html' => '<blockquote class="twitter-tweet" data-width="550" onclick="steal()" style="color:red"><p lang="en" dir="ltr" onmouseover="steal()">Hello <img src="x" onerror="steal()">world <a href="javascript:steal()">bad</a> <a href="https://x.com/jack/status/20">good</a></p><script>steal()</script></blockquote><script async src="https://evil.example/widgets.js"></script>',
    ])]);

    expect(displayEmbed(storedEmbed('https://x.com/jack/status/20')))->toBe(
        '<figure class="rep-embed" data-type="embed" data-url="https://x.com/jack/status/20" data-provider="x" data-kind="rich">'
        .'<blockquote class="twitter-tweet" data-width="550"><p lang="en" dir="ltr">Hello world <a>bad</a> <a href="https://x.com/jack/status/20">good</a></p></blockquote>'
        .'<script async="" src="https://platform.x.com/widgets.js" charset="utf-8"></script></figure>',
    );
});

it('shows a photo linked to its page', function (): void {
    Http::preventStrayRequests();
    Http::fake(['www.flickr.com/services/oembed/*' => Http::response([
        'type' => 'photo', 'title' => 'Bees "&" <b>', 'url' => 'https://live.staticflickr.com/1.jpg', 'width' => 800, 'height' => 533,
    ])]);

    expect(displayEmbed(storedEmbed('https://www.flickr.com/photos/bees/1')))->toBe(
        '<figure class="rep-embed" data-type="embed" data-url="https://www.flickr.com/photos/bees/1" data-provider="flickr" data-kind="photo">'
        .'<a href="https://www.flickr.com/photos/bees/1"><img src="https://live.staticflickr.com/1.jpg" alt="Bees &quot;&amp;&quot; &lt;b&gt;" width="800" height="533" loading="lazy"></a></figure>',
    );
});

it('shows the stored link when the lookup fails', function (): void {
    Http::preventStrayRequests();
    Http::fake(['www.youtube.com/oembed*' => Http::response([], 404)]);

    expect(displayEmbed(storedEmbed('https://youtu.be/abc')))
        ->toBe('<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube"><a href="https://youtu.be/abc">https://youtu.be/abc</a></figure>');
});

it('ignores an oEmbed answer that came with the document', function (): void {
    Http::preventStrayRequests();
    Http::fake(['www.youtube.com/oembed*' => Http::response([], 404)]);

    $document = ['type' => 'doc', 'content' => [['type' => 'embed', 'attrs' => [
        'url' => 'https://youtu.be/abc',
        'oembed' => json_encode(youtubeVideo('<iframe src="https://www.youtube.com/embed/attacker"></iframe>')),
    ]]]];

    expect(displayEmbed($document))->not->toContain('iframe')
        ->and(RichContentPlusRenderer::make($document)->plugins([EmbedPlugin::make()])->toStorableHtml())->not->toContain('oembed');
});

it('never writes display markup to stored HTML', function (): void {
    fakeYouTube(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc"></iframe>'));

    $document = RichContentPlusRenderer::make(storedEmbed('https://youtu.be/abc'))->plugins([EmbedPlugin::make()])->parseHtml()->toArray();

    expect(RichContentPlusRenderer::make($document)->plugins([EmbedPlugin::make()])->toStorableHtml())
        ->toBe('<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube"><a href="https://youtu.be/abc">https://youtu.be/abc</a></figure>');
});

it('looks each URL up once for a whole batch of documents', function (): void {
    fakeYouTube(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc"></iframe>'));

    $rendered = RichContentPlusBatchRenderer::make([
        'first' => storedEmbed('https://youtu.be/abc').'<p>Between</p>'.storedEmbed('https://youtu.be/abc'),
        'second' => '<blockquote>'.storedEmbed('https://youtu.be/abc').'</blockquote>',
        'none' => '<p>No embeds</p>',
    ])->plugins([EmbedPlugin::make(), BlockquotePlugin::make()])->toHtml();

    expect(substr_count($rendered['first'], '<iframe'))->toBe(2)
        ->and($rendered['second'])->toContain('<iframe')
        ->and($rendered['none'])->toBe('<p>No embeds</p>');

    Http::assertSentCount(1);
});

it('renders patched stored HTML exactly as the parse path and the document do', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'www.youtube.com/oembed*' => Http::response(youtubeVideo('<iframe src="https://www.youtube.com/embed/abc"></iframe>')),
        'vimeo.com/api/oembed.json*' => Http::response([], 404),
    ]);

    $stored = '<p>Before</p>'.storedEmbed('https://youtu.be/abc', 'A & B').'<blockquote>'.storedEmbed('https://vimeo.com/1').'</blockquote>';
    $plugins = [EmbedPlugin::make(), BlockquotePlugin::make()];
    $document = RichContentPlusRenderer::make($stored)->plugins($plugins)->parseHtml()->toArray();

    expect(RichContentPlusRenderer::make($stored)->plugins($plugins)->toHtml())
        ->toContain('<iframe')
        ->toBe(RichContentPlusRenderer::make($stored)->plugins($plugins)->parseHtml()->toHtml())
        ->toBe(RichContentPlusRenderer::make($document)->plugins($plugins)->toHtml());
});
