<?php

use App\Crm\Notifications\Studio\TasksDue;
use App\Models\User;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Models\InAppNotification;
use App\Notifications\Models\NotificationPreference;
use App\Staff\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('points stored notification classes at where the classes moved', function () {
    $migration = require database_path('migrations/2026_10_29_000100_rename_moved_notification_classes.php');
    $user = User::factory()->create();
    $owner = ['notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id];

    NotificationPreference::create([...$owner, 'notification_class' => 'App\Notifications\Studio\TasksDue', 'channel' => 'mail', 'enabled' => false, 'mode' => NotificationMode::Briefing]);
    InAppNotification::create([...$owner, 'type' => 'App\Notifications\StaffInvited', 'data' => []]);

    $migration->up();

    expect(NotificationPreference::query()->pluck('notification_class')->all())->toBe([TasksDue::class])
        ->and(InAppNotification::query()->pluck('type')->all())->toBe([StaffInvited::class])
        ->and(array_filter((new ReflectionClassConstant($migration, 'MOVED'))->getValue(), fn (string $class) => ! class_exists($class)))->toBe([]);
});
