import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
// In a package of your own, import it from the copy Composer installs:
// './vendor/webwizardsusa/filament-rich-editor-plus/vite/shared-tiptap.js'.
import { sharedTiptap } from '../rich-editor-plus/vite/shared-tiptap.js'

const packageRoot = path.dirname(fileURLToPath(import.meta.url))

/**
 * Must match `RichEditorPlusEmbedServiceProvider::assetId()`: `RegistersPluginAssets` looks for
 * `dist/{assetId}.js`, `dist/{assetId}.css` and the hot file `public/{assetId}.hot` in the app.
 */
const assetId = 'rich-editor-plus-embed'

const entry = 'resources/js/index.js'

/**
 * `vite build` writes the module and stylesheet into dist/, for `filament:assets` to publish. `vite` serves the
 * module to the host app with HMR for the CSS, and writes the hot file `moduleUrl()` checks for. Either way the
 * TipTap and ProseMirror imports are rewritten to the editor's shared instances, so neither is bundled.
 */
export default defineConfig(({ command }) => {
    if (command === 'serve') {
        // The host app: this package sits in its packages/ directory.
        const appRoot = path.resolve(packageRoot, '../..')

        return {
            root: appRoot,
            // Each dev server in the app needs a cache dir of its own, or they wipe each other's pre-bundled deps.
            cacheDir: path.join(appRoot, `node_modules/.vite/${assetId}`),
            plugins: [
                // List any package you import that imports TipTap itself, such as `@tiptap/extension-*`.
                sharedTiptap({ exclude: [] }),
                laravel({
                    // Relative to the app root, as `RegistersPluginAssets::devServerEntry()` expects.
                    input: [path.relative(appRoot, path.join(packageRoot, entry))],
                    hotFile: path.join(appRoot, `public/${assetId}.hot`),
                    refresh: [path.join(packageRoot, 'src/**/*.php')],
                }),
            ],
            server: {
                port: 5180,
                strictPort: true,
            },
        }
    }

    return {
        root: packageRoot,
        publicDir: false,
        plugins: [sharedTiptap()],
        build: {
            // The browser loads the file as it is, so minify it fully.
            minify: true,
            rolldownOptions: {
                output: { minify: true },
            },
            outDir: 'dist',
            emptyOutDir: true,
            cssCodeSplit: false,
            lib: {
                entry: path.resolve(packageRoot, entry),
                formats: ['es'],
                fileName: () => `${assetId}.js`,
                cssFileName: assetId,
            },
        },
    }
})
