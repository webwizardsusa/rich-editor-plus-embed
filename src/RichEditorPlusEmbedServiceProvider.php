<?php

namespace Webwizardsusa\RichEditorPlusEmbed;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webwizardsusa\RichEditorPlus\Support\RegistersPluginAssets;

/**
 * Registers the package's config, translations and its built JS module and stylesheet. `RegistersPluginAssets` finds
 * `dist/rich-editor-plus-embed.{js,css}`, or the Vite dev server while `public/rich-editor-plus-embed.hot` exists.
 */
class RichEditorPlusEmbedServiceProvider extends PackageServiceProvider
{
    use RegistersPluginAssets;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('rich-editor-plus-embed')
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        $this->registerPluginAssets();
    }

    public static function assetPackage(): string
    {
        return 'webwizardsusa/rich-editor-plus-embed';
    }

    public static function assetId(): string
    {
        return 'rich-editor-plus-embed';
    }
}
