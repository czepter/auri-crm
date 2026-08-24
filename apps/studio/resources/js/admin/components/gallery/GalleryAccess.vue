<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'

const props = defineProps<{
  gallery: { id: string; url: string; viewPassword: string | null; downloadPin: string | null; expiresAt: string | null }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
}>()

const secrets = useForm({
  view_password: props.gallery.viewPassword ?? '',
  download_pin: props.gallery.downloadPin ?? '',
})

function saveSecrets() {
  secrets.transform((data) => ({
    view_password: data.view_password === '' ? null : data.view_password,
    download_pin: data.download_pin === '' ? null : data.download_pin,
  })).patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}
</script>

<template>
  <h2 class="mt-0">{{ $t('galleryRecord.access') }}</h2>

  <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.link') }}</div>
      <div class="mt-1.5 text-[17px] leading-none font-semibold tracking-[-0.018em] tabular-nums"><a class="text-[17px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" :href="gallery.url">{{ gallery.url }}</a></div>

      <div v-if="siblings.length" class="mt-2 flex gap-1.5">
        <Link
          v-for="sibling in siblings"
          :key="sibling.id"
          class="inline-flex items-baseline gap-1.5 rounded-full px-3 py-1.5 text-[13px] no-underline"
          :class="sibling.open ? 'bg-foreground text-paper' : 'bg-sunk text-foreground'"
          :href="`/galerien/${sibling.id}`"
        >
          {{ sibling.title ?? '—' }}<span
            class="text-[11.5px]"
            :class="sibling.open ? 'text-[#B9B9BD]' : 'text-ink-2'"
          >{{ sibling.assetCount }}</span>
        </Link>
      </div>
    </div>

    <div class="flex min-w-0 flex-col gap-2">
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.gateView') }}</div>
      <Input v-model="secrets.view_password" class="w-[190px]" type="text" @blur="saveSecrets" />
    </div>

    <div class="flex min-w-0 flex-col gap-2">
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.gateDownload') }}</div>
      <Input v-model="secrets.download_pin" class="w-[110px]" type="text" @blur="saveSecrets" />
    </div>

    <div class="flex min-w-0 flex-col gap-2">
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.expiry') }}</div>
      <div class="mt-[5px] text-[15px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ day(gallery.expiresAt) }}</div>
    </div>
  </div>
</template>
