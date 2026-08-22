<?php

namespace App\Contracts\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Contracts\Enums\ContractStatus;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use Database\Factories\Contracts\ContractFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'project_id', 'contract_template_id', 'requested_by_id',
        'status', 'artifact_path', 'rendered_at', 'error',
    ];

    protected $attributes = ['status' => ContractStatus::Pending];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'rendered_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'contract_template_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
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
