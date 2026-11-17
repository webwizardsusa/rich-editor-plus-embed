---
title: Plugin options
weight: 30
description: Every EmbedPlugin option - providers, maxwidth, privacy-enhanced YouTube, the editor preview, discovery and the embedder.
---

`EmbedPlugin` is configured with fluent methods:

| Method | Default |
|---|---|
| [`providers()`, `withProviders()`, `withoutProviders()`](#providers) | The built-in providers |
| [`maxWidth(?int $width)`](#maxwidth) | `oembed.max_width` (800) |
| [`privacyEnhancedYouTube(bool $condition = true)`](#privacyenhancedyoutube) | `oembed.privacy_enhanced_youtube` (off) |
| [`editorPreview(EditorPreview $preview)`](#editorpreview) | `EditorPreview::Card` |
| [`discovery(bool $condition = true)`](#discovery) | `oembed.discovery` (off) |
| [`embedder(Embedder\|Closure $embedder)`](#embedder) | A new embedder with the app's defaults |

The lookup settings start from Laravel oEmbed's [config](docs:laravel-oembed/configuration), so a value you set there
applies to every field, and a method here changes it for this plugin only.

Give the renderer the same settings as the field, so the same URLs are embedded and their stored answers are reused
(see [Rendering](rendering.md)).

## Providers

YouTube, Vimeo, Spotify, SoundCloud, X, TikTok, Bluesky and Flickr are built in. Change the list per field:

```php
use Webwizardsusa\OEmbed\Providers\Provider;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

EmbedPlugin::make()
    ->withoutProviders(['tiktok'])
    ->withProviders([
        Provider::make('loom')
            ->label('Loom')
            ->urls(['*.loom.com/share/*'])
            ->endpoint('https://www.loom.com/v1/oembed')
            ->iframe(['www.loom.com']),
    ]);
```

| Method | |
|---|---|
| `providers(array $providers)` | Embeds from these providers instead of the built-in ones. |
| `withProviders(array $providers)` | Adds providers. One with the name of a built-in provider replaces it. |
| `withoutProviders(array $names)` | Removes providers by name, such as `['tiktok', 'x']`. |

Providers are `Webwizardsusa\OEmbed\Providers\Provider` objects from Laravel oEmbed; see
[Built-in providers](docs:laravel-oembed/usage/providers) for their names and URL patterns, and
[Custom providers](docs:laravel-oembed/extending/custom-providers) for writing one.

The provider list decides which pasted URLs the editor asks about, which URLs the Embed dialog accepts, and which
stored embeds render. The provider names also become slash-menu keywords, so `/youtube` or `/x` finds the Embed block.

An embed whose URL no provider takes any more (because you removed its provider, say) stays in the content and renders
as its link.

## `maxWidth()`

```php
EmbedPlugin::make()->maxWidth(640);
```

The `maxwidth` sent to providers, in pixels, which they use to size their players. `null` sends none. Defaults to
`oembed.max_width` (800). Answers are stored per `maxwidth`, so the field and the renderer should agree.

## `privacyEnhancedYouTube()`

```php
EmbedPlugin::make()->privacyEnhancedYouTube();
```

Plays YouTube videos from `www.youtube-nocookie.com`, which sets no cookies until the video is played. Only rendered
players change; what is stored stays the same, so you can turn it on or off at any time.

## `editorPreview()`

How embeds look **inside the editor**. Rendered content always shows the embed itself.

```php
use Webwizardsusa\RichEditorPlusEmbed\EditorPreview;

EmbedPlugin::make()->editorPreview(EditorPreview::Embed);
```

| Value | The editor shows |
|---|---|
| `EditorPreview::Link` | The URL and the provider's name. |
| `EditorPreview::Card` (the default) | A card with the thumbnail, title and provider stored with the embed. Only the thumbnail is loaded from the provider. |
| `EditorPreview::Embed` | The embed as it renders: the player or photo, built on the server exactly as for display. |

With `EditorPreview::Embed`, posts that need their provider's script (X, Bluesky) show as their card instead, with the
note "The post shows on the published page". Their scripts would have to run in the panel itself, and the plugin
doesn't do that. An embed that can't be shown any more shows its card with a warning.

See [Editing embeds](editing-embeds.md#how-embeds-look-while-editing) for how each one behaves, and
[Content Security Policy](rendering.md#content-security-policy) for what the panel then needs to allow.

## `discovery()`

```php
EmbedPlugin::make()->discovery();
```

Also embeds URLs no provider takes, through the oEmbed endpoint their page names, as WordPress sites and many others
do. Off by default (unless `oembed.discovery` is on), because the server then fetches pages that people link to.

With discovery on:

- the editor asks the server about any pasted URL, not only those on a provider's host;
- the Embed dialog accepts any site that supports oEmbed, and says so in its help text;
- a discovered player shows only when it comes from the page's own host, in a sandbox; posts that need a script
  show as their link.

Every request goes through Laravel oEmbed's private-network guard. Read
[Discovery](docs:laravel-oembed/security/discovery) for the limits, including the five pages discovered per render.

[`BareUrlEmbedPlugin`](bare-urls.md) never uses discovery, so a bare link to any site doesn't make the server fetch
it.

## `embedder()`

The plugin keeps its lookup settings on a Laravel oEmbed `Embedder`. Settings without a method on the plugin, such
as the store, go through `embedder()`:

```php
use Webwizardsusa\OEmbed\Embedder;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

EmbedPlugin::make()->embedder(fn (Embedder $embedder) => $embedder->store('database'));
```

It takes a closure, called straight away with the plugin's embedder to change it, or an `Embedder` instance to use
instead:

```php
use Webwizardsusa\OEmbed\Facades\OEmbed;

EmbedPlugin::make()->embedder(OEmbed::discovery()->maxWidth(640));
```

The plugin's other methods (`providers()`, `maxWidth()`, `discovery()` and the rest) change whichever embedder it has,
so call `embedder($instance)` first when you combine them.

The plugin's embedder starts from the app's defaults. To change the providers or defaults of every field and renderer
at once, bind your own embedder in the container: see
[App-wide providers and defaults](docs:laravel-oembed/extending/app-defaults).

## Getters

For code that needs to know the plugin's settings: `getProviders()` (a `ProviderRegistry`), `getMaxWidth()`,
`isPrivacyEnhancedYouTube()`, `discovers()`, `getEditorPreview()`, `getEmbedder()` and `getStylesheet()`.
`takes($url)` answers whether the plugin would look a URL up, and `resolveEmbed($url)` looks it up and returns the
attributes an embed would store, or `null`.
