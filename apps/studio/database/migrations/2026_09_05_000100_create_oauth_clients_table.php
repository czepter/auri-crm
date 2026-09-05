<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // N-04: what config/mcp_oauth.php's single well-known client stopped
        // being enough for. A remote MCP client (claude.ai) is handed no
        // client_id to paste — it registers itself over RFC 7591 and expects
        // one back, so the allow list needs rows.
        //
        // Tenant-scoped like everything else: MT-01 gives each tenant its own
        // host, a client registers against the host it will authorize on, and
        // a registration made on one tenant's host has no business naming a
        // redirect_uri for another's.
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('client_id');
            $table->string('client_name')->nullable();
            $table->jsonb('redirect_uris');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
