<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes "the wizard was never opened" from "display_name is
     * still blank" — display_name alone cannot tell those apart, and every
     * other studio_settings column already has a fallback or a default. See
     * App\Http\Middleware\RequireStudioOnboarding, the only reader.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->timestampTz('onboarding_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
