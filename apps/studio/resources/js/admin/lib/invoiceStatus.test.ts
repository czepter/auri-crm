import { describe, expect, it } from 'vitest'
import { invoiceStatusDetail } from './invoiceStatus'

const t = (key: string, named?: Record<string, unknown>, plural?: number) => `${key}|${JSON.stringify(named ?? {})}|${plural ?? ''}`
const row = { status: 'open', amount: 200_000, paidAmount: 0, dueOn: '2026-09-18', overdueDays: null, settledOn: null, cancelledOn: null }

describe('invoiceStatusDetail', () => {
  it('names the days and what is still owed when overdue', () => {
    expect(invoiceStatusDetail({ ...row, status: 'overdue', paidAmount: 150_000, overdueDays: 16 }, t))
      .toBe(`invoicesOverview.detailOverdue|${JSON.stringify({ amount: '500,00\u00a0€' })}|16`)
  })

  it('names paid and total when partly paid', () => {
    expect(invoiceStatusDetail({ ...row, status: 'partial', paidAmount: 100_000 }, t))
      .toContain('"paid":"1.000,00\u00a0€","total":"2.000,00\u00a0€"')
  })

  it('gives the due date for an open invoice and the day for a settled or cancelled one', () => {
    expect(invoiceStatusDetail(row, t)).toContain('detailDue')
    expect(invoiceStatusDetail({ ...row, status: 'paid', settledOn: '2026-09-20' }, t)).toContain('detailOn')
    expect(invoiceStatusDetail({ ...row, status: 'cancelled', cancelledOn: '2026-09-20' }, t)).toContain('detailOn')
  })

  it('says nothing when the date is missing', () => {
    expect(invoiceStatusDetail({ ...row, status: 'paid' }, t)).toBeNull()
  })
})
