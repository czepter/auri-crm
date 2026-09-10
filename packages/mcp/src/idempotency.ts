import { createHash } from 'node:crypto'

/**
 * I-03's `EnsureIdempotentWrite` keys a claim on (tenant, api_token,
 * Idempotency-Key), so the key this sends has to be stable across a retry of
 * the *same* logical write and different for a different one.
 *
 * It was built out of the MCP request id, on the assumption that a client
 * counts one up per call and reuses it only on a retry. claude.ai does not:
 * every tool call is its own POST carrying the same id, so the key came out
 * constant per tool per session and the second write of a session was
 * refused as a replay of the first. The request id cannot carry this.
 *
 * The payload can. Hashing what is actually being written keeps a retry
 * identical — a retry sends the same body — and separates two writes that
 * differ in any argument.
 *
 * ponytail: two writes that are byte-identical on purpose (the same note
 * filed twice) collide inside the claim's window and the second is refused.
 * Give the tools a caller-supplied request id if that ever needs to be
 * possible.
 */
export function idempotencyKeyFor(toolName: string, payload: unknown): string {
  const digest = createHash('sha256').update(canonical(payload)).digest('hex')

  return `mcp:${toolName}:${digest.slice(0, 32)}`
}

/**
 * JSON with object keys in a fixed order, so two calls that differ only in
 * the order their arguments arrive hash the same.
 */
function canonical(value: unknown): string {
  if (value === null || typeof value !== 'object') return JSON.stringify(value) ?? 'null'
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`

  const entries = Object.entries(value as Record<string, unknown>)
    .filter(([, item]) => item !== undefined)
    .sort(([a], [b]) => (a < b ? -1 : 1))
    .map(([key, item]) => `${JSON.stringify(key)}:${canonical(item)}`)

  return `{${entries.join(',')}}`
}
