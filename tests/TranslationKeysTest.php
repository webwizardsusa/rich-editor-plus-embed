<?php

use Symfony\Component\Finder\Finder;
use Webwizardsusa\RichEditorPlusEmbed\Tests\TestCase;

uses(TestCase::class);

it('resolves every translation key the package uses', function (): void {
    $missing = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__).'/src')->name('*.php') as $file) {
        preg_match_all("/'(rich-editor-plus-embed::[a-z0-9_.]+[a-z0-9_])'/", $file->getContents(), $matches);

        foreach ($matches[1] as $key) {
            if (__($key) === $key) {
                $missing[] = "{$key} ({$file->getRelativePathname()})";
            }
        }
    }

    expect($missing)->toBe([]);
});
