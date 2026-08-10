/// <reference path="../.astro/types.d.ts" />
/// <reference types="astro/client" />

// G-13: src/middleware.ts stores the request's X-Request-Id here — accepted
// from the edge or minted, the same id forwarded on every outgoing api call
// (src/lib/apiClient.ts) and attached to any GlitchTip event this request's
// page render captures.
declare namespace App {
  interface Locals {
    requestId: string
  }
}
