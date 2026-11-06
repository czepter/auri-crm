<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
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
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  tokens: { id: string; name: string; scopes: string[]; createdAt: string; lastUsedAt: string | null }[]
  capabilities: { value: string; label: string }[]
  mcpUrl: string
}>()
useLiveProps(['tokens'])

const secret = computed(() => usePage().props.flash?.secret as string | undefined)
const copied = ref<string | null>(null)

function copy(value: string) {
  navigator.clipboard
    ?.writeText(value)
    .then(() => (copied.value = value))
    .catch(() => (copied.value = null))
}

// The connection signs in through the studio's own OAuth approval
// (mcp-claude-verbinden.md), so each client needs the address and nothing else.
const clients = computed(() => [
  { name: 'Claude', setup: props.mcpUrl },
  { name: 'Claude Code', setup: `claude mcp add --transport http studio ${props.mcpUrl}` },
  { name: 'Cursor', setup: JSON.stringify({ mcpServers: { studio: { url: props.mcpUrl } } }, null, 2) },
])

const form = useForm({ name: '', scopes: [] as string[] })

function toggleScope(value: string, on: boolean | 'indeterminate') {
  form.scopes = on === true ? [...form.scopes, value] : form.scopes.filter((s) => s !== value)
}

function create() {
  form.post('/einstellungen/api-token', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      copied.value = null
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
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('apiTokens.title')"
    >
      <template #nav><SettingsNav active="api-tokens" /></template>

      <div class="min-w-0">
        <div v-if="secret" class="mb-8">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('apiTokens.secret') }}</div>
          <code class="block break-all text-ui tabular-nums">{{ secret }}</code>
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('apiTokens.secretHint') }}</span>
          <div class="mt-2">
            <Button type="button" variant="pill-muted" @click="copy(secret)">
              {{ copied === secret ? $t('gallery.copied') : $t('gallery.copy') }}
            </Button>
          </div>
        </div>

        <div class="mb-8">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">MCP</div>
          <div
            v-for="client in clients"
            :key="client.name"
            class="flex items-start gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
          >
            <div class="min-w-0 flex-1">
              <div class="text-ui font-semibold">{{ client.name }}</div>
              <pre class="m-0 mt-1 text-small leading-[1.5] break-all whitespace-pre-wrap text-ink-2"><code>{{ client.setup }}</code></pre>
            </div>
            <Button type="button" variant="pill-muted" @click="copy(client.setup)">
              {{ copied === client.setup ? $t('gallery.copied') : $t('gallery.copy') }}
            </Button>
          </div>
          <Link class="mt-2.5 inline-block text-small" href="/hilfe/mcp-claude-verbinden">{{ $t('apiTokens.mcpHelp') }}</Link>
        </div>

        <form id="api-token-form" @submit.prevent="create">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('apiTokens.new') }}</div>
          <div class="mb-6 flex min-w-0 flex-col gap-1.5">
            <Label for="api-token-name">{{ $t('apiTokens.name') }}</Label>
            <Input id="api-token-name" v-model="form.name" type="text" maxlength="255" />
            <p v-if="form.errors.name" class="text-small leading-[1.45] text-destructive">{{ form.errors.name }}</p>
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
            <p v-if="form.errors.scopes" class="text-small leading-[1.45] text-destructive">{{ form.errors.scopes }}</p>
          </div>
          <Button type="submit" :disabled="form.processing">{{ $t('apiTokens.create') }}</Button>
        </form>
      </div>

      <template #aside>
        <Button v-if="!tokens.length" variant="outline" as-child>
          <Link href="/hilfe/mcp-claude-verbinden">{{ $t('apiTokens.mcpHelp') }}</Link>
        </Button>
        <h2 v-else class="mb-2 text-ui font-semibold">{{ $t('apiTokens.clients') }}</h2>
        <div
          v-for="token in tokens"
          :key="token.id"
          class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
        >
          <div class="min-w-0 flex-1">
            <div class="truncate text-ui font-semibold">{{ token.name }}</div>
            <div class="text-small leading-[1.45] text-ink-2">
              {{ $t('apiTokens.lastUsed') }}: {{ token.lastUsedAt ? $d(new Date(token.lastUsedAt)) : $t('apiTokens.never') }}
            </div>
            <div class="text-small leading-[1.45] text-faint">{{ token.scopes.map((s) => labels[s] ?? s).join(', ') }}</div>
          </div>
          <Button type="button" variant="outline" @click="revoking = token.id; confirmingRevoke = true">
            {{ $t('apiTokens.revoke') }}
          </Button>
        </div>
      </template>
    </FormShell>

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
