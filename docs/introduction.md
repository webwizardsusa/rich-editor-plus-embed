---
title: Introduction
weight: 10
description: What Rich Editor Plus Embed adds to the editor, a short example, and what it needs.
---

Rich Editor Plus Embed adds embeds to [Rich Editor Plus](docs:rich-editor-plus/introduction): paste a YouTube,
Vimeo, X or other supported URL on an empty line, or choose **Embed** from the slash menu, and it becomes an embed.
When the content is rendered, the embed shows as the provider's player, post or photo.

```php
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

RichEditorPlus::make('content')->addPlugins([
    EmbedPlugin::make(),
]);
```

## How it works

The lookups, providers, discovery and safe rendering come from
[Laravel oEmbed](docs:laravel-oembed/introduction) (`webwizardsusa/laravel-oembed`), which Composer installs with this
package. This package adds the editor's side:

- an `embed` block, inserted from the slash menu or by pasting a URL, with an optional caption;
- an **Edit embed** tool to change an embed's URL or caption;
- three ways to show embeds while editing: a link, a card, or the embed itself;
- rendering for display, looking up every embed in a document (or a batch of documents) at once;
- Markdown support, for fields that store Markdown;
- an optional plugin that shows paragraphs holding only a URL as that URL's embed.

Only the URL and a little metadata are stored. The provider's markup is built when content is rendered, and rebuilt
from an allowlist rather than printed as it came, so stored content never holds third-party HTML. See
[Rendering](rendering.md).

## Built-in providers

YouTube, Vimeo, Spotify, SoundCloud, X, TikTok, Bluesky and Flickr. None needs an account or a token. You can remove
any of them or [add your own](extending.md#custom-providers).

## Requirements

- PHP 8.3 or later
- Rich Editor Plus 0.1 (and so Filament 5)
- Laravel oEmbed, installed automatically

## Where to go next

- [Installation](installation.md)
- [Plugin options](plugin-options.md), for providers, previews and discovery
- [Editing embeds](editing-embeds.md), for what editors see and do
- [Rendering](rendering.md), for showing content on your site
