# Rich Editor Plus Embed

oEmbed embeds for [Rich Editor Plus](https://github.com/webwizardsusa/filament-rich-editor-plus): paste a YouTube,
Vimeo, X or other supported URL on an empty line, or choose Embed from the slash menu, and it becomes an embed. Only
the URL and a little metadata are stored; the provider's player, post or photo is rebuilt safely when content is
rendered. Lookups, providers, discovery and stores come from
[`webwizardsusa/laravel-oembed`](https://github.com/webwizardsusa/laravel-oembed), which is installed with it.

Requires PHP 8.3+ and Rich Editor Plus (Filament 5).

```bash
composer require webwizardsusa/rich-editor-plus-embed
php artisan filament:assets
```

```php
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlusEmbed\EditorPreview;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

RichEditorPlus::make('content')->addPlugins([
    EmbedPlugin::make()
        ->withoutProviders(['tiktok'])
        ->editorPreview(EditorPreview::Embed),
]);
```

## Documentation

Read the full documentation at
[https://DOCS_SITE_URL/docs/rich-editor-plus-embed](https://DOCS_SITE_URL/docs/rich-editor-plus-embed).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
