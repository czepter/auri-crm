<?php

namespace App\Portal\Http\Controllers;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MergeFieldResolver;
use App\Http\Controllers\Controller;
use App\Portal\Support\PortalSettings;
use App\Settings\Support\StudioSettingsResolver;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ein Rechtstext, den das Studio selbst geschrieben hat: dieselbe Vorlage wie
 * Angebot und Rechnung, nur ohne Briefkopf und ohne PDF — ein Kunde liest AGB
 * und Widerrufsbelehrung im Browser, und beim Annehmen muss der Text neben dem
 * Häkchen erreichbar sein.
 *
 * Wie ImpressumController öffentlich und auch bei geschlossenem Portal
 * erreichbar: ein Vertragstext, der hinter einer Wartungsseite liegt, ist zum
 * Zeitpunkt des Vertragsschlusses nicht gestellt worden.
 */
class LegalTextController extends Controller
{
    public function __invoke(string $text, StudioSettingsResolver $settings, DocumentMergeData $merge): Response
    {
        abort_unless(in_array($text, PortalSettings::LEGAL_TEXTS, true), 404);

        $family = $settings->legalFamily($text);

        abort_if($family === null, 404);

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Legal)
            ->where('family_id', $family)
            ->firstOrFail();

        return Inertia::render('Rechtstext', [
            'title' => $template->name,
            'body' => MergeFieldResolver::resolve($template->body, $merge->forStudio()),
        ]);
    }
}
