---
title: Rendering
weight: 50
description: Showing content with embeds on your site, how embeds are stored and rendered, and the Content Security Policy they need.
---

## Rendering content

Render content with Rich Editor Plus's renderer and the **same plugins and settings as the field**, and include the
plugin's stylesheet on every page that shows it:

```php
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

$embeds = EmbedPlugin::make()->privacyEnhancedYouTube();

$html = RichContentPlusRenderer::make($post->content)
    ->plugins([...PluginRegistry::defaultPlugins(), $embeds])
    ->toHtml();
```

```blade
<style>{!! $embeds->getStylesheet() !!}</style>

{!! $html !!}
```

`plugins()` sets the renderer's whole plugin list, so include the editor's default plugins and any others the field
has. See the [Rich Editor Plus docs](docs:rich-editor-plus/introduction) for the renderer itself, including when
stored HTML is trusted.

Every embed in the content is looked up at once: stored answers from the
[store](docs:laravel-oembed/usage/stores), and the rest in one pooled request.

### Many documents at once

To render a list, such as posts on an index page, use the batch renderer. Every embed in every document is looked up
together, once per URL:

```php
$html = RichContentPlusRenderer::many($posts->pluck('content', 'id'))
    ->plugins([...PluginRegistry::defaultPlugins(), $embeds])
    ->toHtml();   // [id => html]
```

## What is rendered

Each embed becomes a `<figure>`, rebuilt from the provider's answer. The provider's HTML is never output as it came:

- **Players** (YouTube, Vimeo, Spotify, SoundCloud, TikTok): the `<iframe>`, only if its `src` is https on one of the
  provider's `iframe()` hosts, with a fixed set of attributes. Videos keep their aspect ratio as they scale.
- **Posts** (X, Bluesky): the provider's `<blockquote>`, kept to text, links and a few inline tags, followed by the
  provider's own `script()`. A script in the answer is never used.
- **Photos** (Flickr): an `<img>` linking to the photo's page.
- **Anything else**, or a lookup that fails, shows the stored link (and caption).

```html
<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube"
        data-title="A video" data-kind="video">
  <div class="rep-embed-frame" style="aspect-ratio: 640 / 360">
    <iframe src="https://www.youtube.com/embed/abc?feature=oembed" width="640" height="360" title="A video"
            loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
  </div>
  <figcaption>Watch this</figcaption>
</figure>
```

The rules are Laravel oEmbed's; see [How embeds are rendered](docs:laravel-oembed/security/rendering) for every
detail. The figure keeps the `rep-embed` class and `data-*` attributes of the stored embed, and `data-kind` is
`video`, `rich` or `photo` when the embed rendered (a link has none).

The provider is always worked out from the URL when rendering, never taken from the stored `data-provider`, so
editing stored HTML can't make one provider's URL render with another's rules.

## How embeds are stored

Only the URL and a little metadata are stored. The provider's markup is built when content is rendered, so stored
content never holds third-party HTML:

```html
<figure class="rep-embed" data-type="embed" data-url="https://www.youtube.com/watch?v=…" data-provider="youtube"
        data-title="…" data-thumbnail="https://i.ytimg.com/…">
  <a href="https://www.youtube.com/watch?v=…">…</a>
  <figcaption>An optional caption</figcaption>
</figure>
```

- The link inside keeps stored HTML readable anywhere the plugin isn't loaded, such as in a feed or an email.
- `data-provider`, `data-title` and `data-thumbnail` are what the editor's card shows. They are left out when empty.
- A thumbnail or URL that isn't `http(s)` is dropped, and a figure without a usable URL isn't read as an embed.

`toStorableHtml()` writes the same, never the rendered player.

## Styling

`getStylesheet()` returns the CSS for rendered embeds:

| Selector | |
|---|---|
| `.rep-embed` | Every embed's figure. |
| `.rep-embed[data-kind="photo"] img` | A photo. |
| `.rep-embed-frame` | The wrapper around a player, with an inline `aspect-ratio` for videos of known size. |
| `.rep-embed blockquote` | A post, before its script turns it into a widget. |
| `.rep-embed figcaption` | The caption. |

Add your own rules for these classes to change how embeds look on your site.

## Content Security Policy

oEmbed lookups happen on the server, so they need nothing from a CSP. Pages that show embeds load the providers'
players, scripts and images in the browser. If your site sends a `Content-Security-Policy` header, allow the
providers you use: Laravel oEmbed's [Content Security Policy](docs:laravel-oembed/security/content-security-policy)
page lists the hosts for each built-in provider. For a provider you add, `frame-src` needs its `iframe()` hosts and
`script-src` its `script()` host.

The admin panel needs the same if it sends a CSP, depending on the [editor preview](plugin-options.md#editorpreview):

- With `EditorPreview::Link`, nothing.
- With `EditorPreview::Card` (the default), cards show the thumbnail URL the provider answered, such as `i.ytimg.com`
  or `i.vimeocdn.com`. Allow those hosts in `img-src`, or allow `https:` images.
- With `EditorPreview::Embed`, the players load too, so the panel needs the `frame-src` hosts and the photo `img-src`
  hosts. No provider scripts run in the panel.
