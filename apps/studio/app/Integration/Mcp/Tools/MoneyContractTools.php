<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #402: money and paperwork, both `scope:manage_contracts` on the api
 * surface (see ProjectPolicy::managePaymentPlan() on why a plan shares the
 * contract's own capability).
 *
 * There is no record_contract_signature here. SignContract's evidence —
 * typed_name, ip_address, user_agent — is the client's own browser session
 * at the moment they typed their name on the portal; a staff member calling
 * this from a chat has none of the three to supply honestly, and fabricating
 * them would misrepresent what the signature row is evidence of. A paper
 * signature stays something staff records outside this tool.
 */
final class MoneyContractTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'create_payment_plan',
                title: 'Create a payment plan',
                description: 'Split a booked project\'s total into installments, due on the dates given. Amounts are in cents: 1.790 € is 179000. Refused unless the project has a Job (book_project) and a signed contract — a plan is created against what the client actually agreed to. Even installments unless the accepted quote carries its own payment stages, in which case those decide the split.',
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
                name: 'set_payment_reminders',
                title: 'Set payment reminders',
                description: 'Turn a payment plan\'s client reminders on or off — the one-off switch for a client already handled by phone.',
                method: 'POST',
                path: '/payment-plans/{payment_plan}/reminders',
                schema: fn (JsonSchema $schema) => [
                    'payment_plan' => $schema->string()->description('The payment plan public id')->required(),
                    'client_reminders_enabled' => $schema->boolean()->required(),
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

            new ToolDefinition(
                name: 'create_contract',
                title: 'Create a contract',
                description: 'Start a new Fassung of a project\'s contract from a template. Renders in the background; the response may still say "pending" — a second read a moment later shows the finished PDF. Nothing goes to the client until send_contract.',
                method: 'POST',
                path: '/projects/{project}/contracts',
                schema: fn (JsonSchema $schema) => [
                    'project' => $schema->string()->description('The project public id')->required(),
                    'template' => $schema->string()->description('Contract template public id')->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_contract',
                title: 'Update a contract',
                description: 'Edit a contract\'s wording. A draft is edited in place; anything already sent, signed or superseded is left untouched and this opens the next Fassung instead, carrying the same signers with their signatures cleared. Re-renders in the background.',
                method: 'PATCH',
                path: '/contracts/{contract}',
                schema: fn (JsonSchema $schema) => [
                    'contract' => $schema->string()->description('The contract public id')->required(),
                    'body' => $schema->string()->max(100000)->description('The full contract text as HTML')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'send_contract',
                title: 'Send a contract',
                description: 'Freeze the current Fassung as the valid one and send it to the client for signature, superseding any Fassung sent before it. Refused unless the contract is still a draft. With silent, nothing goes out — for a contract already signed outside the app.',
                method: 'POST',
                path: '/contracts/{contract}/send',
                schema: fn (JsonSchema $schema) => [
                    'contract' => $schema->string()->description('The contract public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => true],
                silent: false,
            ),

            new ToolDefinition(
                name: 'set_contract_deadline',
                title: 'Set a contract signing deadline',
                description: 'Set or clear the date a contract should be signed by — what the "follow up before it lapses" automation hangs off.',
                method: 'PATCH',
                path: '/contracts/{contract}/deadline',
                schema: fn (JsonSchema $schema) => [
                    'contract' => $schema->string()->description('The contract public id')->required(),
                    'sign_deadline_on' => $schema->string()->nullable()->description('YYYY-MM-DD, or null to clear it')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'add_contract_signer',
                title: 'Add a contract signer',
                description: 'Add a project contact to the list of people expected to sign the current Fassung. Only contacts already on the project qualify.',
                method: 'POST',
                path: '/contracts/{contract}/signers',
                schema: fn (JsonSchema $schema) => [
                    'contract' => $schema->string()->description('The contract public id')->required(),
                    'contact' => $schema->string()->description('Contact public id, already on the project')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'remove_contract_signer',
                title: 'Remove a contract signer',
                description: 'Take a signer off a contract. Refused once they have signed — a signature is evidence, not a mistake to undo.',
                method: 'DELETE',
                path: '/contract-signers/{signer}',
                schema: fn (JsonSchema $schema) => [
                    'signer' => $schema->string()->description('The contract signer public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Removed signer '.$arguments['signer'].'.',
            ),

            new ToolDefinition(
                name: 'remind_contract_signer',
                title: 'Remind a contract signer',
                description: 'Record that a signer was nudged to sign. Sends nothing — there is no client-facing contract mail in this app — this is the studio\'s own note of the nudge, made some other way.',
                method: 'POST',
                path: '/contract-signers/{signer}/remind',
                schema: fn (JsonSchema $schema) => [
                    'signer' => $schema->string()->description('The contract signer public id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),
        ];
    }
}
