import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'

/**
 * N-01's whole scope: a bare, connectable MCP server — zero tools or
 * resources registered. Tool surface generated from packages/contracts
 * lands in N-02/N-03; this just proves the transports work.
 */
export function createMcpServer(): McpServer {
  return new McpServer({ name: 'studio-mcp', version: '0.0.1' })
}
