<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    private const CATEGORIES = [
        'software_abos' => 'Software & Abos',
        'hosting_domains' => 'Hosting & Domains',
        'werbung' => 'Werbung',
        'weiterbildung' => 'Weiterbildung',
        'fotozubehoer' => 'Fotozubehör',
        'buero' => 'Büro',
        'reise' => 'Reise',
        'versicherung' => 'Versicherung',
        'gebuehren' => 'Gebühren',
        'sonstiges' => 'Sonstiges',
    ];

    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('euer_line', 50);
            $table->string('skr04_account', 10)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'key']);
        });

        $now = now('UTC');
        $sort = 0;

        // Inserted here, not seeded: every RefreshDatabase run and every fresh
        // tenant needs the ten rows before the first expense.
        foreach (self::CATEGORIES as $key => $label) {
            DB::table('expense_categories')->insertOrIgnore([
                'tenant_id' => config('tenancy.tenant_id'),
                'public_id' => (string) new Cuid2,
                'key' => $key,
                'label' => $label,
                'euer_line' => $key === 'reise' ? 'reisekosten' : 'sonstige_betriebsausgaben',
                'sort_order' => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
