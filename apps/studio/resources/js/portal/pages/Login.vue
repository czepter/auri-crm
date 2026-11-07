<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'

const form = useForm({ email: '' })
const status = computed(() => usePage().props.flash.status)

function submit() {
  form.post('/anmelden', { onSuccess: () => form.reset() })
}
</script>

<template>
  <PortalShell>
    <!-- Nobody is signed in, so portalNav is null and the bar carries the
         wordmark alone. -->
    <PortalHeader />

    <PortalWrap class="flex items-center py-24">
      <div class="animate-rise motion-reduce:animate-none w-full max-w-[440px]">
        <h1 class="font-serif text-[clamp(38px,5.4vw,56px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.signInTitle') }}
        </h1>

        <!-- The same sentence whatever was typed: known address, unknown
             address or one that has asked too often. -->
        <p v-if="status" class="mt-10 rounded-md bg-sunk p-6 text-[15px] leading-[1.6]">{{ status }}</p>

        <form v-else class="mt-10 grid gap-6" @submit.prevent="submit">
          <PortalField :label="$t('portal.email')">
            <Input v-model="form.email" type="email" autocomplete="email" required />
            <span v-if="form.errors.email" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.email }}</span>
          </PortalField>

          <Button class="justify-self-start" variant="portal" type="submit" :state="form.processing ? 'loading' : 'idle'" :disabled="form.processing">
            {{ $t('portal.sendLink') }}
          </Button>
        </form>
      </div>
    </PortalWrap>

    <!-- § 5 TMG: reachable from the one screen a client can see before signing in. -->
    <PortalFooter />
  </PortalShell>
</template>
