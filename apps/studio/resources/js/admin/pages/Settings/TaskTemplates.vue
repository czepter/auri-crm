<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

const props = defineProps<{
  templates: App.Crm.Data.TaskTemplateData[]
  audiences: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
  previewDate: string | null
  previewShootKind: string | null
  preview: { title: string; dueOn: string | null }[] | null
}>()

// The same disabled "bald" list Settings/Numbering.vue carries: Nummernkreise,
// Verfügbarkeit, Zahlungen and Benachrichtigungen have their own live route
// already, so only the panes still living inside Index.vue (and reachable
// only from there) stay disabled here.
const comingSoon = ['navVat', 'navDocuments', 'navGeneral'] as const

// One form per template, keyed by its id — an independent draft per row,
// matching how Settings/Numbering.vue and Settings/Availability.vue edit
// their own rows.
const forms = Object.fromEntries(
  props.templates.map((template) => [
    template.id,
    useForm({
      title: template.title,
      audience: template.audience,
      day_offset: template.dayOffset,
      shoot_kind: template.shootKind ?? '',
      is_active: template.isActive,
      sort_order: template.sortOrder,
    }),
  ]),
)

function save(id: string) {
  forms[id]
    .transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null }))
    .patch(`/einstellungen/aufgabenvorlagen/${id}`, { preserveScroll: true })
}

function remove(id: string) {
  router.delete(`/einstellungen/aufgabenvorlagen/${id}`, { preserveScroll: true })
}

const newForm = useForm({
  title: '',
  audience: 'internal',
  day_offset: 0,
  shoot_kind: '',
  is_active: true,
  sort_order: 0,
})

const adding = ref(false)

function add() {
  newForm
    .transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null }))
    .post('/einstellungen/aufgabenvorlagen', {
      preserveScroll: true,
      onSuccess: () => {
        newForm.reset()
        adding.value = false
      },
    })
}

const previewForm = useForm({
  preview_date: props.previewDate ?? '',
  preview_shoot_kind: props.previewShootKind ?? '',
})

function runPreview() {
  router.get('/einstellungen/aufgabenvorlagen', {
    preview_date: previewForm.preview_date || undefined,
    preview_shoot_kind: previewForm.preview_shoot_kind || undefined,
  }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('taskTemplates.title') }}</h1>
      </div>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav on" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('taskTemplates.previewLabel') }}</div>
          <form id="preview-form" @submit.prevent="runPreview">
            <div class="fgrid grp">
              <div class="fld">
                <Label for="preview-date">{{ $t('taskTemplates.previewDateField') }}</Label>
                <Input id="preview-date" v-model="previewForm.preview_date" type="date" />
              </div>
              <div class="fld">
                <Label for="preview-kind">{{ $t('taskTemplates.shootKindField') }}</Label>
                <Select
                  :model-value="toOption(previewForm.preview_shoot_kind)"
                  @update:model-value="(value) => (previewForm.preview_shoot_kind = fromOption(value))"
                >
                  <SelectTrigger id="preview-kind">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NONE">{{ $t('taskTemplates.shootKindAll') }}</SelectItem>
                    <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div class="formfoot">
              <Button type="submit" :disabled="!previewForm.preview_date">
                {{ $t('taskTemplates.previewButton') }}
              </Button>
            </div>
          </form>

          <div v-if="preview" class="card static" style="margin-top: var(--s4)">
            <p v-if="preview.length === 0" class="hint" style="margin: 0">{{ $t('taskTemplates.previewEmpty') }}</p>
            <div v-for="row in preview" :key="row.title" class="taskrow">
              <span class="ttext">{{ row.title }}</span>
              <span class="tdate">{{ row.dueOn ?? '—' }}</span>
            </div>
          </div>
        </div>

        <p v-if="templates.length === 0" class="hint">{{ $t('taskTemplates.empty') }}</p>

        <div v-for="template in templates" :key="template.id" class="dsec">
          <div class="dlab">{{ template.title }}</div>

          <form :id="`task-template-form-${template.id}`" @submit.prevent="save(template.id)">
            <div class="fgrid grp">
              <div class="fld wide">
                <Label :for="`title-${template.id}`">{{ $t('taskTemplates.titleField') }}</Label>
                <Input :id="`title-${template.id}`" v-model="forms[template.id].title" type="text" maxlength="255" />
                <p v-if="forms[template.id].errors.title" class="err">{{ forms[template.id].errors.title }}</p>
              </div>

              <div class="fld">
                <Label :for="`audience-${template.id}`">{{ $t('taskTemplates.audienceField') }}</Label>
                <Select
                  :model-value="forms[template.id].audience"
                  @update:model-value="(value) => (forms[template.id].audience = String(value))"
                >
                  <SelectTrigger :id="`audience-${template.id}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="fld">
                <Label :for="`offset-${template.id}`">{{ $t('taskTemplates.dayOffsetField') }}</Label>
                <Input :id="`offset-${template.id}`" v-model.number="forms[template.id].day_offset" class="tabular-nums" type="number" />
                <span class="hint">{{ $t('taskTemplates.dayOffsetHint') }}</span>
                <p v-if="forms[template.id].errors.day_offset" class="err">{{ forms[template.id].errors.day_offset }}</p>
              </div>

              <div class="fld">
                <Label :for="`kind-${template.id}`">{{ $t('taskTemplates.shootKindField') }}</Label>
                <Select
                  :model-value="toOption(forms[template.id].shoot_kind)"
                  @update:model-value="(value) => (forms[template.id].shoot_kind = fromOption(value))"
                >
                  <SelectTrigger :id="`kind-${template.id}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NONE">{{ $t('taskTemplates.shootKindAll') }}</SelectItem>
                    <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="fld">
                <Label :for="`sort-${template.id}`">{{ $t('taskTemplates.sortOrderField') }}</Label>
                <Input :id="`sort-${template.id}`" v-model.number="forms[template.id].sort_order" class="tabular-nums" type="number" min="0" />
                <p v-if="forms[template.id].errors.sort_order" class="err">{{ forms[template.id].errors.sort_order }}</p>
              </div>

              <div class="fld">
                <div class="cbrow">
                  <Checkbox :id="`active-${template.id}`" v-model="forms[template.id].is_active" />
                  <Label variant="inline" :for="`active-${template.id}`">{{ $t('taskTemplates.activeField') }}</Label>
                </div>
              </div>
            </div>

            <div class="formfoot">
              <Button
                type="submit"
                :disabled="!forms[template.id].isDirty || forms[template.id].processing"
                :style="!forms[template.id].isDirty ? 'opacity:.4' : undefined"
              >
                <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                {{ $t('taskTemplates.save') }}
              </Button>
              <span v-if="forms[template.id].isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
              <Button type="button" variant="outline" @click="remove(template.id)">{{ $t('taskTemplates.delete') }}</Button>
            </div>
          </form>
        </div>

        <!-- Every template above is an editable form of its own. A sixth
             identical form at the bottom that happens to mean "new" was the
             one thing on this screen you had to read the heading to tell
             apart; as a dialog it announces itself. -->
        <div class="dsec">
          <Dialog v-model:open="adding">
            <DialogTrigger as-child>
              <Button type="button" variant="outline">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                {{ $t('taskTemplates.add') }}
              </Button>
            </DialogTrigger>

            <DialogContent>
              <DialogHeader>
                <DialogTitle>{{ $t('taskTemplates.add') }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <form id="task-template-new-form" class="fgrid" @submit.prevent="add">
                  <div class="fld wide">
                <Label for="new-title">{{ $t('taskTemplates.titleField') }}</Label>
                <Input id="new-title" v-model="newForm.title" type="text" maxlength="255" />
                <p v-if="newForm.errors.title" class="err">{{ newForm.errors.title }}</p>
              </div>

              <div class="fld">
                <Label for="new-audience">{{ $t('taskTemplates.audienceField') }}</Label>
                <Select
                  :model-value="newForm.audience"
                  @update:model-value="(value) => (newForm.audience = String(value))"
                >
                  <SelectTrigger id="new-audience">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="fld">
                <Label for="new-offset">{{ $t('taskTemplates.dayOffsetField') }}</Label>
                <Input id="new-offset" v-model.number="newForm.day_offset" class="tabular-nums" type="number" />
                <p v-if="newForm.errors.day_offset" class="err">{{ newForm.errors.day_offset }}</p>
              </div>

              <div class="fld">
                <Label for="new-kind">{{ $t('taskTemplates.shootKindField') }}</Label>
                <Select
                  :model-value="toOption(newForm.shoot_kind)"
                  @update:model-value="(value) => (newForm.shoot_kind = fromOption(value))"
                >
                  <SelectTrigger id="new-kind">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NONE">{{ $t('taskTemplates.shootKindAll') }}</SelectItem>
                    <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="fld">
                <Label for="new-sort">{{ $t('taskTemplates.sortOrderField') }}</Label>
                <Input id="new-sort" v-model.number="newForm.sort_order" class="tabular-nums" type="number" min="0" />
                <p v-if="newForm.errors.sort_order" class="err">{{ newForm.errors.sort_order }}</p>
              </div>
                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="task-template-new-form" :disabled="newForm.processing">
                  {{ $t('taskTemplates.add') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
