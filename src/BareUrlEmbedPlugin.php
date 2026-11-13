<?php

namespace Webwizardsusa\RichEditorPlusEmbed;

use Webwizardsusa\RichEditorPlus\Plugins\Contracts\PreparesRenderedContent;
use Webwizardsusa\RichEditorPlus\Plugins\Plugin;
use Webwizardsusa\RichEditorPlus\Support\JsExtension;

/**
 * Shows a paragraph that holds nothing but a URL a provider takes as that URL's embed, as WordPress does. The URL
 * may be plain text or a link to itself. Only rendering for display changes: the stored content and the editor keep
 * the paragraph, and a URL the provider has nothing for stays as it is.
 *
 * It needs {@see EmbedPlugin} on the same field or renderer, and uses its providers and settings (or those of the
 * plugin given to {@see using()}). It runs before it, so the embeds it makes are looked up with the others.
 *
 * Paragraphs aren't nodes the renderer can patch in stored HTML, so with this plugin stored HTML is parsed whole when
 * rendered. That is slower for long documents, which is why it's a separate plugin.
 */
class BareUrlEmbedPlugin extends Plugin implements PreparesRenderedContent
{
    public const string ID = 'webwizardsusa/embed-bare-urls';

    protected ?EmbedPlugin $embeds = null;

    /**
     * The embed plugin whose providers and settings to use. Give it the same one the field or renderer has.
     */
    public function using(EmbedPlugin $embeds): static
    {
        $this->embeds = $embeds;

        return $this;
    }

    public function getEmbedPlugin(): EmbedPlugin
    {
        return $this->embeds ??= EmbedPlugin::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getDependencies(): array
    {
        return [EmbedPlugin::ID];
    }

    /**
     * Before {@see EmbedPlugin} (the default 100), so it looks the new embeds up together with the stored ones.
     */
    public function getPriority(): int
    {
        return 90;
    }

    /**
     * Nothing changes in the editor; the module registers an empty extension for this id.
     */
    public function getJsExtension(): JsExtension
    {
        return JsExtension::make($this->getId())->module(RichEditorPlusEmbedServiceProvider::moduleUrl());
    }

    public function prepareRenderedContents(array $documents): array
    {
        $urls = [];

        foreach ($documents as $document) {
            if (is_array($document)) {
                $this->collectBareUrls($document, $urls);
            }
        }

        if ($urls === []) {
            return $documents;
        }

        $embeds = $this->getEmbedPlugin();
        $providers = $embeds->getProviders();
        $answers = $embeds->getResolver()->resolveMany(array_keys($urls));

        foreach ($documents as $key => $document) {
            if (is_array($document)) {
                $documents[$key] = $this->replaceBareUrls($document, static function (string $url) use ($answers, $providers): ?array {
                    $answer = $answers[$url] ?? null;
                    $provider = $providers->match($url);

                    if ($answer === null || $provider === null) {
                        return null;
                    }

                    return ['type' => 'embed', 'attrs' => [
                        'url' => $url,
                        'provider' => $provider->getName(),
                        'title' => $answer->title,
                        'thumbnail' => $answer->thumbnailUrl,
                        'caption' => '',
                    ]];
                });
            }
        }

        return $documents;
    }

    /**
     * The URL a paragraph holds and nothing else, as plain text or as a link to itself, when a provider takes it.
     *
     * @param  array<string, mixed>  $node
     */
    protected function bareUrl(array $node): ?string
    {
        if (($node['type'] ?? null) !== 'paragraph' || ! is_array($node['content'] ?? null) || count($node['content']) !== 1) {
            return null;
        }

        $text = $node['content'][0];

        if (! is_array($text) || ($text['type'] ?? null) !== 'text' || ! is_string($text['text'] ?? null)) {
            return null;
        }

        $url = trim($text['text']);

        foreach (is_array($text['marks'] ?? null) ? $text['marks'] : [] as $mark) {
            $isLinkToItself = is_array($mark) && ($mark['type'] ?? null) === 'link' && trim((string) ($mark['attrs']['href'] ?? '')) === $url;

            if (! $isLinkToItself) {
                return null;
            }
        }

        if (! EmbedPlugin::isAllowedUrl($url) || $this->getEmbedPlugin()->getProviders()->match($url) === null) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $urls
     */
    protected function collectBareUrls(array $node, array &$urls): void
    {
        $url = $this->bareUrl($node);

        if ($url !== null) {
            $urls[$url] = true;

            return;
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $this->collectBareUrls($child, $urls);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(string): ?array<string, mixed>  $embedFor
     * @return array<string, mixed>
     */
    protected function replaceBareUrls(array $node, callable $embedFor): array
    {
        $url = $this->bareUrl($node);

        if ($url !== null) {
            return $embedFor($url) ?? $node;
        }

        if (is_array($node['content'] ?? null)) {
            foreach ($node['content'] as $index => $child) {
                if (is_array($child)) {
                    $node['content'][$index] = $this->replaceBareUrls($child, $embedFor);
                }
            }
        }

        return $node;
    }
}
