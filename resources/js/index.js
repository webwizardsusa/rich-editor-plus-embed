import '../css/index.css'
import { NodeSelection } from '@tiptap/pm/state'
import { createEmbed } from './embed'
import { createEmbedPaste } from './paste'

/**
 * The module `EmbedPlugin` points `JsExtension::module()` at. Rich Editor Plus imports it once per page, before it
 * builds an editor, and calls this with its shared API. The `registerExtension()` id matches `EmbedPlugin::ID`, and
 * the factory gets the options from `EmbedPlugin::getJsExtension()` and the editor's context (`callPlugin`,
 * `processing`, `runAction`).
 *
 * The `@tiptap/*` imports resolve to the editor's own TipTap and ProseMirror through the `sharedTiptap()` Vite plugin,
 * so this bundle holds only the code in this folder.
 */
export default function ({ registerExtension, registerActionArguments }) {
    registerExtension('webwizardsusa/embed', (options, tiptap, context) => [createEmbed(options, context), createEmbedPaste(options, context)])

    // `BareUrlEmbedPlugin` only changes rendered content, so it has nothing to add to the editor.
    registerExtension('webwizardsusa/embed-bare-urls', () => [])

    // The modal edits the selected embed, and inserts a new one otherwise.
    registerActionArguments('embed', (editor) => {
        const { selection } = editor.state

        if (!(selection instanceof NodeSelection) || selection.node.type.name !== 'embed') {
            return {}
        }

        return { editing: true, ...selection.node.attrs }
    })
}

// The editor imports the module once, so a changed module can only take effect on a fresh page.
if (import.meta.hot) {
    import.meta.hot.accept(() => window.location.reload())
}
