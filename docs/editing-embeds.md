---
title: Editing embeds
weight: 40
description: Inserting embeds from the slash menu or by pasting, editing their URL and caption, and how they look in the editor.
---

## Inserting an embed

### From the slash menu

Type `/embed` in an empty paragraph (or `/youtube`, `/x`, `/video`, `/post`: every provider's name is a keyword)
and choose **Embed**. It opens the Embed dialog:

- **URL**: required, an `http(s)` URL of at most 2048 characters. Its help text lists the providers the field embeds
  from ("A link to a page on YouTube, Vimeo, …"), and mentions other sites that support oEmbed when
  [discovery](plugin-options.md#discovery) is on.
- **Caption**: optional, at most 500 characters.

Submitting looks the URL up. A URL no provider takes is refused with "This site can't be embedded."; a URL the
provider has nothing for (a deleted or private video, say) with "Nothing could be embedded from this URL. Check that
the page exists and is public." Otherwise the embed is inserted at the cursor with the title and thumbnail the provider
answered.

### By pasting

Paste a URL into an **empty paragraph** and it becomes an embed, as in WordPress:

1. The URL goes in at once, as a link, so the editor never waits.
2. The server looks it up (the editor shows "Embedding…" meanwhile).
3. If it can be embedded, the link becomes the embed.

The swap is its own undo step: undo turns the embed back into the link. URLs that can't be embedded stay links.

Only plain text that is a single `http(s)` URL counts, pasted with nothing selected into a paragraph with nothing in
it, in a place where a block can stand (not, for example, a table cell that only takes text). Without discovery, the
browser only asks about URLs on a provider's host; with discovery, it asks about any URL. The server decides either
way. If the paragraph has been edited before the answer arrives, it is left as it is.

## Editing an embed

Click an embed to select it, then use **Edit embed** on its block toolbar. The same dialog opens with the embed's URL
and caption filled in. Submitting looks the URL up again and updates the embed's URL, title, thumbnail and caption.

Embeds are blocks: they can be dragged, moved, copied and deleted like any other block.

The block's name is `embed`, and the tool's name is `editEmbed`, for when you arrange the toolbar or hide tools on a
field (see the [Rich Editor Plus docs](docs:rich-editor-plus/introduction)).

## Captions

A caption is plain text, shown under the embed in the editor and in rendered content (in a `<figcaption>`). Set it in
the Embed dialog. It is escaped wherever it is shown, so markup in a caption shows as text.

## How embeds look while editing

Choose with [`editorPreview()`](plugin-options.md#editorpreview):

### `EditorPreview::Link`

The provider's name and the URL. Nothing is loaded from the provider. Use it when editors don't need to see the
content, or the panel's CSP allows nothing from providers.

### `EditorPreview::Card` (the default)

The thumbnail, the provider's name, the title and the URL, all from what was stored with the embed when it was
inserted. Only the thumbnail image is loaded from the provider, without a referrer. A card doesn't change when the
provider's title changes; editing the embed refreshes it.

### `EditorPreview::Embed`

The embed as it renders on your site: the player or photo, rebuilt on the server from the provider's answer exactly as
for display. While it loads, the embed shows "Loading embed…".

- **Click once to select** the embed; a selected player can then be played. A shield over the player catches the first
  click, so clicking a video doesn't start it while you're arranging content.
- **Posts** that need their provider's script (X, Bluesky) show as their card, with the note "The post shows on the
  published page". Their scripts aren't run in the panel.
- An embed that can't be shown (the lookup fails, or the provider no longer takes the URL) shows its card with the
  warning "This embed can't be shown".

The editor asks for the previews of all embeds on screen in batches of up to 20, once per URL per page load.

## Read-only fields

A disabled field still shows its embeds in the chosen preview: the lookups it makes don't change anything, so they are
answered for a disabled field too.

## Text output

Where Rich Editor Plus converts content to plain text, an embed becomes its URL.
