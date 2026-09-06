<?php

use App\Crm\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists every task of the studio with its assignee', function () {
    $staff = staffMember();
    Task::factory()->create(['assignee_id' => $staff->id, 'title' => 'Galerie freigeben']);
    Task::factory()->create(['title' => 'Zweitfotograf anfragen']);

    $this->actingAs($staff, 'staff')
        ->get('/aufgaben')
        ->assertInertia(fn ($page) => $page
            ->component('Tasks/Index')
            ->has('tasks', 2)
            ->has('staffOptions')
            ->where('tasks.0.assigneeName', $staff->name)
        );
});
