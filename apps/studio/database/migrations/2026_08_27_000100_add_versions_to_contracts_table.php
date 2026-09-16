<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Fassung 1, 2, 3 … within one project. The unique index is what
            // makes two simultaneous "Fassung sichern" clicks collide in the
            // database rather than produce two Fassung 3.
            $table->unsignedInteger('version')->default(1);
            $table->string('state')->default('draft');

            // The wording of this Fassung, once someone has edited it. Null
            // means "still exactly the template row this contract names" —
            // see EnsureContractArtifact, which falls back to it.
            $table->text('body')->nullable();
            $table->json('blocks')->nullable();

            // Null while the Fassung is a draft. The moment it is set, the
            // previously valid Fassung becomes Ersetzt.
            $table->timestampTz('sent_at')->nullable();

            $table->unique(['project_id', 'version']);
        });

        $this->backfill();
    }

    /**
     * Every contract that exists today was rendered and visible to its client
     * the moment it was created, so none of them is a draft: they are
     * numbered by age within their project, the newest and the signed one are
     * valid, everything older is Ersetzt.
     */
    private function backfill(): void
    {
        $rows = DB::table('contracts')->orderBy('project_id')->orderBy('id')
            ->get(['id', 'project_id', 'created_at']);

        $signed = DB::table('contract_signatures')->pluck('contract_id')->flip();
        $newest = $rows->groupBy('project_id')->map(fn ($group) => $group->last()->id);

        $version = [];

        foreach ($rows as $row) {
            $version[$row->project_id] = ($version[$row->project_id] ?? 0) + 1;

            $isValid = $signed->has($row->id) || $newest[$row->project_id] === $row->id;

            DB::table('contracts')->where('id', $row->id)->update([
                'version' => $version[$row->project_id],
                'state' => $isValid ? 'valid' : 'superseded',
                'sent_at' => $row->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'version']);
            $table->dropColumn(['version', 'state', 'body', 'blocks', 'sent_at']);
        });
    }
};
