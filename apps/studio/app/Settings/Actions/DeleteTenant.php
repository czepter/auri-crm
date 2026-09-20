<?php

namespace App\Settings\Actions;

use App\Settings\Jobs\ReadStudioSettingsJob;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * #490: die einzige Stelle, die ein Studio löscht. Übrig bleibt eine leere
 * Einstellungszeile, ohne die keine Seite rendert (Marke, Zeitzone), und der
 * Vermerk studio.deleted im Protokoll. Danach greift /einrichtung.
 *
 * ponytail: Stufen, Projekttypen und Nummernkreise legt nur die Migration an
 * und sind mit dem Studio weg. Neu anlegen, sobald eine Installation nach der
 * Löschung weiterbetrieben wird.
 */
class DeleteTenant
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    public function __invoke(string $tenantId, string $trigger): void
    {
        $this->files($tenantId);

        DB::transaction(function () use ($tenantId, $trigger) {
            $this->rows($tenantId);

            (new StudioSettings)->forceFill(['tenant_id' => $tenantId])->save();

            // Der Audit-Log lehnt Löschen im Model ab; rows() geht am Model vorbei, dieser Eintrag bleibt.
            AuditLogger::record('studio.deleted', context: ['trigger' => $trigger]);
        });

        $this->settings->forget();
        Cache::store('redis')->forget(ReadStudioSettingsJob::CACHE_KEY);

        DB::table('sessions')->delete();

        $queue = Queue::connection();

        if ($queue instanceof ClearableQueue) {
            foreach (collect(config('horizon.defaults'))->flatMap(fn (array $supervisor) => $supervisor['queue'])->unique() as $name) {
                $queue->clear($name);
            }
        }
    }

    /** Alles unter `<Verzeichnis>/<Studio>` auf beiden Platten; die Fragebogen-Dateien liegen ohne Studio im Pfad. */
    private function files(string $tenantId): void
    {
        $local = Storage::disk('local');

        $paths = DB::table('form_instance_files')->where('tenant_id', $tenantId)->pluck('path');
        $paths->map(fn (string $path) => dirname($path))->unique()->each(fn (string $dir) => $local->deleteDirectory($dir));

        foreach (['local', 's3'] as $name) {
            $disk = Storage::disk($name);

            foreach ($disk->directories() as $directory) {
                $disk->deleteDirectory("{$directory}/{$tenantId}");
            }
        }
    }

    private function rows(string $tenantId): void
    {
        foreach ($this->ordered() as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                // RESTRICT prüft je Zeile, nicht am Ende der Anweisung: ein Selbstverweis muss vorher weg.
                if ($key['foreign_table'] === $table && in_array($key['on_delete'], ['restrict', 'no action'], true)) {
                    DB::table($table)->where('tenant_id', $tenantId)->update(array_fill_keys($key['columns'], null));
                }
            }

            DB::table($table)->where('tenant_id', $tenantId)->delete();
        }
    }

    /**
     * Jede Tabelle mit tenant_id, jede vor den Tabellen, auf die sie mit
     * NO ACTION oder RESTRICT verweist. CASCADE und SET NULL blockieren nichts;
     * nur so ist der Kreis zwischen threads und messages, galleries und assets
     * keiner.
     *
     * @return list<string>
     */
    private function ordered(): array
    {
        $tables = collect(Schema::getTables())->pluck('name')->filter(fn (string $table) => Schema::hasColumn($table, 'tenant_id'));

        $referrers = [];

        foreach ($tables as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                if ($key['foreign_table'] !== $table && in_array($key['on_delete'], ['restrict', 'no action'], true)) {
                    $referrers[$key['foreign_table']][] = $table;
                }
            }
        }

        $ordered = [];
        $visit = function (string $table) use (&$visit, &$ordered, $referrers) {
            if (in_array($table, $ordered, true)) {
                return;
            }

            foreach ($referrers[$table] ?? [] as $referrer) {
                $visit($referrer);
            }

            $ordered[] = $table;
        };

        $tables->each($visit);

        return $ordered;
    }
}
