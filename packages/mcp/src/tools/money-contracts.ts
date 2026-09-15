import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult } from '../results.js'
import { silentField, writeHeaders } from './write.js'

/**
 * #402: the payments and contracts half of N-03's write surface — money and
 * paperwork, both `scope:manage_contracts` on the api surface (see
 * ProjectPolicy::managePaymentPlan() on why a plan shares the contract's own
 * capability). Split out of write.ts by domain rather than piled into it.
 *
 * There is no record_contract_signature here. SignContract's evidence —
 * typed_name, ip_address, user_agent — is the client's own browser session
 * at the moment they typed their name on the portal; a staff member calling
 * this from a chat has none of the three to supply honestly, and fabricating
 * them would misrepresent what the signature row is evidence of. A paper
 * signature stays something staff records outside this tool, same as today.
 */
export function registerMoneyContractsTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCreatePaymentPlan(server, clientFor)
  registerUpdatePaymentPlan(server, clientFor)
  registerSetPaymentReminders(server, clientFor)
  registerRecordPayment(server, clientFor)
  registerIssueInvoice(server, clientFor)
  registerCreateContract(server, clientFor)
  registerUpdateContract(server, clientFor)
  registerSendContract(server, clientFor)
  registerSetContractDeadline(server, clientFor)
  registerAddContractSigner(server, clientFor)
  registerRemoveContractSigner(server, clientFor)
  registerRemindContractSigner(server, clientFor)
}

function registerCreatePaymentPlan(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_payment_plan',
    {
      title: 'Create a payment plan',
      description:
        "Split a booked project's total into installments, due on the dates given. Amounts are in cents: 1.790 € is 179000. Refused unless the project has a Job (book_project) and an accepted quote — a plan is created against what the client actually agreed to. Even installments unless the accepted quote carries its own payment stages, in which case those decide the split.",
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

function registerCreateContract(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_contract',
    {
      title: 'Create a contract',
      description:
        "Start a new Fassung of a project's contract from a template. Renders in the background; the response may still say \"pending\" — a second read a moment later shows the finished PDF. Nothing goes to the client until send_contract.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        template: z.string().describe('Contract template public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent, ...body }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/contracts', {
        params: { path: { project } },
        body: body as never,
        headers: writeHeaders('create_contract', { project, ...body }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerUpdateContract(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_contract',
    {
      title: 'Update a contract',
      description:
        "Edit a contract's wording. A draft is edited in place; anything already sent, signed or superseded is left untouched and this opens the next Fassung instead, carrying the same signers with their signatures cleared. Re-renders in the background.",
      inputSchema: {
        contract: z.string().describe('The contract public id'),
        body: z.string().max(100000).describe('The full contract text as HTML'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ contract, silent, ...fields }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).PATCH('/contracts/{contract}', {
        params: { path: { contract } },
        body: fields as never,
        headers: writeHeaders('update_contract', { contract, ...fields }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerSendContract(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'send_contract',
    {
      title: 'Send a contract',
      description:
        "Freeze the current Fassung as the valid one and send it to the client for signature, superseding any Fassung sent before it. Refused unless the contract is still a draft. With silent, nothing goes out — for a contract already signed outside the app.",
      inputSchema: {
        contract: z.string().describe('The contract public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true },
    },
    async ({ contract, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/contracts/{contract}/send', {
        params: { path: { contract } },
        headers: writeHeaders('send_contract', { contract }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerSetContractDeadline(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_contract_deadline',
    {
      title: 'Set a contract signing deadline',
      description: 'Set or clear the date a contract should be signed by — what the "follow up before it lapses" automation hangs off.',
      inputSchema: {
        contract: z.string().describe('The contract public id'),
        sign_deadline_on: z.string().nullable().describe('YYYY-MM-DD, or null to clear it'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ contract, sign_deadline_on: signDeadlineOn, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).PATCH('/contracts/{contract}/deadline', {
        params: { path: { contract } },
        body: { sign_deadline_on: signDeadlineOn } as never,
        headers: writeHeaders('set_contract_deadline', { contract, sign_deadline_on: signDeadlineOn }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAddContractSigner(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_contract_signer',
    {
      title: 'Add a contract signer',
      description: 'Add a project contact to the list of people expected to sign the current Fassung. Only contacts already on the project qualify.',
      inputSchema: {
        contract: z.string().describe('The contract public id'),
        contact: z.string().describe('Contact public id, already on the project'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ contract, silent, ...body }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/contracts/{contract}/signers', {
        params: { path: { contract } },
        body: body as never,
        headers: writeHeaders('add_contract_signer', { contract, ...body }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerRemoveContractSigner(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'remove_contract_signer',
    {
      title: 'Remove a contract signer',
      description: 'Take a signer off a contract. Refused once they have signed — a signature is evidence, not a mistake to undo.',
      inputSchema: {
        signer: z.string().describe('The contract signer public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ signer, silent }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/contract-signers/{signer}', {
        params: { path: { signer } },
        headers: writeHeaders('remove_contract_signer', { signer }, silent),
      })

      // 204, no body — see delete_project in write.ts.
      return response.ok ? { content: [{ type: 'text', text: `Removed signer ${signer}.` }] } : apiResult(undefined, error, response)
    },
  )
}

function registerRemindContractSigner(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'remind_contract_signer',
    {
      title: 'Remind a contract signer',
      description: "Record that a signer was nudged to sign. Sends nothing — there is no client-facing contract mail in this app — this is the studio's own note of the nudge, made some other way.",
      inputSchema: {
        signer: z.string().describe('The contract signer public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ signer, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/contract-signers/{signer}/remind', {
        params: { path: { signer } },
        headers: writeHeaders('remind_contract_signer', { signer }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}
