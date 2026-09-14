import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'

import { apiClientFactory, type ApiClientFactory } from './client.js'
import { registerReadTools } from './tools.js'
import { registerMessageTools } from './tools/messages.js'
import { registerWriteTools } from './tools/write.js'

/**
 * N-01 proved the transports work with zero tools registered. N-02 adds the
 * read half (tools.ts, four read tools over I-02's api routes) and N-03 adds
 * the write half (tools/write.ts, gated behind explicit confirmation) — both
 * via the same typed client every other caller of the api surface uses: no
 * DB connection, no special access, an ordinary bearer-token client of
 * App\Integration\Models\ApiToken. `apiClient` defaults to the real one but
 * is an argument rather than always constructed here, so a test can hand
 * this a fake and exercise tool registration without a live api surface.
 * It is a factory, not a client: each call builds one for the bearer that
 * made it (see client.ts).
 */
export function createMcpServer(clientFor: ApiClientFactory = apiClientFactory()): McpServer {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerReadTools(server, clientFor)
  registerWriteTools(server, clientFor)
  registerMessageTools(server, clientFor)
  return server
}
