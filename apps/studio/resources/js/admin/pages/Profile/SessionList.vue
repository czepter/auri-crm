<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import FormSection from '@/ui/page/FormSection.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import Chip from '@/ui/page/Chip.vue'

type Session = { id: string; ids: string[]; count: number; device: string; ip: string | null; lastActiveAt: string; current: boolean }

const props = defineProps<{ sessions: Session[] }>()

const form = useForm({ current_password: '' })

const hasOthers = computed(() => props.sessions.some((session) => !session.current))

function lastActive(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function end(session: Session) {
  router.delete(`/profil/sitzungen/${session.id}`, { data: { ids: session.ids }, preserveScroll: true })
}

function endOthers() {
  form.delete('/profil/sitzungen', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <FormSection :title="$t('profile.sessionsSection')">
    <div
      v-for="session in sessions"
      :key="session.id"
      class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
    >
      <span class="min-w-0 flex-1">
        <span class="block truncate text-ui leading-[1.35] font-semibold tracking-[-0.015em]">{{ session.count > 1 ? `${session.device} (${session.count}×)` : session.device }}</span>
        <span class="mt-px block text-small leading-[1.35] text-ink-2">{{ [session.ip, lastActive(session.lastActiveAt)].filter(Boolean).join(' · ') }}</span>
      </span>
      <Chip v-if="session.current" tone="success">{{ $t('profile.sessionCurrent') }}</Chip>
      <Button v-else type="button" variant="outline" tone="accent" @click="end(session)">{{ $t('profile.sessionEnd') }}</Button>
    </div>

    <form v-if="hasOthers" class="mt-4 flex items-end gap-3" @submit.prevent="endOthers">
      <Field :label="$t('profile.currentPassword')" id="sess-passwort" class="max-w-[300px]" :error="form.errors.current_password">
        <Input id="sess-passwort" v-model="form.current_password" type="password" autocomplete="current-password" />
      </Field>
      <Button type="submit" variant="outline" tone="accent" :disabled="form.processing">{{ $t('profile.sessionsEndOthers') }}</Button>
    </form>
  </FormSection>
</template>
