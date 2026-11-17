---
title: Markdown
weight: 70
description: Using embeds on Rich Editor Plus fields that store Markdown, and how an embed is written in Markdown.
---

## Using embeds on a Markdown field

Embeds work on Rich Editor Plus fields that store Markdown, and so does [`BareUrlEmbedPlugin`](bare-urls.md). Add the
plugins as on any other field:

```php
use Webwizardsusa\RichEditorPlus\Plugins\Markdown\MarkdownPlugin;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlusEmbed\BareUrlEmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

$embeds = EmbedPlugin::make();

RichEditorPlus::make('content')->addPlugins([
    MarkdownPlugin::make()->store(),
    $embeds,
    BareUrlEmbedPlugin::make()->using($embeds),
]);
```

Render it with the Markdown format and the same plugins:

```php
use Webwizardsusa\RichEditorPlus\Plugins\Markdown\MarkdownFormat;
use Webwizardsusa\RichEditorPlus\Rendering\RichContentPlusRenderer;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;

RichContentPlusRenderer::make($post->content)
    ->plugins([...PluginRegistry::defaultPlugins(), $embeds, BareUrlEmbedPlugin::make()->using($embeds)])
    ->format(MarkdownFormat::make())
    ->toHtml();
```

See the [Rich Editor Plus docs](docs:rich-editor-plus/introduction) for Markdown storage itself.

## How an embed is written

Markdown has no syntax for an embed, so an embed is written as its stored `<figure>`, an HTML block on a line of its
own:

```markdown
Some text before.

<figure class="rep-embed" data-type="embed" data-url="https://youtu.be/abc" data-provider="youtube" data-title="A video"><a href="https://youtu.be/abc">A video</a><figcaption>Watch this</figcaption></figure>

Some text after.
```

- Markdown keeps raw HTML, so this reads back as the same embed, with its caption, title and thumbnail.
- Outside the editor, wherever Markdown is rendered without the plugin (GitHub, a static site generator), it shows as
  the link and caption.
- The figure is written on one line, because a blank line would end the HTML block. Line breaks and runs of
  whitespace in a title or caption become single spaces.
- Titles and captions are escaped, so markup in a caption stays text.
- An embed without a usable URL writes nothing.

## Writing embeds by hand

You can write an embed into Markdown yourself. Only `data-type="embed"` and an `http(s)` `data-url` are needed; the
provider is worked out from the URL:

```markdown
Intro

<figure data-type="embed" data-url="https://vimeo.com/76979871"></figure>
```

Or, with `BareUrlEmbedPlugin`, put the URL on a line of its own:

```markdown
Intro

https://vimeo.com/76979871
```
