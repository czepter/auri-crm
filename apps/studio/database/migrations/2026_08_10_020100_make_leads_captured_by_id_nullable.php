<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * I-03: CaptureInquiry now also runs for the api surface's "capture lead"
 * endpoint, where the caller is an ApiToken rather than a staff User — see
 * the sibling migration on internal_notes.author_id for the same reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leads ALTER COLUMN captured_by_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leads ALTER COLUMN captured_by_id SET NOT NULL');
    }
};
