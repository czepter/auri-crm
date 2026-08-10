import { randomUUID } from 'node:crypto'

/**
 * I-03's `EnsureIdempotentWrite` keys a claim on (tenant, api_token,
 * Idempotency-Key) — so the key this sends only has to be stable across a
 * retry of the *same* logical tool call, not globally unique forever. The
 * MCP protocol's own JSON-RPC request id is exactly that: a client-side
 * retry of a call that never got a response reuses the original request's
 * id (StdioClientTransport/StreamableHTTPClientTransport both do), while
 * every new call gets a fresh one. Falls back to a one-off UUID only when a
 * transport doesn't expose a request id at all, in which case retries of
 * that call simply aren't safe to dedupe — no worse than not having this.
 */
export function idempotencyKeyFor(
  toolName: string,
  extra: { requestId?: string | number; sessionId?: string },
): string {
  if (extra.requestId === undefined) {
    return `mcp:${toolName}:${randomUUID()}`
  }

  return `mcp:${toolName}:${extra.sessionId ?? 'stdio'}:${extra.requestId}`
}
