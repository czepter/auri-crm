<?php

namespace App\Integration\Mcp;

use App\Integration\Mcp\Tools\BookingTaskTools;
use App\Integration\Mcp\Tools\CrmEditTools;
use App\Integration\Mcp\Tools\GalleryTools;
use App\Integration\Mcp\Tools\MessageTools;
use App\Integration\Mcp\Tools\MoneyContractTools;
use App\Integration\Mcp\Tools\QuoteActionTools;
use App\Integration\Mcp\Tools\ReadTools;
use App\Integration\Mcp\Tools\StudioOpsTools;
use App\Integration\Mcp\Tools\WriteTools;
use Laravel\Mcp\Server;

/**
 * The studio's MCP server, mounted on the api surface (routes/mcp.php).
 *
 * It holds no data of its own: every tool stands for one route in
 * routes/api.php and is answered by it — see ApiCall.
 */
class CrmServer extends Server
{
    protected string $name = 'studio-mcp';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        The studio's CRM: leads and projects, shoots and crew, quotes, contracts, payments, galleries, tasks and messages.
        Every id in an argument or an answer is a public_id, never a row's internal integer id.
        MARKDOWN;

    /**
     * A page a client never asked to be split. The tool list runs to three
     * figures, and a client that stops at the first page would see a third
     * of the CRM.
     */
    public int $maxPaginationLength = 250;

    public int $defaultPaginationLength = 250;

    protected function boot(): void
    {
        $this->tools = array_map(
            fn (ToolDefinition $definition): ApiTool => new ApiTool($definition),
            self::definitions(),
        );
    }

    /** @return list<ToolDefinition> */
    public static function definitions(): array
    {
        return [
            ...ReadTools::definitions(),
            ...WriteTools::definitions(),
            ...CrmEditTools::definitions(),
            ...QuoteActionTools::definitions(),
            ...MoneyContractTools::definitions(),
            ...BookingTaskTools::definitions(),
            ...MessageTools::definitions(),
            ...GalleryTools::definitions(),
            ...StudioOpsTools::definitions(),
        ];
    }
}
