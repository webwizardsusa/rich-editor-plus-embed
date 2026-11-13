<?php

namespace Webwizardsusa\RichEditorPlusEmbed;

/**
 * How embeds look inside the editor. Rendered content always shows the embed itself.
 */
enum EditorPreview: string
{
    /**
     * The URL as a link, with the provider's name.
     */
    case Link = 'link';

    /**
     * A card with the thumbnail, title and provider stored with the embed. Nothing is loaded from the provider but the
     * thumbnail.
     */
    case Card = 'card';

    /**
     * The embed as it is rendered: the provider's player or the photo. Posts that need the provider's script (X,
     * Bluesky) show as their card, because their scripts are not run in the panel.
     */
    case Embed = 'embed';
}
