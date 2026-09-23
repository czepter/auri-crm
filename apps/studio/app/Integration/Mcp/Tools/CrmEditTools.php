<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #400/#363: the MCP-audit findings — contact, project, shoot and
 * project-contact writes the admin surface already had and the api surface
 * (and so the tools calling it) did not.
 */
final class CrmEditTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'create_contact',
                title: 'Create a contact',
                description: 'Add a person to the address book on their own, without a project. An email already on file is refused — search_contacts finds them instead.',
                method: 'POST',
                path: '/contacts',
                schema: fn (JsonSchema $schema) => [
                    'name' => $schema->string()->max(255)->required(),
                    'email' => $schema->string()->format('email')->max(255)->nullable(),
                    'phone' => $schema->string()->max(64)->nullable(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_contact',
                title: 'Update a contact',
                description: 'Change a contact\'s name, e-mail, phone or billing address. Only the fields given change. search_contacts shows the current values.',
                method: 'PATCH',
                path: '/contacts/{contact}',
                schema: fn (JsonSchema $schema) => [
                    'contact' => $schema->string()->description('The contact public id')->required(),
                    'name' => $schema->string()->max(255),
                    'email' => $schema->string()->format('email')->max(255)->nullable(),
                    'phone' => $schema->string()->max(64)->nullable(),
                    'street' => $schema->string()->max(255)->nullable(),
                    'postcode' => $schema->string()->max(32)->nullable(),
                    'city' => $schema->string()->max(255)->nullable(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_case',
                title: 'Update a case',
                description: 'Change a case\'s name, type or lead source. Only the fields given change; type_other and source_other are kept unless given even when type or source changes to something else. get_case shows the current values.',
                method: 'PATCH',
                path: '/cases/{case}',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'name' => $schema->string()->max(255),
                    'type' => $schema->string()->description('The project type key — see list_project_types'),
                    'type_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was. Only kept beside type: other.'),
                    'source' => $schema->string()->enum(['referral', 'website', 'instagram', 'wedding_fair', 'other']),
                    'source_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was. Only kept beside source: other.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'remove_case_contact',
                title: 'Remove a case contact',
                description: 'Detach a secondary contact — added by add_case_contact — from a case. The primary contact cannot be removed this way.',
                method: 'DELETE',
                path: '/cases/{case}/contacts/{contact}',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'contact' => $schema->string()->description('The contact public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Removed contact '.$arguments['contact'].' from case '.$arguments['case'].'.',
            ),

            new ToolDefinition(
                name: 'update_shoot',
                title: 'Update a shoot',
                description: 'Change a shoot\'s date, call time, end time, venue, label or kind. Only the fields given change. get_case shows the current values.',
                method: 'PATCH',
                path: '/shoots/{shoot}',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'kind' => $schema->string()->max(255),
                    'label' => $schema->string()->max(255)->nullable(),
                    'date' => $schema->string()->nullable()->description('YYYY-MM-DD'),
                    'call_time' => $schema->string()->nullable()->description('HH:MM'),
                    'end_time' => $schema->string()->nullable()->description('HH:MM'),
                    'venue_name' => $schema->string()->max(255)->nullable(),
                    'venue_city' => $schema->string()->max(255)->nullable(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_shoot',
                title: 'Delete a shoot',
                description: 'Remove a shooting day that was added by mistake. The primary shoot cannot be deleted this way — set_primary_shoot another one first, or use delete_case.',
                method: 'DELETE',
                path: '/shoots/{shoot}',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted shoot '.$arguments['shoot'].'.',
            ),

            new ToolDefinition(
                name: 'set_primary_shoot',
                title: 'Set the primary shoot',
                description: 'Move the "Haupttermin" designation to a different shoot on the same project. A cancelled shoot cannot become primary.',
                method: 'POST',
                path: '/shoots/{shoot}/primary',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id to make primary')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),
        ];
    }
}
