<?php

namespace App\Gallery\Actions;

use App\Crm\Actions\CreateTask;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Task;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryActivity;
use App\Models\User;

class CreateFavoritesTask
{
    public function __construct(private readonly CreateTask $createTask) {}

    public function __invoke(Gallery $gallery, ?User $actor): Task
    {
        abort_if($gallery->case === null, 422);

        return GalleryActivity::openTask($gallery) ?? $this->createTask->forCase($gallery->case, [
            'title' => GalleryActivity::taskTitle($gallery),
            'audience' => TaskAudience::Internal->value,
            'assignee' => $actor?->public_id,
        ]);
    }
}
