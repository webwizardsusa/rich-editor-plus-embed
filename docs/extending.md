---
title: Extending
weight: 80
description: Add providers, change providers for every field, and change the embed node in the browser.
---

## Custom providers

Providers come from [Laravel oEmbed](docs:laravel-oembed/introduction). Build one with `Provider::make()` and give it
to the plugin:

```php
use Webwizardsusa\OEmbed\Providers\Provider;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

$loom = Provider::make('loom')
    ->label('Loom')
    ->urls(['*.loom.com/share/*'])
    ->endpoint('https://www.loom.com/v1/oembed')
    ->iframe(['www.loom.com']);

EmbedPlugin::make()->withProviders([$loom]);
```

Everything about providers (name rules, URL patterns, endpoints with access tokens, `iframe()` versus `script()`, and
whether an answer will render) is in [Custom providers](docs:laravel-oembed/extending/custom-providers).

For the editor, a provider also gives:

- its **label**, shown on the card and link previews and in the Embed dialog's help text;
- its **name**, a slash-menu keyword (`/loom`) and the stored `data-provider`;
- its **hosts**, from the URL patterns, which the browser uses to decide whether a pasted URL is worth asking the
  server about.

Give the renderer the same providers as the field, or embeds from the added provider render as links.

## Providers for every field

The plugin's embedder is resolved from the container, so binding your own `Embedder` changes the providers and
defaults of every `EmbedPlugin`, on every field and renderer, as well as Laravel oEmbed's own facade and components.
See [App-wide providers and defaults](docs:laravel-oembed/extending/app-defaults).

## Another store per field

```php
use Webwizardsusa\OEmbed\Embedder;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

EmbedPlugin::make()->embedder(fn (Embedder $embedder) => $embedder->store('database'));
```

Writing a store of your own is covered in [Custom stores](docs:laravel-oembed/extending/custom-stores).

## Changing the editor node

The browser side of the plugin registers the JS extension `webwizardsusa/embed`, whose factory returns two TipTap
extensions: the `embed` node and `embedPaste`, which handles pasted URLs. Rich Editor Plus lets you decorate another
plugin's extensions with `extendExtension()`, without forking the plugin.

Write an ES module whose default export receives the editor's shared API:

```js
// public/js/embed-tweaks.js
export default function ({ extendExtension }) {
    extendExtension('webwizardsusa/embed', (extension) => {
        // The factory returns the embed node and the paste handler; change only the node.
        if (extension.name !== 'embed') {
            return extension
        }

        return extension.extend({
            draggable: false,
        })
    })
}
```

The decorator runs on every extension the factory produces and receives `{ options, tiptap }` as its second argument:
the options the PHP plugin sent (`preview`, `hosts`, `discovery`, `providers`, `labels`) and the editor's shared
TipTap modules. Return the extension to use, or nothing to keep it. Use the shared `tiptap` modules rather than
importing TipTap yourself, so everything uses the editor's one ProseMirror instance.

Attach the module to the plugin from a service provider's `boot()` method:

```php
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\PluginExtension;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;

RichEditorPlus::extendPlugin(EmbedPlugin::ID, fn (PluginExtension $extension) => $extension->addModule(asset('js/embed-tweaks.js')));
```

The editor imports the module once per page, before it builds an editor, on every field that has `EmbedPlugin`. See the
[Rich Editor Plus docs](docs:rich-editor-plus/introduction) for writing editor plugins and modules.

> [!WARNING]
> Keep the node's name, attributes, `parseHTML()` and `renderHTML()` as they are. The PHP plugin declares the same
> node and reads and writes the same stored HTML, so a node that stores something else won't read back on the server.
> Change behaviour (dragging, key bindings, the node view) rather than the stored shape.

## Editor requests

For reference, the plugin's JS asks the server two things through Rich Editor Plus's `callPlugin()`, answered by
`EmbedPlugin::answerEditorRequest()`:

| Request | Payload | Answer |
|---|---|---|
| `resolve` | `{ url }` | `{ embed: { url, provider, title, thumbnail } }`, or `{ error }` with the dialog's message |
| `previews` | `{ urls }` (at most 20 used) | `{ previews: { [url]: { html } } }`, only with `EditorPreview::Embed`; `html` is `null` for posts that need a script |

Both only read, so they are answered for disabled fields too. They run as a method of the field, under the page's own
authorization.

## Commands

The `embed` node adds two TipTap commands, which the Embed dialog sends:

- `insertEmbed(attrs)` inserts an embed at the selection.
- `updateEmbed(attrs)` changes the selected embed.

`attrs` are `url`, `provider`, `title`, `thumbnail` and `caption`. Unknown keys are dropped, a thumbnail that isn't
`http(s)` is dropped, and a command whose `url` isn't `http(s)` does nothing.
