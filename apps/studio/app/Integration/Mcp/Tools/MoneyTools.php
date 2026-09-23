<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #402: the payments half of N-03's write surface, `scope:manage_contracts`
 * on the api surface (see ClientCasePolicy::managePaymentPlan()).
 */
final class MoneyTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'create_payment_plan',
                title: 'Create a payment plan',
                description: 'Split a booked project\'s total into installments, due on the dates given. Amounts are in cents: 1.790 € is 179000. Refused unless the project is booked (book_project). Even installments unless the accepted quote carries its own payment stages, in which case those decide the split.',
                method: 'POST',
                path: '/projects/{project}/payment-plan',
                schema: fn (JsonSchema $schema) => [
                    'project' => $schema->string()->description('The project public id')->required(),
                    'total' => $schema->integer()->min(0)->required(),
                    'installments' => $schema->array()->items($schema->object([
                        'due_on' => $schema->string()->description('YYYY-MM-DD')->required(),
                    ]))->min(1)->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_payment_plan',
                title: 'Update a payment plan',
                description: 'Replace a payment plan\'s total and every installment. Installments must sum to total. Refused once the plan\'s first installment has been invoiced — issue_invoice is a one-way door for the plan shape past that point.',
                method: 'PATCH',
                path: '/payment-plans/{payment_plan}',
                schema: fn (JsonSchema $schema) => [
                    'payment_plan' => $schema->string()->description('The payment plan public id')->required(),
                    'total' => $schema->integer()->min(0)->description('Cents')->required(),
                    'installments' => $schema->array()->items($schema->object([
                        'due_on' => $schema->string()->description('YYYY-MM-DD')->required(),
                        'amount' => $schema->integer()->min(0)->description('Cents')->required(),
                        'status' => $schema->string()->enum(['scheduled', 'paid'])->required(),
                    ]))->min(1)->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'record_payment',
                title: 'Record a payment',
                description: 'Mark one installment paid, with the date, method and reference staff actually have in hand. Idempotent: an installment already paid is returned as-is.',
                method: 'POST',
                path: '/payment-installments/{installment}/paid',
                schema: fn (JsonSchema $schema) => [
                    'installment' => $schema->string()->description('The payment installment public id')->required(),
                    'paid_on' => $schema->string()->description('YYYY-MM-DD, never in the future')->required(),
                    'method' => $schema->string()->enum(['stripe', 'transfer', 'manual'])->required(),
                    'reference' => $schema->string()->max(255)->nullable(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'issue_invoice',
                title: 'Issue an invoice',
                description: 'Draw an invoice number for one installment and stamp it on — the Verwendungszweck the client transfers under. Immutable once issued: there is no undo, and no edit past this point. Idempotent: an already-invoiced installment is returned untouched rather than drawing a second number. Refused while the studio has neither Steuernummer nor USt-IdNr. on file (§ 14 UStG).',
                method: 'POST',
                path: '/payment-installments/{installment}/invoice',
                schema: fn (JsonSchema $schema) => [
                    'installment' => $schema->string()->description('The payment installment public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

        ];
    }
}
