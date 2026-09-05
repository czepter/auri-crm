<?php

namespace App\Crm\Enums;

/**
 * A German label for a stored enum value. Every CRM enum reaches a screen, and
 * this is what lets a controller hand the frontend {value, label} pairs without
 * knowing which enum it holds.
 */
interface HasLabel
{
    public function label(): string;
}
