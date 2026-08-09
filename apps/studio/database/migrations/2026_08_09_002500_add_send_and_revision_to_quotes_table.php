<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Q-06's three columns: sent_at and number are set exactly once, by
 * App\Quotes\Actions\SendQuote, alongside the status flip to `sent` — see
 * Quote::snapshotVat() for why every other snapshotted column already lived
 * on this table before this migration. predecessor_id is the "a revision is
 * a new row" half of the story: nullable because a first-ever quote has none,
 * self-referencing because a revision is a quote exactly like its
 * predecessor was.
 *
 * restrictOnDelete on predecessor_id: nothing in this schema deletes a quote
 * today, but a predecessor a revision still points to must not be able to
 * disappear out from under it — the same reasoning contract_template_id
 * gives on the contracts table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestampTz('sent_at')->nullable()->after('valid_until');
            $table->string('number')->nullable()->after('sent_at');
            $table->foreignId('predecessor_id')->nullable()->after('project_id')
                ->constrained('quotes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('predecessor_id');
            $table->dropColumn(['sent_at', 'number']);
        });
    }
};
