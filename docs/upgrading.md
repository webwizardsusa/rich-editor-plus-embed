---
title: Upgrading
weight: 90
description: Upgrading from 0.3 to 0.4, which moves lookups to Laravel oEmbed.
---

## From 0.3 to 0.4

The providers, the oEmbed client, the cache, discovery, the private-network guard and the markup builder moved to
[`webwizardsusa/laravel-oembed`](docs:laravel-oembed/introduction), which Composer installs with this package.
`EmbedPlugin` keeps its methods and delegates them to a Laravel oEmbed `Embedder`.

### Unchanged

- `EmbedPlugin`'s methods: `providers()`, `withProviders()`, `withoutProviders()`, `maxWidth()`,
  `privacyEnhancedYouTube()`, `editorPreview()`, `discovery()` and the getters.
- `BareUrlEmbedPlugin` and `EditorPreview`.
- The stored and rendered HTML, the editor and Markdown. Stored content needs no migration.

### Namespaces

If your code builds providers or uses the oEmbed classes directly, update the imports:

| 0.3 | 0.4 |
|---|---|
| `Webwizardsusa\RichEditorPlusEmbed\Providers\Provider` | `Webwizardsusa\OEmbed\Providers\Provider` |
| `Webwizardsusa\RichEditorPlusEmbed\Providers\BuiltInProviders` | `Webwizardsusa\OEmbed\Providers\BuiltInProviders` |
| `Webwizardsusa\RichEditorPlusEmbed\Providers\ProviderRegistry` | `Webwizardsusa\OEmbed\Providers\ProviderRegistry` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\OEmbedClient` | `Webwizardsusa\OEmbed\OEmbedClient` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\OEmbedResponse` | `Webwizardsusa\OEmbed\OEmbedResponse` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\Resolver` | `Webwizardsusa\OEmbed\Resolver` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\Discovery` | `Webwizardsusa\OEmbed\Discovery` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\UrlGuard` | `Webwizardsusa\OEmbed\UrlGuard` |
| `Webwizardsusa\RichEditorPlusEmbed\Rendering\EmbedMarkup` | `Webwizardsusa\OEmbed\Rendering\EmbedMarkup` |
| `Webwizardsusa\RichEditorPlusEmbed\OEmbed\EmbedCache` | Removed: answers are kept in [stores](docs:laravel-oembed/usage/stores) |

`EmbedPlugin::getResolver()` now returns `Webwizardsusa\OEmbed\Resolver`. If you rebound `UrlGuard` in tests, bind
the new class.

### Config

`config/rich-editor-plus-embed.php` is replaced by Laravel oEmbed's `config/oembed.php`. If you published the old
config, publish the new one, copy your values over, and delete the old file:

```bash
php artisan vendor:publish --tag=oembed-config
```

| 0.3 key | 0.4 |
|---|---|
| `rich-editor-plus-embed.cache.store` | `oembed.cache.store` |
| `rich-editor-plus-embed.cache.ttl` | `oembed.ttl` |
| `rich-editor-plus-embed.cache.failure_ttl` | `oembed.failure_ttl` |
| `rich-editor-plus-embed.timeout` | `oembed.timeout` |
| `rich-editor-plus-embed.max_width` | `oembed.max_width` |

Rename the environment variable too:

```env
# Before
RICH_EDITOR_PLUS_EMBED_CACHE_STORE=redis

# After
OEMBED_CACHE_STORE=redis
```

The new config also has keys that apply to every embedder in the app, including Laravel oEmbed's own facade and
components: `store`, `database.*`, `discovery` and `privacy_enhanced_youtube`. Turning on `oembed.discovery` turns
discovery on for every `EmbedPlugin` that doesn't set it. See the
[configuration reference](docs:laravel-oembed/configuration).

### Cache

Cache keys have a new prefix (`oembed:` instead of `rich-editor-plus-embed:`), so every URL is looked up once more
after upgrading. The old entries expire on their own.

### New

- `EmbedPlugin::embedder()` reaches settings without a method of their own, such as the store (see
  [Plugin options](plugin-options.md#embedder)).
- Answers can be kept in a database table, or a store of your own (see
  [Stores](docs:laravel-oembed/usage/stores)).
- Providers and defaults can be changed for the whole app by binding an `Embedder` (see
  [App-wide providers and defaults](docs:laravel-oembed/extending/app-defaults)).
