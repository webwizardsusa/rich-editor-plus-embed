<?php

namespace Webwizardsusa\RichEditorPlusEmbed;

use Closure;
use DOMElement;
use Webwizardsusa\RichEditorPlus\Content\ContentSpec;
use Webwizardsusa\RichEditorPlus\Content\NodeSpec;
use Webwizardsusa\RichEditorPlus\Plugins\Contracts\AnswersEditorRequests;
use Webwizardsusa\RichEditorPlus\Plugins\Contracts\PatchesRenderedMarkup;
use Webwizardsusa\RichEditorPlus\Plugins\Plugin;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\BlockType;
use Webwizardsusa\RichEditorPlus\Support\JsExtension;
use Webwizardsusa\RichEditorPlus\Support\Tool;
use Webwizardsusa\RichEditorPlusEmbed\Actions\EmbedAction;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\EmbedCache;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\OEmbedClient;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\OEmbedResponse;
use Webwizardsusa\RichEditorPlusEmbed\OEmbed\Resolver;
use Webwizardsusa\RichEditorPlusEmbed\Providers\BuiltInProviders;
use Webwizardsusa\RichEditorPlusEmbed\Providers\Provider;
use Webwizardsusa\RichEditorPlusEmbed\Providers\ProviderRegistry;
use Webwizardsusa\RichEditorPlusEmbed\Rendering\EmbedMarkup;

/**
 * Embedded content from a URL, such as a video or a post, stored as
 * `<figure class="rep-embed" data-type="embed" data-url="…" data-provider="…" data-title="…" data-thumbnail="…"><a href="…">…</a><figcaption>…</figcaption></figure>`.
 *
 * Only the URL and a little metadata are stored; the provider's markup is built when content is rendered. The link
 * inside the figure keeps stored HTML readable where the plugin isn't loaded. The provider is worked out from the URL
 * each time, never taken from the stored HTML, and an embed whose URL no provider takes any more is kept as a link.
 * `resources/js/embed.js` defines the same node for the browser.
 *
 * For display, {@see prepareRenderedContents()} looks every embed in the batch up at once and hands the answer to the
 * node in its transient `oembed` attribute, which never reaches stored HTML. {@see EmbedMarkup} then rebuilds the
 * provider's markup from it; an embed without a usable answer is shown as its stored link.
 */
class EmbedPlugin extends Plugin implements AnswersEditorRequests, PatchesRenderedMarkup
{
    public const string ID = 'webwizardsusa/embed';

    /**
     * The most embeds the editor can ask to preview in one request.
     */
    public const int MAX_PREVIEWS = 20;

    protected EditorPreview $editorPreview = EditorPreview::Card;

    /**
     * @var array<Provider> | null
     */
    protected ?array $providers = null;

    /**
     * @var array<Provider>
     */
    protected array $addedProviders = [];

    /**
     * @var array<string>
     */
    protected array $removedProviders = [];

    protected ?int $maxWidth = null;

    protected bool $isPrivacyEnhancedYouTube = false;

    /**
     * Embeds from these providers instead of the built-in ones.
     *
     * @param  array<Provider>  $providers
     */
    public function providers(array $providers): static
    {
        $this->providers = $providers;

        return $this;
    }

    /**
     * Adds providers to the list; one with the name of a built-in provider replaces it.
     *
     * @param  array<Provider>  $providers
     */
    public function withProviders(array $providers): static
    {
        $this->addedProviders = [...$this->addedProviders, ...$providers];

        return $this;
    }

    /**
     * @param  array<string>  $names
     */
    public function withoutProviders(array $names): static
    {
        $this->removedProviders = [...$this->removedProviders, ...$names];

        return $this;
    }

    /**
     * The `maxwidth` sent to providers. Defaults to the `rich-editor-plus-embed.max_width` config value.
     */
    public function maxWidth(?int $width): static
    {
        $this->maxWidth = $width;

        return $this;
    }

    /**
     * Plays YouTube videos from `www.youtube-nocookie.com`, which sets no cookies until the video is played.
     */
    public function privacyEnhancedYouTube(bool $condition = true): static
    {
        $this->isPrivacyEnhancedYouTube = $condition;

        return $this;
    }

    public function isPrivacyEnhancedYouTube(): bool
    {
        return $this->isPrivacyEnhancedYouTube;
    }

    /**
     * How embeds look in the editor: a link, a card (the default), or the embed itself.
     */
    public function editorPreview(EditorPreview $preview): static
    {
        $this->editorPreview = $preview;

        return $this;
    }

    public function getEditorPreview(): EditorPreview
    {
        return $this->editorPreview;
    }

    public function getProviders(): ProviderRegistry
    {
        return ProviderRegistry::make($this->providers ?? BuiltInProviders::all())
            ->add($this->addedProviders)
            ->remove($this->removedProviders);
    }

    public function getMaxWidth(): ?int
    {
        $configured = config('rich-editor-plus-embed.max_width');

        return $this->maxWidth ?? (is_numeric($configured) ? (int) $configured : null);
    }

    public function getResolver(): Resolver
    {
        return new Resolver($this->getProviders(), OEmbedClient::fromConfig(), EmbedCache::fromConfig(), $this->getMaxWidth());
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * The attributes of an embed for the URL, from its provider's answer. Null when no provider takes the URL or its
     * provider has nothing to embed for it.
     *
     * @return array{url: string, provider: string, title: ?string, thumbnail: ?string} | null
     */
    public function resolveEmbed(string $url): ?array
    {
        $url = trim($url);
        $provider = static::isAllowedUrl($url) ? $this->getProviders()->match($url) : null;
        $answer = $provider === null ? null : $this->getResolver()->resolve($url);

        if ($answer === null) {
            return null;
        }

        return [
            'url' => $url,
            'provider' => $provider->getName(),
            'title' => $answer->title,
            'thumbnail' => $answer->thumbnailUrl,
        ];
    }

    /**
     * The display markup of each URL's embed, for the editor's {@see EditorPreview::Embed} preview: the inside of the
     * rendered figure. A post that needs its provider's script (X, Bluesky) answers null markup: those scripts don't
     * render inside a sandboxed frame and aren't run in the panel itself, so the editor shows its card. URLs that would
     * only show their link are left out.
     *
     * @param  array<mixed>  $urls
     * @return array<string, array{html: ?string}>
     */
    public function getEditorPreviews(array $urls): array
    {
        $urls = array_slice(array_values(array_unique(array_filter(
            array_map(static fn (mixed $url): ?string => static::isAllowedUrl($url) && strlen($url) <= ProviderRegistry::MAX_URL_LENGTH ? trim($url) : null, $urls),
        ))), 0, static::MAX_PREVIEWS);

        if ($urls === []) {
            return [];
        }

        // One pooled lookup for the lot; rendering each one below then reads the cache.
        $this->getResolver()->resolveMany($urls);

        $previews = [];

        foreach ($urls as $url) {
            $html = RichContentPlusRenderer::make(['type' => 'doc', 'content' => [['type' => 'embed', 'attrs' => ['url' => $url]]]])
                ->plugins([$this])
                ->toHtml();

            if (preg_match('#^<figure\b[^>]*\bdata-kind="[a-z]+"[^>]*>(.*)</figure>$#s', $html, $matches) === 1) {
                $previews[$url] = ['html' => str_contains($matches[1], '<script') ? null : $matches[1]];
            }
        }

        return $previews;
    }

    /**
     * `resolve` looks a URL up for the editor (pasting one, for example) and answers the embed's attributes or an
     * error. `previews` answers {@see getEditorPreviews()}, only when the field previews embeds. Neither changes
     * anything, so both are answered for a disabled field too.
     */
    public function answerEditorRequest(string $request, array $payload, RichEditorPlus $field): array
    {
        return match ($request) {
            'resolve' => $this->answerResolveRequest($payload['url'] ?? null),
            'previews' => $this->editorPreview === EditorPreview::Embed && is_array($payload['urls'] ?? null)
                ? ['previews' => $this->getEditorPreviews($payload['urls'])]
                : ['previews' => []],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function answerResolveRequest(mixed $url): array
    {
        if (! is_string($url) || strlen($url) > ProviderRegistry::MAX_URL_LENGTH || $this->getProviders()->match(trim($url)) === null) {
            return ['error' => __('rich-editor-plus-embed::plugins.embed.modal.url_unsupported')];
        }

        $embed = $this->resolveEmbed($url);

        return $embed === null
            ? ['error' => __('rich-editor-plus-embed::plugins.embed.modal.url_unavailable')]
            : ['embed' => $embed];
    }

    public function getActions(): array
    {
        return [EmbedAction::make($this)];
    }

    public function getBlockTypes(): array
    {
        return [
            BlockType::make('embed')
                ->label(__('rich-editor-plus-embed::plugins.embed.block'))
                ->description(__('rich-editor-plus-embed::plugins.embed.description'))
                ->icon('mdi-play-box-outline')
                ->keywords(['embed', 'oembed', 'video', 'audio', 'post', ...array_keys($this->getProviders()->all())])
                ->node('embed')
                ->action('embed')
                ->order(65),
        ];
    }

    public function getTools(): array
    {
        return [
            Tool::make('editEmbed')
                ->label(__('rich-editor-plus-embed::plugins.embed.edit'))
                ->icon('mdi-pencil-outline')
                ->action('embed')
                ->block()
                ->nodes(['embed'])
                ->order(10),
        ];
    }

    public function getNodes(): array
    {
        $data = static fn (string $name): Closure => static fn (DOMElement $element): ?string => trim($element->getAttribute("data-{$name}")) ?: null;

        $noAttributes = static fn (): array => [];

        $providers = $this->getProviders();
        $markup = new EmbedMarkup($this->isPrivacyEnhancedYouTube);

        return [
            NodeSpec::make('embed')
                ->atom()
                ->parseUsing(static fn (DOMElement $element): bool => strtolower($element->tagName) === 'figure'
                    && $element->getAttribute('data-type') === 'embed'
                    && static::isAllowedUrl($element->getAttribute('data-url')))
                ->attribute('url', parse: $data('url'), render: $noAttributes)
                ->attribute('provider', parse: static fn (DOMElement $element): ?string => $providers->match(trim($element->getAttribute('data-url')))?->getName(), render: $noAttributes)
                ->attribute('title', parse: $data('title'), render: $noAttributes)
                ->attribute('thumbnail', parse: $data('thumbnail'), render: $noAttributes)
                ->attribute('caption', parse: static fn (DOMElement $element): string => trim($element->getElementsByTagName('figcaption')->item(0)?->textContent ?? ''), render: $noAttributes, default: '')
                // The oEmbed answer as JSON, filled in for display only. Transient attributes are URL attributes in
                // core; JSON has no scheme, so it passes the check, and it is checked again as an answer when read.
                ->transientAttribute('oembed', ['http', 'https'])
                ->renderUsing(static fn (array $node, array $attributes): array => static::renderFigure(
                    is_array($node['attrs'] ?? null) ? $node['attrs'] : [],
                    $providers,
                    $markup,
                    is_string($attributes['oembed'] ?? null) ? $attributes['oembed'] : null,
                ))
                ->textUsing(static fn (array $node): string => static::isAllowedUrl($node['attrs']['url'] ?? null) ? trim($node['attrs']['url']) : ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getRenderedNodes(): array
    {
        return ['embed'];
    }

    /**
     * @return array<int, string>
     */
    public function getRenderedMarkupNodes(): array
    {
        return ['embed'];
    }

    /**
     * Gives every embed in the batch its oEmbed answer, looking each URL up once. An `oembed` attribute already in a
     * document came from the browser, so it is always dropped first.
     */
    public function prepareRenderedContents(array $documents): array
    {
        $urls = [];

        foreach ($documents as $document) {
            if (is_array($document)) {
                $this->collectUrls($document, $urls);
            }
        }

        $answers = $urls === [] ? [] : $this->getResolver()->resolveMany(array_keys($urls));

        foreach ($documents as $key => $document) {
            if (is_array($document)) {
                $documents[$key] = $this->fillAnswers($document, $answers);
            }
        }

        return $documents;
    }

    /**
     * CSS for rendered embeds: responsive players and the link shown when there's nothing to embed. Include it on every
     * page that shows the content.
     */
    public function getStylesheet(): string
    {
        return implode("\n", [
            '.rep-embed{margin:1rem 0;max-width:100%}',
            '.rep-embed-frame iframe{display:block;width:100%;max-width:100%;border:0}',
            '.rep-embed-frame[style*="aspect-ratio"] iframe{height:100%}',
            '.rep-embed[data-kind="photo"] img{display:block;max-width:100%;height:auto}',
            '.rep-embed blockquote{margin:0}',
            '.rep-embed figcaption{font-size:.875em;opacity:.8;margin-top:.375rem}',
        ]);
    }

    public function getJsExtension(): JsExtension
    {
        $providers = $this->getProviders()->all();
        $hosts = [];

        foreach ($providers as $provider) {
            $hosts = [...$hosts, ...$provider->getHosts()];
        }

        return JsExtension::make($this->getId(), [
            'preview' => $this->editorPreview->value,
            // Only for spotting pasted URLs worth asking about; the server decides what is embedded.
            'hosts' => array_values(array_unique($hosts)),
            'providers' => array_map(static fn (Provider $provider): string => $provider->getLabel(), $providers),
            'labels' => [
                'loading' => __('rich-editor-plus-embed::plugins.embed.editor.loading'),
                'unavailable' => __('rich-editor-plus-embed::plugins.embed.editor.unavailable'),
                'publishedOnly' => __('rich-editor-plus-embed::plugins.embed.editor.published_only'),
                'embedding' => __('rich-editor-plus-embed::plugins.embed.editor.embedding'),
            ],
        ])->module(RichEditorPlusEmbedServiceProvider::moduleUrl());
    }

    /**
     * An embed URL must be absolute http(s); any other scheme is refused, however it is disguised.
     */
    public static function isAllowedUrl(mixed $url): bool
    {
        return is_string($url)
            && preg_match('#^\s*https?://#i', $url) === 1
            && ContentSpec::isAllowedUrl($url, ['http', 'https']);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $urls
     */
    protected function collectUrls(array $node, array &$urls): void
    {
        if (($node['type'] ?? null) === 'embed' && static::isAllowedUrl($node['attrs']['url'] ?? null)) {
            $urls[trim($node['attrs']['url'])] = true;
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $this->collectUrls($child, $urls);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, ?OEmbedResponse>  $answers
     * @return array<string, mixed>
     */
    protected function fillAnswers(array $node, array $answers): array
    {
        if (($node['type'] ?? null) === 'embed') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            unset($attrs['oembed']);

            $answer = static::isAllowedUrl($attrs['url'] ?? null) ? ($answers[trim($attrs['url'])] ?? null) : null;

            if ($answer !== null) {
                $attrs['oembed'] = json_encode($answer->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }

            $node['attrs'] = $attrs;
        }

        if (is_array($node['content'] ?? null)) {
            foreach ($node['content'] as $index => $child) {
                if (is_array($child)) {
                    $node['content'][$index] = $this->fillAnswers($child, $answers);
                }
            }
        }

        return $node;
    }

    /**
     * An embed's figure. The attributes come from the browser, so each one is checked again here: a node without a
     * usable URL renders nothing, the provider is the one that takes the URL, and a thumbnail that isn't http(s) is
     * dropped. With an oEmbed answer (only while rendering for display) the figure holds the provider's markup;
     * without one, or when the answer can't be embedded safely, it holds the link that is stored.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<mixed>
     */
    protected static function renderFigure(array $attrs, ProviderRegistry $providers, EmbedMarkup $markup, ?string $oembed = null): array
    {
        if (! static::isAllowedUrl($attrs['url'] ?? null)) {
            return ['figure', ['class' => 'rep-embed', 'data-type' => 'embed']];
        }

        $url = trim($attrs['url']);
        $provider = $providers->match($url);
        $text = static fn (string $name): ?string => is_string($attrs[$name] ?? null) && trim($attrs[$name]) !== '' ? trim($attrs[$name]) : null;
        $title = $text('title');
        $caption = $text('caption');

        $figure = array_filter([
            'class' => 'rep-embed',
            'data-type' => 'embed',
            'data-url' => $url,
            'data-provider' => $provider?->getName(),
            'data-title' => $title,
            'data-thumbnail' => static::isAllowedUrl($attrs['thumbnail'] ?? null) ? trim($attrs['thumbnail']) : null,
        ], static fn (?string $value): bool => $value !== null);

        $decoded = $provider !== null && $oembed !== null ? json_decode($oembed, true) : null;
        $answer = is_array($decoded) ? OEmbedResponse::fromArray($decoded) : null;
        $display = $answer !== null ? $markup->build($provider, $answer, $url) : null;
        $captionElement = $caption !== null ? [['figcaption', $caption]] : [];

        if ($display === null) {
            return ['figure', $figure, ['a', ['href' => $url], $title ?? $url], ...$captionElement];
        }

        return ['figure', [...$figure, 'data-kind' => $display['kind']], ...$display['content'], ...$captionElement];
    }
}
