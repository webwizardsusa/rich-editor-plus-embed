---
title: Installation
weight: 20
description: Install the plugin, add it to a field, publish its assets and include the stylesheet where content is shown.
---

## Install the package

```bash
composer require webwizardsusa/rich-editor-plus-embed
```

This also installs [Laravel oEmbed](docs:laravel-oembed/introduction), which does the lookups. Both service providers
are registered by package discovery.

Publish the plugin's editor script and styles with Filament's asset command, as for any Filament plugin, and run it
again after every update:

```bash
php artisan filament:assets
```

## Add the plugin to a field

```php
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

RichEditorPlus::make('content')->addPlugins([
    EmbedPlugin::make(),
]);
```

The field now has an **Embed** block in its slash menu, embeds pasted URLs, and has an **Edit embed** tool on embeds.
The plugin's options, such as which providers to embed from and how embeds look while editing, are in
[Plugin options](plugin-options.md).

Use the same plugin settings wherever the content is rendered (see [Rendering](rendering.md)). A simple way is to
build the plugin in one place, such as a static method on your model or a small factory class, and use it for both the
field and the renderer.

## Lookups, stores and config

The plugin has no config file of its own. Lookups are configured by Laravel oEmbed's `config/oembed.php`: where
answers are kept, how long, the request timeout, the default `maxwidth`, and app-wide discovery and
privacy-enhanced YouTube. See:

- [Laravel oEmbed configuration](docs:laravel-oembed/configuration)
- [Stores](docs:laravel-oembed/usage/stores), to keep answers in a database table instead of the cache
  (`OEMBED_STORE=database`), and to prune it

Answers are kept in your default cache store unless you change it, for a week, and failed lookups for ten minutes.

## Translations (optional)

The plugin's labels and messages live in the `rich-editor-plus-embed` translation namespace. Publish them to change the
wording or add languages:

```bash
php artisan vendor:publish --tag=rich-editor-plus-embed-translations
```

## Include the stylesheet where content is shown

Rendered embeds need a little CSS: players that keep their aspect ratio, responsive photos and captions. The plugin
returns it from `getStylesheet()`; print it on every page that shows content with embeds:

```blade
<style>{!! \Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin::make()->getStylesheet() !!}</style>
```

The panel needs nothing more: the editor's styles come with the assets published by `filament:assets`.

## Content Security Policy

Lookups happen on the server, so they need nothing from a CSP. If your site sends a `Content-Security-Policy` header,
pages that show embeds must allow the providers' players, scripts and images, and so may your panel, depending on the
editor preview. See [Content Security Policy](rendering.md#content-security-policy).
