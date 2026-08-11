/**
 * Which chip a payment installment wears. `paid` is the persisted status;
 * `due` and `upcoming` are not — mockup 04-auftrag distinguishes a
 * near-term installment from a further-out one purely by date, so this
 * derives the split at render time rather than adding a second persisted
 * state PaymentInstallment.status would otherwise have to track in step
 * with the calendar.
 */
export function installmentChipClass(installment: App.Payments.Data.PaymentInstallmentData): 'paid' | 'due' | 'upcoming' {
  if (installment.status === 'paid') {
    return 'paid'
  }

  const daysUntilDue = (new Date(installment.dueOn).getTime() - Date.now()) / 86_400_000

  return daysUntilDue <= 14 ? 'due' : 'upcoming'
}
