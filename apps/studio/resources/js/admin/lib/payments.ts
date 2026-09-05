/**
 * Which chip a payment installment wears. `paid` and `late` come straight
 * from the server — `isLate` is PaymentInstallmentData's own derivation of
 * App\Payments\Actions\DetectOverdueInstallments's overdue condition, so a
 * second, client-side definition of "overdue" (today's browser clock vs.
 * dueOn) never has a chance to disagree with it. `due` and `upcoming` are
 * not persisted: mockup 04-auftrag distinguishes a near-term installment
 * from a further-out one purely by date, so this derives that split at
 * render time rather than adding a second persisted state
 * PaymentInstallment.status would otherwise have to track in step with the
 * calendar.
 */
export function installmentChipClass(installment: App.Payments.Data.PaymentInstallmentData): 'paid' | 'late' | 'due' | 'upcoming' {
  if (installment.status === 'paid') {
    return 'paid'
  }

  if (installment.isLate) {
    return 'late'
  }

  const daysUntilDue = (new Date(installment.dueOn).getTime() - Date.now()) / 86_400_000

  return daysUntilDue <= 14 ? 'due' : 'upcoming'
}
