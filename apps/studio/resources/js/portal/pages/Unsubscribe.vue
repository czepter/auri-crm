<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
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
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="flex items-center py-24">
      <div class="animate-rise motion-reduce:animate-none w-full max-w-[620px]">
        <p class="mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ $t('portal.unsubscribeKicker') }}</p>
        <h1 class="font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.unsubscribeTitle') }}
        </h1>

        <p class="mt-6 max-w-[52ch] text-[17px] leading-[1.55] text-muted-foreground">
          {{ unsubscribed ? $t('portal.unsubscribeDone') : $t('portal.unsubscribeHint') }}
        </p>

        <form v-if="!unsubscribed" class="mt-10" @submit.prevent="submit">
          <Button variant="portal" type="submit" :state="form.processing ? 'loading' : 'idle'" :disabled="form.processing">
            {{ $t('portal.unsubscribeConfirm') }}
          </Button>
        </form>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
