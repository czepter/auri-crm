<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #403: the admin-only quote actions and the personal service
 * recommendations the MCP audit found missing.
 */
final class QuoteActionTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'create_quote_addendum',
                title: 'Create a quote addendum',
                description: 'Start a new draft quote beside an already-accepted one, for adding to a booking after the fact. The accepted quote stays accepted, unlike revise_quote which supersedes it. Fails if the given quote is not accepted.',
                method: 'POST',
                path: '/quotes/{quote}/addendum',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The accepted quote public id to add to')->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'duplicate_quote',
                title: 'Duplicate a quote',
                description: 'Copy a quote’s lines, payment plan and terms into a new independent draft on the same project. Status, sent/accepted history and predecessor stay behind.',
                method: 'POST',
                path: '/quotes/{quote}/duplicate',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id to copy')->required(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_quote',
                title: 'Delete a quote',
                description: 'Delete a draft quote. Refused once it has been sent — a client may already hold a copy. There is no undo.',
                method: 'DELETE',
                path: '/quotes/{quote}',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted quote '.$arguments['quote'].'.',
            ),

            new ToolDefinition(
                name: 'mark_quote_sent',
                title: 'Mark a quote sent by the studio itself',
                description: 'Record a draft quote as delivered outside the app — WhatsApp, a phone call, the studio’s own mail client. Same state change as send_quote, channel ‘self’, no client notification.',
                method: 'POST',
                path: '/quotes/{quote}/mark-sent',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'adopt_quote_recipient',
                title: 'Adopt a quote’s recipient address',
                description: 'Write a quote’s recipient address (street, postcode, city) back onto its recipient contact. Fails if the quote has no recipient contact.',
                method: 'POST',
                path: '/quotes/{quote}/adopt-recipient',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'save_quote_as_package',
                title: 'Save a quote as a package',
                description: 'Turn a quote, or one of its lines, into a catalogue package — the only way to write a package’s hourly rate and payment plan from here. Give `item` (a line’s id from get_case) to save just that line; omit it to save the whole quote.',
                method: 'POST',
                path: '/quotes/{quote}/save-as-package',
                schema: fn (JsonSchema $schema) => [
                    'quote' => $schema->string()->description('The quote public id')->required(),
                    'name' => $schema->string()->max(255)->description('The new package’s name')->required(),
                    'item' => $schema->string()->nullable()->description('A quote line’s id, to save only that line'),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'list_service_suggestions',
                title: 'List a contact’s service suggestions',
                description: 'Lists the packages this studio has personally suggested to a contact — what suggest_service writes, and what the contact sees in their portal.',
                method: 'GET',
                path: '/contacts/{contact}/service-suggestions',
                schema: fn (JsonSchema $schema) => [
                    'contact' => $schema->string()->description('The contact public id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            ),

            new ToolDefinition(
                name: 'suggest_service',
                title: 'Suggest a service to a contact',
                description: 'Personally recommend a package to a contact, with an optional note — what shows in their portal. The package must be offered there (portal_offered on the package); suggesting the same package again replaces the note rather than adding a second suggestion.',
                method: 'POST',
                path: '/contacts/{contact}/service-suggestions',
                schema: fn (JsonSchema $schema) => [
                    'contact' => $schema->string()->description('The contact public id')->required(),
                    'package' => $schema->string()->description('The package public id — must have portal_offered set')->required(),
                    'note' => $schema->string()->max(2000)->nullable(),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'delete_service_suggestion',
                title: 'Delete a service suggestion',
                description: 'Withdraw a personal service suggestion from a contact’s portal. There is no undo.',
                method: 'DELETE',
                path: '/service-suggestions/{suggestion}',
                schema: fn (JsonSchema $schema) => [
                    'suggestion' => $schema->string()->description('The service suggestion public id, from list_service_suggestions')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
                confirmation: fn (array $arguments) => 'Deleted service suggestion '.$arguments['suggestion'].'.',
            ),
        ];
    }
}
