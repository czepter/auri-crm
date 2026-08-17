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
    <div data-theme="crm">
      <div class="hrow">
        <h1>{{ $t('calendar.subscribe') }}</h1>
      </div>

      <!-- C-13 (#249): the subscription URL is a credential — anyone holding
           it reads this person's schedule — so it stands here beside the way
           to revoke it, and not over the calendar itself. -->
      <div class="sheet">
        <div class="access">
          <div class="col wide">
            <div class="lab">{{ $t('calendar.feedUrl') }}</div>
            <div class="val"><code>{{ feedUrl }}</code></div>
            <div class="act">
              <button class="btn-2" type="button" @click="copy(feedUrl)">
                {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
              </button>
              <button class="btn-2" type="button" style="margin-left: var(--s2)" @click="regenerate()">
                {{ $t('calendar.revoke') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
