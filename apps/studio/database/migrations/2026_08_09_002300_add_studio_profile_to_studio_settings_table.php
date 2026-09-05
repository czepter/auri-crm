<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-02's three fieldsets — legal entity, brand, bank — on top of T-01's
     * lone `display_name` scaffolding column, which stays exactly as it was
     * and is not duplicated here. All nullable: nothing in this table is
     * required until the fieldset that owns it says so (Steuernummer/
     * USt-IdNr is enforced in StudioSettingsController's validation, not
     * here — a database-level constraint can't express "at least one of
     * two columns").
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            // Legal entity — §5 TMG / §14 UStG particulars.
            $table->string('legal_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('street')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('register_number')->nullable();
            $table->string('register_court')->nullable();
            $table->string('representative')->nullable();

            // Brand. display_name already exists (T-01); logo_path points at
            // the one stored file the PDF renderer and the portal both read
            // rather than each holding their own upload.
            $table->string('website')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('logo_path')->nullable();

            // Bank — optional today (T-07 governs when it becomes required).
            $table->string('bank_account_holder')->nullable();
            $table->string('bank_iban')->nullable();
            $table->string('bank_bic')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
                'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
                'website', 'accent_color', 'logo_path',
                'bank_account_holder', 'bank_iban', 'bank_bic',
            ]);
        });
    }
};
