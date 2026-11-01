<?php

namespace App\Help\Support;

use App\Help\Data\HelpEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Finder;

final class HelpIndex
{
    /**
     * Der Schlüssel trägt den Hash über alle mtimes: entrypoint.sh räumt
     * bewusst keine Caches und der Store ist Redis, ein Index aus dem
     * vorigen Release stünde sonst nach dem Deploy noch da.
     */
    public function all(): Collection
    {
        $dateien = collect(Finder::create()->files()->in($this->pfad())->name('*.md'))
            ->map(fn ($file) => [$file->getPathname(), $file->getMTime()])
            ->sortBy(fn ($paar) => $paar[0])
            ->values();

        $schluessel = 'help.index.'.md5($dateien->toJson());
        $cached = Cache::get($schluessel);

        if ($cached instanceof Collection && $cached->every(fn ($entry) => $entry instanceof HelpEntry)) {
            return $cached;
        }

        $eintraege = $dateien->map(fn ($paar) => $this->lesen($paar[0]))->values();
        Cache::forever($schluessel, $eintraege);

        return $eintraege;
    }

    /**
     * @param  array<string>  $capabilities
     * @return array<HelpEntry> Null bis zwei, Aufgabe vor Referenz.
     */
    public function forRoute(string $routeName, array $capabilities): array
    {
        return $this->all()
            ->filter(fn (HelpEntry $entry) => in_array($routeName, $entry->screens, true))
            ->filter(fn (HelpEntry $entry) => $this->darf($entry, $capabilities))
            ->sortBy(fn (HelpEntry $entry) => $entry->art === 'aufgabe' ? 0 : 1)
            ->values()
            ->all();
    }

    /** @param  array<string>  $capabilities */
    public function find(string $slug, array $capabilities): ?HelpEntry
    {
        return $this->all()
            ->first(fn (HelpEntry $entry) => $entry->slug === $slug
                && $this->darf($entry, $capabilities));
    }

    /** @param  array<string>  $capabilities */
    public function visible(array $capabilities): Collection
    {
        return $this->all()->filter(fn (HelpEntry $entry) => $this->darf($entry, $capabilities))->values();
    }

    /** @param  array<string>  $capabilities */
    private function darf(HelpEntry $entry, array $capabilities): bool
    {
        return $entry->faehigkeit === null
            || in_array($entry->faehigkeit, $capabilities, true);
    }

    private function lesen(string $pfad): HelpEntry
    {
        [$kopf, $rumpf] = HelpFrontmatter::parse(file_get_contents($pfad));

        return new HelpEntry(
            slug: basename($pfad, '.md'),
            titel: $kopf['titel'] ?? '',
            beschreibung: $kopf['beschreibung'] ?? '',
            art: $kopf['art'] ?? '',
            reihenfolge: $kopf['reihenfolge'] ?? null,
            screens: $kopf['screens'] ?? [],
            faehigkeit: $kopf['faehigkeit'] ?? null,
            // Reintext für die Suche: Markdown-Zeichen weg, Zeilenumbrüche zu
            // Leerzeichen, damit ein Substring über Zeilengrenzen trifft.
            // strip_tags zuerst: es braucht heile Tags. Erst die Zeichen zu
            // entfernen nimmt einem `<form …>` im Codeblock sein `>`, und der
            // nun offene Tag frisst den Rest des Artikels.
            suchtext: trim(preg_replace('/\s+/', ' ', str_replace(
                ['#', '*', '`', '>', '_'], '', strip_tags($rumpf),
            ))),
        );
    }

    private function pfad(): string
    {
        return resource_path('help');
    }
}
