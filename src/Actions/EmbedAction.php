<?php

namespace Webwizardsusa\RichEditorPlusEmbed\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\EditorCommand;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Providers\Provider;

/**
 * The modal for inserting an embed, or editing the selected one. The browser mounts it with the selection and, when
 * editing, the embed's attributes; submitting looks the URL up and sends an `insertEmbed` or `updateEmbed` command
 * with what the provider answered.
 */
class EmbedAction
{
    public static function make(EmbedPlugin $plugin): Action
    {
        return Action::make('embed')
            ->label(__('rich-editor-plus-embed::plugins.embed.modal.heading'))
            ->modalHeading(__('rich-editor-plus-embed::plugins.embed.modal.heading'))
            ->modalSubmitActionLabel(__('rich-editor-plus-embed::plugins.embed.modal.submit'))
            ->modalWidth(Width::Large)
            ->fillForm(static fn (array $arguments): array => [
                'url' => EmbedPlugin::isAllowedUrl($arguments['url'] ?? null) ? trim($arguments['url']) : null,
                'caption' => is_string($arguments['caption'] ?? null) ? $arguments['caption'] : '',
            ])
            ->schema([
                TextInput::make('url')
                    ->label(__('rich-editor-plus-embed::plugins.embed.modal.url'))
                    ->helperText(__('rich-editor-plus-embed::plugins.embed.modal.url_help', [
                        'providers' => implode(', ', array_map(static fn (Provider $provider): string => $provider->getLabel(), $plugin->getProviders()->all())),
                    ]))
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($plugin): void {
                        if ($plugin->getProviders()->match(is_string($value) ? trim($value) : null) === null) {
                            $fail(__('rich-editor-plus-embed::plugins.embed.modal.url_unsupported'));

                            return;
                        }

                        if ($plugin->resolveEmbed(trim($value)) === null) {
                            $fail(__('rich-editor-plus-embed::plugins.embed.modal.url_unavailable'));
                        }
                    }),
                TextInput::make('caption')
                    ->label(__('rich-editor-plus-embed::plugins.embed.modal.caption'))
                    ->maxLength(500),
            ])
            ->action(static function (array $arguments, array $data, RichEditorPlus $component) use ($plugin): void {
                $embed = $plugin->resolveEmbed(trim((string) $data['url']));

                if ($embed === null) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make(($arguments['editing'] ?? false) ? 'updateEmbed' : 'insertEmbed', [[
                            ...$embed,
                            'caption' => trim((string) ($data['caption'] ?? '')),
                        ]]),
                    ],
                    $arguments['editorSelection'] ?? null,
                );
            });
    }
}
