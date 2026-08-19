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
  <div data-tokens class="mx-auto max-w-[420px] px-8 py-16">
    <span
      class="mb-11 flex items-baseline gap-2.25 text-[19px] leading-[1.1] font-bold tracking-[-0.035em] text-foreground"
    >
      <i class="size-[7px] flex-none rounded-full bg-action" />{{ $page.props.brand }}
    </span>

    <h1 class="mb-3.5 text-[24px] leading-none font-bold tracking-[-0.04em]">
      {{ $t('portal.signInTitle') }}
    </h1>
    <!-- No password, ever. The client proves an inbox and nothing else. -->
    <p class="mb-8 text-[14px] leading-[1.5] font-normal text-muted-foreground">{{ $t('portal.signInHint') }}</p>

    <!-- The same sentence whatever was typed: known address, unknown address
         or one that has asked too often. -->
    <p v-if="status" class="rounded-md border border-border bg-muted p-5 text-[14px] leading-[1.5] font-normal text-foreground">{{ status }}</p>

    <form v-else @submit.prevent="submit">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label for="email">{{ $t('portal.email') }}</Label>
        <Input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
        />
        <p v-if="form.errors.email" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.email }}</p>
      </div>

      <Button class="w-full justify-center" type="submit" :disabled="form.processing">
        {{ $t('portal.sendLink') }}
      </Button>
    </form>
  </div>

  <!-- § 5 TMG: reachable from the one screen a client can see before signing in. -->
  <PortalFooter />
</template>
