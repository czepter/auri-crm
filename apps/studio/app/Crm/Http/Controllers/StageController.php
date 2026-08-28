<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\DeleteStage;
use App\Crm\Enums\StageColor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Stage;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The studio's own funnel: what the two pipelines are called, in what order,
 * in what colour, and which project types exist.
 *
 * No screen of its own. The stages are read on /anfragen and /projekte, so
 * they are edited there too, in an overlay over the list they order —
 * StageSettingsPayload puts them on those two pages. These are the writes
 * behind it, and each one returns to the page that sent it.
 */
class StageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Stage::class);

        $validated = $request->validate([
            'pipeline' => ['required', Rule::enum(StagePipeline::class)],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', Rule::enum(StageColor::class)],
            'after' => ['nullable', 'string', 'size:24'],
        ]);

        $pipeline = StagePipeline::from($validated['pipeline']);
        $after = isset($validated['after']) ? Stage::findByPublicId($validated['after']) : null;

        $stage = Stage::create([
            'pipeline' => $pipeline,
            'key' => $this->key($pipeline, $validated['name']),
            'name' => $validated['name'],
            'color' => $validated['color'],
            'sort_order' => ($after?->sort_order ?? -1) + 1,
        ]);

        // Everything the new stage was inserted before moves down one. Gaps
        // are harmless — the order is read, never counted on to be dense.
        Stage::query()
            ->pipeline($pipeline)
            ->whereKeyNot($stage->getKey())
            ->where('sort_order', '>=', $stage->sort_order)
            ->increment('sort_order');

        AuditLogger::record(
            event: 'stage.created',
            actor: auth('staff')->user(),
            context: ['pipeline' => $pipeline->value, 'stage' => $stage->key],
            subject: $stage->public_id,
        );

        return back();
    }

    public function update(Request $request, Stage $stage): RedirectResponse
    {
        $this->authorize('update', $stage);

        // The key is absent on purpose: leads.stage and shoots.status store
        // it, so renaming a stage may not orphan the records standing on it.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', Rule::enum(StageColor::class)],
            'is_active' => ['boolean'],
        ]);

        $stage->update($validated);

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', Stage::class);

        $validated = $request->validate([
            'pipeline' => ['required', Rule::enum(StagePipeline::class)],
            'order' => ['required', 'array'],
            'order.*' => ['required', 'string', 'size:24'],
        ]);

        $stages = Stage::query()->pipeline(StagePipeline::from($validated['pipeline']))->get()->keyBy('public_id');

        foreach ($validated['order'] as $position => $publicId) {
            $stages->get($publicId)?->update(['sort_order' => $position]);
        }

        return back();
    }

    public function destroy(Request $request, Stage $stage, DeleteStage $delete): RedirectResponse
    {
        $this->authorize('delete', $stage);

        $validated = $request->validate([
            'move_to' => ['nullable', 'string', 'size:24'],
        ]);

        try {
            $delete(
                $stage,
                isset($validated['move_to']) ? Stage::findByPublicId($validated['move_to']) : null,
                auth('staff')->user(),
            );
        } catch (DomainException $e) {
            // The dialog asks for the target beside the count; a refusal
            // belongs on that field rather than in a 500.
            throw ValidationException::withMessages(['move_to' => $e->getMessage()]);
        }

        return back();
    }

    /** @return array<string, mixed> */
    private function row(Stage $stage): array
    {
        return [
            'id' => $stage->public_id,
            'key' => $stage->key,
            'name' => $stage->name,
            'color' => $stage->color->value,
            'css' => $stage->color->css(),
            'anchor' => $stage->anchor?->value,
            'anchorLabel' => $stage->anchor?->label(),
            'isActive' => $stage->is_active,
        ];
    }

    /**
     * A slug of the name, kept unique within its pipeline. Generated once and
     * never again: the key is what the records hold, and a later rename must
     * not move them.
     */
    private function key(StagePipeline $pipeline, string $name): string
    {
        $base = str($name)->slug('_')->limit(60, '')->toString() ?: 'stufe';
        $key = $base;
        $suffix = 2;

        while (Stage::query()->pipeline($pipeline)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
