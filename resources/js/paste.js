import { Extension } from '@tiptap/core'
import { closeHistory } from '@tiptap/pm/history'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { clean } from './embed'
import { isAllowedUrl, matchesHost } from './urls'

/**
 * The paragraph holding nothing but the pasted URL, nearest to where it was pasted, or null once it's gone or edited.
 */
const pastedParagraph = (doc, pastedUrl, near) => {
    let found = null

    doc.descendants((node, pos) => {
        if (node.type.name === 'paragraph' && node.childCount === 1 && node.textContent === pastedUrl) {
            if (found === null || Math.abs(pos - near) < Math.abs(found.pos - near)) {
                found = { node, pos }
            }
        }

        return !node.isTextblock
    })

    return found
}

/**
 * Pasting a URL a provider takes into an empty paragraph embeds it, as WordPress does. The URL goes in at once as a
 * link; the server looks it up (`EmbedPlugin` `resolve` request) and, if it can be embedded, the link becomes the embed.
 * The swap is its own undo step, so undo turns the embed back into the link. URLs the server refuses stay links.
 */
export function createEmbedPaste({ hosts = [], labels = {} } = {}, { callPlugin, processing } = {}) {
    return Extension.create({
        name: 'embedPaste',

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('embedPaste'),
                    props: {
                        handlePaste(view, event) {
                            const pasted = event.clipboardData?.getData('text/plain')?.trim() ?? ''
                            const { selection, schema } = view.state
                            const { $from } = selection

                            if (
                                !callPlugin ||
                                pasted === '' ||
                                /\s/.test(pasted) ||
                                !isAllowedUrl(pasted) ||
                                !matchesHost(pasted, hosts) ||
                                !selection.empty ||
                                $from.parent.type.name !== 'paragraph' ||
                                $from.parent.content.size !== 0 ||
                                // An embed can stand where the paragraph is (not inside a table cell that only takes text, say).
                                !$from.node(-1).canReplaceWith($from.index(-1), $from.indexAfter(-1), schema.nodes.embed)
                            ) {
                                return false
                            }

                            const marks = schema.marks.link ? [schema.marks.link.create({ href: pasted })] : []
                            const near = $from.before()

                            view.dispatch(closeHistory(view.state.tr.insert($from.pos, schema.text(pasted, marks))))

                            const lookup = Promise.resolve(callPlugin('webwizardsusa/embed', 'resolve', { url: pasted })).catch(() => ({}))

                            ;(processing ? processing(lookup, labels.embedding) : lookup).then((answer) => {
                                const embed = answer?.embed
                                const paragraph = embed && !editor.isDestroyed ? pastedParagraph(editor.state.doc, pasted, near) : null

                                if (paragraph === null) {
                                    return
                                }

                                const tr = editor.state.tr.replaceWith(
                                    paragraph.pos,
                                    paragraph.pos + paragraph.node.nodeSize,
                                    schema.nodes.embed.create(clean(embed)),
                                )

                                editor.view.dispatch(closeHistory(tr))
                            })

                            return true
                        },
                    },
                }),
            ]
        },
    })
}
