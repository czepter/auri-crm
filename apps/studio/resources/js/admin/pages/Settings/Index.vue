<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()

// Which pane of the section shell is showing — client-side only, the same
// second-level tab set the mockup draws as two static variants of one
// screen. Nothing here is routed: both forms already save through their own
// endpoint, so switching panes has no request to make.
const pane = ref<'profile' | 'vat'>('profile')

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
const form = useForm({ display_name: props.settings.displayName ?? '' })

// vat_rate travels the form as a percent string ("19.00") — the same euro/
// percent boundary Projects/Quotes.vue crosses with the same two functions
// — and only becomes basis points at submit time, in transform() below.
const vatForm = useForm({
  vat_mode: props.settings.vatMode,
  vat_rate: basisPointsToPercent(props.settings.vatRate),
  vat_note: props.settings.vatNote ?? '',
})

const activeForm = computed(() => (pane.value === 'vat' ? vatForm : form))

// Every other future group (Nummernkreise, Dokumente, ...) later stories
// land — rendered disabled with the "bald" chip screen 4's Vertrag tab
// already uses for a superseded quote, per T-00's mockup. Zahlungsdaten &
// USt is T-03's own pane now, so it drops out of this list.
const comingSoon = ['navNumbering', 'navDocuments', 'navPayments', 'navPortal', 'navNotifications', 'navGeneral'] as const

function submit() {
  form.patch('/einstellungen')
}

function submitVat() {
  vatForm
    .transform((data) => ({
      vat_mode: data.vat_mode,
      vat_rate: percentToBasisPoints(data.vat_rate),
      vat_note: data.vat_note || null,
    }))
    .patch('/einstellungen/ust', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ pane === 'vat' ? $t('settings.vatTitle') : $t('settings.profileTitle') }}</h1>
      </div>
      <span v-if="activeForm.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        :form="pane === 'vat' ? 'vat-form' : 'settings-form'"
        :disabled="!activeForm.isDirty || activeForm.processing"
        :style="!activeForm.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" :class="{ on: pane === 'profile' }" href="#" @click.prevent="pane = 'profile'">
          {{ $t('settings.navProfile') }}
        </a>
        <a class="nav" :class="{ on: pane === 'vat' }" href="#" @click.prevent="pane = 'vat'">
          {{ $t('settings.navVat') }}
        </a>
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

      <form v-if="pane === 'profile'" id="settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.brand') }}</div>
          <div class="grp">
            <div class="fld wide">
              <label for="display-name">{{ $t('settings.displayName') }}</label>
              <input id="display-name" v-model="form.display_name" class="inp" type="text" />
              <p v-if="form.errors.display_name" class="err">{{ form.errors.display_name }}</p>
            </div>
          </div>
        </div>
      </form>

      <form v-else id="vat-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submitVat">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.vatModeLabel') }}</div>
          <div class="grp">
            <div style="display: flex; flex-direction: column; gap: var(--s3)">
              <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                <input v-model="vatForm.vat_mode" type="radio" name="vatmode" value="standard" />
                {{ $t('settings.vatModeStandard') }}
              </label>
              <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                <input v-model="vatForm.vat_mode" type="radio" name="vatmode" value="small_business" />
                {{ $t('settings.vatModeSmallBusiness') }}
              </label>
            </div>
            <p class="hint" style="margin-top: var(--s3); color: var(--accent)">{{ $t('settings.vatModeWarning') }}</p>
          </div>
          <div class="grp">
            <div class="fld" style="max-width: 160px">
              <label for="v-satz">{{ $t('settings.vatRateLabel') }}</label>
              <input id="v-satz" v-model="vatForm.vat_rate" class="inp num" type="text" inputmode="decimal" />
              <span class="hint">{{ $t('settings.vatRateHint') }}</span>
              <p v-if="vatForm.errors.vat_rate" class="err">{{ vatForm.errors.vat_rate }}</p>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.vatNoteLabel') }}</div>
          <div class="grp">
            <div class="fld">
              <label for="v-hinweis">{{ $t('settings.vatNoteFieldLabel') }} <em>{{ $t('settings.optional') }}</em></label>
              <textarea id="v-hinweis" v-model="vatForm.vat_note" class="inp" rows="3"></textarea>
              <span class="hint">{{ $t('settings.vatNoteHint') }}</span>
              <p v-if="vatForm.errors.vat_note" class="err">{{ vatForm.errors.vat_note }}</p>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.vatPreviewLabel') }}</div>
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('settings.vatPreviewKicker') }}</span></div>
            <p v-if="vatForm.vat_note" style="margin: 0; font: 400 13px/1.5 var(--font); color: var(--ink)">
              {{ vatForm.vat_note }}
            </p>
            <p v-else class="hint" style="margin: 0">{{ $t('settings.vatPreviewEmpty') }}</p>
          </div>
          <span class="hint" style="display: block; margin-top: var(--s3)">{{ $t('settings.vatPreviewHint') }}</span>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
