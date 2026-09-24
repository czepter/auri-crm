<?php

namespace App\Contracts\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Contracts\Enums\ContractState;
use App\Contracts\Enums\ContractStatus;
use App\Crm\Models\ClientCase;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use Database\Factories\Contracts\ContractFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One render of one exact App\Documents\Models\DocumentTemplate version
 * (type contract, T-12), for one project.
 *
 * contract_template_id names a specific row, not "whichever version is
 * current" — RenderContractJob resolves merge fields against real project
 * data at the moment it runs and bakes the result into the PDF this row
 * points at. Editing the template afterwards can never move that pointer or
 * touch the file it already wrote (see DocumentTemplate::newVersion()). The
 * column keeps its M-01 name even though it now points at document_templates
 * — see the T-12 migration that moved that table's data.
 */
class Contract extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'case_id', 'contract_template_id', 'requested_by_id',
        'status', 'artifact_path', 'rendered_at', 'error',
        'version', 'state', 'body', 'blocks', 'sent_at', 'sign_deadline_on',
    ];

    protected $attributes = [
        'status' => ContractStatus::Pending,
        'state' => ContractState::Draft,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'state' => ContractState::class,
            'blocks' => 'array',
            'rendered_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'sign_deadline_on' => 'immutable_date',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'contract_template_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ContractSigner::class);
    }

    /** The wording this Fassung renders — its own once edited, the template's until then. */
    public function resolvedBody(): string
    {
        return $this->body ?? $this->template->body;
    }

    /**
     * Sending is what moves validity: the Fassung a client is looking at
     * becomes the valid one, and whichever held that place stops being it.
     * One statement per side, in one transaction, so no project is ever
     * momentarily without a valid contract or briefly with two.
     */
    public function markSent(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('case_id', $this->case_id)
                ->where('id', '!=', $this->id)
                ->where('state', ContractState::Valid)
                ->update(['state' => ContractState::Superseded]);

            $this->update(['state' => ContractState::Valid, 'sent_at' => now()]);
        });
    }

    /** Null until App\Contracts\Actions\SignContract writes the one row this Contract will ever have. */
    public function signature(): HasOne
    {
        return $this->hasOne(ContractSignature::class);
    }

    /**
     * The column and the disk can disagree — EraseContactDsgvo deletes the
     * file and leaves the path, so every read of the bytes asks here first.
     */
    public function hasArtifact(): bool
    {
        return $this->artifact_path !== null && Storage::disk('local')->exists($this->artifact_path);
    }

    protected static function newFactory(): Factory
    {
        return ContractFactory::new();
    }
}
