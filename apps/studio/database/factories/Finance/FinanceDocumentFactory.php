<?php

namespace Database\Factories\Finance;

use App\Finance\Models\FinanceDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceDocument>
 */
class FinanceDocumentFactory extends Factory
{
    protected $model = FinanceDocument::class;

    public function definition(): array
    {
        return [
            'filename' => 'rechnung.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'uploaded_at' => now(),
        ];
    }
}
