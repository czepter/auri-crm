<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { CopyButton } from '@/ui/copy-button'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{
  gallery: {
    id: string
    url: string
    viewPassword: string | null
    requiresVerifiedEmail: boolean
    isExternal: boolean
    listedOnHome: boolean
    homePinned: boolean
    homeBlocker: string | null
    homeMode: 'none' | 'redirect' | 'overview'
  }
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

const home = useForm({ listed_on_home: props.gallery.listedOnHome, home_pinned: props.gallery.homePinned })

function saveHome() {
  home.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}
</script>

<template>
  <h2 class="mt-0">{{ $t('galleryRecord.access') }}</h2>

  <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
      <div class="text-small text-ink-2">{{ $t('galleryRecord.link') }}</div>
      <div class="mt-1.5 flex items-center gap-2.5">
        <a class="text-title tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline" :href="gallery.url">{{ gallery.url }}</a>
        <CopyButton :value="gallery.url" />
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

  <template v-if="!gallery.isExternal">
    <div class="mt-3 flex items-center gap-2.5">
      <Checkbox id="cb-home-listed" v-model="home.listed_on_home" @update:model-value="saveHome" />
      <Label variant="inline" for="cb-home-listed">{{ $t('galleryRecord.listedOnHome') }}</Label>
      <template v-if="home.listed_on_home">
        <Link
          v-if="gallery.homeMode !== 'overview'"
          class="text-small text-ink-2 no-underline hover:underline"
          href="/galerien?einstellungen=startseite"
        >{{ $t('galleryRecord.homeOff') }} →</Link>
        <Chip v-else-if="gallery.homeBlocker" tone="warn">
          {{ $t('galleryRecord.homeHidden', { reason: $t(`galleryRecord.homeBlocker.${gallery.homeBlocker}`) }) }}
        </Chip>
        <Chip v-else tone="success">{{ $t('galleryRecord.homeListed') }}</Chip>
      </template>
    </div>
    <div class="mt-3 flex items-center gap-2.5">
      <Checkbox id="cb-home-pinned" v-model="home.home_pinned" :disabled="!home.listed_on_home" @update:model-value="saveHome" />
      <Label variant="inline" for="cb-home-pinned">{{ $t('galleryRecord.homePinned') }}</Label>
    </div>
  </template>
</template>
