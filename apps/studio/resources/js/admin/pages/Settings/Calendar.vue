<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ feedUrl: string }>()

const copied = ref(false)

function copy(url: string) {
  navigator.clipboard
    ?.writeText(url)
    .then(() => (copied.value = true))
    .catch(() => (copied.value = false))
}

function regenerate() {
  router.post('/kalender/abo', {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <div class="flex items-center gap-3 px-8 pt-[22px]">
        <h1 class="mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em]">{{ $t('calendar.subscribe') }}</h1>
      </div>

      <!-- C-13 (#249): the subscription URL is a credential — anyone holding
           it reads this person's schedule — so it stands here beside the way
           to revoke it, and not over the calendar itself. -->
      <div class="max-w-[760px] px-8 pt-7 pb-8">
        <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
          <div class="ml-auto flex min-w-0 flex-col gap-2">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('calendar.feedUrl') }}</div>
            <div class="mt-1.5 text-[17px] leading-none font-semibold tracking-[-0.018em] tabular-nums"><code>{{ feedUrl }}</code></div>
            <div class="mt-[7px]">
              <button class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]" type="button" @click="copy(feedUrl)">
                {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
              </button>
              <button class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC] ml-2" type="button" @click="regenerate()">
                {{ $t('calendar.revoke') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
