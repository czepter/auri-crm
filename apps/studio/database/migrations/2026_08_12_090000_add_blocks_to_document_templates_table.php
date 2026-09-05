<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The block editor's source of truth. `body` keeps its exact meaning — HTML,
 * serialized from these blocks on save — so MergeFieldResolver,
 * DocumentRenderer and the Gotenberg pipeline are untouched by this column.
 *
 * Nullable with no backfill: a template written before the editor existed
 * keeps blocks null and is imported into a single rawHtml block the first
 * time someone opens it. A template nobody edits stays exactly as it is.
 *
 * ponytail: blocks and body are written together by the client and could in
 * principle disagree if a bug posts one without the other. blocks is
 * authoritative and re-saving repairs body. Upgrade path if that ever bites:
 * a PHP serializer deriving body server-side, which is deliberately not
 * written here — it would be a second renderer to keep in step with the TS one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->jsonb('blocks')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};
