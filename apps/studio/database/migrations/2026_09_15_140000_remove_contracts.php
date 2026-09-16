<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #441: the accepted quote is the agreement; contracts go. No archive — the
 * one production instance never created a contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contract_signers');
        Schema::dropIfExists('contracts');

        DB::table('document_templates')->where('type', 'contract')->delete();
        DB::table('number_ranges')->where('document_type', 'contract')->delete();
        DB::table('quote_number_counters')->where('document_type', 'contract')->delete();

        DB::table('automation_rules')
            ->where('anchor', 'contract_deadline')
            ->orWhere('condition', 'contract_unsigned')
            ->delete();
        DB::table('automation_runs')->where('anchorable_type', 'App\Contracts\Models\Contract')->delete();

        DB::table('notification_preferences')->where('notification_class', 'App\Notifications\Studio\ContractSigned')->delete();
        DB::table('in_app_notifications')->where('type', 'App\Notifications\Studio\ContractSigned')->delete();

        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('portal_sign_contracts_enabled'));
        DB::statement("UPDATE contacts SET portal_overrides = portal_overrides - 'sign_contracts' WHERE portal_overrides IS NOT NULL");

        $this->retireContractStage();
    }

    /** Leads on it move back to the accepted quote, and so does whatever hangs off its key. */
    private function retireContractStage(): void
    {
        foreach (DB::table('stages')->where('pipeline', 'lead')->where('anchor', 'contract_sent')->get() as $stage) {
            $target = DB::table('stages')
                ->where('tenant_id', $stage->tenant_id)
                ->where('pipeline', 'lead')
                ->where('anchor', 'quote_accepted')
                ->value('key');

            DB::table('leads')->where('tenant_id', $stage->tenant_id)->where('stage', $stage->key)->update(['stage' => $target]);
            DB::table('task_templates')->where('tenant_id', $stage->tenant_id)->where('pipeline', 'lead')->where('stage', $stage->key)->update(['stage' => $target]);
            DB::table('tasks')->where('tenant_id', $stage->tenant_id)->where('due_anchor', "stage:{$stage->key}")->update(['due_anchor' => "stage:{$target}"]);
            DB::table('stages')->where('id', $stage->id)->delete();
        }
    }
};
