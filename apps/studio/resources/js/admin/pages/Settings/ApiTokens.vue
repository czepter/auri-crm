<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/ui/alert-dialog'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  tokens: { id: string; name: string; scopes: string[]; createdAt: string }[]
  capabilities: { value: string; label: string }[]
}>()

const secret = computed(() => usePage().props.flash?.secret as string | undefined)
const copied = ref(false)

function copy(value: string) {
  navigator.clipboard
    ?.writeText(value)
    .then(() => (copied.value = true))
    .catch(() => (copied.value = false))
}

const form = useForm({ name: '', scopes: [] as string[] })

function toggleScope(value: string, on: boolean | 'indeterminate') {
  form.scopes = on === true ? [...form.scopes, value] : form.scopes.filter((s) => s !== value)
}

function create() {
  form.post('/einstellungen/api-token', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      copied.value = false
    },
  })
}

const labels = computed(() => Object.fromEntries(props.capabilities.map((c) => [c.value, c.label])))

// Kept apart from the open state: AlertDialogAction closes the dialog before its @click runs.
const revoking = ref<string | null>(null)
const confirmingRevoke = ref(false)

function revoke() {
  if (revoking.value) router.delete(`/einstellungen/api-token/${revoking.value}`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('apiTokens.title')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="api-tokens" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <div v-if="secret" class="mb-8">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('apiTokens.secret') }}</div>
                <code class="block break-all text-[14px] tabular-nums">{{ secret }}</code>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('apiTokens.secretHint') }}</span>
                <div class="mt-2">
                  <Button type="button" variant="pill-muted" @click="copy(secret)">
                    {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
                  </Button>
                </div>
              </div>

              <div v-if="tokens.length" class="mb-8">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('apiTokens.active') }}</div>
                <div
                  v-for="token in tokens"
                  :key="token.id"
                  class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
                >
                  <div class="min-w-0 flex-1">
                    <div class="text-[14px] font-semibold">{{ token.name }}</div>
                    <div class="text-[11.5px] leading-[1.45] text-faint">
                      {{ token.scopes.map((s) => labels[s] ?? s).join(', ') }} · {{ $d(new Date(token.createdAt)) }}
                    </div>
                  </div>
                  <Button
                    type="button"
                    variant="outline"
                    @click="revoking = token.id; confirmingRevoke = true"
                  >
                    {{ $t('apiTokens.revoke') }}
                  </Button>
                </div>
              </div>

              <form id="api-token-form" @submit.prevent="create">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('apiTokens.new') }}</div>
                <div class="mb-6 flex min-w-0 flex-col gap-1.5">
                  <Label for="api-token-name">{{ $t('apiTokens.name') }}</Label>
                  <Input id="api-token-name" v-model="form.name" type="text" maxlength="255" />
                  <p v-if="form.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="mb-6">
                  <Label>{{ $t('apiTokens.scopes') }}</Label>
                  <div class="mt-2 grid grid-cols-2 gap-x-6 gap-y-2.5 max-[640px]:grid-cols-1">
                    <div v-for="capability in capabilities" :key="capability.value" class="flex items-center gap-2.5">
                      <Checkbox
                        :id="`scope-${capability.value}`"
                        :model-value="form.scopes.includes(capability.value)"
                        @update:model-value="toggleScope(capability.value, $event)"
                      />
                      <Label variant="inline" :for="`scope-${capability.value}`">{{ capability.label }}</Label>
                    </div>
                  </div>
                  <p v-if="form.errors.scopes" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.scopes }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">{{ $t('apiTokens.create') }}</Button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </Page>

    <AlertDialog v-model:open="confirmingRevoke">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('apiTokens.revokeTitle') }}</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogAction class="bg-destructive" @click="revoke">{{ $t('apiTokens.revoke') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AdminLayout>
</template>
