<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('quote_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('via');
            $table->string('ip_hash', 16)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('counts')->default(true);
            $table->timestampTz('viewed_at');

            $table->index('quote_id');
            $table->index('invoice_id');
        });

        DB::statement('ALTER TABLE document_views ADD CONSTRAINT document_views_one_document CHECK ((quote_id IS NULL) <> (invoice_id IS NULL))');

        DB::table('invoices')->whereNotNull('viewed_at')->orderBy('id')->each(fn ($invoice) => DB::table('document_views')->insert([
            'tenant_id' => $invoice->tenant_id,
            'public_id' => (string) new Cuid2,
            'invoice_id' => $invoice->id,
            'via' => 'portal',
            'viewed_at' => $invoice->viewed_at,
        ]));

        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('viewed_at'));
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->timestampTz('viewed_at')->nullable());

        DB::statement('UPDATE invoices SET viewed_at = (SELECT MIN(viewed_at) FROM document_views WHERE document_views.invoice_id = invoices.id AND counts)');

        Schema::dropIfExists('document_views');
    }
};
