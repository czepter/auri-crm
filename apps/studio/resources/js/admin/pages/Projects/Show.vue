<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import PaymentInstallmentsEditor, { type PaymentInstallmentRow } from '@/admin/components/PaymentInstallmentsEditor.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'
import { installmentChipClass } from '@/admin/lib/payments'
import { centsToEuros, eurosToCents, formatEuros } from '@/admin/lib/money'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

const { t } = useI18n()

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  paymentPlan: App.Payments.Data.PaymentPlanData | null
  notes: App.Crm.Data.InternalNoteData[]
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
  activity: { event: string; label: string; at: string; actorName: string | null }[]
}>()

const composer = useForm({ body: '' })
const editing = ref<string | null>(null)
const draft = ref('')

function shortDate(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) : null
}

const addingTask = ref(false)
const taskForm = useForm({ title: '', due_on: '', audience: 'internal', shoot: '' })

function createTask() {
  taskForm.post(`/auftraege/${props.header.id}/aufgaben`, {
    preserveScroll: true,
    onSuccess: () => {
      taskForm.reset()
      addingTask.value = false
    },
  })
}

function toggleTask(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true })
}

function write() {
  composer.post(`/auftraege/${props.header.id}/notizen`, {
    preserveScroll: true,
    onSuccess: () => composer.reset(),
  })
}

function edit(note: App.Crm.Data.InternalNoteData) {
  editing.value = note.id
  draft.value = note.body
}

function save(id: string) {
  router.patch(`/notizen/${id}`, { body: draft.value }, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
  })
}

function remove(id: string) {
  router.delete(`/notizen/${id}`, { preserveScroll: true })
}

// Its own confirm, worded to the one thing this button changes: this
// project's portal_visible, nothing about the contacts on it. Revoking a
// contact's access entirely lives on the contact screen instead — a
// deliberately separate control for a deliberately separate question.
// Hiding warns about scope, showing does not — it is the reversible half.
const portalVisibilityConfirm = computed(() =>
  props.header.portalVisible
    ? t('project.portalVisibilityHideConfirm')
    : t('project.portalVisibilityShowConfirm'),
)

const portalVisibilityLabel = computed(() =>
  props.header.portalVisible
    ? t('project.portalVisibilityHide')
    : t('project.portalVisibilityShow'),
)

function togglePortalVisibility() {
  router.post(`/auftraege/${props.header.id}/portal-sichtbarkeit`, {}, { preserveScroll: true })
}

// --- Zahlungen: create a plan when the job has none yet ------------------
// Amounts are never entered by hand here — CreatePaymentPlan splits the
// total evenly across the due dates given (App\Payments\Support\PaymentInstallmentSplitter),
// with any remainder cent landing on the last one. Editing individual
// amounts afterwards is what the edit form below is for.
const creatingPlan = ref(false)
const createPlanForm = useForm({
  total: '',
  dueDates: [''] as string[],
})

function addDueDate() {
  createPlanForm.dueDates = [...createPlanForm.dueDates, '']
}

function removeDueDate(index: number) {
  createPlanForm.dueDates = createPlanForm.dueDates.filter((_, i) => i !== index)
}

function createPlan() {
  createPlanForm
    .transform((data) => ({
      total: eurosToCents(data.total) ?? 0,
      installments: data.dueDates.map((dueOn: string) => ({ due_on: dueOn })),
    }))
    .post(`/auftraege/${props.header.id}/zahlungsplan`, {
      preserveScroll: true,
      onSuccess: () => {
        createPlanForm.reset()
        creatingPlan.value = false
      },
    })
}

// --- Zahlungen: the editable schedule once a plan exists ------------------
const editPlanForm = props.paymentPlan
  ? useForm({
      total: centsToEuros(props.paymentPlan.total),
      installments: props.paymentPlan.installments.map(
        (installment): PaymentInstallmentRow => ({
          dueOn: installment.dueOn,
          amount: centsToEuros(installment.amount),
          status: installment.status as 'scheduled' | 'paid',
        }),
      ),
    })
  : null

function savePlan() {
  editPlanForm
    ?.transform((data) => ({
      total: eurosToCents(data.total) ?? 0,
      installments: data.installments.map((installment: PaymentInstallmentRow) => ({
        due_on: installment.dueOn,
        amount: eurosToCents(installment.amount) ?? 0,
        status: installment.status,
      })),
    }))
    .patch(`/zahlungsplaene/${props.paymentPlan?.id}`, { preserveScroll: true })
}

// --- Zahlungen: T-07's staff action, date + method + reference recorded
// once — never a status flipped on its own (App\Payments\Actions\
// MarkInstallmentPaid). One open form at a time, keyed by installment id,
// the same shape the notes composer above uses for `editing`/`draft`.
const markingPaid = ref<string | null>(null)
const markPaidForm = useForm({ paid_on: '', method: 'transfer', reference: '' })

// Which instalment the dialog is standing in front of — the schedule stays
// on screen behind it, which is the point: "welche Rate war das" is answered
// by the row, not by a form that replaced it.
const markingPaidInstallment = computed(
  () => props.paymentPlan?.installments.find((i) => i.id === markingPaid.value) ?? null,
)

function openMarkPaid(installment: App.Payments.Data.PaymentInstallmentData) {
  markingPaid.value = installment.id
  markPaidForm.reset()
  markPaidForm.paid_on = new Date().toISOString().slice(0, 10)
  // The invoice number is what the client transferred under once one has
  // been issued; the plan-level reference is only still here for plans
  // created before M-06 stopped allocating it.
  markPaidForm.reference = installment.invoiceNumber ?? props.paymentPlan?.reference ?? ''
}

// --- M-06: Fällig stellen ------------------------------------------------
// A bare post, no form: issuing takes no input, and the number it allocates
// is not something the studio gets to choose.
const issuingInvoice = ref<string | null>(null)

function issueInvoice(installmentId: string) {
  issuingInvoice.value = installmentId
  router.post(`/raten/${installmentId}/rechnung`, {}, {
    preserveScroll: true,
    onFinish: () => (issuingInvoice.value = null),
  })
}

function submitMarkPaid(installmentId: string) {
  markPaidForm.post(`/raten/${installmentId}/bezahlt`, {
    preserveScroll: true,
    onSuccess: () => (markingPaid.value = null),
  })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="overview" />

    <!-- The overview is self-sufficient: narrative left, facts right. Tabs
         hold depth only. -->
    <div class="split">
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.shoots') }}</span>
            <span class="chip next">{{ $t('board.shootCount', header.shootCount) }}</span>
          </div>

          <div class="sess">
            <div
              v-for="shoot in shoots"
              :key="shoot.id"
              class="srow"
              :class="{ cancelled: shoot.isCancelled }"
            >
              <div class="stop">
                <div class="sdate">
                  {{ dayAndMonth(shoot.date) ?? '—' }}
                  <em>{{ weekday(shoot.date) ?? $t('board.openDate') }}</em>
                </div>
                <div class="sname">
                  {{ shoot.label ?? shoot.kind }}
                  <span v-if="shoot.isPrimary" class="prim">{{ $t('project.primary') }}</span>
                  <span>{{ [shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`].filter(Boolean).join(' · ') || '—' }}</span>
                </div>
                <CrewStack :crew="shoot.crew" />
                <span class="chip" :class="chipClass(shoot, header.position.value)">
                  {{ shoot.statusLabel }}
                </span>
              </div>
            </div>

            <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
          </div>

          <Link class="blkfoot" :href="`/auftraege/${header.id}/shootings`">
            {{ $t('project.toShoots') }}
          </Link>
        </div>

        <!-- Task.audience is a real field (vision §3.3), rendered as its own
             badge. It never stands in for InternalNote below, which is a
             separate model with no portal route at all. -->
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.tasks') }}</span>
            <!-- This card sits in a 296px rail. A four-field form unfolding
                 inside it squeezed the task list it belongs to down to
                 nothing; the dialog gives the form room the rail never had. -->
            <Dialog v-if="header.canManageTasks" v-model:open="addingTask">
              <DialogTrigger as-child>
                <Button type="button" variant="outline">{{ $t('project.taskAdd') }}</Button>
              </DialogTrigger>

              <DialogContent>
                <DialogHeader>
                  <DialogTitle>{{ $t('project.taskAdd') }}</DialogTitle>
                </DialogHeader>

                <DialogBody>
                  <form id="task-create-form" class="fgrid" @submit.prevent="createTask">
                    <div class="fld wide">
                      <Label for="task_title">{{ $t('project.taskTitle') }}</Label>
                      <Input id="task_title" v-model="taskForm.title" type="text" />
                      <p v-if="taskForm.errors.title" class="err">{{ taskForm.errors.title }}</p>
                    </div>
                    <div class="fld">
                      <Label for="task_due">{{ $t('project.taskDue') }}<em>{{ $t('project.optional') }}</em></Label>
                      <Input id="task_due" v-model="taskForm.due_on" type="date" />
                    </div>
                    <div class="fld">
                      <Label for="task_audience">{{ $t('project.taskAudience') }}</Label>
                      <Select
                        :model-value="taskForm.audience"
                        @update:model-value="(value) => (taskForm.audience = String(value))"
                      >
                        <SelectTrigger id="task_audience">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="internal">Intern</SelectItem>
                          <SelectItem value="client">Kunde</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div class="fld wide" v-if="shootOptions.length">
                      <Label for="task_shoot">{{ $t('project.taskShoot') }}<em>{{ $t('project.optional') }}</em></Label>
                      <Select
                        :model-value="toOption(taskForm.shoot)"
                        @update:model-value="(value) => (taskForm.shoot = fromOption(value))"
                      >
                        <SelectTrigger id="task_shoot">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem :value="NONE">{{ $t('project.taskNoShoot') }}</SelectItem>
                          <SelectItem v-for="option in shootOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </form>
                </DialogBody>

                <DialogFooter>
                  <Button type="submit" form="task-create-form" :disabled="taskForm.processing">
                    {{ $t('common.save') }}
                  </Button>
                  <DialogClose as-child>
                    <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                  </DialogClose>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>

          <div class="tlist">
            <div v-for="task in tasks" :key="task.id" class="taskrow">
              <!-- Completion is a toggle, not a delete: what was owed and when
                   it was done is the record. -->
              <Button
                v-if="header.canManageTasks"
                type="button" variant="outline"
                :aria-label="$t(task.isDone ? 'project.taskReopen' : 'project.taskComplete')"
                @click="toggleTask(task)"
              >
                {{ task.isDone ? '✓' : '○' }}
              </Button>
              <span class="ttext" :style="task.isDone ? 'color: var(--faint); text-decoration: line-through' : ''">
                {{ task.title }}
                <template v-if="task.shootLabel"> · {{ task.shootLabel }}</template>
              </span>
              <span class="tdate" :style="task.isOverdue ? 'color: var(--late)' : ''">
                {{ task.dueOn ? shortDate(task.dueOn) : '—' }}
              </span>
              <span class="taudience" :class="task.audience">{{ task.audienceLabel }}</span>
            </div>
          </div>

          <p v-if="!tasks.length" class="hint">{{ $t('project.noTasks') }}</p>
        </div>

        <!-- Its own model, never a flag on a client-visible message. Nothing
             here has a portal route, and nothing here may acquire one. -->
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.notes') }}</span>
            <span class="chip next">{{ $t('project.notesInternalOnly') }}</span>
          </div>

          <div v-for="note in notes" :key="note.id" class="note">
            <div class="head">
              <b>{{ note.author }}</b>
              <span>{{ new Date(note.writtenAt).toLocaleDateString('de-DE') }}</span>
              <span v-if="header.canManageNotes" class="acts">
                <Button type="button" variant="outline" @click="edit(note)">
                  {{ $t('project.noteEdit') }}
                </Button>
                <Button type="button" variant="outline" @click="remove(note.id)">
                  {{ $t('project.noteDelete') }}
                </Button>
              </span>
            </div>

            <Textarea
              v-if="editing === note.id"
              v-model="draft"
              rows="3"
              @keydown.escape="editing = null" />
            <p v-else>{{ note.body }}</p>

            <div v-if="editing === note.id" class="formfoot">
              <Button type="button" @click="save(note.id)">{{ $t('common.save') }}</Button>
            </div>
          </div>

          <form v-if="header.canManageNotes" class="composer" @submit.prevent="write">
            <Textarea
              v-model="composer.body"
              rows="2"
              :placeholder="$t('project.notePlaceholder')" />
            <p v-if="composer.errors.body" class="err">{{ composer.errors.body }}</p>
            <div class="formfoot" style="margin-top: 0">
              <Button type="submit" :disabled="composer.processing">
                {{ $t('common.save') }}
              </Button>
            </div>
          </form>
        </div>
      </div>

      <!-- ---------------- right: facts ---------------- -->
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead"><span class="kick">{{ $t('project.booking') }}</span></div>
          <div class="tiles">
            <div class="fact">
              <div class="l">{{ $t('project.mainDateLabel') }}</div>
              <div class="v">
                {{ header.primaryDate
                    ? new Date(header.primaryDate).toLocaleDateString('de-DE')
                    : $t('board.openDate') }}
              </div>
              <div class="s">{{ header.type }}</div>
            </div>
            <div class="fact">
              <div class="l">{{ $t('project.position') }}</div>
              <div class="v">{{ header.position.label }}</div>
              <div class="s">{{ header.isBooked ? $t('project.booked') : $t('project.notBooked') }}</div>
            </div>
            <div :class="['fact', { ghost: !header.portalVisible }]">
              <div class="l">{{ $t('project.portalStatus') }}</div>
              <div class="v">
                {{ header.portalVisible ? $t('project.portalVisible') : $t('project.portalHidden') }}
              </div>
            </div>
          </div>

          <AlertDialog v-if="header.canManagePortalVisibility">
            <AlertDialogTrigger as-child>
              <Button type="button" variant="outline" style="margin-top: var(--s3)">
                {{ portalVisibilityLabel }}
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>{{ portalVisibilityConfirm }}</AlertDialogTitle>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogAction @click="togglePortalVisibility">{{ portalVisibilityLabel }}</AlertDialogAction>
                <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>

        <div class="perf"></div>

        <div class="feedlab">{{ $t('project.activity') }}</div>
        <div class="feed">
          <div v-for="(event, index) in activity" :key="index" class="ev">
            <div class="dot"></div>
            <div>
              <div class="t">{{ event.label }}</div>
              <div class="w">
                {{ new Date(event.at).toLocaleString('de-DE', {
                     day: '2-digit', month: 'short', year: 'numeric',
                     hour: '2-digit', minute: '2-digit',
                   }) }}
                <template v-if="event.actorName"> · {{ event.actorName }}</template>
              </div>
            </div>
          </div>
        </div>

        <!-- Zahlungen: a preview compressed into the Übersicht tab, mockup
             04-auftrag's own vocabulary (.paylist/.payrow/.paystatus) — no
             Zahlungen tab of its own, see the note on ProjectHeaderData.
             A project without a Job has no plan (M-03), so this waits for
             one to exist. -->
        <div v-if="header.isBooked" class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('payments.title') }}</span>
            <span v-if="paymentPlan" class="chip now">{{ $t('payments.open', { amount: formatEuros(paymentPlan.openTotal) }) }}</span>
          </div>

          <template v-if="paymentPlan">
            <div class="paylist">
              <div v-for="installment in paymentPlan.installments" :key="installment.id" class="payrow">
                <span class="payname">{{ installment.label }}</span>
                <span class="val">{{ formatEuros(installment.amount) }}</span>
                <span class="paystatus" :class="installmentChipClass(installment)">
                  {{ installment.status === 'paid' ? $t('payments.statusPaid') : dayAndMonth(installment.dueOn) }}
                </span>
                <Button
                  v-if="installment.status !== 'paid' && header.canManagePaymentPlan && markingPaid !== installment.id"
                  type="button" variant="ghost"
                  @click="openMarkPaid(installment)"
                >
                  {{ $t('payments.markPaid') }}
                </Button>
                <span v-else-if="installment.status === 'paid' && installment.settledViaLabel" class="hint">
                  {{ $t('payments.settledVia', { method: installment.settledViaLabel, date: installment.paidOn }) }}
                </span>

                <!-- M-06: the row S-09 deferred. "Fällig stellen" until the
                     invoice exists, then its number, then its PDF once the
                     render has returned — never a link that would 404. -->
                <Button
                  v-if="!installment.invoiceNumber && header.canManagePaymentPlan"
                  type="button" variant="ghost"
                  :disabled="issuingInvoice === installment.id"
                  @click="issueInvoice(installment.id)"
                >
                  {{ $t('payments.issueInvoice') }}
                </Button>
                <a
                  v-else-if="installment.hasInvoiceDocument"
                  class="hint" :href="`/raten/${installment.id}/rechnung`" target="_blank"
                >{{ installment.invoiceNumber }}</a>
                <span v-else-if="installment.invoiceNumber" class="hint">
                  {{ $t('payments.invoiceRendering', { number: installment.invoiceNumber }) }}
                </span>
              </div>
            </div>

            <template v-if="header.canManagePaymentPlan && paymentPlan.isEditable && editPlanForm">
              <div class="dsec">
                <div class="dlab">{{ $t('payments.installments') }}</div>
                <PaymentInstallmentsEditor v-model="editPlanForm.installments" />
              </div>

              <form class="fgrid" @submit.prevent="savePlan">
                <div class="fld">
                  <Label for="plan-total">{{ $t('payments.total') }}</Label>
                  <Input id="plan-total" v-model="editPlanForm.total" class="tabular-nums" type="text" inputmode="decimal" required />
                </div>
                <p v-if="editPlanForm.errors.installments" class="err">{{ editPlanForm.errors.installments }}</p>
                <div class="fld wide">
                  <div class="formfoot" style="margin-top: 0">
                    <Button type="submit" :disabled="editPlanForm.processing">
                      {{ $t('common.save') }}
                    </Button>
                  </div>
                </div>
              </form>
            </template>
            <p v-else-if="header.canManagePaymentPlan" class="hint">{{ $t('payments.notEditable') }}</p>
          </template>

          <template v-else>
            <p class="hint">{{ $t('payments.empty') }}</p>

            <!-- The due dates are a repeatable row, so this form has no fixed
                 height — the one case in this file that needs DialogBody's
                 scroll rather than just its room. -->
            <Dialog v-if="header.canManagePaymentPlan" v-model:open="creatingPlan">
              <DialogTrigger as-child>
                <Button type="button" variant="outline">{{ $t('payments.create') }}</Button>
              </DialogTrigger>

              <DialogContent>
                <DialogHeader>
                  <DialogTitle>{{ $t('payments.create') }}</DialogTitle>
                </DialogHeader>

                <DialogBody>
                  <form id="plan-create-form" class="fgrid" @submit.prevent="createPlan">
                    <div class="fld">
                        <Label for="plan-create-total">{{ $t('payments.total') }}</Label>
                        <Input
                          id="plan-create-total"
                          v-model="createPlanForm.total" class="tabular-nums"
                          type="text"
                          inputmode="decimal"
                          required />
                      </div>
                      <div class="fld wide">
                        <Label>{{ $t('payments.dueOn') }}</Label>
                        <div
                          v-for="(dueDate, index) in createPlanForm.dueDates"
                          :key="index"
                          class="hrow"
                          style="gap: var(--s3); margin-top: var(--s2)"
                        >
                          <Input v-model="createPlanForm.dueDates[index]" type="date" required style="max-width: 200px" />
                          <Button
                            v-if="createPlanForm.dueDates.length > 1"
                            type="button" variant="outline" tone="accent"
                            @click="removeDueDate(index)"
                          >
                            {{ $t('common.remove') }}
                          </Button>
                        </div>
                        <Button type="button" variant="outline" style="margin-top: var(--s2)" @click="addDueDate">
                          {{ $t('payments.addInstallment') }}
                        </Button>
                      </div>
                  </form>
                </DialogBody>

                <DialogFooter>
                  <Button type="submit" form="plan-create-form" :disabled="createPlanForm.processing">
                    {{ $t('common.save') }}
                  </Button>
                  <DialogClose as-child>
                    <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                  </DialogClose>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </template>
        </div>
      </div>
    </div>

    <!-- One dialog for the whole schedule, keyed by markingPaid — the form was
         previously injected into the row itself, which pushed every later
         instalment down while you were reading them against each other. -->
    <Dialog
      :open="markingPaid !== null"
      @update:open="(open) => { if (!open) markingPaid = null }"
    >
      <DialogContent class="max-w-[480px]">
        <DialogHeader>
          <DialogTitle>{{ $t('payments.markPaid') }}</DialogTitle>
          <DialogDescription v-if="markingPaidInstallment">
            {{ markingPaidInstallment.label }} · {{ formatEuros(markingPaidInstallment.amount) }}
          </DialogDescription>
        </DialogHeader>

        <DialogBody>
          <form
            v-if="markingPaid"
            id="mark-paid-form"
            class="fgrid"
            @submit.prevent="submitMarkPaid(markingPaid)"
          >
            <div class="fld">
              <Label for="mp-date">{{ $t('payments.markPaidDate') }}</Label>
              <Input id="mp-date" v-model="markPaidForm.paid_on" type="date" required />
            </div>
            <div class="fld">
              <Label for="mp-method">{{ $t('payments.markPaidMethod') }}</Label>
              <Select
                :model-value="markPaidForm.method"
                @update:model-value="(value) => (markPaidForm.method = String(value))"
              >
                <SelectTrigger id="mp-method">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="transfer">{{ $t('payments.markPaidMethodTransfer') }}</SelectItem>
                  <SelectItem value="stripe">{{ $t('payments.markPaidMethodStripe') }}</SelectItem>
                  <SelectItem value="manual">{{ $t('payments.markPaidMethodManual') }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="fld wide">
              <Label for="mp-ref">{{ $t('payments.markPaidReference') }}</Label>
              <Input id="mp-ref" v-model="markPaidForm.reference" type="text" />
            </div>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button type="submit" form="mark-paid-form" :disabled="markPaidForm.processing">
            {{ $t('payments.markPaidSubmit') }}
          </Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
