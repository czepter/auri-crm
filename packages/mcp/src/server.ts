import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'

import { createApiClient, type ApiClient } from './client.js'
import { registerWriteTools } from './tools/write.js'

/**
 * N-01 proved the transports; N-03 adds the tool surface's write half —
 * capture_lead, advance_lead_stage, add_internal_note, gated behind explicit
 * confirmation (see tools/write.ts). Read tools (N-02) register the same
 * way once they land. `apiClient` is injectable so tests can point the
 * server at a fake CRM instead of a real one.
 */
export function createMcpServer(apiClient: ApiClient = createApiClient()): McpServer {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerWriteTools(server, apiClient)

  return server
}
