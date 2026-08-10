<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * I-03: an internal note written through the api surface has no staff User
 * behind it — an ApiToken is its own kind of principal (I-01), never a User
 * row. author_id null means "the studio's own integration wrote this", not
 * "nobody"; InternalNoteData::fromNote falls back to a label for exactly
 * that case. Every note still written from the admin surface keeps a real
 * author — nothing here changes InternalNoteController's own behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE internal_notes ALTER COLUMN author_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE internal_notes ALTER COLUMN author_id SET NOT NULL');
    }
};
