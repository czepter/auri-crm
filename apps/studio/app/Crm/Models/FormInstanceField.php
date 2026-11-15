<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\FormFieldType;
use App\Models\User;
use Database\Factories\Crm\FormInstanceFieldFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Visus\Cuid2\Cuid2;

/**
 * One question on a FormInstance. Shaped after FormField on purpose — same
 * columns — but this is the project's own copy: editing it never writes back
 * to the FormField rows it may have started as (see FormInstance's own doc comment).
 */
class FormInstanceField extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['label', 'type', 'required', 'options', 'sort_order'];

    protected $attributes = ['required' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function formInstance(): BelongsTo
    {
        return $this->belongsTo(FormInstance::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(FormInstanceFile::class)->orderBy('id');
    }

    /**
     * A "Datei" field's configuration, stored in the same options column
     * select keeps its choices in. Defaults fill in for a field seeded from
     * a template that never configured one.
     *
     * @return array{formats: list<string>, maxSize: int, multiple: bool}
     */
    public function fileOptions(): array
    {
        return [
            'formats' => $this->options['formats'] ?? FormFieldType::FILE_FORMATS,
            'maxSize' => (int) ($this->options['maxSize'] ?? FormFieldType::FILE_MAX_MB),
            'multiple' => (bool) ($this->options['multiple'] ?? false),
        ];
    }

    /**
     * A "Gruppenfotos" field's configuration, in the same options column
     * select keeps its choices in.
     *
     * @return array{help: ?string, suggestions: list<string>, minutesPerGroup: int}
     */
    public function groupPhotoOptions(): array
    {
        $suggestions = array_filter((array) ($this->options['suggestions'] ?? []), 'is_string');

        return [
            'help' => $this->options['help'] ?? null,
            'suggestions' => array_values($suggestions),
            'minutesPerGroup' => (int) ($this->options['minutesPerGroup'] ?? 2),
        ];
    }

    /**
     * A Gruppenfotos answer rides as JSON in the one text column every other
     * answer uses, so this is the gate on its shape: keys the builder never
     * wrote are dropped, and a member who is not in the people list cannot
     * exist — the list the studio prints has to be the list the client saw.
     */
    public static function normaliseGroupPhotos(?string $value): ?string
    {
        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return null;
        }

        $clean = static fn (mixed $raw): ?string => is_string($raw) && trim($raw) !== ''
            ? mb_substr(trim($raw), 0, 120)
            : null;

        $people = collect((array) ($decoded['people'] ?? []))
            ->map($clean)->filter()->unique()
            ->take(FormFieldType::GROUP_PEOPLE_MAX)->values();

        $groups = collect((array) ($decoded['groups'] ?? []))
            ->map(function (mixed $group) use ($clean, $people): ?array {
                $name = is_array($group) ? $clean($group['name'] ?? null) : null;

                if ($name === null) {
                    return null;
                }

                return [
                    'name' => $name,
                    'members' => collect((array) ($group['members'] ?? []))
                        ->map($clean)->filter()
                        ->filter(fn (string $member) => $people->contains($member))
                        ->unique()->values()->all(),
                ];
            })
            ->filter()->take(FormFieldType::GROUP_SHOTS_MAX)->values();

        if ($people->isEmpty() && $groups->isEmpty()) {
            return null;
        }

        return json_encode(['people' => $people->all(), 'groups' => $groups->all()], JSON_UNESCAPED_UNICODE);
    }

    /**
     * The upload rules this field's own configuration implies. Both surfaces
     * validate through here, so a format or size the studio never allowed is
     * rejected wherever the file arrives from.
     *
     * @return list<mixed>
     */
    public function fileRules(): array
    {
        $options = $this->fileOptions();

        return [
            'required',
            'file',
            'mimes:'.implode(',', $options['formats']),
            'max:'.($options['maxSize'] * 1024),
        ];
    }

    /**
     * Stores one uploaded file against this field. A single-file field keeps
     * exactly one: the new upload replaces what was there, bytes and all.
     */
    public function attachFile(UploadedFile $file, Contact|User|null $actor = null): FormInstanceFile
    {
        if (! $this->fileOptions()['multiple']) {
            $this->files()->get()->each->purge();
        }

        // Guessed from the bytes, not from the name the client sent — that
        // name is echoed back to a browser and never trusted as a path.
        $key = (string) new Cuid2;

        $path = Storage::disk('local')->putFileAs(
            "form-instance-files/{$this->public_id}",
            $file,
            $key.'.'.$file->extension(),
        );

        return $this->files()->create([
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            ...match (true) {
                $actor instanceof Contact => ['contact_id' => $actor->id],
                $actor instanceof User => ['author_id' => $actor->id],
                default => [],
            },
        ]);
    }

    /** The latest answer version, or null before this field has ever been answered. */
    public function currentAnswer(): HasOne
    {
        return $this->hasOne(FormAnswer::class)->latestOfMany('version');
    }

    /**
     * E-03: records a new answer version and leaves every earlier one
     * untouched — no update, no delete. Covers both the first submission and
     * every edit afterwards; there is no separate "draft" to publish, each
     * call is itself the new current version. Same transaction shape as
     * Gallery::publish() and DocumentTemplate::newVersion(): the max-version
     * read and the insert happen together, so two concurrent answers either
     * serialize on it or one loses the unique (tenant_id, field, version)
     * index, never both landing the same version number.
     */
    public function answer(?string $value, Contact|User|null $actor = null): FormAnswer
    {
        return DB::transaction(function () use ($value, $actor) {
            $next = 1 + (int) $this->answers()->max('version');

            return $this->answers()->create([
                'version' => $next,
                'value' => $value,
                // P-03 (#222): who typed it. A studio recording an answer off
                // a phone call and the client typing it themselves are
                // different facts, and this table is append-only, so the
                // distinction stays readable years later.
                ...match (true) {
                    $actor instanceof Contact => ['contact_id' => $actor->id],
                    $actor instanceof User => ['author_id' => $actor->id],
                    default => [],
                },
            ]);
        });
    }

    protected static function newFactory(): Factory
    {
        return FormInstanceFieldFactory::new();
    }
}
