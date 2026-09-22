<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAP = [
        'owner' => 'admin',
        'photographer' => 'manager',
        'editor' => 'contributor',
        'second_shooter' => 'contributor',
        'assistant' => 'contributor',
    ];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            DB::table('users')->where('role', $from)->update(['role' => $to]);
            DB::table('staff_invitations')->where('role', $from)->update(['role' => $to]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('contributor')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('assistant')->change();
        });

        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'photographer']);
        DB::table('users')->where('role', 'contributor')->update(['role' => 'assistant']);
        DB::table('staff_invitations')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('staff_invitations')->where('role', 'manager')->update(['role' => 'photographer']);
        DB::table('staff_invitations')->where('role', 'contributor')->update(['role' => 'assistant']);
        DB::table('staff_invitations')->where('role', 'reader')->update(['role' => 'assistant']);
        DB::table('users')->where('role', 'reader')->update(['role' => 'assistant']);
    }
};
