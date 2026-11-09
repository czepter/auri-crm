<?php

namespace App\Integration\Mcp\Tools;

use App\Integration\Mcp\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * #405: reading and sharing galleries. Uploads, ordering, sets, cover and
 * presets stay admin-only — see routes/api.php's own doc comment on the
 * scope split.
 */
final class GalleryTools
{
    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            new ToolDefinition(
                name: 'list_galleries',
                title: 'List galleries',
                description: 'Lists galleries with their project, current published version and draft picture count.',
                method: 'GET',
                path: '/galleries',
                schema: fn (JsonSchema $schema) => [
                    'cursor' => $schema->string()->description('Opaque cursor from a previous call\'s nextCursor.'),
                    'since' => $schema->string()->description('ISO 8601 datetime — only galleries updated at or after this instant.'),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => [
                    'galleries' => $body['data'],
                    'nextCursor' => $body['meta']['next_cursor'],
                ],
                outputSchema: fn (JsonSchema $schema) => [
                    'galleries' => $schema->array()->items(self::row($schema)),
                    'nextCursor' => $schema->string()->nullable(),
                ],
            ),

            new ToolDefinition(
                name: 'get_gallery',
                title: 'Get a gallery',
                description: 'Gets one gallery by its public_id, with status, slug, expiry, download/comment/favorite/view counts and the feature flags.',
                method: 'GET',
                path: '/gallery-records/{gallery}',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => ['gallery' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['gallery' => self::detail($schema)],
            ),

            new ToolDefinition(
                name: 'list_gallery_favorites',
                title: 'List gallery favorites',
                description: 'The filenames a client favorited in a gallery\'s currently published version, one per favorite. Empty before the first publish.',
                method: 'GET',
                path: '/gallery-records/{gallery}/favorites',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
                structure: fn (array $body) => ['filenames' => $body['data']],
                outputSchema: fn (JsonSchema $schema) => ['filenames' => $schema->array()->items($schema->string())],
            ),

            new ToolDefinition(
                name: 'create_gallery',
                title: 'Create a gallery',
                description: 'Start a delivery gallery, on a project or standing alone. is_external true links to pictures hosted elsewhere (external_url required) and owns no draft or version history of its own.',
                method: 'POST',
                path: '/galleries',
                schema: fn (JsonSchema $schema) => [
                    'case_id' => $schema->string()->nullable()->description('Project public_id'),
                    'shoot_id' => $schema->string()->nullable()->description('Shoot public_id, on that project'),
                    'title' => $schema->string()->max(200)->required(),
                    'is_external' => $schema->boolean()->required(),
                    'external_url' => $schema->string()->format('uri')->max(2000)->nullable(),
                    'expires_at' => $schema->string()->nullable()->description('YYYY-MM-DD'),
                    ...self::featureFields($schema),
                ],
                silent: false,
            ),

            new ToolDefinition(
                name: 'update_gallery',
                title: 'Update a gallery',
                description: 'Change a gallery\'s expiry and feature flags. View password, download PIN and design stay admin-only.',
                method: 'PATCH',
                path: '/gallery-records/{gallery}',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                    'expires_at' => $schema->string()->nullable()->description('YYYY-MM-DD'),
                    ...self::featureFields($schema),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'publish_gallery',
                title: 'Publish a gallery',
                description: 'Snapshot the draft into a new published version. Every guest already inside sees the new version immediately. Given a subject, also mails the project\'s primary contact — omit it to publish without notifying anyone.',
                method: 'POST',
                path: '/gallery-records/{gallery}/publish',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                    'subject' => $schema->string()->max(255)->nullable(),
                    'message' => $schema->string()->nullable(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
                silent: false,
            ),

            new ToolDefinition(
                name: 'withdraw_gallery',
                title: 'Withdraw a gallery',
                description: 'Stop showing a gallery to anyone outside the studio. Rotates the preview pass, so a preview link already sent stops opening the draft too.',
                method: 'POST',
                path: '/gallery-records/{gallery}/withdraw',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'start_gallery_prerelease',
                title: 'Start gallery pre-release',
                description: 'Share a gallery\'s address before it has anything published — its own pre-release page collects addresses to notify at first publish. Refused once a version exists, or for an external gallery.',
                method: 'POST',
                path: '/gallery-records/{gallery}/prerelease/start',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
                silent: false,
            ),

            new ToolDefinition(
                name: 'stop_gallery_prerelease',
                title: 'Stop gallery pre-release',
                description: 'End pre-release. Deletes everyone still on the waiting list — none of them were notified yet, so there is nothing to keep.',
                method: 'POST',
                path: '/gallery-records/{gallery}/prerelease/stop',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ToolDefinition::OVERWRITES,
                silent: false,
            ),

            new ToolDefinition(
                name: 'create_gallery_preview_link',
                title: 'Create a gallery preview link',
                description: 'Mint a time-limited link that opens the draft without a password, for handing the work in progress to a couple or a second shooter. Rotates the pass, so any earlier preview link stops working the moment this one is issued.',
                method: 'POST',
                path: '/gallery-records/{gallery}/preview-link',
                schema: fn (JsonSchema $schema) => [
                    'gallery' => $schema->string()->description('The gallery\'s public_id')->required(),
                ],
                annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => false],
                silent: false,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function featureFields(JsonSchema $schema): array
    {
        return [
            'download_rule' => $schema->string()->enum(['open', 'after_payment', 'off']),
            'view_mode' => $schema->string()->enum(['none', 'text', 'logo', 'locked']),
            'lists_enabled' => $schema->boolean(),
            'comments_enabled' => $schema->boolean(),
        ];
    }

    private static function row(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'title' => $schema->string(),
            'projectId' => $schema->string()->nullable(),
            'currentVersion' => $schema->integer()->nullable(),
            'draftAssetCount' => $schema->integer(),
            'status' => $schema->string()->enum(['draft', 'prerelease', 'published', 'withdrawn']),
            'updatedAt' => $schema->string(),
        ]);
    }

    private static function detail(JsonSchema $schema): object
    {
        return $schema->object([
            'id' => $schema->string(),
            'title' => $schema->string(),
            'projectId' => $schema->string()->nullable(),
            'currentVersion' => $schema->integer()->nullable(),
            'draftAssetCount' => $schema->integer(),
            'status' => $schema->string()->enum(['draft', 'prerelease', 'published', 'withdrawn']),
            'updatedAt' => $schema->string(),
            'slug' => $schema->string(),
            'url' => $schema->string(),
            'isExternal' => $schema->boolean(),
            'externalUrl' => $schema->string()->nullable(),
            'expiresAt' => $schema->string()->nullable(),
            'downloadsEnabled' => $schema->boolean(),
            'downloadRule' => $schema->string()->enum(['open', 'after_payment', 'off']),
            'viewMode' => $schema->string()->enum(['none', 'text', 'logo', 'locked']),
            'listsEnabled' => $schema->boolean(),
            'commentsEnabled' => $schema->boolean(),
            'favoriteCount' => $schema->integer(),
            'downloadCount' => $schema->integer(),
            'commentCount' => $schema->integer(),
            'viewCount' => $schema->integer(),
        ]);
    }
}
