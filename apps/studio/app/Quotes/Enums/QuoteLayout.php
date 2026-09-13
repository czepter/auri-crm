<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The five print layouts RenderQuotePdf can render an Angebot in — one
 * studio-wide choice (App\Settings\Support\StudioSettingsResolver::
 * quoteLayout()), not per template. Reference markup for each case:
 * apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue.
 */
#[TypeScript]
enum QuoteLayout: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Letter = 'letter';
    case HeaderBand = 'header_band';
    case Structured = 'structured';
}
