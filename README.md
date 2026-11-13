# Rich Editor Plus Embed

oEmbed embeds for [Rich Editor Plus](https://github.com/webwizardsusa/filament-rich-editor-plus): paste a YouTube,
Vimeo, X or other supported URL on an empty line, or choose Embed from the slash menu, and it becomes an embed.

> Work in progress: the package works end to end, but hasn't been released yet.

```php
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

RichEditorPlus::make('content')->addPlugins([
    EmbedPlugin::make(),
]);
```

## In the editor

Type `/embed` (or `/youtube`, `/x`…) and enter a URL, or paste a URL a provider takes into an empty paragraph: it goes
in as a link at once and becomes the embed when the provider answers. Undo turns it back into the link; URLs that
can't be embedded stay links. Select an embed and use **Edit embed** on its toolbar to change the URL or caption.

Choose how embeds look while editing:

```php
use Webwizardsusa\RichEditorPlusEmbed\EditorPreview;

EmbedPlugin::make()->editorPreview(EditorPreview::Embed);
```

- `EditorPreview::Link`: the URL and the provider's name.
- `EditorPreview::Card` (the default): the thumbnail, title and provider stored with the embed. Only the thumbnail is
  loaded from the provider.
- `EditorPreview::Embed`: the embed as it's rendered: players and photos, rebuilt on the server exactly as for display.
  Posts that need their provider's script (X, Bluesky) show as their card instead, with a note: their scripts would
  have to run in the panel itself (they don't render in a sandboxed frame), and the plugin doesn't do that.

Click an embed once to select it; with `EditorPreview::Embed`, a selected player can then be played.

## Providers

YouTube, Vimeo, Spotify, SoundCloud, X, TikTok, Bluesky and Flickr are built in. None needs an account or a token.
Change the list per field:

```php
use Webwizardsusa\RichEditorPlusEmbed\Providers\Provider;

EmbedPlugin::make()
    ->withoutProviders(['tiktok'])
    ->withProviders([
        Provider::make('loom')
            ->label('Loom')
            ->urls(['*.loom.com/share/*'])
            ->endpoint('https://www.loom.com/v1/oembed')
            ->iframe(['www.loom.com']),
    ])
    ->maxWidth(640);
```

`providers([...])` replaces the built-in list. URL patterns are `host/path`: a host starting with `*.` matches the
domain and its subdomains, and `*` in the path matches anything. The endpoint must be https, and the client only ever
requests it (never the URL being embedded) and doesn't follow redirects. `iframe()` names the hosts the provider's
player may load from; `script()` is the script that turns a provider's `<blockquote>` into an embed.

Answers are cached (a week by default, ten minutes for a failed lookup) in the store set by
`RICH_EDITOR_PLUS_EMBED_CACHE_STORE`. Publish the config with
`php artisan vendor:publish --tag=rich-editor-plus-embed-config` to change the times, the timeout or the default
`maxwidth`.

## Showing embeds

Render content with the same plugin settings as the field, and include the plugin's CSS on every page that shows it:

```blade
<style>{!! \Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin::make()->getStylesheet() !!}</style>

{!! \Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer::make($post->body)
    ->plugins([\Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin::make()->privacyEnhancedYouTube()])
    ->toHtml() !!}
```

Every embed in the content (or in a `RichContentPlusBatchRenderer` batch) is looked up at once, from the cache where
possible. The provider's HTML is never output as it came: it is parsed and rebuilt.

- **Players** (YouTube, Vimeo, Spotify, SoundCloud, TikTok): the `<iframe>`, only if its `src` is https on one of the
  provider's `iframe()` hosts, with a fixed set of attributes. Videos keep their aspect ratio as they scale.
- **Posts** (X, Bluesky): the provider's `<blockquote>`, kept to text, links and a few inline tags, followed by the
  provider's own `script()`. A script in the answer is never used.
- **Photos** (Flickr): an `<img>` linking to the photo's page.
- Anything else, or a lookup that fails, shows the stored link.

`privacyEnhancedYouTube()` plays YouTube videos from `www.youtube-nocookie.com`.

### Content Security Policy

oEmbed lookups happen on the server, so they need nothing from a CSP. But pages that show embeds load the providers'
players, scripts and images in the browser. If your site sends a `Content-Security-Policy` header, allow the
providers you use. For the built-in ones:

| Provider | `frame-src` | `script-src` | `connect-src` | `img-src` |
|---|---|---|---|---|
| YouTube | `www.youtube.com`, `www.youtube-nocookie.com` | | | |
| Vimeo | `player.vimeo.com` | | | |
| Spotify | `open.spotify.com` | | | |
| SoundCloud | `w.soundcloud.com` | | | |
| TikTok | `www.tiktok.com` | | | |
| X | `platform.twitter.com`, `platform.x.com` | `platform.x.com`, `platform.twitter.com` | `syndication.twitter.com`, `cdn.syndication.twimg.com` | |
| Bluesky | `embed.bsky.app` | `embed.bsky.app` | | |
| Flickr | | | | `*.staticflickr.com` |

All of these are `https:`. For a provider you add, `frame-src` needs its `iframe()` hosts and `script-src` its
`script()` host. A provider's script may load more from its own domains: the X entries above are what its script
loaded when this was written, and providers do change them, so check the browser console for CSP errors after adding
or updating one.

The admin panel needs the same if it sends a CSP:

- With `EditorPreview::Card` (the default), cards show the thumbnail URL the provider answered, such as `i.ytimg.com`
  or `i.vimeocdn.com`. Allow those hosts in `img-src`, or allow `https:` images.
- With `EditorPreview::Embed`, the players load too, so the panel needs the `frame-src` hosts and the photo `img-src`
  hosts. No provider scripts run in the panel.

### Bare URLs

To show a paragraph that holds nothing but a URL (as text, or as a link to itself) as that URL's embed, as WordPress
does, add `BareUrlEmbedPlugin` next to `EmbedPlugin`, on the field and wherever the content is rendered:

```php
use Webwizardsusa\RichEditorPlusEmbed\BareUrlEmbedPlugin;

$embeds = EmbedPlugin::make()->privacyEnhancedYouTube();

RichContentPlusRenderer::make($post->body)
    ->plugins([$embeds, BareUrlEmbedPlugin::make()->using($embeds)])
    ->toHtml();
```

It uses the providers and settings of the embed plugin given to `using()`. Only rendered content changes: the stored
content and the editor keep the paragraph, and a URL the provider has nothing for stays a link. It makes content
written before the plugin was added, or pasted elsewhere, show its embeds.

Paragraphs aren't nodes the renderer can patch in stored HTML, so with this plugin stored HTML is parsed whole when
rendered, which is slower for long documents. That's why it's a separate plugin.

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

## What goes where

```
composer.json                 Requires webwizardsusa/filament-rich-editor-plus, registers the service provider
package.json                  Vite and the TipTap packages, as dev dependencies only
vite.config.js                `vite build` writes dist/; `vite` runs the dev server on port 5180
config/rich-editor-plus-embed.php   Cache, timeout and maxwidth for oEmbed lookups
src/
  RichEditorPlusEmbedServiceProvider.php   Config, translations, and the built assets via RegistersPluginAssets
  EmbedPlugin.php             The `embed` node: NodeSpec, JsExtension, and the field's providers
  Providers/                  Provider, the built-in list, and the registry that matches URLs to providers
  OEmbed/                     The HTTP client, the checked OEmbedResponse, the cache, and the Resolver tying them together
  Rendering/EmbedMarkup.php   Rebuilds a provider's markup for display from an oEmbed answer
  Actions/EmbedAction.php     The insert/edit modal
  EditorPreview.php           How embeds look in the editor: link, card or embed
  BareUrlEmbedPlugin.php      Optional: shows paragraphs holding only a URL as its embed
resources/
  js/index.js                 The module entry: registers the JS extension
  js/embed.js                 The TipTap node, its preview node view and the insert/update commands
  js/paste.js                 Pasting a URL into an empty paragraph embeds it
  js/previews.js              Batched requests for the `embed` editor preview
  js/urls.js                  URL checks shared by the node and paste handling
  css/index.css               Editor styles, built into dist/
  lang/en/plugins.php         Translations
tests/                        Pest tests, run with Testbench
```

## Testing

```bash
composer test
```
