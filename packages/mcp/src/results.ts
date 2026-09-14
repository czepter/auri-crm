import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js'

/**
 * openapi-fetch returns `error: undefined` on a 204, and on any error
 * response whose Content-Length is "0" (dist/index.mjs's early-return
 * before it tries to parse a body) — a proxy 502 or a throttled response
 * both look like that. `response.ok` is the one field it always sets from
 * the real HTTP status, so it — not the presence of `error` — decides
 * success here.
 */
function apiErrorMessage(error: unknown): string {
  if (error && typeof error === 'object') {
    const message = 'message' in error && typeof error.message === 'string' ? error.message : undefined
    const errors = 'errors' in error && error.errors && typeof error.errors === 'object' ? JSON.stringify(error.errors) : undefined
    if (message) {
      return errors ? `${message} ${errors}` : message
    }
  }
  return 'The api surface returned an error with no message.'
}

export function errorResult(error: unknown, response: Response): CallToolResult {
  return { isError: true, content: [{ type: 'text', text: `${response.status} ${response.statusText}: ${apiErrorMessage(error)}` }] }
}

export function apiResult(data: unknown, error: unknown, response: Response): CallToolResult {
  if (!response.ok) {
    return errorResult(error, response)
  }

  return { content: [{ type: 'text', text: JSON.stringify(data) }] }
}

export function jsonResult<T extends Record<string, unknown>>(structuredContent: T): {
  content: [{ type: 'text'; text: string }]
  structuredContent: T
} {
  return { content: [{ type: 'text', text: JSON.stringify(structuredContent) }], structuredContent }
}
