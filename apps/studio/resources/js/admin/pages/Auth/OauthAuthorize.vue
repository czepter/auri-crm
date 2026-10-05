<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Button } from '@/ui/button'

// N-04: no mockup exists for this screen (nothing OAuth-shaped in
// mockups/admin/) — a minimal functional consent screen, styled off the
// same .authwrap/.authcard shell Login.vue and MfaChallenge.vue already use,
// rather than a bespoke design pass for a screen this app has exactly one of.
const props = defineProps<{
  clientId: string
  clientName: string
  redirectHost: string
  redirectUri: string
  codeChallenge: string
  state: string | null
  scope: string
  scopes: { value: string; label: string }[]
}>()

const form = useForm({
  client_id: props.clientId,
  redirect_uri: props.redirectUri,
  code_challenge: props.codeChallenge,
  scope: props.scope,
  state: props.state,
  decision: 'approve' as 'approve' | 'deny',
})

function submit(decision: 'approve' | 'deny') {
  form.decision = decision
  form.post('/oauth/authorize')
}
</script>

<template>
  <AuthLayout :title="$t('auth.oauthTitle')">
      <p class="mb-4 text-small leading-[1.45] font-normal text-faint">{{ $t('auth.oauthHint', { client: clientName }) }}</p>

      <ul class="mt-0 mb-4 pl-4">
        <li v-for="item in scopes" :key="item.value">{{ item.label }}</li>
      </ul>

      <p class="mb-6 text-small leading-[1.45] font-normal text-faint">
        {{ $t('auth.oauthTarget', { host: redirectHost }) }}
      </p>

      <div class="flex gap-2.5">
        <Button
          type="button"
          class="flex-1 justify-center"
          :disabled="form.processing"
          @click="submit('approve')"
        >
          {{ $t('auth.oauthApprove') }}
        </Button>
        <Button variant="outline"
          type="button"
          class="flex-1 justify-center"
          :disabled="form.processing"
          @click="submit('deny')"
        >
          {{ $t('auth.oauthDeny') }}
        </Button>
      </div>
  </AuthLayout>
</template>
