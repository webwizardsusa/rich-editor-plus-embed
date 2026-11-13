<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\Resolver;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\UrlGuard;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    PluginRegistry::flush();

    // Every blog.example.com name is public; nothing does a real DNS lookup.
    $this->app->instance(UrlGuard::class, new UrlGuard(static fn (string $host): array => str_ends_with($host, 'example.com') ? ['93.184.216.34'] : []));
});

/**
 * Pages on blog.example.com that name their oEmbed endpoint, which answers with the given markup.
 */
function fakeDiscoverableBlog(string $html, string $type = 'rich'): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://blog.example.com/oembed*' => Http::response(['type' => $type, 'title' => 'A post', 'html' => $html], 200, ['Content-Type' => 'application/json']),
        'https://blog.example.com/*' => fn (Request $request) => Http::response(
            '<html><head><link rel="alternate" type="application/json+oembed" href="/oembed?url='.urlencode($request->url()).'"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);
}

function renderDiscovered(string $url, ?EmbedPlugin $plugin = null): string
{
    return RichContentPlusRenderer::make('<figure class="rep-embed" data-type="embed" data-url="'.e($url).'"><a href="'.e($url).'">'.e($url).'</a></figure>')
        ->plugins([$plugin ?? EmbedPlugin::make()->discovery()])
        ->toHtml();
}

it('does not look up sites no provider takes unless discovery is on', function (): void {
    fakeDiscoverableBlog('<iframe src="https://blog.example.com/embed/1"></iframe>');

    expect(RichEditorPlus::make('body')->addPlugins([EmbedPlugin::make()])->answerPluginRequestForJs(EmbedPlugin::ID, 'resolve', ['url' => 'https://blog.example.com/posts/1']))
        ->toHaveKey('error')
        ->and(renderDiscovered('https://blog.example.com/posts/1', EmbedPlugin::make()))->not->toContain('<iframe');

    Http::assertNothingSent();
});

it('resolves a discovered page for the editor, with no provider name', function (): void {
    fakeDiscoverableBlog('<iframe src="https://blog.example.com/embed/1"></iframe>');

    expect(RichEditorPlus::make('body')->addPlugins([EmbedPlugin::make()->discovery()])->answerPluginRequestForJs(EmbedPlugin::ID, 'resolve', ['url' => 'https://blog.example.com/posts/1']))
        ->toBe(['embed' => ['url' => 'https://blog.example.com/posts/1', 'provider' => null, 'title' => 'A post', 'thumbnail' => null]]);
});

it('shows a discovered player from the page\'s own host, sandboxed', function (): void {
    fakeDiscoverableBlog('<iframe src="https://blog.example.com/embed/1" width="600" height="300"></iframe>');

    expect(renderDiscovered('https://blog.example.com/posts/1'))->toBe(
        '<figure class="rep-embed" data-type="embed" data-url="https://blog.example.com/posts/1" data-kind="rich"><div class="rep-embed-frame">'
        .'<iframe src="https://blog.example.com/embed/1" width="600" height="300" title="A post" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation"></iframe>'
        .'</div></figure>',
    );
});

it('shows the link for discovered markup it cannot trust', function (string $html): void {
    fakeDiscoverableBlog($html);

    expect(renderDiscovered('https://blog.example.com/posts/1'))->not->toContain('<iframe')->not->toContain('<script')->toContain('<a href="https://blog.example.com/posts/1">');
})->with([
    'a player from another host' => '<iframe src="https://evil.example.net/embed/1"></iframe>',
    'a player from a subdomain' => '<iframe src="https://cdn.blog.example.com/embed/1"></iframe>',
    'a post that needs a script' => '<blockquote class="post">Hi</blockquote><script src="https://blog.example.com/embed.js"></script>',
]);

it('discovers at most a few uncached pages per render, and caches what it found', function (): void {
    fakeDiscoverableBlog('<iframe src="https://blog.example.com/embed/1"></iframe>');

    $urls = array_map(static fn (int $index): string => "https://blog.example.com/posts/{$index}", range(1, Resolver::MAX_DISCOVERIES + 2));
    $resolver = EmbedPlugin::make()->discovery()->getResolver();

    $first = $resolver->resolveMany($urls);
    $pagesFetched = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/posts/'))->count();
    $second = $resolver->resolveMany($urls);

    expect(array_filter($first))->toHaveCount(Resolver::MAX_DISCOVERIES)
        ->and($pagesFetched)->toBe(Resolver::MAX_DISCOVERIES)
        ->and(array_filter($second))->toHaveCount(count($urls));
});

it('tells the editor to treat any pasted URL as a candidate', function (): void {
    expect(EmbedPlugin::make()->discovery()->getJsExtension()->getOptions()['discovery'])->toBeTrue()
        ->and(EmbedPlugin::make()->getJsExtension()->getOptions()['discovery'])->toBeFalse();
});
