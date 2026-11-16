<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The invoice as a document of its own, its drafts, and Gutschriften —
 * `scope:manage_contracts` on the api surface. issue_invoice (MoneyTools)
 * bills one installment; the tools here write positions.
 */
final class InvoiceTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'list_invoices',
                title: 'List invoices',
                description: 'Issued invoices with status, amounts, paid and credited sums and their Gutschriften, oldest id first. Drafts are not listed here: list_invoice_drafts.',
                method: 'GET',
                path: '/invoices',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['invoices' => $body['data'], 'nextCursor' => $body['meta']['next_cursor']],
            ),

            new ToolDefinition(
                name: 'get_invoice',
                title: 'Get an invoice',
                description: 'One invoice with its positions, net, VAT, gross and what happened to it.',
                method: 'GET',
                path: '/invoices/{invoice}',
                schema: fn (JsonSchema $schema) => [
                    'invoice' => $schema->string()->description('The invoice public id')->required(),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['invoice' => $body['data']],
            ),

            new ToolDefinition(
                name: 'issue_invoice_from_items',
                title: 'Issue an invoice from positions',
                description: 'Draw an invoice number and issue an invoice written from positions rather than from one installment (issue_invoice). Name a contact or a case; with a case alone the primary contact is the recipient. A position naming an `installment_id` bills that rate at its own gross, every other position is charged on top and needs due_on. Amounts are in cents. Immutable once issued. Refused while the studio has neither Steuernummer nor USt-IdNr. on file, and for an issued_on before the latest invoice.',
                method: 'POST',
                path: '/invoices',
                schema: fn (JsonSchema $schema) => self::invoiceFields($schema, issue: true),
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                structure: fn (array $body) => ['invoice' => $body['data']],
            ),

            new ToolDefinition(
                name: 'list_invoice_drafts',
                title: 'List invoice drafts',
                description: 'Invoices saved without a number. The id is what get_invoice_draft, update_invoice_draft, delete_invoice_draft and issue_invoice_draft take.',
                method: 'GET',
                path: '/invoice-drafts',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['drafts' => $body['data'], 'nextCursor' => $body['meta']['next_cursor']],
            ),

            new ToolDefinition(
                name: 'get_invoice_draft',
                title: 'Get an invoice draft',
                description: 'One invoice draft with the form as it was saved.',
                method: 'GET',
                path: '/invoice-drafts/{draft}',
                schema: fn (JsonSchema $schema) => [
                    'draft' => $schema->string()->description('The invoice draft public id')->required(),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['draft' => $body['data']],
            ),

            new ToolDefinition(
                name: 'create_invoice_draft',
                title: 'Create an invoice draft',
                description: 'Save invoice positions without drawing a number. Every field is optional: a half-filled form is a valid draft. issue_invoice_draft issues it.',
                method: 'POST',
                path: '/invoice-drafts',
                schema: fn (JsonSchema $schema) => self::invoiceFields($schema, issue: false),
                silent: false,
                structure: fn (array $body) => ['draft' => $body['data']],
            ),

            new ToolDefinition(
                name: 'update_invoice_draft',
                title: 'Update an invoice draft',
                description: 'Replace an invoice draft with the form given. Fields left out are cleared, not kept.',
                method: 'PUT',
                path: '/invoice-drafts/{draft}',
                schema: fn (JsonSchema $schema) => [
                    'draft' => $schema->string()->description('The invoice draft public id')->required(),
                    ...self::invoiceFields($schema, issue: false),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                structure: fn (array $body) => ['draft' => $body['data']],
            ),

            new ToolDefinition(
                name: 'delete_invoice_draft',
                title: 'Delete an invoice draft',
                description: 'Delete an invoice draft. There is no undo.',
                method: 'DELETE',
                path: '/invoice-drafts/{draft}',
                schema: fn (JsonSchema $schema) => [
                    'draft' => $schema->string()->description('The invoice draft public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted invoice draft '.$arguments['draft'].'.',
            ),

            new ToolDefinition(
                name: 'issue_invoice_draft',
                title: 'Issue an invoice draft',
                description: 'Issue a saved draft as it stands, with the same rules as issue_invoice_from_items. The draft is deleted once the invoice exists.',
                method: 'POST',
                path: '/invoice-drafts/{draft}/issue',
                schema: fn (JsonSchema $schema) => [
                    'draft' => $schema->string()->description('The invoice draft public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                structure: fn (array $body) => ['invoice' => $body['data']],
            ),

            new ToolDefinition(
                name: 'list_credit_notes',
                title: 'List credit notes',
                description: 'Issued Gutschriften with the invoice each one credits, oldest id first.',
                method: 'GET',
                path: '/credit-notes',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['creditNotes' => $body['data'], 'nextCursor' => $body['meta']['next_cursor']],
            ),

            new ToolDefinition(
                name: 'issue_credit_note',
                title: 'Issue a credit note',
                description: 'Draw a Gutschrift number against a paid invoice. `amount` is in cents and at most what was paid and not yet credited; a Stripe payment is refunded. issued_on may not lie before the invoice or the latest Gutschrift. With `send` the invoice\'s contact is mailed the Gutschrift. Immutable once issued.',
                method: 'POST',
                path: '/invoices/{invoice}/credit-notes',
                schema: fn (JsonSchema $schema) => [
                    'invoice' => $schema->string()->description('The invoice public id')->required(),
                    'amount' => $schema->integer()->min(1)->description('Cents')->required(),
                    'reason' => $schema->string()->max(1000)->required(),
                    'issued_on' => $schema->string()->nullable()->description('YYYY-MM-DD, never in the future. Defaults to today.'),
                    'intro' => $schema->string()->nullable(),
                    'outro' => $schema->string()->nullable(),
                    'send' => $schema->boolean()->description('Mail the Gutschrift to the invoice\'s contact.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'get_credit_note_draft',
                title: 'Get a credit note draft',
                description: 'The Gutschrift form saved for an invoice, null if none.',
                method: 'GET',
                path: '/invoices/{invoice}/credit-note-draft',
                schema: fn (JsonSchema $schema) => [
                    'invoice' => $schema->string()->description('The invoice public id')->required(),
                ],
                annotations: ToolDefinition::READ,
                structure: fn (array $body) => ['draft' => $body['data']],
            ),

            new ToolDefinition(
                name: 'save_credit_note_draft',
                title: 'Save a credit note draft',
                description: 'Keep a Gutschrift form for an invoice without drawing a number. One draft per invoice: saving again replaces it. Issuing a Gutschrift for the invoice deletes it.',
                method: 'PUT',
                path: '/invoices/{invoice}/credit-note-draft',
                schema: fn (JsonSchema $schema) => [
                    'invoice' => $schema->string()->description('The invoice public id')->required(),
                    'issued_on' => $schema->string()->nullable()->description('YYYY-MM-DD'),
                    'intro' => $schema->string()->nullable(),
                    'outro' => $schema->string()->nullable(),
                    'reason' => $schema->string()->max(1000)->nullable(),
                    'send' => $schema->boolean(),
                    'taken' => $schema->array()->items($schema->boolean())->description('Per invoice position, in order: whether the Gutschrift takes it back.'),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                structure: fn (array $body) => ['draft' => $body['data']],
            ),

            new ToolDefinition(
                name: 'delete_credit_note_draft',
                title: 'Delete a credit note draft',
                description: 'Delete the Gutschrift draft of an invoice. There is no undo.',
                method: 'DELETE',
                path: '/invoices/{invoice}/credit-note-draft',
                schema: fn (JsonSchema $schema) => [
                    'invoice' => $schema->string()->description('The invoice public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted the credit note draft of invoice '.$arguments['invoice'].'.',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function invoiceFields(JsonSchema $schema, bool $issue): array
    {
        $required = fn ($type) => $issue ? $type->required() : $type;

        return [
            'contact_id' => $schema->string()->nullable()->description('Recipient contact public id.'),
            'case_id' => $schema->string()->nullable()->description('Project public id; its primary contact is the recipient when no contact is named.'),
            'issued_on' => $schema->string()->nullable()->description('YYYY-MM-DD, never in the future. Defaults to today.'),
            'due_on' => $schema->string()->nullable()->description('YYYY-MM-DD. Required when a position is not an installment.'),
            'intro' => $schema->string()->nullable(),
            'outro' => $schema->string()->nullable(),
            'discount_type' => $schema->string()->enum(['percentage', 'amount'])->nullable(),
            'discount_value' => $schema->integer()->min(1)->nullable()->description('Percent, or cents for type amount.'),
            'items' => $required($schema->array()->items($schema->object([
                'title' => $issue ? $schema->string()->max(255)->required() : $schema->string()->max(255)->nullable(),
                'description' => $schema->string()->max(2000)->nullable(),
                'quantity' => $schema->integer()->min(1)->nullable(),
                'unit_price' => $schema->integer()->min(0)->nullable()->description('Net cents'),
                'discount_type' => $schema->string()->enum(['percentage', 'amount'])->nullable(),
                'discount_value' => $schema->integer()->min(1)->nullable(),
                'vat_rate' => $schema->integer()->min(0)->max(10000)->nullable()->description('Hundredths of a percent: 1900 is 19 %. Defaults to the studio rate.'),
                'installment_id' => $schema->string()->nullable()->description('Payment installment public id to bill at its own gross.'),
                'quote_item_id' => $schema->string()->nullable()->description('Quote item public id of the project\'s accepted quote this position bills.'),
            ]))->min($issue ? 1 : 0)),
        ];
    }
}
