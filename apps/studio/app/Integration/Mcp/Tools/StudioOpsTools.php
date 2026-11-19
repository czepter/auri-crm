<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #406: the crew, studio-settings, closures, number-range, form and
 * portal-visibility ops the admin surface has and the api surface gained
 * alongside these tools — everything I-02/I-03's original tool set left out.
 */
final class StudioOpsTools
{
    private const FORM_FIELD_TYPES = ['text', 'short_text', 'boolean', 'select', 'date', 'time', 'name_list', 'group_photos', 'file'];

    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'list_staff',
                title: 'List staff',
                description: 'Every staff user, with the public id assign_crew needs.',
                method: 'GET',
                path: '/staff',
                schema: fn (JsonSchema $schema) => [],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'list_crew',
                title: 'List a shoot\'s crew',
                description: 'Who is assigned to a shoot, and in what role.',
                method: 'GET',
                path: '/shoots/{shoot}/crew',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'assign_crew',
                title: 'Assign crew to a shoot',
                description: 'Puts a staff user on a shoot in a given role. Assigning someone already on the day updates their role instead of failing.',
                method: 'POST',
                path: '/shoots/{shoot}/crew',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'user' => $schema->string()->description('The staff public id, from list_staff')->required(),
                    'role' => $schema->string()->enum(['lead_photographer', 'second_shooter', 'assistant'])->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'remove_crew',
                title: 'Remove a crew assignment',
                description: 'Takes a staff user off a shoot. The assignment id comes from list_crew.',
                method: 'DELETE',
                path: '/crew/{assignment}',
                schema: fn (JsonSchema $schema) => [
                    'assignment' => $schema->string()->description('The crew assignment public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Removed crew assignment '.$arguments['assignment'].'.',
            ),

            new ToolDefinition(
                name: 'get_studio_settings',
                title: 'Get studio settings',
                description: 'VAT mode and rate, and the quote/payment defaults create_quote relies on. Never mail credentials, bank details or tokens.',
                method: 'GET',
                path: '/studio-settings',
                schema: fn (JsonSchema $schema) => [],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'update_legal_texts',
                title: 'Set legal text links',
                description: 'Links the portal and quote mails send clients to for Impressum, Datenschutz, AGB and Widerrufsbelehrung. Only the texts passed change; null clears a link. A quote cannot be sent while the Widerrufsbelehrung has neither a link nor a template.',
                method: 'PATCH',
                path: '/studio-settings/legal-texts',
                schema: fn (JsonSchema $schema) => [
                    'impressum' => $schema->string()->format('uri')->max(255)->nullable(),
                    'datenschutz' => $schema->string()->format('uri')->max(255)->nullable(),
                    'agb' => $schema->string()->format('uri')->max(255)->nullable(),
                    'widerruf' => $schema->string()->format('uri')->max(255)->nullable(),
                ],
                annotations: ToolDefinition::OVERWRITES,
            ),

            new ToolDefinition(
                name: 'list_studio_closures',
                title: 'List studio closures',
                description: 'The studio\'s own closed date ranges, the second conflict source shoots_in_date_range does not see on its own. A message, when set, is the notice clients read in the portal.',
                method: 'GET',
                path: '/studio-closures',
                schema: fn (JsonSchema $schema) => [],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'list_number_ranges',
                title: 'List number ranges',
                description: 'One row per document type, with its prefix and a preview of the next number it would issue.',
                method: 'GET',
                path: '/number-ranges',
                schema: fn (JsonSchema $schema) => [],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'list_form_templates',
                title: 'List form templates',
                description: 'The studio\'s active questionnaire templates with their fields. The template id is what create_form seeds from; the field shape is the one update_form takes.',
                method: 'GET',
                path: '/form-templates',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                    'limit' => $schema->integer()->min(1)->max(100)->description('Page size, up to 100. Defaults to 25.'),
                ],
                annotations: ToolDefinition::READ,
                payload: fn (array $arguments) => [
                    'cursor' => $arguments['cursor'] ?? null,
                    'per_page' => $arguments['limit'] ?? null,
                ],
                structure: fn (array $body) => [
                    'templates' => array_map(self::withSelectOptionsOnly(...), $body['data']),
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
                outputSchema: fn (JsonSchema $schema) => [
                    'templates' => $schema->array()->items(self::formTemplate($schema)),
                    'nextCursor' => $schema->string()->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'create_form_template',
                title: 'Create a form template',
                description: 'Creates a questionnaire template that create_form can seed projects from. Field ids are returned for update_form_template.',
                method: 'POST',
                path: '/form-templates',
                schema: fn (JsonSchema $schema) => [
                    'name' => $schema->string()->max(120)->required()->description('Unique per studio, case-insensitive.'),
                    'description' => $schema->string()->max(500)->nullable()->description('Staff-only note.'),
                    'project_type' => $schema->string()->nullable()->description('The project type key — see list_project_types'),
                    'fields' => $schema->array()->items(self::templateField($schema))->max(100),
                ],
                silent: false,
                structure: fn (array $body) => ['template' => self::withSelectOptionsOnly($body['data'])],
                outputSchema: fn (JsonSchema $schema) => ['template' => self::formTemplate($schema)],
            ),

            new ToolDefinition(
                name: 'update_form_template',
                title: 'Replace a form template\'s fields',
                description: 'Renames a template and sets its fields. Send every field the template should keep, each with its id from list_form_templates. Forms already created from the template are not changed.',
                method: 'PATCH',
                path: '/form-templates/{template}',
                schema: fn (JsonSchema $schema) => [
                    'template' => $schema->string()->description('The form template public id')->required(),
                    'name' => $schema->string()->max(120),
                    'description' => $schema->string()->max(500)->nullable()->description('Omitting it keeps the current one.'),
                    'project_type' => $schema->string()->nullable()->description('The project type key — see list_project_types. Omitting it keeps the current one.'),
                    'fields' => $schema->array()->items(self::templateField($schema))->max(100)->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
                structure: fn (array $body) => ['template' => self::withSelectOptionsOnly($body['data'])],
                outputSchema: fn (JsonSchema $schema) => ['template' => self::formTemplate($schema)],
            ),

            new ToolDefinition(
                name: 'list_forms',
                title: 'List a project\'s forms',
                description: 'A project\'s questionnaires, seeded or blank, shared or not, with their fields and current answers.',
                method: 'GET',
                path: '/cases/{case}/forms',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => ['forms' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['forms' => $schema->array()->items(self::form($schema))],
            ),

            new ToolDefinition(
                name: 'create_form',
                title: 'Create a form',
                description: 'Seeds a new questionnaire on a project from a template\'s current questions, or starts one blank when `template` is omitted.',
                method: 'POST',
                path: '/cases/{case}/forms',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'template' => $schema->string()->nullable()->description('A form template public id'),
                ],
                silent: false,
                // Omitted rather than null: "no template" is a blank form,
                // and the route reads the key's absence, not its value.
                payload: fn (array $arguments) => array_filter(['template' => $arguments['template'] ?? null], fn ($value) => $value !== null),
                structure: fn (array $body) => ['form' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['form' => self::form($schema)],
            ),

            new ToolDefinition(
                name: 'update_form',
                title: 'Replace a form\'s fields',
                description: 'Renames a form and sets its fields, the same shape create_form seeds, personalised. Send every field the form should keep, each with its id from list_forms.',
                method: 'PATCH',
                path: '/forms/{form}',
                schema: fn (JsonSchema $schema) => [
                    'form' => $schema->string()->description('The form instance public id')->required(),
                    'name' => $schema->string()->required(),
                    'fields' => $schema->array()->items($schema->object([
                        'id' => $schema->string()->nullable()->description('The field public id from list_forms. Send it to keep the field and its answers; a field without one is created new, a field left out is deleted with its answers.'),
                        'label' => $schema->string()->required(),
                        'type' => $schema->string()->enum(self::FORM_FIELD_TYPES)->required(),
                        'required' => $schema->boolean()->required(),
                        'help' => $schema->string()->nullable()->description('Shown with the question in the portal. Plain text, max 500 characters. Omitting it clears it.'),
                        'options' => $schema->array()->items($schema->string())->nullable()->description('Only "select" reads this, as a list of choices. Leave unset for every other type — "file" and "group_photos" answers are out of scope here.'),
                    ]))->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
                structure: fn (array $body) => ['form' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['form' => self::form($schema)],
            ),

            new ToolDefinition(
                name: 'share_form',
                title: 'Share or unshare a form',
                description: 'Puts a questionnaire in front of the client on the portal, or takes it back. Unsharing hides it; it never deletes an answer already given.',
                method: 'POST',
                path: '/forms/{form}/share',
                schema: fn (JsonSchema $schema) => [
                    'form' => $schema->string()->description('The form instance public id')->required(),
                    'shared' => $schema->boolean()->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
                structure: fn (array $body) => ['form' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['form' => self::form($schema)],
            ),

            new ToolDefinition(
                name: 'close_form',
                title: 'Close or reopen a form',
                description: 'Locks a questionnaire on the portal so the client can no longer edit it, or reopens it. Staff can still correct answers; nothing already answered is deleted.',
                method: 'POST',
                path: '/forms/{form}/close',
                schema: fn (JsonSchema $schema) => [
                    'form' => $schema->string()->description('The form instance public id')->required(),
                    'closed' => $schema->boolean()->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
                structure: fn (array $body) => ['form' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['form' => self::form($schema)],
            ),

            new ToolDefinition(
                name: 'answer_form_field',
                title: 'Record an answer',
                description: 'Records (or corrects) the answer to one question on a form already seeded. Appends the next answer version; every earlier one stays. A "file" field cannot be answered here — there is no way to attach bytes through this tool.',
                method: 'POST',
                path: '/form-fields/{field}/answer',
                schema: fn (JsonSchema $schema) => [
                    'field' => $schema->string()->description('The form field public id')->required(),
                    'value' => $schema->string()->nullable(),
                ],
                silent: false,
                payload: fn (array $arguments) => array_filter(['value' => $arguments['value'] ?? null], fn ($value) => $value !== null),
                structure: fn (array $body) => ['form' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['form' => self::form($schema)],
            ),

            new ToolDefinition(
                name: 'list_shoot_timeline',
                title: 'List a shoot\'s timeline',
                description: 'A shoot\'s day-of schedule: one row per stop, in time order.',
                method: 'GET',
                path: '/shoots/{shoot}/timeline',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'add_timeline_entry',
                title: 'Add a timeline entry',
                description: 'Adds one stop to a shoot\'s day-of schedule.',
                method: 'POST',
                path: '/shoots/{shoot}/timeline',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'time' => $schema->string()->description('HH:MM, 24h')->required(),
                    'title' => $schema->string()->required(),
                    'location' => $schema->string()->nullable(),
                    'note' => $schema->string()->nullable(),
                    'isTransit' => $schema->boolean()->nullable()->description('Marks a travel leg between two real stops rather than a stop itself.'),
                ],
                silent: false,
                payload: fn (array $arguments) => array_filter([
                    'time' => $arguments['time'],
                    'title' => $arguments['title'],
                    'location' => $arguments['location'] ?? null,
                    'note' => $arguments['note'] ?? null,
                    'is_transit' => $arguments['isTransit'] ?? null,
                ], fn ($value) => $value !== null),
            ),

            new ToolDefinition(
                name: 'delete_timeline_entry',
                title: 'Delete a timeline entry',
                description: 'Removes one stop from a shoot\'s day-of schedule.',
                method: 'DELETE',
                path: '/timeline/{entry}',
                schema: fn (JsonSchema $schema) => [
                    'entry' => $schema->string()->description('The timeline entry public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted timeline entry '.$arguments['entry'].'.',
            ),

            new ToolDefinition(
                name: 'list_shot_list',
                title: 'List a shoot\'s shot list',
                description: 'The shots a studio means to get on a shoot, checked off as they happen.',
                method: 'GET',
                path: '/shoots/{shoot}/shot-list',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'add_shot_list_item',
                title: 'Add a shot list item',
                description: 'Adds one shot to a shoot\'s shot list.',
                method: 'POST',
                path: '/shoots/{shoot}/shot-list',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'label' => $schema->string()->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'check_shot_list_item',
                title: 'Check off a shot',
                description: 'Marks a shot list item as done. Checking an already-checked item leaves it checked — safe to retry.',
                method: 'POST',
                path: '/shot-list/{item}/check',
                schema: fn (JsonSchema $schema) => [
                    'item' => $schema->string()->description('The shot list item public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_shot_list_item',
                title: 'Delete a shot list item',
                description: 'Removes one shot from a shoot\'s shot list.',
                method: 'DELETE',
                path: '/shot-list/{item}',
                schema: fn (JsonSchema $schema) => [
                    'item' => $schema->string()->description('The shot list item public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted shot list item '.$arguments['item'].'.',
            ),

            new ToolDefinition(
                name: 'set_portal_visibility',
                title: 'Set a project\'s portal visibility',
                description: 'Shows or hides a project on the portal. Takes the value explicitly rather than flipping it, so a retry always lands on the same state.',
                method: 'POST',
                path: '/cases/{case}/portal-visibility',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'visible' => $schema->boolean()->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),
        ];
    }

    /**
     * A file or group_photos field set up in the builder stores an options
     * object, not a string list; list_forms hides it the same way.
     */
    private static function withSelectOptionsOnly(array $template): array
    {
        $template['fields'] = array_map(
            fn (array $field) => $field['type'] === 'select' ? $field : [...$field, 'options' => null],
            $template['fields'],
        );

        return $template;
    }

    private static function templateField(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string()->nullable()->description('The field public id. Send it to keep the field; a field without one is created new, a field left out is deleted.'),
            'label' => $schema->string()->max(255)->required(),
            'type' => $schema->string()->enum(self::FORM_FIELD_TYPES)->required(),
            'required' => $schema->boolean()->required(),
            'help' => $schema->string()->max(500)->nullable()->description('Shown with the question in the portal. Plain text. Omitting it clears it.'),
            'options' => $schema->array()->items($schema->string())->nullable()->description('Only "select" reads this, as a list of choices. Leave unset for every other type.'),
        ]);
    }

    private static function formTemplate(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'name' => $schema->string(),
            'description' => $schema->string()->nullable(),
            'projectType' => $schema->string()->nullable(),
            'projectTypeLabel' => $schema->string()->nullable(),
            'isActive' => $schema->boolean(),
            'fieldCount' => $schema->integer(),
            'updatedAt' => $schema->string(),
            'fields' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'label' => $schema->string(),
                'type' => $schema->string()->enum(self::FORM_FIELD_TYPES),
                'typeLabel' => $schema->string(),
                'required' => $schema->boolean(),
                'help' => $schema->string()->nullable(),
                'options' => $schema->array()->items($schema->string())->nullable(),
            ])),
        ]);
    }

    private static function form(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'name' => $schema->string(),
            'sourceTemplateName' => $schema->string()->nullable(),
            'updatedAt' => $schema->string(),
            'sharedAt' => $schema->string()->nullable(),
            'closedAt' => $schema->string()->nullable(),
            'fields' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'label' => $schema->string(),
                'type' => $schema->string()->enum(self::FORM_FIELD_TYPES),
                'typeLabel' => $schema->string(),
                'required' => $schema->boolean(),
                'help' => $schema->string()->nullable(),
                'options' => $schema->array()->items($schema->string())->nullable(),
                'currentAnswer' => $schema->string()->nullable(),
                'answeredAt' => $schema->string()->nullable(),
                'groupPhotos' => $schema->object([
                    'suggestions' => $schema->array()->items($schema->string()),
                    'minutesPerGroup' => $schema->integer(),
                ])->nullable(),
                'fileFormats' => $schema->array()->items($schema->string())->nullable(),
                'fileMaxSize' => $schema->integer()->nullable(),
                'fileMultiple' => $schema->boolean(),
                'files' => $schema->array()->items($schema->object([
                    'id' => $schema->string(),
                    'name' => $schema->string(),
                    'size' => $schema->integer(),
                    'uploadedAt' => $schema->string(),
                ])),
            ])),
        ]);
    }
}
