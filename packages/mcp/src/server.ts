import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'

import { createApiClient } from './client.js'
import { registerReadTools } from './tools.js'

/**
 * N-01 proved the transports work with zero tools registered. N-02 is the
 * first tool surface: four read tools (tools.ts) over I-02's api routes,
 * via the same typed client every other caller of that surface uses — no
 * DB connection, no special access, an ordinary bearer-token client of
 * App\Integration\Models\ApiToken. `client` defaults to the real one but is
 * an argument rather than always constructed here, so a test can hand this
 * a fake and exercise tool registration without a live api surface.
 */
export function createMcpServer(client: ReturnType<typeof createApiClient> = createApiClient()): McpServer {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerReadTools(server, client)
  return server
}
