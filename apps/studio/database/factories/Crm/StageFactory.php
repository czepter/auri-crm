<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StageColor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    protected $model = Stage::class;

    public function definition(): array
    {
        return [
            'pipeline' => StagePipeline::Lead,
            'key' => 'vorgespraech',
            'name' => 'Vorgespräch',
            'color' => StageColor::Blue,
            'anchor' => null,
            'sort_order' => 90,
        ];
    }

    public function pipeline(StagePipeline $pipeline): static
    {
        return $this->state(fn () => ['pipeline' => $pipeline]);
    }

    public function anchored(StageAnchor $anchor): static
    {
        return $this->state(fn () => ['anchor' => $anchor]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
