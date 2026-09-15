import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult } from '../results.js'
import { silentField, writeHeaders } from './write.js'

/**
 * #402: the payments half of N-03's write surface, `scope:manage_contracts`
 * on the api surface (see ProjectPolicy::managePaymentPlan()). Split out of
 * write.ts by domain rather than piled into it.
 */
export function registerMoneyTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCreatePaymentPlan(server, clientFor)
  registerUpdatePaymentPlan(server, clientFor)
  registerSetPaymentReminders(server, clientFor)
  registerRecordPayment(server, clientFor)
  registerIssueInvoice(server, clientFor)
}

function registerCreatePaymentPlan(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_payment_plan',
    {
      title: 'Create a payment plan',
      description:
        "Split a booked project's total into installments, due on the dates given. Amounts are in cents: 1.790 € is 179000. Refused unless the project has a Job (book_project). Even installments unless the accepted quote carries its own payment stages, in which case those decide the split.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        total: z.number().int().min(0),
        installments: z.array(z.object({ due_on: z.string().describe('YYYY-MM-DD') })).min(1),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent, ...plan }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/payment-plan', {
        params: { path: { project } },
        body: plan as never,
        headers: writeHeaders('create_payment_plan', { project, ...plan }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

const paymentPlanInstallmentInput = z.object({
  due_on: z.string().describe('YYYY-MM-DD'),
  amount: z.number().int().min(0).describe('Cents'),
  status: z.enum(['scheduled', 'paid']),
})

function registerUpdatePaymentPlan(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_payment_plan',
    {
      title: 'Update a payment plan',
      description:
        "Replace a payment plan's total and every installment. Installments must sum to total. Refused once the plan's first installment has been invoiced — issue_invoice is a one-way door for the plan shape past that point.",
      inputSchema: {
        payment_plan: z.string().describe('The payment plan public id'),
        total: z.number().int().min(0).describe('Cents'),
        installments: z.array(paymentPlanInstallmentInput).min(1),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ payment_plan: paymentPlan, silent, ...fields }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).PATCH('/payment-plans/{paymentPlan}', {
        params: { path: { paymentPlan } },
        body: fields as never,
        headers: writeHeaders('update_payment_plan', { paymentPlan, ...fields }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerSetPaymentReminders(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_payment_reminders',
    {
      title: 'Set payment reminders',
      description: "Turn a payment plan's client reminders on or off — the one-off switch for a client already handled by phone.",
      inputSchema: {
        payment_plan: z.string().describe('The payment plan public id'),
        client_reminders_enabled: z.boolean(),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ payment_plan: paymentPlan, client_reminders_enabled: enabled, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/payment-plans/{paymentPlan}/reminders', {
        params: { path: { paymentPlan } },
        body: { client_reminders_enabled: enabled } as never,
        headers: writeHeaders('set_payment_reminders', { paymentPlan, client_reminders_enabled: enabled }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

const settledViaEnum = ['stripe', 'transfer', 'manual'] as const

function registerRecordPayment(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'record_payment',
    {
      title: 'Record a payment',
      description: 'Mark one installment paid, with the date, method and reference staff actually have in hand. Idempotent: an installment already paid is returned as-is.',
      inputSchema: {
        installment: z.string().describe('The payment installment public id'),
        paid_on: z.string().describe('YYYY-MM-DD, never in the future'),
        method: z.enum(settledViaEnum),
        reference: z.string().max(255).nullish(),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ installment, silent, ...payment }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/payment-installments/{installment}/paid', {
        params: { path: { installment } },
        body: payment as never,
        headers: writeHeaders('record_payment', { installment, ...payment }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerIssueInvoice(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'issue_invoice',
    {
      title: 'Issue an invoice',
      description:
        'Draw an invoice number for one installment and stamp it on — the Verwendungszweck the client transfers under. Immutable once issued: there is no undo, and no edit past this point. Idempotent: an already-invoiced installment is returned untouched rather than drawing a second number. Refused while the studio has neither Steuernummer nor USt-IdNr. on file (§ 14 UStG).',
      inputSchema: {
        installment: z.string().describe('The payment installment public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ installment, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/payment-installments/{installment}/invoice', {
        params: { path: { installment } },
        headers: writeHeaders('issue_invoice', { installment }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}
