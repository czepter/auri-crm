<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

// N-04: no mockup exists for this screen (nothing OAuth-shaped in
// mockups/admin/) — a minimal functional consent screen, styled off the
// same .authwrap/.authcard shell Login.vue and MfaChallenge.vue already use,
// rather than a bespoke design pass for a screen this app has exactly one of.
const props = defineProps<{
  redirectUri: string
  codeChallenge: string
  state: string | null
  scope: string
  scopes: { value: string; label: string }[]
}>()

const form = useForm({
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
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('auth.oauthTitle') }}</h1>
      <p class="hint" style="margin-bottom: var(--s4)">{{ $t('auth.oauthHint') }}</p>

      <ul style="margin: 0 0 var(--s5); padding-left: var(--s4)">
        <li v-for="item in scopes" :key="item.value">{{ item.label }}</li>
      </ul>

      <div style="display: flex; gap: var(--s3)">
        <Button
          type="button"
          style="flex: 1; justify-content: center"
          :disabled="form.processing"
          @click="submit('approve')"
        >
          {{ $t('auth.oauthApprove') }}
        </Button>
        <Button variant="outline"
          type="button"
          style="flex: 1; justify-content: center"
          :disabled="form.processing"
          @click="submit('deny')"
        >
          {{ $t('auth.oauthDeny') }}
        </Button>
      </div>
    </div>
  </div>
</template>
