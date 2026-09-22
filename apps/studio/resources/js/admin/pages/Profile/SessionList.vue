<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'

type Session = { id: string; device: string; ip: string | null; lastActiveAt: string; current: boolean }

const props = defineProps<{ sessions: Session[] }>()

const form = useForm({ current_password: '' })

const hasOthers = computed(() => props.sessions.some((session) => !session.current))

function lastActive(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function end(session: Session) {
  router.delete(`/profil/sitzungen/${session.id}`, { preserveScroll: true })
}

function endOthers() {
  form.delete('/profil/sitzungen', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <div class="mt-8">
    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.sessionsSection') }}</div>
    <div
      v-for="session in sessions"
      :key="session.id"
      class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
    >
      <span class="min-w-0 flex-1">
        <span class="block truncate text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em]">{{ session.device }}</span>
        <span class="mt-px block text-[12px] leading-[1.35] text-ink-2">{{ [session.ip, lastActive(session.lastActiveAt)].filter(Boolean).join(' · ') }}</span>
      </span>
      <span v-if="session.current" class="flex-none rounded-[var(--r-sm)] bg-ok-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-ok">{{ $t('profile.sessionCurrent') }}</span>
      <Button v-else type="button" variant="outline" tone="accent" @click="end(session)">{{ $t('profile.sessionEnd') }}</Button>
    </div>

    <form v-if="hasOthers" class="mt-4 flex items-end gap-3" @submit.prevent="endOthers">
      <Field :label="$t('profile.currentPassword')" id="sess-passwort" class="max-w-[300px]" :error="form.errors.current_password">
        <Input id="sess-passwort" v-model="form.current_password" type="password" autocomplete="current-password" />
      </Field>
      <Button type="submit" variant="outline" tone="accent" :disabled="form.processing">{{ $t('profile.sessionsEndOthers') }}</Button>
    </form>
  </div>
</template>
