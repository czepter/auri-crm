<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts used only by the Playwright suite. Never registered in
 * DatabaseSeeder — it is invoked explicitly by the e2e setup.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'e2e-basic@studio.test'],
            [
                'name' => 'E2E Basic',
                'password' => 'correct-horse',
                'role' => StaffRole::Owner,
            ],
        );
    }
}
