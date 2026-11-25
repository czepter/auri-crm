import { formatEuros } from '@/lib/money'
import { monthDate } from '@/admin/lib/dates'

type Row = Pick<App.Payments.Data.InvoiceRowData, 'status' | 'amount' | 'paidAmount' | 'dueOn' | 'overdueDays' | 'settledOn' | 'cancelledOn'>
type Translate = (key: string, named?: Record<string, unknown>, plural?: number) => string

export function invoiceStatusDetail(row: Row, t: Translate): string | null {
  switch (row.status) {
    case 'overdue':
      return t('invoicesOverview.detailOverdue', { amount: formatEuros(row.amount - row.paidAmount) }, row.overdueDays ?? 0)
    case 'partial':
      return t('invoicesOverview.detailPartial', { paid: formatEuros(row.paidAmount), total: formatEuros(row.amount) })
    case 'open':
      return row.dueOn ? t('invoicesOverview.detailDue', { date: monthDate(row.dueOn) }) : null
    case 'paid':
      return row.settledOn ? t('invoicesOverview.detailOn', { date: monthDate(row.settledOn) }) : null
    case 'cancelled':
      return row.cancelledOn ? t('invoicesOverview.detailOn', { date: monthDate(row.cancelledOn) }) : null
    default:
      return null
  }
}
