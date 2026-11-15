<?php

namespace App\Crm\Support;

use App\Crm\Enums\SocialPlatform;
use Illuminate\Validation\Rule;

class SocialProfiles
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'social_profiles' => ['nullable', 'array', 'max:10'],
            'social_profiles.*.platform' => ['required', Rule::enum(SocialPlatform::class)],
            'social_profiles.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<int, array{platform: string, value: string}>|null  $profiles
     * @return list<array{platform: string, value: string}>|null
     */
    public static function normalize(?array $profiles): ?array
    {
        return collect($profiles)
            ->map(fn (array $profile) => ['platform' => $profile['platform'], 'value' => trim($profile['value'])])
            ->values()->all() ?: null;
    }
}
