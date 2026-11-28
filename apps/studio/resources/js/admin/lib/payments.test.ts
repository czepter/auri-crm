import { describe, expect, it } from 'vitest'
import { installmentChipClass } from './payments'

function installment(overrides: Partial<App.Payments.Data.PaymentInstallmentData>): App.Payments.Data.PaymentInstallmentData {
  return {
    id: 'inst_1',
    amount: 100000,
    dueOn: new Date(Date.now() + 30 * 86_400_000).toISOString().slice(0, 10),
    status: 'scheduled',
    statusLabel: 'Geplant',
    sortOrder: 0,
    label: 'Anzahlung',
    settledVia: null,
    settledViaLabel: null,
    paidOn: null,
    paymentReference: null,
    paidAmount: 0,
    remaining: 100000,
    isLate: false,
    cancelledOn: null,
    invoiceId: null,
    invoiceNumber: null,
    invoicedOn: null,
    hasInvoiceDocument: false,
    creditNotes: [],
    ...overrides,
  }
}

describe('installmentChipClass', () => {
  it('is paid when the persisted status says so, regardless of isLate', () => {
    expect(installmentChipClass(installment({ status: 'paid', isLate: true }))).toBe('paid')
  })

  it('is late when the server derived it as such — the source of truth, not a client-side date check', () => {
    // dueOn is weeks out here on purpose: isLate is what the chip trusts, never a local comparison against dueOn.
    expect(installmentChipClass(installment({ isLate: true }))).toBe('late')
  })

  it('is due within 14 days when not late', () => {
    const dueOn = new Date(Date.now() + 5 * 86_400_000).toISOString().slice(0, 10)
    expect(installmentChipClass(installment({ dueOn, isLate: false }))).toBe('due')
  })

  it('is upcoming beyond 14 days when not late', () => {
    const dueOn = new Date(Date.now() + 30 * 86_400_000).toISOString().slice(0, 10)
    expect(installmentChipClass(installment({ dueOn, isLate: false }))).toBe('upcoming')
  })
})
