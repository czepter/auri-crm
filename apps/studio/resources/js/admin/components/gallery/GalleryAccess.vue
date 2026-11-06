<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Button } from '@/ui/button'

const props = defineProps<{
  gallery: { id: string; url: string; viewPassword: string | null; requiresVerifiedEmail: boolean }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
}>()

const secret = useForm({ view_password: props.gallery.viewPassword ?? '' })

function saveSecret() {
  secret.transform((data) => ({
    view_password: data.view_password === '' ? null : data.view_password,
  })).patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const emailGate = useForm({ requires_verified_email: props.gallery.requiresVerifiedEmail })

function saveEmailGate() {
  emailGate.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const copied = ref(false)

function copy() {
  navigator.clipboard
    ?.writeText(props.gallery.url)
    .then(() => (copied.value = true))
    .catch(() => (copied.value = false))
}
</script>

<template>
  <h2 class="mt-0">{{ $t('galleryRecord.access') }}</h2>

  <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
      <div class="text-small text-ink-2">{{ $t('galleryRecord.link') }}</div>
      <div class="mt-1.5 flex items-center gap-2.5">
        <a class="text-title tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline" :href="gallery.url">{{ gallery.url }}</a>
        <Button type="button" variant="pill-muted" @click="copy">{{ copied ? $t('gallery.copied') : $t('gallery.copy') }}</Button>
      </div>

      <div v-if="siblings.length" class="mt-2 flex gap-1.5">
        <Link
          v-for="sibling in siblings"
          :key="sibling.id"
          class="inline-flex items-baseline gap-1.5 rounded-[var(--r-sm)] px-3 py-1.5 text-small no-underline"
          :class="sibling.open ? 'bg-paper font-medium text-foreground shadow-[inset_0_0_0_1px_var(--faint)]' : 'bg-sunk text-foreground'"
          :href="`/galerien/${sibling.id}`"
        >
          {{ sibling.title ?? '—' }}<span
            class="text-small"
            :class="sibling.open ? 'text-faint' : 'text-ink-2'"
          >{{ sibling.assetCount }}</span>
        </Link>
      </div>
    </div>

    <div class="flex min-w-0 flex-col gap-2">
      <div class="text-small text-ink-2">{{ $t('gallery.gateView') }}</div>
      <Input v-model="secret.view_password" class="w-[190px]" type="text" @blur="saveSecret" />
    </div>
  </div>

  <div class="flex items-center gap-2.5">
    <Checkbox id="cb-email-gate" v-model="emailGate.requires_verified_email" @update:model-value="saveEmailGate" />
    <Label variant="inline" for="cb-email-gate">{{ $t('galleryRecord.emailGate') }}</Label>
  </div>
</template>
