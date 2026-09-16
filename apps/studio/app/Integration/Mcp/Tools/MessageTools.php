<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #404: the send half of E-06/E-13/E-12 that add_message never covered —
 * add_message (WriteTools) only backfills history, nothing leaves. Every
 * tool here reaches the client or moves the inbox, so `silent` is left off
 * throughout: a silent send is a contradiction (that is what add_message is
 * for), and offering the flag would only invite `send_message` to become a
 * second, confusing way to write history nobody reads.
 */
final class MessageTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'send_message',
                title: 'Send a message',
                description: 'Reply on a project\'s conversation — the client receives it by mail, unlike add_message, which only backfills history and sends nothing. Needs a thread the client already started; there is none to reply to on a project with no conversation yet. With scheduled_for it goes out later instead, cancellable until then with cancel_scheduled_message.',
                method: 'POST',
                path: '/projects/{project}/messages/send',
                schema: fn (JsonSchema $schema) => [
                    'project' => $schema->string()->description('The project public id')->required(),
                    'body' => $schema->string()->max(5000)->required(),
                    'scheduled_for' => $schema->string()->nullable()->description('When to send it. ISO 8601, in the future. Immediate if omitted.'),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
            ),

            new ToolDefinition(
                name: 'cancel_scheduled_message',
                title: 'Cancel a scheduled message',
                description: 'Deletes a message send_message scheduled for later, before it goes out. Refused once it has already sent.',
                method: 'DELETE',
                path: '/messages/{message}',
                schema: fn (JsonSchema $schema) => [
                    'message' => $schema->string()->description('The message public id, from send_message')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                confirmation: fn (array $arguments) => 'Cancelled message '.$arguments['message'].'.',
            ),

            new ToolDefinition(
                name: 'list_inbox_threads',
                title: 'List inbox threads',
                description: 'The Posteingang tray: every conversation, newest first, with its work state (unbeantwortet/wartet/erledigt), snippet and how long the newest line has waited. projectId is null for a conversation the client started without naming a booking — file_thread_to_project is what gives it one.',
                method: 'GET',
                path: '/inbox/threads',
                schema: fn (JsonSchema $schema) => [],
                annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'reply_inbox_thread',
                title: 'Reply on an inbox thread',
                description: 'The studio\'s reply from the Posteingang screen, by thread id rather than project — for a conversation list_inbox_threads shows, including one with no project yet.',
                method: 'POST',
                path: '/inbox/threads/{thread}/reply',
                schema: fn (JsonSchema $schema) => [
                    'thread' => $schema->string()->description('The thread public id, from list_inbox_threads')->required(),
                    'body' => $schema->string()->max(20000)->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
            ),

            new ToolDefinition(
                name: 'close_inbox_thread',
                title: 'Close or reopen an inbox thread',
                description: 'Toggles a conversation\'s erledigt state: closes an unanswered or waiting one, or reopens one already marked done. A client writing again reopens it on its own regardless.',
                method: 'POST',
                path: '/inbox/threads/{thread}/close',
                schema: fn (JsonSchema $schema) => [
                    'thread' => $schema->string()->description('The thread public id, from list_inbox_threads')->required(),
                ],
            ),

            new ToolDefinition(
                name: 'attach_inbound_mail',
                title: 'Attach an inbound mail to a thread',
                description: 'Files a mail the automatic ingest could not place onto the conversation it belongs to — the triage tray a studio works from list_inbox_threads. Writes it as a message from whichever of the thread\'s contacts sent it.',
                method: 'POST',
                path: '/inbox/messages/{inbound_message}/attach',
                schema: fn (JsonSchema $schema) => [
                    'inbound_message' => $schema->string()->description('The inbound mail id, from the Posteingang triage tray')->required(),
                    'thread' => $schema->string()->description('The thread public id it belongs on')->required(),
                ],
            ),

            new ToolDefinition(
                name: 'file_thread_to_project',
                title: 'File a thread to a project',
                description: 'Points a conversation that started without naming a booking (projectId null on list_inbox_threads) at the project it turned out to be about. One direction only — a thread that already names a project cannot be re-filed or detached.',
                method: 'POST',
                path: '/inbox/threads/{thread}/file',
                schema: fn (JsonSchema $schema) => [
                    'thread' => $schema->string()->description('The thread public id, from list_inbox_threads')->required(),
                    'project' => $schema->string()->description('The project public id to file it under')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            ),
        ];
    }
}
