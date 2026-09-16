<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StageColor;
use App\Crm\Enums\StagePipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    public function up(): void
    {
        // The studio's own funnel, where LeadStage and ShootStatus used to be
        // the whole of it. `key` is what leads.stage and shoots.status hold, so
        // seeding it from the enum cases leaves every existing row valid and
        // makes this migration a widening rather than a data move.
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('pipeline');
            $table->string('key');
            $table->string('name');
            $table->string('color')->default(StageColor::Grey->value);

            // Null for most of them: behaviour is the exception, not the rule
            // (see StageAnchor). Unique per pipeline, or two stages would each
            // claim to be the one that books the project.
            $table->string('anchor')->nullable();

            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'pipeline', 'key']);
            $table->unique(['tenant_id', 'pipeline', 'anchor']);
            $table->index(['tenant_id', 'pipeline', 'sort_order']);
        });

        // Was ProjectType, an enum of three. A table because a studio that
        // shoots Neugeborene has no way to say so today.
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'key']);
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('stages');
    }

    /**
     * Today's enums, written out as rows. Runs inside the migration rather than
     * from a seeder because a deployment that migrates without seeding would
     * come up with no stages at all, and every board would be empty.
     */
    private function seedDefaults(): void
    {
        $tenantId = config('tenancy.tenant_id');
        $now = now();

        $leadAnchors = [
            'new' => StageAnchor::Entry,
            'quote_sent' => StageAnchor::QuoteSent,
            'quote_accepted' => StageAnchor::QuoteAccepted,
            'booked' => StageAnchor::Won,
        ];

        $projectAnchors = [
            'requested' => StageAnchor::Entry,
            'booked' => StageAnchor::Booked,
            'shot' => StageAnchor::Shot,
            'ready' => StageAnchor::Ready,
            'delivered' => StageAnchor::Delivered,
            'archived' => StageAnchor::Archived,
        ];

        $colors = [
            'new' => StageColor::Grey, 'contacted' => StageColor::Amber, 'quote_sent' => StageColor::Amber,
            'quote_accepted' => StageColor::Olive, 'contract_sent' => StageColor::Green, 'booked' => StageColor::Green,
            'requested' => StageColor::Grey, 'prep' => StageColor::Teal, 'shot' => StageColor::Blue,
            'culling' => StageColor::Violet, 'editing' => StageColor::Amber, 'ready' => StageColor::Green,
            'delivered' => StageColor::Green, 'archived' => StageColor::Grey,
        ];

        $rows = [];

        foreach (LeadStage::cases() as $stage) {
            $rows[] = [
                'pipeline' => StagePipeline::Lead->value,
                'key' => $stage->value,
                'name' => $stage->label(),
                'color' => ($colors[$stage->value] ?? StageColor::Grey)->value,
                'anchor' => ($leadAnchors[$stage->value] ?? null)?->value,
                'sort_order' => $stage->position(),
            ];
        }

        foreach (ShootStatus::cases() as $status) {
            $rows[] = [
                'pipeline' => StagePipeline::Project->value,
                'key' => $status->value,
                'name' => $status->label(),
                'color' => ($colors[$status->value] ?? StageColor::Grey)->value,
                'anchor' => ($projectAnchors[$status->value] ?? null)?->value,
                'sort_order' => $status->position(),
            ];
        }

        DB::table('stages')->insert(array_map(fn (array $row) => [
            ...$row,
            'tenant_id' => $tenantId,
            'public_id' => (string) new Cuid2,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));

        DB::table('project_types')->insert(array_map(fn (ProjectType $type, int $index) => [
            'tenant_id' => $tenantId,
            'public_id' => (string) new Cuid2,
            'key' => $type->value,
            'name' => $type->label(),
            'sort_order' => $index,
            'is_active' => true,
            'is_default' => $index === 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ProjectType::cases(), array_keys(ProjectType::cases())));
    }
};
