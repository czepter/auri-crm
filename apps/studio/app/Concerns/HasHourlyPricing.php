<?php

namespace App\Concerns;

use InvalidArgumentException;

/**
 * Q-08: the hourly rate and the bounds a client's slider moves between,
 * shared by Quote (the copy) and Package (the template).
 *
 * A trait rather than a base class: the two models share these five columns
 * and nothing else, and Package lives in Catalog while Quote lives in
 * Quotes. bootHasHourlyPricing() is Laravel's own hook, so neither model's
 * existing booted() has to know this guard exists.
 */
trait HasHourlyPricing
{
    protected static function bootHasHourlyPricing(): void
    {
        static::saving(function (self $model) {
            if ($model->hourly_rate === null) {
                return;
            }

            if ($model->min_hours !== null && $model->max_hours !== null && $model->min_hours > $model->max_hours) {
                throw new InvalidArgumentException('min_hours cannot exceed max_hours.');
            }

            if ($model->hours === null) {
                return;
            }

            if ($model->min_hours !== null && $model->hours < $model->min_hours) {
                throw new InvalidArgumentException('hours is below min_hours.');
            }

            if ($model->max_hours !== null && $model->hours > $model->max_hours) {
                throw new InvalidArgumentException('hours is above max_hours.');
            }
        });
    }

    /** The switch: null is a classic quote and every screen behaves as it did before. */
    public function hasSlider(): bool
    {
        return $this->hourly_rate !== null;
    }
}
