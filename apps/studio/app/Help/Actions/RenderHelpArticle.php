<?php

namespace App\Help\Actions;

use App\Help\Data\HelpArticle;
use App\Help\Data\HelpEntry;
use App\Help\Support\HelpFrontmatter;
use App\Help\Support\HelpPlaceholders;
use Illuminate\Support\Str;

final class RenderHelpArticle
{
    public function __construct(private readonly HelpPlaceholders $platzhalter) {}

    public function __invoke(HelpEntry $entry): HelpArticle
    {
        [, $rumpf] = HelpFrontmatter::parse(
            file_get_contents(resource_path("help/{$entry->slug}.md")),
        );

        // Erst rendern, dann ersetzen: ein Wert, der vor dem Rendern
        // eingesetzt wird, kann die Struktur brechen. Und escaped, weil ein
        // Teil der Werte aus Eingaben des Inhabers stammt — der Artikeltext
        // selbst ist unserer und darf HTML sein.
        $html = Str::markdown($rumpf);

        $werte = $this->platzhalter->werte();

        // Ein Abschnitt mit leerem Platzhalter entfällt ganz.
        $html = preg_replace_callback(
            '/<!--wenn:(\w+)-->(.*?)<!--\/wenn-->/s',
            fn (array $treffer) => ($werte[$treffer[1]] ?? '') === '' ? '' : $treffer[2],
            $html,
        );

        foreach ($werte as $name => $wert) {
            $html = str_replace("%{$name}%", e($wert), $html);
        }

        return new HelpArticle($entry->slug, $entry->titel, $entry->art, $html);
    }
}
