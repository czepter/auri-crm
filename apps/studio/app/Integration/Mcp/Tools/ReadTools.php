<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * N-02: the reads that give an assistant the questions I-02's index routes
 * cannot answer on their own — search, get-one, a date range, the funnel
 * summary and the leads behind it.
 *
 * The output schemas below mirror the matching App\Integration\Data class,
 * which is what the route actually returns: public_id only, never a row's
 * internal integer id, and every amount in cents.
 */
final class ReadTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'search_contacts',
                title: 'Search contacts',
                description: 'Lists contacts on the CRM\'s api surface, or searches them by name or email with `query` — the search runs over the whole contact list on the server, not the page this call returns. Every id is a public_id, never a row\'s internal integer id.',
                method: 'GET',
                path: '/contacts',
                schema: fn (JsonSchema $schema) => [
                    'query' => $schema->string()->description('Case-insensitive substring match against name or email.'),
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                    'since' => $schema->string()->description('ISO 8601 datetime — only contacts updated at or after this instant.'),
                    'limit' => $schema->integer()->min(1)->max(100)->description('Page size, up to the api surface\'s max of 100. Defaults to 25.'),
                ],
                annotations: ToolDefinition::READ,
                payload: fn (array $arguments) => [
                    'query' => $arguments['query'] ?? null,
                    'cursor' => $arguments['cursor'] ?? null,
                    'since' => $arguments['since'] ?? null,
                    'per_page' => $arguments['limit'] ?? null,
                ],
                structure: fn (array $body) => [
                    'contacts' => $body['data'],
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
                outputSchema: fn (JsonSchema $schema) => [
                    'contacts' => $schema->array()->items(self::contact($schema)),
                    'nextCursor' => $schema->string()->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'get_project',
                title: 'Get a project',
                description: 'Gets a single project by its public_id, with its lead (including its inquiry text), its contacts and their roles, its shoots and its quotes — enough to verify a capture or an import without a second call. Internal notes are not part of it; they need their own scope. The project index returns the lean shape instead.',
                method: 'GET',
                path: '/projects/{publicId}',
                schema: fn (JsonSchema $schema) => [
                    'publicId' => $schema->string()->description('The project\'s public_id, never its internal integer id.')->required(),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['project' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['project' => self::projectDetail($schema)],
            ),

            new ToolDefinition(
                name: 'get_project_money',
                title: 'Get a project\'s payments',
                description: '#402: what get_project leaves out — the payment plan (null if none exists yet). Check this before offering create_payment_plan or record_payment: a plan already exists once this returns one.',
                method: 'GET',
                path: '/projects/{publicId}/money',
                schema: fn (JsonSchema $schema) => [
                    'publicId' => $schema->string()->description('The project\'s public_id, never its internal integer id.')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => $body['data'],
                outputSchema: fn (JsonSchema $schema) => [
                    'paymentPlan' => self::paymentPlan($schema)->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'list_internal_notes',
                title: 'List internal notes',
                description: 'Lists a project\'s staff-only internal notes, oldest first by when they happened. The ids are what update_internal_note and delete_internal_note take.',
                method: 'GET',
                path: '/projects/{publicId}/notes',
                schema: fn (JsonSchema $schema) => [
                    'publicId' => $schema->string()->description('The project\'s public_id, never its internal integer id.')->required(),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['notes' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => [
                    'notes' => $schema->array()->items($schema->object([
                        'id' => $schema->string(),
                        'body' => $schema->string(),
                        'author' => $schema->string()->nullable(),
                        'writtenAt' => $schema->string(),
                        'occurredAt' => $schema->string(),
                    ])),
                ],
            ),

            new ToolDefinition(
                name: 'list_project_types',
                title: 'List project types',
                description: 'C-25: every project type the studio offers with the fields a project of that type carries — the vocabulary `set_project_fields` writes against. `key` is what a value is stored under, `type` says what shape it takes (text, textarea, number, select, tags, date, boolean), `options` is the list a select or tags field offers, and `allowNewOptions` says whether writing an option that is not on that list adds it. Inactive fields are left out: a caller may only write what the studio still asks for.',
                method: 'GET',
                path: '/project-types',
                schema: fn (JsonSchema $schema) => [],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['types' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => [
                    'types' => $schema->array()->items($schema->object([
                        'id' => $schema->string(),
                        'key' => $schema->string(),
                        'name' => $schema->string(),
                        'isActive' => $schema->boolean(),
                        'isDefault' => $schema->boolean(),
                        'fields' => $schema->array()->items($schema->object([
                            'id' => $schema->string(),
                            'key' => $schema->string(),
                            'label' => $schema->string(),
                            'type' => $schema->string(),
                            'options' => $schema->array()->items($schema->string()),
                            'unit' => $schema->string()->nullable(),
                            'required' => $schema->boolean(),
                            'allowNewOptions' => $schema->boolean(),
                        ])),
                    ])),
                ],
            ),

            new ToolDefinition(
                name: 'list_packages',
                title: 'List packages',
                description: 'The studio\'s packages with their lines, amounts in cents. The id is what create_quote\'s `package` and update_package take. `isActive` false means the package is hidden from what a new quote can start from.',
                method: 'GET',
                path: '/packages',
                schema: fn (JsonSchema $schema) => [],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['packages' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => [
                    'packages' => $schema->array()->items($schema->object([
                        'id' => $schema->string(),
                        'name' => $schema->string(),
                        'description' => $schema->string()->nullable(),
                        'isActive' => $schema->boolean(),
                        'portalOffered' => $schema->boolean(),
                        'portalPriceMode' => $schema->string(),
                        'price' => $schema->integer()->nullable(),
                        'items' => $schema->array()->items($schema->object([
                            'id' => $schema->string(),
                            'title' => $schema->string(),
                            'description' => $schema->string()->nullable(),
                            'quantity' => $schema->integer(),
                            'unitPrice' => $schema->integer()->nullable(),
                            'selectable' => $schema->boolean(),
                            'selected' => $schema->boolean(),
                            'outcome' => $schema->string()->nullable(),
                        ])),
                    ])),
                ],
            ),

            new ToolDefinition(
                name: 'shoots_in_date_range',
                title: 'Shoots in a date range',
                description: 'Lists shoots whose date falls in [from, to], either bound optional — N-02\'s addition of `from`/`to` filters to I-02\'s GET /api/shoots, on top of the plain `since` staleness filter it already had. The question this answers: which shoots are scheduled, and by extension unstaffed, in a given window.',
                method: 'GET',
                path: '/shoots',
                schema: fn (JsonSchema $schema) => [
                    'from' => $schema->string()->description('Inclusive start date, YYYY-MM-DD. Left out, every shoot up to `to` matches.'),
                    'to' => $schema->string()->description('Inclusive end date, YYYY-MM-DD. Left out, every shoot from `from` onward matches.'),
                    'since' => $schema->string()->description('ISO 8601 datetime — only shoots updated at or after this instant.'),
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                    'include_cancelled' => $schema->boolean()->description('Keep cancelled shoots in the result. Off by default: an absage frees the date.'),
                ],
                annotations: ToolDefinition::READ,
                payload: fn (array $arguments) => [
                    'from' => $arguments['from'] ?? null,
                    'to' => $arguments['to'] ?? null,
                    'since' => $arguments['since'] ?? null,
                    'cursor' => $arguments['cursor'] ?? null,
                    'cancelled' => ($arguments['include_cancelled'] ?? false) ? null : 'false',
                ],
                structure: fn (array $body) => [
                    'shoots' => $body['data'],
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
                outputSchema: fn (JsonSchema $schema) => [
                    'shoots' => $schema->array()->items(self::shoot($schema)),
                    'nextCursor' => $schema->string()->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'list_leads',
                title: 'List leads',
                description: 'The rows behind `pipeline_snapshot`: which leads stand in a stage, which were closed, which came in over a window. `closed` left out returns both open and closed leads; a closed one keeps the stage it was closed in, so `stage` and `closed` narrow independently. `sourceOther` carries the platform behind source \'other\'.',
                method: 'GET',
                path: '/leads',
                schema: fn (JsonSchema $schema) => [
                    'stage' => $schema->string()->description('A lead stage key, e.g. "quote_sent" — see pipeline_snapshot for the studio\'s own stages.'),
                    'closed' => $schema->boolean()->description('true for lost and declined leads only, false for the ones still in play.'),
                    'captured_from' => $schema->string()->description('Inclusive start date the inquiry arrived on, YYYY-MM-DD.'),
                    'captured_to' => $schema->string()->description('Inclusive end date the inquiry arrived on, YYYY-MM-DD.'),
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => [
                    'leads' => $body['data'],
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
                outputSchema: fn (JsonSchema $schema) => [
                    'leads' => $schema->array()->items(self::lead($schema)),
                    'nextCursor' => $schema->string()->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'pipeline_snapshot',
                title: 'Lead pipeline snapshot',
                description: 'The sales funnel as a count per LeadStage, in funnel order — N-02\'s addition of GET /api/pipeline-snapshot, since nothing on the api surface answered "how many leads are at each stage" before this. Closed leads (lost, declined or otherwise closed) are excluded from every count; use list_leads with `closed: true` for those. Inactive stages are left out rather than shown with a 0.',
                method: 'GET',
                path: '/pipeline-snapshot',
                schema: fn (JsonSchema $schema) => [],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['stages' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => [
                    'stages' => $schema->array()->items($schema->object([
                        'stage' => $schema->string(),
                        'label' => $schema->string(),
                        'count' => $schema->integer(),
                    ])),
                ],
            ),
        ];
    }

    private static function contact(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'name' => $schema->string(),
            'email' => $schema->string()->nullable(),
            'phone' => $schema->string()->nullable(),
            'street' => $schema->string()->nullable(),
            'postcode' => $schema->string()->nullable(),
            'city' => $schema->string()->nullable(),
            'updatedAt' => $schema->string(),
        ]);
    }

    private static function shoot(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'projectId' => $schema->string(),
            'kind' => $schema->string(),
            'label' => $schema->string()->nullable(),
            'date' => $schema->string()->nullable(),
            'callTime' => $schema->string()->nullable(),
            'endTime' => $schema->string()->nullable(),
            'venueName' => $schema->string()->nullable(),
            'venueCity' => $schema->string()->nullable(),
            'status' => $schema->string(),
            'isPrimary' => $schema->boolean(),
            'cancelledAt' => $schema->string()->nullable(),
            'cancelledReason' => $schema->string()->nullable(),
            'crew' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'userId' => $schema->string(),
                'name' => $schema->string(),
                'initials' => $schema->string(),
                'role' => $schema->string(),
                'roleLabel' => $schema->string(),
            ])),
            'updatedAt' => $schema->string(),
        ]);
    }

    private static function lead(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'projectId' => $schema->string(),
            'projectName' => $schema->string(),
            'source' => $schema->string(),
            'sourceOther' => $schema->string()->nullable(),
            'stage' => $schema->string(),
            'stageEnteredAt' => $schema->string()->nullable(),
            'isLost' => $schema->boolean(),
            'outcome' => $schema->string()->nullable(),
            'lostReason' => $schema->string()->nullable(),
            'capturedAt' => $schema->string(),
            'lostAt' => $schema->string()->nullable(),
            'updatedAt' => $schema->string(),
        ]);
    }

    /** What GET /projects/{project} alone returns — see ProjectDetailReadData. */
    private static function projectDetail(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'name' => $schema->string(),
            'type' => $schema->string(),
            'typeOther' => $schema->string()->nullable(),
            'portalVisible' => $schema->boolean(),
            // C-25: keyed by ProjectField.key — list_project_types says what each means.
            'fields' => $schema->object(),
            'updatedAt' => $schema->string(),
            'lead' => $schema->object([
                'stage' => $schema->string(),
                'source' => $schema->string(),
                'sourceOther' => $schema->string()->nullable(),
                'inquiryText' => $schema->string()->nullable(),
                'capturedVia' => $schema->string(),
                'capturedViaOther' => $schema->string()->nullable(),
                'capturedAt' => $schema->string(),
                'stageEnteredAt' => $schema->string()->nullable(),
                'outcome' => $schema->string()->nullable(),
                'lostReason' => $schema->string()->nullable(),
                'lostAt' => $schema->string()->nullable(),
            ])->nullable(),
            'job' => $schema->object([
                'id' => $schema->string(),
                'projectId' => $schema->string(),
                'bookedAt' => $schema->string(),
                'bookedVia' => $schema->string(),
                'updatedAt' => $schema->string(),
            ])->nullable(),
            'openTaskCount' => $schema->integer(),
            'contacts' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'name' => $schema->string(),
                'email' => $schema->string()->nullable(),
                'phone' => $schema->string()->nullable(),
                'street' => $schema->string()->nullable(),
                'postcode' => $schema->string()->nullable(),
                'city' => $schema->string()->nullable(),
                'role' => $schema->string(),
                'updatedAt' => $schema->string(),
            ])),
            'shoots' => $schema->array()->items(self::shoot($schema)),
            'quotes' => $schema->array()->items(self::quote($schema)),
        ]);
    }

    /** QuoteData, amounts in cents. */
    private static function quote(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'isAddendum' => $schema->boolean(),
            'status' => $schema->string(),
            'pricingMode' => $schema->string(),
            'number' => $schema->string()->nullable(),
            'validUntil' => $schema->string()->nullable(),
            'sentAt' => $schema->string()->nullable(),
            'total' => $schema->integer(),
            'discountType' => $schema->string()->nullable(),
            'discountValue' => $schema->integer()->nullable(),
            'discountAmount' => $schema->integer(),
            'net' => $schema->integer(),
            'vatMode' => $schema->string(),
            'vatRate' => $schema->number(),
            'vatNote' => $schema->string()->nullable(),
            'vatAmount' => $schema->integer(),
            'intro' => $schema->string()->nullable(),
            'outro' => $schema->string()->nullable(),
            'hourlyRate' => $schema->integer()->nullable(),
            'minHours' => $schema->number()->nullable(),
            'maxHours' => $schema->number()->nullable(),
            'imagesPerHour' => $schema->integer()->nullable(),
            'hours' => $schema->number()->nullable(),
            'paymentStages' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'percent' => $schema->number(),
                'label' => $schema->string(),
                'minimum' => $schema->integer(),
            ])),
            'recipientContact' => $schema->string()->nullable(),
            'recipientStreet' => $schema->string()->nullable(),
            'recipientPostcode' => $schema->string()->nullable(),
            'recipientCity' => $schema->string()->nullable(),
            'portalUrl' => $schema->string()->nullable(),
            'items' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'title' => $schema->string(),
                'description' => $schema->string()->nullable(),
                'quantity' => $schema->integer(),
                'unitPrice' => $schema->integer()->nullable(),
                'netTotal' => $schema->integer(),
                'selectable' => $schema->boolean(),
                'selected' => $schema->boolean(),
                'outcome' => $schema->string()->nullable(),
            ])),
        ]);
    }

    /** PaymentPlanData — see create_payment_plan. Amounts in cents. */
    private static function paymentPlan(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'total' => $schema->integer(),
            'openTotal' => $schema->integer(),
            'isEditable' => $schema->boolean(),
            'clientRemindersEnabled' => $schema->boolean(),
            'installments' => $schema->array()->items($schema->object([
                'id' => $schema->string(),
                'amount' => $schema->integer(),
                'dueOn' => $schema->string(),
                'status' => $schema->string(),
                'label' => $schema->string(),
                'settledVia' => $schema->string()->nullable(),
                'paidOn' => $schema->string()->nullable(),
                'paymentReference' => $schema->string()->nullable(),
                'isLate' => $schema->boolean(),
                'invoiceNumber' => $schema->string()->nullable(),
                'invoicedOn' => $schema->string()->nullable(),
                'hasInvoiceDocument' => $schema->boolean(),
            ])),
        ]);
    }
}
