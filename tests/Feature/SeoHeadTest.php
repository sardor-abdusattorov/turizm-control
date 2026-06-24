<?php

use App\Models\Settings;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Render the panel head-start hook (which carries the SEO/OG tags) to a string. */
function seoHead(): string
{
    clear_settings_cache();

    $rendered = FilamentView::renderHook('panels::head.start');

    return $rendered instanceof Htmlable ? $rendered->toHtml() : (string) $rendered;
}

it('injects localised SEO and Open Graph tags from settings', function () {
    app()->setLocale('ru');

    Settings::create(['key' => 'seo.title.ru', 'value' => 'Заголовок сайта']);
    Settings::create(['key' => 'seo.description.ru', 'value' => 'Описание сайта']);
    Settings::create(['key' => 'seo.keywords.ru', 'value' => 'договоры, контроль']);
    Settings::create(['key' => 'seo.indexing_enabled', 'value' => true]);

    expect(seoHead())
        ->toContain('<meta name="description" content="Описание сайта">')
        ->toContain('<meta name="keywords" content="договоры, контроль">')
        ->toContain('<meta property="og:title" content="Заголовок сайта">')
        ->toContain('content="index,follow"');
});

it('emits noindex when indexing is turned off', function () {
    Settings::create(['key' => 'seo.indexing_enabled', 'value' => false]);

    expect(seoHead())->toContain('content="noindex,nofollow"');
});

it('adds the og:image tag from the public disk when one is configured', function () {
    Settings::create(['key' => 'seo.og_image', 'value' => 'images/og.png']);

    expect(seoHead())
        ->toContain('property="og:image"')
        ->toContain('/storage/images/og.png');
});

it('falls back to the brand icon so link previews always have an image', function () {
    expect(seoHead())
        ->toContain('property="og:image"')
        ->toContain('images/favicon/android-chrome-512x512.png');
});
