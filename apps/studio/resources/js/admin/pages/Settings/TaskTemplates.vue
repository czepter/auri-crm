<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{
  templates: App.Crm.Data.TaskTemplateData[]
  audiences: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
  previewDate: string | null
  previewShootKind: string | null
  preview: { title: string; dueOn: string | null }[] | null
}>()

// The same disabled "bald" list Settings/Numbering.vue carries: Nummernkreise,
// Verfügbarkeit and Zahlungen have their own live route already, so only the
// panes still living inside Index.vue (and reachable only from there) stay
// disabled here.
const comingSoon = ['navVat', 'navDocuments', 'navNotifications', 'navGeneral'] as const

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

function add() {
  newForm
    .transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null }))
    .post('/einstellungen/aufgabenvorlagen', {
      preserveScroll: true,
      onSuccess: () => newForm.reset(),
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
                <label for="preview-date">{{ $t('taskTemplates.previewDateField') }}</label>
                <input id="preview-date" v-model="previewForm.preview_date" class="inp" type="date" />
              </div>
              <div class="fld">
                <label for="preview-kind">{{ $t('taskTemplates.shootKindField') }}</label>
                <select id="preview-kind" v-model="previewForm.preview_shoot_kind" class="inp">
                  <option value="">{{ $t('taskTemplates.shootKindAll') }}</option>
                  <option v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                </select>
              </div>
            </div>
            <div class="formfoot">
              <button class="btn" type="submit" :disabled="!previewForm.preview_date">
                {{ $t('taskTemplates.previewButton') }}
              </button>
            </div>
          </form>
          <span class="hint" style="display: block; margin-top: var(--s3)">{{ $t('taskTemplates.previewHint') }}</span>

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
                <label :for="`title-${template.id}`">{{ $t('taskTemplates.titleField') }}</label>
                <input :id="`title-${template.id}`" v-model="forms[template.id].title" class="inp" type="text" maxlength="255" />
                <p v-if="forms[template.id].errors.title" class="err">{{ forms[template.id].errors.title }}</p>
              </div>

              <div class="fld">
                <label :for="`audience-${template.id}`">{{ $t('taskTemplates.audienceField') }}</label>
                <select :id="`audience-${template.id}`" v-model="forms[template.id].audience" class="inp">
                  <option v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</option>
                </select>
              </div>

              <div class="fld">
                <label :for="`offset-${template.id}`">{{ $t('taskTemplates.dayOffsetField') }}</label>
                <input :id="`offset-${template.id}`" v-model.number="forms[template.id].day_offset" class="inp num" type="number" />
                <span class="hint">{{ $t('taskTemplates.dayOffsetHint') }}</span>
                <p v-if="forms[template.id].errors.day_offset" class="err">{{ forms[template.id].errors.day_offset }}</p>
              </div>

              <div class="fld">
                <label :for="`kind-${template.id}`">{{ $t('taskTemplates.shootKindField') }}</label>
                <select :id="`kind-${template.id}`" v-model="forms[template.id].shoot_kind" class="inp">
                  <option value="">{{ $t('taskTemplates.shootKindAll') }}</option>
                  <option v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                </select>
              </div>

              <div class="fld">
                <label :for="`sort-${template.id}`">{{ $t('taskTemplates.sortOrderField') }}</label>
                <input :id="`sort-${template.id}`" v-model.number="forms[template.id].sort_order" class="inp num" type="number" min="0" />
                <p v-if="forms[template.id].errors.sort_order" class="err">{{ forms[template.id].errors.sort_order }}</p>
              </div>

              <div class="fld">
                <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                  <input v-model="forms[template.id].is_active" type="checkbox" />
                  {{ $t('taskTemplates.activeField') }}
                </label>
              </div>
            </div>

            <div class="formfoot">
              <button
                class="btn"
                type="submit"
                :disabled="!forms[template.id].isDirty || forms[template.id].processing"
                :style="!forms[template.id].isDirty ? 'opacity:.4' : undefined"
              >
                <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                {{ $t('taskTemplates.save') }}
              </button>
              <span v-if="forms[template.id].isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
              <button type="button" class="abtn" @click="remove(template.id)">{{ $t('taskTemplates.delete') }}</button>
            </div>
          </form>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('taskTemplates.add') }}</div>
          <form id="task-template-new-form" @submit.prevent="add">
            <div class="fgrid grp">
              <div class="fld wide">
                <label for="new-title">{{ $t('taskTemplates.titleField') }}</label>
                <input id="new-title" v-model="newForm.title" class="inp" type="text" maxlength="255" />
                <p v-if="newForm.errors.title" class="err">{{ newForm.errors.title }}</p>
              </div>

              <div class="fld">
                <label for="new-audience">{{ $t('taskTemplates.audienceField') }}</label>
                <select id="new-audience" v-model="newForm.audience" class="inp">
                  <option v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</option>
                </select>
              </div>

              <div class="fld">
                <label for="new-offset">{{ $t('taskTemplates.dayOffsetField') }}</label>
                <input id="new-offset" v-model.number="newForm.day_offset" class="inp num" type="number" />
                <p v-if="newForm.errors.day_offset" class="err">{{ newForm.errors.day_offset }}</p>
              </div>

              <div class="fld">
                <label for="new-kind">{{ $t('taskTemplates.shootKindField') }}</label>
                <select id="new-kind" v-model="newForm.shoot_kind" class="inp">
                  <option value="">{{ $t('taskTemplates.shootKindAll') }}</option>
                  <option v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                </select>
              </div>

              <div class="fld">
                <label for="new-sort">{{ $t('taskTemplates.sortOrderField') }}</label>
                <input id="new-sort" v-model.number="newForm.sort_order" class="inp num" type="number" min="0" />
                <p v-if="newForm.errors.sort_order" class="err">{{ newForm.errors.sort_order }}</p>
              </div>
            </div>
            <div class="formfoot">
              <button class="btn" type="submit" :disabled="newForm.processing">{{ $t('taskTemplates.add') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
