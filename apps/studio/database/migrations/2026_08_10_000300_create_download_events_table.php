<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_events', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zip_build_id')->constrained()->cascadeOnDelete();

            // No account behind this — the same bare-string shape
            // AssetFavorite's guest_identifier already established (G-09).
            $table->string('requester_identifier');
            $table->string('ip', 45)->nullable();

            // The calendar day the request landed on (UTC) — the actual
            // bounding unit (G-11, #60): DownloadEvent::requesterDayCount()
            // groups on (requester_identifier, request_date), never on the
            // row count, so a resumed Range request is free and only a
            // genuinely new requester/day counts.
            $table->date('request_date');

            $table->timestampsTz();

            $table->index(['tenant_id', 'zip_build_id', 'requester_identifier', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_events');
    }
};
