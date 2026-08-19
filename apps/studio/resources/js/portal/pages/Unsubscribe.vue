<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

// T-11: reached from a link in a mail already delivered — see QuoteSent's
// own footer line — never from portal navigation. `contact` is the public_id
// this screen's own route is keyed on, carried only so the form below posts
// back to the same one; nothing here displays it.
const props = defineProps<{
  contact: string
  unsubscribed: boolean
}>()

const form = useForm({})

function submit() {
  form.post(`/abmelden/${props.contact}`)
}
</script>

<template>
  <PortalHeader />

  <PortalWrap>
    <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.unsubscribeKicker') }}</div>
    <h1>{{ $t('portal.unsubscribeTitle') }}</h1>

    <p v-if="unsubscribed">{{ $t('portal.unsubscribeDone') }}</p>
    <template v-else>
      <p>{{ $t('portal.unsubscribeHint') }}</p>
      <form @submit.prevent="submit">
        <Button type="submit" :disabled="form.processing">
          {{ $t('portal.unsubscribeConfirm') }}
        </Button>
      </form>
    </template>
  </PortalWrap>

  <PortalFooter />
</template>
