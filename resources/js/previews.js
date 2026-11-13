// Must match `EmbedPlugin::MAX_PREVIEWS`: the server answers at most this many URLs per request.
const MAX_PREVIEWS = 20

/**
 * The display markup of embeds, for the `embed` editor preview, asked from `EmbedPlugin::getEditorPreviews()`. URLs
 * asked for in the same tick go in one request, and each answer is kept for the page's lifetime. A URL the server
 * has no preview for resolves to null, and a post shown only on the published page to `{ html: null }`.
 */
export function createPreviewCache(callPlugin, pluginId) {
    const answers = new Map()
    let pending = new Map()

    const flush = () => {
        const batch = pending
        const urls = [...batch.keys()]

        pending = new Map()

        for (let start = 0; start < urls.length; start += MAX_PREVIEWS) {
            const chunk = urls.slice(start, start + MAX_PREVIEWS)

            Promise.resolve(callPlugin ? callPlugin(pluginId, 'previews', { urls: chunk }) : {})
                .catch(() => ({}))
                .then((answer) => {
                    for (const url of chunk) {
                        const preview = answer?.previews?.[url]

                        batch.get(url)(preview ? { html: typeof preview.html === 'string' ? preview.html : null } : null)
                    }
                })
        }
    }

    return {
        get(url) {
            if (!answers.has(url)) {
                answers.set(
                    url,
                    new Promise((resolve) => {
                        if (pending.size === 0) {
                            queueMicrotask(flush)
                        }

                        pending.set(url, resolve)
                    }),
                )
            }

            return answers.get(url)
        },
    }
}
