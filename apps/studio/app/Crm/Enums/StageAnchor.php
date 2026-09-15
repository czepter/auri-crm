<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a stage MEANS to the rest of the app, kept apart from what it is called.
 *
 * Renaming "Geliefert" must not stop the gallery from opening, and a studio
 * that inserts "Vorgespräch" between two stages must not have to teach the code
 * about it. So behaviour hangs off the anchor a stage carries, never off its
 * name, its key or its position — the anchor is the only thing the code may
 * branch on. A stage without one is presentation, which is most of them.
 *
 * The cases are exactly the branches that exist today (BookProject,
 * AdvanceLeadStage, CalendarController, BookingFunnel, the portal's phase
 * grouping). A tenth case belongs here only when code needs to ask for it.
 */
#[TypeScript]
enum StageAnchor: string implements HasLabel
{
    /* lead */
    case Entry = 'entry';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case Won = 'won';

    /* project */
    case Booked = 'booked';
    case Shot = 'shot';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Eingang',
            self::QuoteSent => 'Angebot raus',
            self::QuoteAccepted => 'Angebot angenommen',
            self::Won => 'Gewonnen, legt Projekt an',
            self::Booked => 'Gebucht',
            self::Shot => 'Fotografiert',
            self::Ready => 'Fertig',
            self::Delivered => 'Ausgeliefert, gibt Galerie frei',
            self::Archived => 'Archiviert',
        };
    }

    /** @return list<self> */
    public static function for(StagePipeline $pipeline): array
    {
        return match ($pipeline) {
            StagePipeline::Lead => [self::Entry, self::QuoteSent, self::QuoteAccepted, self::Won],
            StagePipeline::Project => [self::Entry, self::Booked, self::Shot, self::Ready, self::Delivered, self::Archived],
        };
    }
}
