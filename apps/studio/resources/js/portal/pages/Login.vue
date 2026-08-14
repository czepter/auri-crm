<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ email: '' })
const status = computed(() => usePage().props.flash.status)

function submit() {
  form.post('/anmelden', { onSuccess: () => form.reset() })
}
</script>

<template>
  <div class="pgate">
    <span class="pmark" style="margin-bottom: var(--s6)">
      <i />Atelier Nordlicht <em>Fotografie · Hamburg</em>
    </span>

    <h1>{{ $t('portal.signInTitle') }}</h1>
    <!-- No password, ever. The client proves an inbox and nothing else. -->
    <p class="lede">{{ $t('portal.signInHint') }}</p>

    <!-- The same sentence whatever was typed: known address, unknown address
         or one that has asked too often. -->
    <p v-if="status" class="sent">{{ status }}</p>

    <form v-else @submit.prevent="submit">
      <div class="fld grp">
        <Label for="email">{{ $t('portal.email') }}</Label>
        <Input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
        />
        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
      </div>

      <Button class="w-full justify-center" type="submit" :disabled="form.processing">
        {{ $t('portal.sendLink') }}
      </Button>
    </form>
  </div>

  <!-- § 5 TMG: reachable from the one screen a client can see before signing in. -->
  <PortalFooter />
</template>
