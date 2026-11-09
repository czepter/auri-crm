<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * N-03: the write tools, each standing for one of I-03's (and C-25's)
 * idempotent api-surface routes.
 *
 * OQ-8 (#94) made "explicit confirmation is the floor" a condition of these
 * shipping at all, and the floor is the client's: the spec puts the
 * confirmation prompt there ("Clients SHOULD prompt for user confirmation on
 * sensitive operations", server/tools § Security Considerations), and
 * `annotations` below is what it gates on — readOnlyHint false throughout,
 * destructiveHint true wherever a call overwrites existing state.
 */
final class WriteTools
{
    private const LEAD_SOURCES = ['referral', 'website', 'instagram', 'wedding_fair', 'other'];

    private const CAPTURE_CHANNELS = ['phone', 'email', 'in_person', 'other'];

    private const LEAD_OUTCOMES = ['lost', 'declined', 'closed'];

    private const FIELD_TYPES = ['text', 'textarea', 'number', 'select', 'tags', 'date', 'boolean'];

    private const DISCOUNT_TYPES = ['percentage', 'amount'];

    private const PRICING_MODES = ['classic', 'choice'];

    private const PORTAL_PRICE_MODES = ['exact', 'from', 'on_request'];

    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'capture_lead',
                title: 'Capture lead',
                description: 'Create a new lead and project from an inquiry, mirroring the studio capture form.',
                method: 'POST',
                path: '/leads',
                schema: fn (JsonSchema $schema) => self::leadFields($schema),
                silent: false,
            ),

            new ToolDefinition(
                name: 'capture_leads_bulk',
                title: 'Capture leads in bulk',
                description: 'Create many leads in one call, for moving existing client history onto this app. All or nothing: one unusable row refuses the whole batch, naming its position, so a retry cannot double-write the rows that were fine.',
                method: 'POST',
                path: '/leads/bulk',
                schema: fn (JsonSchema $schema) => [
                    'leads' => $schema->array()->items($schema->object(self::leadFields($schema)))->min(1)->max(500)->required(),
                    // The one tool where this defaults to true: a batch is
                    // history, and the people in it heard about their
                    // enquiry when they made it.
                    'silent' => $schema->boolean()->default(true)->description('Default true, because a batch is history: no mail, no in-app notice, no follow-up tasks. Set false only for leads that really are arriving now.'),
                ],
                silent: true,
            ),

            new ToolDefinition(
                name: 'advance_lead_stage',
                title: 'Advance lead stage',
                description: 'Move a lead to a new stage in the sales funnel (Lead.stage), the same transition the board\'s drag makes.',
                method: 'POST',
                path: '/cases/{case}/lead/stage',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'stage' => $schema->string()->description('The stage key in the sales funnel — see list_leads or pipeline_snapshot')->required(),
                    'entered_at' => $schema->string()->nullable()->description('Since when it stands there, for a move entered after the fact. ISO 8601 or YYYY-MM-DD, never in the future.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_lead',
                title: 'Update lead',
                description: 'Replace a lead\'s inquiry text, the one written at capture, and optionally correct how it arrived. get_case shows the current values.',
                method: 'PATCH',
                path: '/cases/{case}/lead',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'inquiry_text' => $schema->string()->max(10000)->nullable()->required(),
                    'captured_via' => $schema->string()->enum(self::CAPTURE_CHANNELS),
                    'captured_via_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was. Only kept beside captured_via: other.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
            ),

            new ToolDefinition(
                name: 'close_lead',
                title: 'Close lead',
                description: 'End the pursuit. `lost` is the client\'s decision, `declined` the studio\'s, `closed` neither — the three are counted apart, so pick the one that happened. The stage is left alone: which stage a lead died in is what the funnel is measured on. Its open shoots are cancelled with it. Reversible with reopen_lead, which takes those absagen back too.',
                method: 'POST',
                path: '/cases/{case}/lead/lost',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'outcome' => $schema->string()->enum(self::LEAD_OUTCOMES)->required(),
                    'reason' => $schema->string()->max(255)->required(),
                    'lost_at' => $schema->string()->nullable()->description('When it ended, for one entered after the fact. Never in the future.'),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'reopen_lead',
                title: 'Reopen lead',
                description: 'Take back a close. A closed lead can make no further move at all, so this is the only way out of one entered by mistake. It returns to the stage it was closed in.',
                method: 'POST',
                path: '/cases/{case}/lead/reopen',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'create_shoot',
                title: 'Create a shoot',
                description: 'Add a shooting day to a project. The first one already exists — capture_lead makes it out of the inquiry date — so this is the second and every one after it: the Feier of a wedding split across two days, an Engagement-Shooting ahead of it, a second portrait session. Leave `date` empty for a day that is agreed but not dated. Leave `kind` empty to take the project type\'s name.',
                method: 'POST',
                path: '/cases/{case}/shoots',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'kind' => $schema->string()->max(255)->nullable(),
                    'label' => $schema->string()->max(255)->nullable()->description('What this day is called on the project screen, where the kind alone is ambiguous'),
                    'date' => $schema->string()->nullable()->description('YYYY-MM-DD'),
                    'call_time' => $schema->string()->nullable()->description('HH:MM'),
                    'end_time' => $schema->string()->nullable()->description('HH:MM, after call_time'),
                    'venue_name' => $schema->string()->max(255)->nullable(),
                    'venue_city' => $schema->string()->max(255)->nullable(),
                    'entered_at' => $schema->string()->nullable()->description('Since when it stands in its first status, for a day entered after the fact. Never in the future.'),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'advance_shoot_status',
                title: 'Advance shoot status',
                description: 'Move one shoot through the production pipeline (Shoot.status) — the transition the project screen makes. A status below the current one is a correction of a mistaken move and restores the moment it first stood there.',
                method: 'POST',
                path: '/shoots/{shoot}/status',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'status' => $schema->string()->description('The stage key in the project pipeline, e.g. \'booked\' or \'delivered\'')->required(),
                    'entered_at' => $schema->string()->nullable()->description('Since when it stands there. Never in the future.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'cancel_shoot',
                title: 'Cancel shoot',
                description: 'Call off one shooting day. The status is left alone — which position it was cancelled in is what the pipeline is measured on. Reversible with uncancel_shoot.',
                method: 'POST',
                path: '/shoots/{shoot}/cancel',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                    'reason' => $schema->string()->max(255)->required(),
                    'cancelled_at' => $schema->string()->nullable()->description('When it was called off. Never in the future.'),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'uncancel_shoot',
                title: 'Uncancel shoot',
                description: 'Take back an absage. A cancelled shoot can make no further move at all, so this is the only way out of one entered by mistake.',
                method: 'POST',
                path: '/shoots/{shoot}/uncancel',
                schema: fn (JsonSchema $schema) => [
                    'shoot' => $schema->string()->description('The shoot public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'add_internal_note',
                title: 'Add internal note',
                description: 'Add a staff-only internal note to a project.',
                method: 'POST',
                path: '/cases/{case}/notes',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'body' => $schema->string()->max(5000)->required(),
                    'occurred_at' => $schema->string()->nullable()->description('When what the note is about happened, for one written up later. ISO 8601 or YYYY-MM-DD, never in the future.'),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_internal_note',
                title: 'Update internal note',
                description: 'Replace the body of an internal note, by the id add_internal_note returned. occurred_at is kept unless given.',
                method: 'PATCH',
                path: '/notes/{note}',
                schema: fn (JsonSchema $schema) => [
                    'note' => $schema->string()->description('The note public id')->required(),
                    'body' => $schema->string()->max(5000)->required(),
                    'occurred_at' => $schema->string()->nullable()->description('When what the note is about happened. ISO 8601 or YYYY-MM-DD, never in the future.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_internal_note',
                title: 'Delete internal note',
                description: 'Delete an internal note, by the id add_internal_note returned. There is no undo.',
                method: 'DELETE',
                path: '/notes/{note}',
                schema: fn (JsonSchema $schema) => [
                    'note' => $schema->string()->description('The note public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted note '.$arguments['note'].'.',
            ),

            new ToolDefinition(
                name: 'add_message',
                title: 'Add message',
                description: 'Write a line of the conversation between client and studio into a project after the fact: a mail, call or chat that happened outside the app. It shows on the project\'s and the inquiry\'s Nachrichten tab. Nothing is sent to anyone. Use this, not add_internal_note, for what client and studio said to each other.',
                method: 'POST',
                path: '/cases/{case}/messages',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'from' => $schema->string()->enum(['client', 'studio'])->description('client: the project\'s main contact, or `contact`. studio: the signed-in staff member.')->required(),
                    'body' => $schema->string()->max(5000)->required(),
                    'sent_at' => $schema->string()->nullable()->description('When it was said. ISO 8601, never in the future. Defaults to now.'),
                    'contact' => $schema->string()->nullable()->description('Contact public id, for from: client when it was not the main contact'),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'add_case_contact',
                title: 'Add case contact',
                description: 'Attach a person to an existing case: the partner of a couple, a payer, a planner. Pass contact_id for someone on file (see search_contacts), or name with email and phone; an email already on file reuses that contact. The primary contact is set at capture and cannot be added here.',
                method: 'POST',
                path: '/cases/{case}/contacts',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'contact_id' => $schema->string()->min(24)->max(24)->nullable()->description('CUID2 of an existing contact'),
                    'name' => $schema->string()->max(255)->nullable(),
                    'email' => $schema->string()->format('email')->max(255)->nullable(),
                    'phone' => $schema->string()->max(64)->nullable(),
                    'role' => $schema->string()->max(60)->nullable()->description('Free text, e.g. Trauredner. Defaults to partner.'),
                ],
                // Attaching the same person in the same role twice leaves one
                // link — but without contact_id or an email already on file,
                // a repeat call makes a new Contact each time.
                silent: false,
            ),

            new ToolDefinition(
                name: 'set_case_fields',
                title: 'Set case fields',
                description: 'C-25: write the values of a case\'s own fields — "120 Gäste, Scheune" out of a mail. Call `list_project_types` first: the keys of `fields` are that type\'s field keys, and each value takes the shape its definition names (a number, a string, a YYYY-MM-DD date, a boolean, or a list of strings for a tags field). Keys are merged, never replaced, so writing one field leaves the rest alone. A value outside a closed option list is refused; on a field that allows new options it is added to the list.',
                method: 'PATCH',
                path: '/cases/{case}/fields',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'fields' => $schema->object()->description('Keyed by field key — see list_project_types.')->required(),
                ],
                // Overwrites whatever stood under those keys, so destructive
                // even though re-sending the same values changes nothing.
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'add_project_field',
                title: 'Add a project field',
                description: 'C-25: define a new field on a project type, so every project of that type carries it. The key is derived from the label and fixed; `options` and `allow_new_options` apply to select and tags only, `unit` to number only. Changes what every project of this type is asked for, so only do it when the studio asked for it.',
                method: 'POST',
                path: '/project-types/{project_type}/fields',
                schema: fn (JsonSchema $schema) => [
                    'project_type' => $schema->string()->description('The project type public id — see list_project_types')->required(),
                    'label' => $schema->string()->max(120)->required(),
                    'type' => $schema->string()->enum(self::FIELD_TYPES)->required(),
                    'options' => $schema->array()->items($schema->string()->max(255))->description('select and tags only'),
                    'unit' => $schema->string()->max(40)->nullable()->description('number only — "Personen" makes 120 read as "120 Personen"'),
                    'required' => $schema->boolean(),
                    'allow_new_options' => $schema->boolean()->description('select and tags only: may a project add an option?'),
                ],
                silent: false,
            ),

            /*
             * No delete_lead beside this: a lead is born with its project and
             * means nothing without it, so the project is the unit that goes.
             */
            new ToolDefinition(
                name: 'delete_case',
                title: 'Delete a case',
                description: 'Deletes a case together with its lead, its shoots, its internal notes and its links to contacts. The contacts themselves stay. Refused while the case is booked or a quote or a gallery hangs off the case — past that point the case is a record of an agreement, and removing it is a decision for a screen rather than a tool. There is no undo.',
                method: 'DELETE',
                path: '/cases/{case}',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted case '.$arguments['case'].'.',
            ),

            new ToolDefinition(
                name: 'create_quote',
                title: 'Create a quote',
                description: 'Start a draft quote (Angebot) on a project. Amounts are in cents: 1.790 € is 179000. Nothing goes to the client; send_quote does that. For a booking already agreed outside the app: create_quote, send_quote with sent_at and silent, then accept_quote.',
                method: 'POST',
                path: '/cases/{case}/quotes',
                schema: fn (JsonSchema $schema) => [
                    'case' => $schema->string()->description('The case public id')->required(),
                    'package' => $schema->string()->nullable()->description('Package public id from list_packages. Its lines, payment plan and hourly rate are copied in; fields given here override them.'),
                    ...self::quoteFields($schema),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_quote',
                title: 'Update a quote',
                description: 'Change a draft quote. Only the fields given change; `items`, when given, replaces every line. A sent quote cannot change: revise_quote makes an editable copy. get_case shows the current quotes.',
                method: 'PATCH',
                path: '/quotes/{quote}',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                    ...self::quoteFields($schema),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'send_quote',
                title: 'Send a quote',
                description: 'Freeze a draft quote, then mail it to the project\'s primary contact. With silent no mail goes out and the quote is marked sent through the studio\'s own channel instead: use that, with sent_at, for a quote the client already received outside the app.',
                method: 'POST',
                path: '/quotes/{quote}/send',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                    'sent_at' => $schema->string()->nullable()->description('When it went out. Never in the future. Defaults to now.'),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => true],
                silent: false,
            ),

            new ToolDefinition(
                name: 'revise_quote',
                title: 'Revise a quote',
                description: 'Replace a sent quote with an editable draft copy of it. The sent one is marked superseded and can no longer be accepted.',
                method: 'POST',
                path: '/quotes/{quote}/revise',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The public id of the sent quote')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'accept_quote',
                title: 'Record quote acceptance',
                description: 'Record the client\'s yes to a sent quote, given by mail, phone or in person. The quote must still be valid at accepted_at. This does not book the project or move the lead; advance_lead_stage does that.',
                method: 'POST',
                path: '/quotes/{quote}/accept',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                    'contact' => $schema->string()->nullable()->description('Public id of the contact who said yes. Defaults to the project\'s primary contact.'),
                    'accepted_at' => $schema->string()->nullable()->description('When they said yes. Never in the future. Defaults to now.'),
                    'hours' => $schema->integer()->min(0)->nullable()->description('Hourly-rate quotes only: the hours agreed'),
                    'items' => $schema->array()->items($schema->string())->description('Hourly-rate quotes only: public ids of the selectable add-on lines the client switched on'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'create_package',
                title: 'Create a package',
                description: 'Add a package to the catalogue new quotes can start from. Amounts are in cents: 1.790 € is 179000.',
                method: 'POST',
                path: '/packages',
                schema: fn (JsonSchema $schema) => [
                    ...self::packageFields($schema),
                    'name' => $schema->string()->max(255)->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_package',
                title: 'Update a package',
                description: 'Change a package. Only the fields given change; `items`, when given, replaces every line. Quotes already made from it keep what they copied. list_packages shows the current catalogue.',
                method: 'PATCH',
                path: '/packages/{package}',
                schema: fn (JsonSchema $schema) => [
                    'package' => $schema->string()->description('The package public id')->required(),
                    ...self::packageFields($schema),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_package',
                title: 'Delete a package',
                description: 'Remove a package from the catalogue. Quotes made from it are untouched — they carry their own copy of the lines. Any service suggestion offering it is deleted, and any lead that named it keeps its record with the package reference cleared. There is no undo; update_package with is_active false only hides it instead.',
                method: 'DELETE',
                path: '/packages/{package}',
                schema: fn (JsonSchema $schema) => [
                    'package' => $schema->string()->description('The package public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted package '.$arguments['package'].'.',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function leadFields(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->string()->min(24)->max(24)->nullable()->description('CUID2 of an existing contact, if reusing one'),
            'contact_name' => $schema->string()->max(255)->nullable(),
            'contact_email' => $schema->string()->format('email')->max(255)->nullable(),
            'contact_phone' => $schema->string()->max(64)->nullable(),
            'people' => $schema->array()->items($schema->object([
                'name' => $schema->string()->max(255)->required(),
                'email' => $schema->string()->format('email')->max(255)->nullable(),
                'phone' => $schema->string()->max(64)->nullable(),
            ]))->description('Everyone on the inquiry besides the contact — the partner of a wedding couple. Each becomes a contact of their own on the project.'),
            'project_name' => $schema->string()->max(255)->required(),
            'type' => $schema->string()->description('The project type key — see list_project_types. A studio defines its own, so this is not a fixed list.')->required(),
            'source' => $schema->string()->enum(self::LEAD_SOURCES)->nullable(),
            'source_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was, e.g. the platform the inquiry came from. Only kept beside source: other.'),
            'captured_via' => $schema->string()->enum(self::CAPTURE_CHANNELS)->nullable(),
            'captured_via_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was. Only kept beside captured_via: other.'),
            'date' => $schema->string()->nullable()->description('YYYY-MM-DD'),
            'inquiry_text' => $schema->string()->max(10000)->nullable(),
            'captured_at' => $schema->string()->nullable()->description('When the inquiry reached the studio, for one entered after the fact. ISO 8601 or YYYY-MM-DD, never in the future. Defaults to now.'),
            'type_other' => $schema->string()->max(120)->nullable()->description('What \'other\' actually was. Only kept beside type: other.'),
            'referred_by_contact_id' => $schema->string()->min(24)->max(24)->nullable()->description('CUID2 of the contact who referred this inquiry, for source: referral'),
            'call_time' => $schema->string()->nullable()->description('HH:MM, first shoot'),
            'end_time' => $schema->string()->nullable()->description('HH:MM, after call_time'),
            'venue_name' => $schema->string()->max(255)->nullable(),
            'venue_city' => $schema->string()->max(255)->nullable(),
            'fields' => $schema->object()->description('The project type\'s own fields — see list_project_types.'),
        ];
    }

    /** @return array<string, mixed> */
    private static function quoteFields(JsonSchema $schema): array
    {
        return [
            'valid_until' => $schema->string()->nullable()->description('YYYY-MM-DD. Defaults to the studio setting at creation.'),
            'pricing_mode' => $schema->string()->enum(self::PRICING_MODES)->description('Omit and it follows hourly_rate: choice with a rate, classic without.'),
            'recipient_contact' => $schema->string()->nullable()->description('Contact public id the quote is addressed to. Defaults to the project\'s payer, else its primary contact.'),
            'recipient_street' => $schema->string()->max(255)->nullable(),
            'recipient_postcode' => $schema->string()->max(32)->nullable(),
            'recipient_city' => $schema->string()->max(255)->nullable(),
            'intro' => $schema->string()->max(5000)->nullable(),
            'outro' => $schema->string()->max(5000)->nullable(),
            'discount_type' => $schema->string()->enum(self::DISCOUNT_TYPES)->nullable(),
            'discount_value' => $schema->integer()->min(1)->nullable()->description('Percent, or cents for amount'),
            'items' => $schema->array()->items($schema->object([
                'id' => $schema->string()->nullable()->description('The existing line id, to keep its picture'),
                'title' => $schema->string()->max(255)->required(),
                'description' => $schema->string()->max(2000)->nullable(),
                'quantity' => $schema->integer()->min(1)->required(),
                'unit_price' => $schema->integer()->min(0)->nullable()->description('Cents. Null makes it an included service.'),
                'discount_type' => $schema->string()->enum(self::DISCOUNT_TYPES)->nullable(),
                'discount_value' => $schema->integer()->min(1)->nullable(),
                'selectable' => $schema->boolean()->description('Choice quotes: the client can switch this line on or off in the portal. Classic quotes: an optional line, priced but outside the total'),
                'selected' => $schema->boolean()->description('Only meaningful with selectable: on by default'),
                'outcome' => $schema->string()->max(255)->nullable(),
            ]))->description('Every line of the quote, in order. Replaces all lines when given.'),
            'hourly_rate' => $schema->integer()->min(1)->nullable()->description('Cents per hour. Set it and the client picks the hours in the portal; null for a fixed-price quote.'),
            'min_hours' => $schema->integer()->min(1)->max(2000)->nullable(),
            'max_hours' => $schema->integer()->min(1)->max(2000)->nullable(),
            'images_per_hour' => $schema->integer()->min(1)->max(2000)->nullable(),
            'hours' => $schema->integer()->min(1)->max(2000)->nullable()->description('The hours preselected'),
            'time_title' => $schema->string()->max(255)->nullable(),
            'time_description' => $schema->string()->max(255)->nullable(),
            'time_sort_order' => $schema->integer()->min(0)->max(2000)->nullable()->description('Where the time line stands between the items, 0 = before the first'),
            'stages' => $schema->array()->items($schema->object([
                'percent' => $schema->integer()->min(0)->max(100)->required(),
                'label' => $schema->string()->max(255)->description('e.g. Anzahlung')->required(),
                'minimum' => $schema->integer()->min(0)->nullable()->description('Cents'),
            ]))->description('The payment plan, in order. Replaces all stages when given.'),
        ];
    }

    /** @return array<string, mixed> */
    private static function packageFields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255),
            'description' => $schema->string()->max(2000)->nullable(),
            'is_active' => $schema->boolean()->description('False hides it from what a new quote can start from, leaving existing quotes alone'),
            'portal_offered' => $schema->boolean()->description('Offered to the client as a personal recommendation in the portal'),
            'portal_price_mode' => $schema->string()->enum(self::PORTAL_PRICE_MODES)->description('exact: fixed price. from: a starting price. on_request: no price shown.'),
            'portal_requires_date' => $schema->boolean(),
            'project_type_key' => $schema->string()->nullable()->description('The project type key — see list_project_types. Required once portal_offered is set.'),
            'gallery_preset_id' => $schema->string()->nullable()->description('Gallery preset public id a project booked with this package starts its gallery from. Null clears it.'),
            'items' => $schema->array()->items($schema->object([
                'id' => $schema->string()->nullable()->description('The existing line id, to keep its picture'),
                'title' => $schema->string()->max(255)->required(),
                'description' => $schema->string()->max(2000)->nullable(),
                'quantity' => $schema->integer()->min(1)->required(),
                'unit_price' => $schema->integer()->min(0)->nullable()->description('Cents. Null makes it an included service.'),
                'selectable' => $schema->boolean()->description('Choice quotes: the client can switch this line on or off in the portal. Classic quotes: an optional line, priced but outside the total'),
                'selected' => $schema->boolean()->description('Only meaningful with selectable: on by default'),
                'outcome' => $schema->string()->max(255)->nullable(),
            ]))->description('Every line of the package, in order. Replaces all lines when given.'),
        ];
    }
}
