<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sample data so local development and tests have something to look
        // at — the whole method is guarded, not only DemoCrmSeeder's call,
        // because db:seed against production is exactly the command someone
        // runs once against the wrong environment. /einrichtung is how
        // production gets its first account.
        if (! app()->isProduction()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $this->call(DemoCrmSeeder::class);
            $this->call(DemoFinanceSeeder::class);
        }
    }
}
