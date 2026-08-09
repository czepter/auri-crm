<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

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
        <label for="email">{{ $t('portal.email') }}</label>
        <input
          id="email"
          v-model="form.email"
          class="inp"
          type="email"
          autocomplete="email"
          required
        />
        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
      </div>

      <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
        {{ $t('portal.sendLink') }}
      </button>
    </form>
  </div>
</template>
