<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('recipient_contact_id')->nullable()->after('project_id')->constrained('contacts')->nullOnDelete();
            $table->string('recipient_street')->nullable()->after('recipient_contact_id');
            $table->string('recipient_postcode', 32)->nullable()->after('recipient_street');
            $table->string('recipient_city')->nullable()->after('recipient_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_contact_id');
            $table->dropColumn(['recipient_street', 'recipient_postcode', 'recipient_city']);
        });
    }
};
