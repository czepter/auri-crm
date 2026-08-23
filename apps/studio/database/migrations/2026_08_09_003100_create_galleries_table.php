<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Nullable: a delivery gallery frequently spans the whole project
            // rather than one shoot (G-05) — the wedding-day gallery, for a
            // project with an engagement shoot and a wedding-day shoot, has
            // nowhere truthful to point if this were required.
            $table->foreignId('shoot_id')->nullable()->constrained()->cascadeOnDelete();

            // Draft state — freely editable, never what a guest is served.
            // publish() reads these to write an immutable GalleryVersion; see
            // Gallery::publish() for why editing these never touches a row
            // already published.
            $table->string('title')->nullable();
            $table->json('assets')->default('[]');

            // Ships false-by-default and inert: nothing yet reads this flag to
            // gate anything, because delivery/download flows are future
            // stories. What keeps it non-decorative is
            // App\Gallery\Listeners\RecomputeGalleryBalanceSettled, wired to
            // the real InstallmentPaid event M-05 already delivers.
            $table->boolean('requires_settled_balance')->default(false);
            $table->boolean('balance_settled')->default(false);

            // P-09's three portal switches, on the gallery rather than in the
            // settings store: they are answered per delivery, and the studio
            // default only decides what a new one is born with. Not
            // three-valued — a gallery either offers the thing or it does
            // not, and there is no level below it to inherit from.
            $table->boolean('downloads_enabled')->default(true);
            $table->boolean('lists_enabled')->default(true);
            $table->boolean('comments_enabled')->default(true);

            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
