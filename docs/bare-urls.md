---
title: Bare URLs
weight: 60
description: Show paragraphs that hold nothing but a URL as that URL's embed, with BareUrlEmbedPlugin.
---

## What it does

`BareUrlEmbedPlugin` shows a paragraph that holds nothing but a URL as that URL's embed, as WordPress does. The URL
may be plain text, or a link to itself:

```html
<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>
<p><a href="https://vimeo.com/76979871">https://vimeo.com/76979871</a></p>
```

This makes content written before you added `EmbedPlugin`, imported from elsewhere, or typed without using the Embed
block, show its embeds.

**Only rendered content changes.** The stored content and the editor keep the paragraph as it is. A URL the provider
has nothing for (or a URL no provider takes) stays a paragraph.

## Usage

Add it next to `EmbedPlugin`, on the field and wherever the content is rendered, and point it at the embed plugin
with `using()`:

```php
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\BareUrlEmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

$embeds = EmbedPlugin::make()->privacyEnhancedYouTube();

// The field
RichEditorPlus::make('content')->addPlugins([
    $embeds,
    BareUrlEmbedPlugin::make()->using($embeds),
]);

// Rendering
RichContentPlusRenderer::make($post->content)
    ->plugins([...PluginRegistry::defaultPlugins(), $embeds, BareUrlEmbedPlugin::make()->using($embeds)])
    ->toHtml();
```

- **`using(EmbedPlugin $embeds)`**: the embed plugin whose providers and settings to use. Give it the same instance
  the field or renderer has. Without it, the plugin uses a new `EmbedPlugin::make()` with the defaults.
- It **requires** `EmbedPlugin` on the same field or renderer; the plugin list throws without it.
- It runs before `EmbedPlugin`, so the bare URLs are looked up together with the stored embeds, in one pooled request.

It adds nothing to the editor.

## Which paragraphs count

A paragraph becomes an embed when all of these hold:

- it holds exactly one piece of text, with nothing else (no other text, no line break, no image);
- the text, trimmed, is an absolute `http(s)` URL;
- the only mark on it, if any, is a link whose `href` is the same URL (so bold or italic URLs don't count);
- a provider of the embed plugin takes the URL;
- the provider's lookup finds something to embed.

Paragraphs inside quotes, lists and other blocks count too.

The embed it renders has no caption, and its title and thumbnail come from the provider's answer.

## Discovery is never used

`BareUrlEmbedPlugin` only embeds URLs that **providers** take, even when the embed plugin has
[discovery](plugin-options.md#discovery) on. A bare link to any site in a paragraph is common, and turning each one
into a server-side fetch of that site would be surprising. Use the Embed block for discovered sites.

## Performance

Paragraphs aren't nodes the renderer can patch in stored HTML, so with this plugin, stored **HTML** is parsed whole
when it is rendered, which is slower for long documents. Content stored as JSON is rendered from the document either
way. That is why this is a separate plugin: add it only where you want bare URLs embedded, and consider caching the
rendered HTML (see the [Rich Editor Plus docs](docs:rich-editor-plus/introduction)).

It works on fields that store Markdown too (see [Markdown](markdown.md)).
