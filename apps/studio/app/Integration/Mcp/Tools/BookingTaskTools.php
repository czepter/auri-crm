<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #401: what an MCP audit found missing — booking, completion and tasks.
 */
final class BookingTaskTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'list_projects',
                title: 'List projects',
                description: 'Lists projects — the same set that answers /projekte. Every project is booked; an unbooked pursuit is a lead, see list_leads for those. Every id is a public_id, never a row\'s internal integer id.',
                method: 'GET',
                path: '/projects',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                    'since' => $schema->string()->description('ISO 8601 datetime — only projects updated at or after this instant.'),
                ],
                annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => [
                    'projects' => $body['data'],
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
            ),

            new ToolDefinition(
                name: 'book_project',
                title: 'Book a project',
                description: 'Commit the studio to a project: record the booking on it and advance every uncancelled shoot to the booked stage, the same move the project screen\'s Buchen button makes. Answers with the project detail. A project already booked is a no-op and keeps its original booking.',
                method: 'POST',
                path: '/cases/{case}/book',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'complete_project',
                title: 'Complete a project',
                description: 'Mark a project done, independent of what its shoots\' statuses stand at. Refused if it is already completed. Reversible with reopen_project.',
                method: 'POST',
                path: '/cases/{case}/complete',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'reopen_project',
                title: 'Reopen a project',
                description: 'Take back a completion entered by mistake. Refused if the project is not completed.',
                method: 'DELETE',
                path: '/cases/{case}/complete',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'list_tasks',
                title: 'List a project’s tasks',
                description: 'A project\'s tasks, open first and by due date, undated last. `done` narrows to completed or open ones; left out, both come back.',
                method: 'GET',
                path: '/cases/{case}/tasks',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'done' => $schema->boolean()->description('true for completed tasks only, false for open ones'),
                ],
                annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => ['tasks' => $body['data']],
            ),

            new ToolDefinition(
                name: 'create_task',
                title: 'Create a task',
                description: 'Add a task to a project, due on a fixed date or hanging off an anchor (a shoot’s date, a shoot reaching a status, a lead reaching a stage) with an offset in days. Never both `due_on` and `anchor`.',
                method: 'POST',
                path: '/cases/{case}/tasks',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'title' => $schema->string()->max(255)->required(),
                    'audience' => $schema->string()->enum(['internal', 'client'])->required(),
                    'due_on' => $schema->string()->nullable()->description('YYYY-MM-DD. Mutually exclusive with anchor.'),
                    'anchor' => $schema->string()->nullable()->description('What the due date hangs off, e.g. \'shoot:<id>\' or \'shoot:<id>:<status>\' or \'stage:<key>\'.'),
                    'offset_days' => $schema->integer()->min(-365)->max(365)->nullable()->description('Days after (or before, negative) the anchor. Only with anchor.'),
                    'shoot' => $schema->string()->nullable()->description('Shoot public id this task belongs to'),
                    'depends_on' => $schema->string()->nullable()->description('Public id of a task on the same project this one waits on'),
                    'assignee' => $schema->string()->nullable()->description('User public id. Left out: crew, then the whole studio.'),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'complete_task',
                title: 'Complete a task',
                description: 'Mark a task done. Refused while it depends on a task that is not done itself. `completed_on` states the day the work actually happened, for one entered after the fact; sending it on a task already done corrects the date rather than reopening it.',
                method: 'POST',
                path: '/tasks/{task}/complete',
                schema: fn (JsonSchema $schema) => [
                    'task' => $schema->string()->description('The task public id')->required(),
                    'completed_on' => $schema->string()->nullable()->description('YYYY-MM-DD, never in the future. Defaults to now.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'reopen_task',
                title: 'Reopen a task',
                description: 'Take a completion back, for the list that only shows what is done.',
                method: 'POST',
                path: '/tasks/{task}/reopen',
                schema: fn (JsonSchema $schema) => [
                    'task' => $schema->string()->description('The task public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'assign_task',
                title: 'Assign a task',
                description: 'Hand a task over to a staff member, or take it back by leaving assignee out.',
                method: 'POST',
                path: '/tasks/{task}/assign',
                schema: fn (JsonSchema $schema) => [
                    'task' => $schema->string()->description('The task public id')->required(),
                    'assignee' => $schema->string()->nullable()->description('User public id. Null clears the assignment.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'set_task_dependency',
                title: 'Set what a task waits on',
                description: 'Change what a task depends on, or clear it by leaving depends_on out. Refused if the chosen task would wait on itself, directly or through a chain.',
                method: 'POST',
                path: '/tasks/{task}/depend',
                schema: fn (JsonSchema $schema) => [
                    'task' => $schema->string()->description('The task public id')->required(),
                    'depends_on' => $schema->string()->nullable()->description('Public id of a task on the same project. Null clears the dependency.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_task',
                title: 'Delete a task',
                description: 'Remove a task. There is no undo.',
                method: 'DELETE',
                path: '/tasks/{task}',
                schema: fn (JsonSchema $schema) => [
                    'task' => $schema->string()->description('The task public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted task '.$arguments['task'].'.',
            ),
        ];
    }
}
