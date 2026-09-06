/*
 * Q-11's distribution rule, for the figure the client watches move while the
 * slider does. App\Payments\Support\PaymentStageSplitter is the authority —
 * it is what actually writes the installments at booking, and it is the one
 * the database's own trigger holds to account. This is the same arithmetic
 * so the rail does not show one split and the invoice another.
 */
export type Stage = { label: string; percent: number; minimum: number }

const ROUNDING = 1000

export function splitStages(total: number, stages: Stage[]): { label: string; amount: number }[] {
  let rest = total
  const last = stages.length - 1

  return stages
    .map((stage, index) => {
      const amount =
        index === last
          ? Math.max(0, rest)
          : Math.min(Math.max(roundToTenEuros((total * stage.percent) / 100), stage.minimum), Math.max(0, rest))

      rest -= amount

      return { label: stage.label, amount }
    })
    .filter((row) => row.amount > 0)
}

function roundToTenEuros(minorUnits: number): number {
  return Math.round(minorUnits / ROUNDING) * ROUNDING
}
