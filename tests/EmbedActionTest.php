<?php

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\Livewire;
use Webwizardsusa\RichEditorPlus\RichEditorPlus;
use Webwizardsusa\RichEditorPlus\Support\PluginRegistry;
use Webwizardsusa\RichEditorPlusEmbed\EmbedPlugin;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

beforeEach(fn (): mixed => PluginRegistry::flush());

class EmbedTestForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([RichEditorPlus::make('content')->addPlugins([EmbedPlugin::make()])])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

function embedAction(): TestAction
{
    return TestAction::make('embed')->schemaComponent('content');
}

function fakeYouTubeVideo(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'www.youtube.com/oembed*' => Http::response(['type' => 'video', 'title' => 'A video', 'html' => '<iframe src="https://www.youtube.com/embed/abc"></iframe>']),
        'vimeo.com/api/oembed.json*' => Http::response([], 404),
    ]);
}

it('inserts an embed with what the provider answered and the caption', function (): void {
    fakeYouTubeVideo();
    $selection = ['type' => 'text', 'anchor' => 1, 'head' => 1];

    Livewire::test(EmbedTestForm::class)
        ->callAction(embedAction(), data: ['url' => 'https://youtu.be/abc', 'caption' => ' Watch this '], arguments: ['editorSelection' => $selection])
        ->assertHasNoActionErrors()
        ->assertDispatched('run-rich-editor-plus-commands', fn (string $event, array $params): bool => $params['editorSelection'] === $selection
            && $params['commands'] === [['name' => 'insertEmbed', 'arguments' => [[
                'url' => 'https://youtu.be/abc',
                'provider' => 'youtube',
                'title' => 'A video',
                'thumbnail' => null,
                'caption' => 'Watch this',
            ]]]]);
});

it('updates the selected embed when editing one', function (): void {
    fakeYouTubeVideo();

    Livewire::test(EmbedTestForm::class)
        ->mountAction(embedAction()->arguments(['editing' => true, 'url' => 'https://youtu.be/abc', 'caption' => 'Old']))
        ->assertSchemaStateSet(['url' => 'https://youtu.be/abc', 'caption' => 'Old'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertDispatched('run-rich-editor-plus-commands', fn (string $event, array $params): bool => $params['commands'][0]['name'] === 'updateEmbed');
});

it('does not fill the form with a URL from the browser that is not http(s)', function (): void {
    Livewire::test(EmbedTestForm::class)
        ->mountAction(embedAction()->arguments(['editing' => true, 'url' => 'javascript:alert(1)']))
        ->assertSchemaStateSet(['url' => null]);
});

it('refuses a URL it cannot embed', function (string $url): void {
    fakeYouTubeVideo();

    Livewire::test(EmbedTestForm::class)
        ->callAction(embedAction(), data: ['url' => $url], arguments: ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]])
        ->assertHasActionErrors(['url'])
        ->assertNotDispatched('run-rich-editor-plus-commands');
})->with([
    'a site no provider takes' => 'https://example.com/page',
    'a page the provider has nothing for' => 'https://vimeo.com/1',
    'not a URL' => 'youtube',
]);

it('offers an embed block in the slash menu and an edit tool on embeds, both opening the modal', function (): void {
    $plugin = EmbedPlugin::make();

    expect($plugin->getBlockTypes()[0]->getAction())->toBe('embed')
        ->and($plugin->getBlockTypes()[0]->getKeywords())->toContain('youtube', 'x')
        ->and($plugin->getTools()[0]->toArray())->toMatchArray(['action' => 'embed']);
});
