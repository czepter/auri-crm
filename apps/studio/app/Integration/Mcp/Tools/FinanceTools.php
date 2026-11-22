<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Expenses, receipts and the EÜR, `scope:manage_finances` on the api surface.
 * The writes make drafts and nothing more: booking, ignoring, locking and
 * deleting have no tool, so a person sees every receipt once.
 */
final class FinanceTools
{
    private const AMOUNTS = 'Amounts are in cents: 119,00 € is 11900. The VAT rate is in basis points: 19 % is 1900.';

    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        $expense = fn (JsonSchema $schema, bool $gross) => [
            'vendor' => $schema->string()->max(200)->nullable(),
            'description' => $schema->string()->max(5000)->nullable(),
            'invoice_no' => $schema->string()->max(100)->nullable(),
            'invoice_date' => $schema->string()->description('YYYY-MM-DD')->nullable(),
            'paid_at' => $schema->string()->description('YYYY-MM-DD, the day it was paid. The expense counts in this year.')->nullable(),
            'gross' => $gross ? $schema->integer()->min(0)->required() : $schema->integer()->min(0),
            'vat_rate' => $schema->integer()->min(0)->max(10000)->description('Basis points; 0 when omitted.'),
            'net' => $schema->integer()->min(0)->description('Cents. Worked out from gross and vat_rate when net and vat are both left out.'),
            'vat' => $schema->integer()->min(0)->description('Cents'),
            'currency' => $schema->string()->description('ISO code, EUR when omitted.'),
            'gross_eur' => $schema->integer()->min(0)->description('Cents actually debited, for a foreign currency. Not needed for EUR.')->nullable(),
            'category_id' => $schema->string()->description('A category public id from list_expense_categories')->nullable(),
            'business_share' => $schema->integer()->min(0)->max(100)->description('Percent that counts as business, 100 when omitted.'),
            'note' => $schema->string()->max(5000)->nullable(),
            'is_self_issued' => $schema->boolean()->description('A receipt substitute made by the studio, for a private purchase without an invoice.'),
            'document_ids' => $schema->array()->items($schema->string())->description('Document ids from request_document_upload'),
        ];

        return [
            new ToolDefinition(
                name: 'request_document_upload',
                title: 'Request a receipt upload',
                description: 'Announce a receipt file and get a signed address to PUT its raw bytes to, valid for 15 minutes and for one file. PDF, JPEG, PNG or WebP up to 10 MB. Pass the sha256 when you have it: a receipt the studio already holds comes back as {documentId, duplicate: true} with no address. `source_ref` is free JSON for where the receipt came from; the studio keeps it and never reads it. The document id goes into create_expense_draft as document_ids.',
                method: 'POST',
                path: '/finance/documents/uploads',
                schema: fn (JsonSchema $schema) => [
                    'filename' => $schema->string()->max(255)->required(),
                    'mime' => $schema->string()->enum(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])->required(),
                    'size' => $schema->integer()->min(1)->max(10 * 1024 * 1024)->description('Bytes')->required(),
                    'sha256' => $schema->string()->description('Hex digest of the file')->nullable(),
                    'source_ref' => $schema->object()->description('Free JSON, kept as given')->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'create_expense_draft',
                title: 'Create an expense draft',
                description: 'Record an expense as a draft for a person to check and book in the screens. It cannot be booked here. '.self::AMOUNTS.' Check find_duplicate_expense first. A draft dated in a locked year is refused.',
                method: 'POST',
                path: '/finance/expenses',
                schema: fn (JsonSchema $schema) => $expense($schema, true),
            ),

            new ToolDefinition(
                name: 'update_expense_draft',
                title: 'Update an expense draft',
                description: 'Change fields of an expense that is still a draft; the others stay as they are. Refused with 422 once it is booked or ignored. '.self::AMOUNTS,
                method: 'PATCH',
                path: '/finance/expenses/{expense}',
                schema: fn (JsonSchema $schema) => ['expense' => $schema->string()->description('The expense public id')->required(), ...$expense($schema, false)],
                annotations: ToolDefinition::OVERWRITES,
            ),

            new ToolDefinition(
                name: 'suggest_asset',
                title: 'Suggest a fixed asset',
                description: 'Carry a draft expense as a fixed asset: GWG up to 800 € net (gross for a Kleinunternehmer year), linear above, where useful_life_months or an afa_category (computer_software, 12 months) is needed. Anything left out is taken from the expense. Calling it again changes the suggestion. Refused with 422 once the expense is booked. A person books it with the expense. '.self::AMOUNTS,
                method: 'POST',
                path: '/finance/expenses/{expense}/asset',
                schema: fn (JsonSchema $schema) => [
                    'expense' => $schema->string()->description('The expense public id')->required(),
                    'name' => $schema->string()->max(200),
                    'acquired_at' => $schema->string()->description('YYYY-MM-DD')->nullable(),
                    'cost_net' => $schema->integer()->min(0)->description('Cents'),
                    'cost_gross' => $schema->integer()->min(0)->description('Cents'),
                    'method' => $schema->string()->enum(['gwg', 'linear']),
                    'useful_life_months' => $schema->integer()->min(1)->max(600)->nullable(),
                    'afa_category' => $schema->string()->enum(['computer_software'])->nullable(),
                ],
                annotations: ToolDefinition::OVERWRITES,
            ),

            new ToolDefinition(
                name: 'list_expenses',
                title: 'List expenses',
                description: 'Lists expenses, drafts first and then the latest payments, at most 200. Filter by the year they count in, status, category key, vendor text or missing receipt. Every amount is in cents.',
                method: 'GET',
                path: '/finance/expenses',
                schema: fn (JsonSchema $schema) => [
                    'year' => $schema->integer()->min(2000)->max(2100),
                    'status' => $schema->string()->enum(['draft', 'booked', 'ignored']),
                    'category' => $schema->string()->description('A category key from list_expense_categories'),
                    'vendor' => $schema->string()->description('Case-insensitive substring'),
                    'without_receipt' => $schema->boolean(),
                ],
                annotations: ToolDefinition::READ,
            ),

            new ToolDefinition(
                name: 'find_duplicate_expense',
                title: 'Find a duplicate expense',
                description: 'Finds expenses that already hold the same receipt (sha256), the same invoice number from the same vendor, or the same vendor, gross amount and invoice date. Returns an empty list when there is none. Call it before create_expense_draft.',
                method: 'GET',
                path: '/finance/expenses/duplicates',
                schema: fn (JsonSchema $schema) => [
                    'sha256' => $schema->string(),
                    'vendor' => $schema->string(),
                    'invoice_no' => $schema->string(),
                    'gross' => $schema->integer()->min(0)->description('Cents'),
                    'invoice_date' => $schema->string()->description('YYYY-MM-DD'),
                ],
                annotations: ToolDefinition::READ,
            ),

            new ToolDefinition(
                name: 'list_expense_categories',
                title: 'List expense categories',
                description: 'Lists the expense categories with the EÜR line each belongs to and, where set, the SKR04 account.',
                method: 'GET',
                path: '/finance/expense-categories',
                schema: fn (JsonSchema $schema) => [],
                annotations: ToolDefinition::READ,
            ),

            new ToolDefinition(
                name: 'list_assets',
                title: 'List fixed assets',
                description: 'Lists booked fixed assets still on the books in a year, with the book value at 31 December of that year, or at `as_of`. Assets disposed of before that year are left out. Every amount is in cents.',
                method: 'GET',
                path: '/finance/assets',
                schema: fn (JsonSchema $schema) => [
                    'year' => $schema->integer()->min(2000)->max(2100)->description('The current year when omitted'),
                    'as_of' => $schema->string()->description('YYYY-MM-DD; the book value at the end of that day\'s month'),
                ],
                annotations: ToolDefinition::READ,
            ),

            new ToolDefinition(
                name: 'get_euer',
                title: 'Get the EÜR of a year',
                description: 'The yearly Einnahmenüberschussrechnung as JSON, with the same figures as the screen: income, expenses per category, depreciation, profit and the checklist of what is still open. A locked year answers with its stored snapshot. Every amount is in cents.',
                method: 'GET',
                path: '/finance/euer/{year}',
                schema: fn (JsonSchema $schema) => ['year' => $schema->integer()->min(2000)->max(2100)->required()],
                annotations: ToolDefinition::READ,
            ),
        ];
    }
}
