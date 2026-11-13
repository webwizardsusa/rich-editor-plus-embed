import { Node } from '@tiptap/core'
import { NodeSelection } from '@tiptap/pm/state'
import { createPreviewCache } from './previews'
import { isAllowedUrl } from './urls'

export const PREVIEWS = ['link', 'card', 'embed']

const text = (value) => (typeof value === 'string' && value.trim() !== '' ? value.trim() : null)

const url = (value) => (isAllowedUrl(value) ? value.trim() : null)

const isEmbedSelected = (state) => state.selection instanceof NodeSelection && state.selection.node.type.name === 'embed'

/**
 * Keeps only the attributes the node has, each in the shape PHP accepts, so commands cannot store an unsafe URL.
 */
export const clean = (attrs) => {
    const cleaners = {
        url,
        provider: text,
        title: text,
        thumbnail: url,
        caption: (value) => text(value) ?? '',
    }

    return Object.fromEntries(
        Object.entries(attrs ?? {})
            .filter(([key]) => key in cleaners)
            .map(([key, value]) => [key, cleaners[key](value)]),
    )
}

/**
 * The browser half of `EmbedPlugin`: an atom block holding a URL and the metadata shown on its preview. The name,
 * tag and attributes match what the PHP plugin declares, and `renderHTML()` writes the same stored HTML.
 *
 * `preview` (PHP: `EmbedPlugin::editorPreview()`) picks how the node view shows it: `link`, `card` (thumbnail, title
 * and provider from the stored attributes), or `embed`, the rendered embed asked from the server.
 */
export function createEmbed({ preview = 'card', providers = {}, labels = {} } = {}, { callPlugin } = {}) {
    const mode = PREVIEWS.includes(preview) ? preview : 'card'
    const previews = mode === 'embed' ? createPreviewCache(callPlugin, 'webwizardsusa/embed') : null
    const providerLabel = (name) => (typeof name === 'string' ? (providers[name] ?? null) : null)

    const dataAttribute = (name, parse = text) => ({
        default: null,
        parseHTML: (element) => parse(element.getAttribute(`data-${name}`)),
        // The figure is written whole in `renderHTML()`, as PHP does.
        renderHTML: () => ({}),
    })

    const element = (tag, className, content = null) => {
        const node = document.createElement(tag)

        node.className = className

        if (content !== null) {
            node.textContent = content
        }

        return node
    }

    const linkPreview = (attrs) => {
        const link = element('div', 'rep-embed-link')
        const label = providerLabel(attrs.provider)

        if (label !== null) {
            link.append(element('span', 'rep-embed-provider', label))
        }

        link.append(element('span', 'rep-embed-url', url(attrs.url) ?? ''))

        return link
    }

    const cardPreview = (attrs, note = null, isWarning = false) => {
        const card = element('div', 'rep-embed-card')
        const thumbnail = url(attrs.thumbnail)

        if (thumbnail !== null) {
            const img = document.createElement('img')

            img.className = 'rep-embed-thumbnail'
            img.src = thumbnail
            img.alt = ''
            img.loading = 'lazy'
            img.referrerPolicy = 'no-referrer'
            card.append(img)
        }

        const body = element('div', 'rep-embed-card-body')
        const label = providerLabel(attrs.provider)

        if (label !== null) {
            body.append(element('span', 'rep-embed-provider', label))
        }

        body.append(element('span', 'rep-embed-title', text(attrs.title) ?? url(attrs.url) ?? ''))
        body.append(element('span', 'rep-embed-url', url(attrs.url) ?? ''))

        if (note !== null) {
            body.append(element('span', isWarning ? 'rep-embed-note rep-embed-note-warning' : 'rep-embed-note', note))
        }

        card.append(body)

        return card
    }

    return Node.create({
        name: 'embed',

        group: 'block',

        atom: true,

        draggable: true,

        selectable: true,

        addAttributes() {
            return {
                url: dataAttribute('url', url),
                provider: dataAttribute('provider'),
                title: dataAttribute('title'),
                thumbnail: dataAttribute('thumbnail', url),
                caption: {
                    default: '',
                    parseHTML: (element) => element.querySelector('figcaption')?.textContent.trim() ?? '',
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'figure[data-type="embed"]',
                    getAttrs: (element) => (isAllowedUrl(element.getAttribute('data-url')) ? null : false),
                },
            ]
        },

        // Same attributes, in the same order, as `EmbedPlugin::renderFigure()` writes them for storage.
        renderHTML({ node }) {
            const href = url(node.attrs.url)

            if (href === null) {
                return ['figure', { class: 'rep-embed', 'data-type': 'embed' }]
            }

            const title = text(node.attrs.title)
            const caption = text(node.attrs.caption)
            const figure = { class: 'rep-embed', 'data-type': 'embed', 'data-url': href }

            for (const [name, value] of [
                ['provider', text(node.attrs.provider)],
                ['title', title],
                ['thumbnail', url(node.attrs.thumbnail)],
            ]) {
                if (value !== null) {
                    figure[`data-${name}`] = value
                }
            }

            return ['figure', figure, ['a', { href }, title ?? href], ...(caption !== null ? [['figcaption', caption]] : [])]
        },

        renderText({ node }) {
            return url(node.attrs.url) ?? ''
        },

        addNodeView() {
            return ({ node: initial }) => {
                let node = initial
                let renders = 0
                const dom = document.createElement('figure')

                const render = () => {
                    const current = ++renders

                    // `classList` keeps ProseMirror's `ProseMirror-selectednode` when a selected embed changes.
                    dom.classList.add('rep-embed')
                    dom.dataset.type = 'embed'
                    dom.dataset.preview = mode
                    dom.contentEditable = 'false'

                    const caption = text(node.attrs.caption)
                    const figcaption = caption === null ? null : element('figcaption', '', caption)
                    const show = (content) => dom.replaceChildren(content, ...(figcaption ? [figcaption] : []))

                    if (mode === 'link') {
                        show(linkPreview(node.attrs))

                        return
                    }

                    if (mode === 'card' || url(node.attrs.url) === null) {
                        show(cardPreview(node.attrs))

                        return
                    }

                    show(element('div', 'rep-embed-loading', labels.loading ?? 'Loading embed…'))

                    previews.get(url(node.attrs.url)).then((answer) => {
                        // A later render owns the figure now.
                        if (current !== renders) {
                            return
                        }

                        if (answer === null) {
                            show(cardPreview(node.attrs, labels.unavailable ?? 'This embed can’t be shown', true))

                            return
                        }

                        if (answer.html === null) {
                            show(cardPreview(node.attrs, labels.publishedOnly ?? 'The post shows on the published page'))

                            return
                        }

                        // A shield over the embed lets a click select the node; once selected, the embed can be used.
                        // The markup is what the server rebuilt from the provider's answer (`EmbedMarkup`): a player
                        // from the provider's own host or a photo, never a script.
                        const frame = element('div', 'rep-embed-preview')

                        frame.innerHTML = answer.html
                        frame.append(element('div', 'rep-embed-shield'))
                        show(frame)
                    })
                }

                render()

                return {
                    dom,
                    update(updated) {
                        if (updated.type.name !== 'embed') {
                            return false
                        }

                        const changed = Object.keys(updated.attrs).some((key) => updated.attrs[key] !== node.attrs[key])

                        node = updated

                        if (changed) {
                            render()
                        }

                        return true
                    },
                    ignoreMutation: () => true,
                }
            }
        },

        addCommands() {
            return {
                // Sent by `EmbedAction`.
                insertEmbed:
                    (attrs) =>
                    ({ commands }) =>
                        url(attrs?.url) !== null && commands.insertContent({ type: 'embed', attrs: clean(attrs) }),

                updateEmbed:
                    (attrs) =>
                    ({ state, commands }) =>
                        isEmbedSelected(state) && url(attrs?.url) !== null && commands.updateAttributes('embed', clean(attrs)),
            }
        },
    })
}
