<?php

namespace App\Settings\Console\Commands;

use App\Enums\StaffRole;
use App\Models\StudioSettings;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Privacy\Actions\DeleteTenant;
use App\Privacy\Notifications\Studio\StudioDeletionReminder;
use Illuminate\Console\Command;

class DeleteDueStudio extends Command
{
    protected $signature = 'studio:delete-due';

    protected $description = 'Löscht das Studio, dessen Löschtag erreicht ist, und erinnert den Inhaber am Tag davor.';

    public function handle(DeleteTenant $delete, NotificationService $notifications): int
    {
        $date = StudioSettings::current()->deletionDate();

        if ($date === null) {
            return self::SUCCESS;
        }

        if (now()->gte($date)) {
            $delete(config('tenancy.tenant_id'), 'deletion_requested');
            $this->info('Studio gelöscht.');
        } elseif ($date->subDay()->toDateString() === now($date->timezone)->toDateString()) {
            $owner = User::query()->where('role', StaffRole::Admin)->first();

            if ($owner !== null) {
                $notifications->send($owner, new StudioDeletionReminder($owner->name, $date));
                $this->info('Erinnerung gesendet.');
            }
        }

        return self::SUCCESS;
    }
}
