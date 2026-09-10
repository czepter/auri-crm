#!/usr/bin/env node
import { createServer as createHttpServer } from 'node:http'

import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js'

import { createAuthenticatedRequestListener, sessionTransports } from './http.js'
import { createMcpServer } from './server.js'

/**
 * Transport picked at boot, not baked in: stdio for local clients (Claude
 * Desktop and friends spawn the process and talk over its stdin/stdout),
 * streamable HTTP for remote/server use. `--http` or MCP_TRANSPORT=http
 * selects HTTP; stdio is the default, matching how local MCP clients invoke
 * a server with no flags at all.
 */
const useHttp = process.argv.includes('--http') || process.env.MCP_TRANSPORT === 'http'

if (useHttp) {
  await startHttp()
} else {
  await startStdio()
}

async function startStdio(): Promise<void> {
  await createMcpServer().connect(new StdioServerTransport())
}

async function startHttp(): Promise<void> {
  // N-04: every request needs a bearer token the CRM's own /oauth/token-info
  // accepts before it reaches the MCP session at all — see http.ts, which
  // also owns the session-to-transport map.
  const port = Number(process.env.PORT ?? 3000)
  createHttpServer(createAuthenticatedRequestListener(sessionTransports())).listen(port)
}
