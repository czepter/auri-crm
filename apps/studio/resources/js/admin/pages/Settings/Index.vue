<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
const form = useForm({ display_name: props.settings.displayName ?? '' })

// Every other future group (Nummernkreise, Dokumente, ...) T-02/T-03 and
// later stories land — rendered disabled with the "bald" chip screen 4's
// Vertrag tab already uses for a superseded quote, per T-00's mockup.
// Zahlungsdaten & USt sits in that list too: T-03 hasn't landed a route
// behind it yet, so it stays disabled beside the rest until it does, rather
// than linking to a screen this story cannot build.
const comingSoon = [
  'navVat',
  'navNumbering',
  'navDocuments',
  'navPayments',
  'navPortal',
  'navNotifications',
  'navGeneral',
] as const

function submit() {
  form.patch('/einstellungen')
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('settings.profileTitle') }}</h1>
      </div>
      <span v-if="form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        form="settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav on" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
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
        <form id="settings-form" @submit.prevent="submit">
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
      </div>
    </div>
  </AdminLayout>
</template>
