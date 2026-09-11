import { describe, expect, it } from 'vitest'

import { idempotencyKeyFor } from './idempotency.js'

// I-03's EnsureIdempotentWrite dedupes on (tenant, api_token, key). The
// contract: the same write -> the same key, so a retry never double-writes;
// a different write -> a different key, so the next call is not refused as a
// replay of the last one.
//
// The version before this keyed on the MCP request id, and these tests
// passed while every second write of a session was refused in production —
// they asserted "a different request id gives a different key" and never
// asked whether a different write actually arrives with a different request
// id. It does not: claude.ai sends every tool call as its own POST carrying
// the same id. The cases below are written against the payload instead,
// because that is the thing the caller can actually vary.

describe('idempotencyKeyFor', () => {
  it('is stable for the same tool and the same arguments, so a retry dedupes', () => {
    const args = { lead: 'lead_abc', stage: 'contacted', entered_at: '2018-10-29' }

    expect(idempotencyKeyFor('advance_lead_stage', args)).toBe(idempotencyKeyFor('advance_lead_stage', { ...args }))
  })

  it('differs when any argument differs, so a second write is not a replay of the first', () => {
    const first = idempotencyKeyFor('advance_lead_stage', { lead: 'lead_abc', stage: 'contacted' })
    const sameLeadLaterStage = idempotencyKeyFor('advance_lead_stage', { lead: 'lead_abc', stage: 'booked' })
    const otherLead = idempotencyKeyFor('advance_lead_stage', { lead: 'lead_xyz', stage: 'contacted' })

    expect(first).not.toBe(sameLeadLaterStage)
    expect(first).not.toBe(otherLead)
  })

  it('ignores the order the arguments arrive in', () => {
    const first = idempotencyKeyFor('close_lead', { lead: 'lead_abc', outcome: 'lost', reason: 'zu teuer' })
    const second = idempotencyKeyFor('close_lead', { reason: 'zu teuer', lead: 'lead_abc', outcome: 'lost' })

    expect(first).toBe(second)
  })

  it('separates an absent argument from one explicitly set', () => {
    const bare = idempotencyKeyFor('capture_lead', { project_name: 'Voss' })
    const dated = idempotencyKeyFor('capture_lead', { project_name: 'Voss', date: '2019-06-29' })

    expect(bare).not.toBe(dated)
  })

  it('differs across tools, so two tools called with the same arguments never collide', () => {
    const args = { project: 'proj_abc' }

    expect(idempotencyKeyFor('reopen_lead', args)).not.toBe(idempotencyKeyFor('uncancel_shoot', args))
  })

  it('hashes nested arguments rather than the reference, so two equal batches dedupe', () => {
    const leads = [{ project_name: 'Voss', type: 'portrait' }]

    expect(idempotencyKeyFor('capture_leads_bulk', { leads })).toBe(
      idempotencyKeyFor('capture_leads_bulk', { leads: [{ type: 'portrait', project_name: 'Voss' }] }),
    )
  })
})
