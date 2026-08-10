import { randomUUID } from 'node:crypto'
import { createServer as createHttpServer } from 'node:http'
import type { AddressInfo } from 'node:net'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js'
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js'
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'
import { afterEach, describe, expect, it } from 'vitest'
import { createMcpServer } from './server.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// N-01's acceptance bar, verbatim: "the server connects over stdio and over
// streamable HTTP". Zero tools registered is correct for this story (that's
// N-02/N-03's job), so a completed initialize handshake — proven by the
// server's own identity coming back — is the whole check.

describe('MCP server transports', () => {
  const clients: Client[] = []
  const httpServers: ReturnType<typeof createHttpServer>[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
    await Promise.all(
      httpServers.splice(0).map(
        (server) =>
          new Promise<void>((resolve) => {
            server.close(() => resolve())
          }),
      ),
    )
  })

  it('connects over stdio', async () => {
    const client = new Client({ name: 'test-client', version: '0.0.1' })
    clients.push(client)

    await client.connect(
      new StdioClientTransport({
        command: process.execPath,
        args: [path.join(__dirname, '..', 'dist', 'index.js'), '--stdio'],
      }),
    )

    expect(client.getServerVersion()).toEqual({ name: 'studio-mcp', version: '0.0.1' })
  })

  it('connects over streamable HTTP', async () => {
    const server = createMcpServer()
    const httpTransport = new StreamableHTTPServerTransport({ sessionIdGenerator: () => randomUUID() })
    await server.connect(httpTransport)

    const httpServer = createHttpServer((req, res) => {
      httpTransport.handleRequest(req, res)
    })
    httpServers.push(httpServer)
    await new Promise<void>((resolve) => httpServer.listen(0, resolve))
    const { port } = httpServer.address() as AddressInfo

    const client = new Client({ name: 'test-client', version: '0.0.1' })
    clients.push(client)
    await client.connect(new StreamableHTTPClientTransport(new URL(`http://127.0.0.1:${port}/mcp`)))

    expect(client.getServerVersion()).toEqual({ name: 'studio-mcp', version: '0.0.1' })
  })
})
