#!/usr/bin/env node
import { randomUUID } from 'node:crypto'
import { createServer as createHttpServer } from 'node:http'

import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js'
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'

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
  const httpTransport = new StreamableHTTPServerTransport({ sessionIdGenerator: () => randomUUID() })
  await createMcpServer().connect(httpTransport)

  const port = Number(process.env.PORT ?? 3000)
  createHttpServer((req, res) => {
    void httpTransport.handleRequest(req, res)
  }).listen(port)
}
