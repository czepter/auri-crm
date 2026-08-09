<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Sample leads so the board has something on it locally. Guarded rather
        // than merely "not called in production", because db:seed is exactly
        // the command someone runs against the wrong environment once.
        if (! app()->isProduction()) {
            $this->call(DemoCrmSeeder::class);
        }
    }
}
