<?php

namespace App\Settings\Enums;

use App\Crm\Enums\HasLabel;
use App\Models\StudioSettings;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * #234: the display pair a studio writes its portal and its gallery in.
 *
 * A closed set, never a free picker, for four reasons the issue records and
 * only the first of which is about taste:
 *
 * 1. **Licences.** A studio's own CI font is almost always a desktop licence;
 *    web use is a separate contract. If an admin uploads a .woff2 and we
 *    serve it, we are the distributor and the studio breaks its agreement.
 *    Every family behind these four cases is under the SIL Open Font License,
 *    which is what makes "licensed once by the product, for everyone"
 *    possible at all.
 * 2. **Metrics.** The scale carries corrections for a particular face —
 *    tracking at display size, leading below one. A broad slab collides with
 *    itself at those values and a high-contrast Didone clips its ascenders,
 *    so each pair brings its own two numbers (resources/css/tokens.css).
 * 3. **Legibility.** A script in body copy and the couple stop reading their
 *    own contract. Only the display pair is selectable; minimum sizes and
 *    contrast stay fixed.
 * 4. **It has to actually change something.** Four pairs that differ plainly —
 *    a quiet grotesque, a high-contrast serif, a fine old-style, a technical
 *    one — because if they were variations of each other the setting would be
 *    decoration and every portal would still look the same.
 */
#[TypeScript]
enum FontPair: string implements HasLabel
{
    case Hanken = 'hanken';
    case Fraunces = 'fraunces';
    case Cormorant = 'cormorant';
    case Grotesk = 'grotesk';

    public function label(): string
    {
        return match ($this) {
            self::Hanken => 'Hanken',
            self::Fraunces => 'Fraunces',
            self::Cormorant => 'Cormorant',
            self::Grotesk => 'Space Grotesk',
        };
    }

    /** What the pair is, in the words the settings screen shows beside it. */
    public function description(): string
    {
        return match ($this) {
            self::Hanken => 'Eine Grotesk, Titel und Text gleich',
            self::Fraunces => 'Kontrastreiche Serif, neutrale Grotesk',
            self::Cormorant => 'Feine Serif, schmale Grotesk',
            self::Grotesk => 'Technische Grotesk',
        };
    }

    /** #234: the display pair this studio's portal and gallery are written in. Null is Hanken, the product's own default. */
    public static function current(): self
    {
        return self::tryFrom((string) StudioSettings::current()->font_pair) ?? self::Hanken;
    }
}
