<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormSection from '@/ui/page/FormSection.vue'
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
import { CopyButton } from '@/ui/copy-button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  tokens: { id: string; name: string; scopes: string[]; createdAt: string; lastUsedAt: string | null }[]
  capabilities: { value: string; label: string }[]
  connections: { id: string; clientId: string; name: string | null; redirectUris: string[]; lastUsedAt: string | null }[]
  mcpUrl: string
}>()
useLiveProps(['tokens', 'connections'])

const secret = computed(() => usePage().props.flash?.secret as string | undefined)
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

const editingToken = ref<string | null>(null)

function editToken(token: (typeof props.tokens)[number]) {
  editingToken.value = token.id
  form.name = token.name
  form.scopes = [...token.scopes]
  form.clearErrors()
}

function resetToken() {
  editingToken.value = null
  form.reset()
  form.clearErrors()
}

function create() {
  const options = { preserveScroll: true, onSuccess: resetToken }
  if (editingToken.value) form.patch(`/einstellungen/api-token/${editingToken.value}`, options)
  else form.post('/einstellungen/api-token', options)
}

const connectionForm = useForm({ client_name: '', redirect_uris: '' })
const editing = ref<string | null>(null)

function editConnection(connection: (typeof props.connections)[number]) {
  editing.value = connection.id
  connectionForm.client_name = connection.name ?? ''
  connectionForm.redirect_uris = connection.redirectUris.join('\n')
  connectionForm.clearErrors()
}

function resetConnection() {
  editing.value = null
  connectionForm.reset()
  connectionForm.clearErrors()
}

function saveConnection() {
  const base = '/einstellungen/api-token/verbindungen'
  const form = connectionForm.transform((data) => ({
    client_name: data.client_name,
    redirect_uris: data.redirect_uris.split('\n').map((uri) => uri.trim()).filter(Boolean),
  }))
  const options = { preserveScroll: true, onSuccess: resetConnection }
  if (editing.value) form.patch(`${base}/${editing.value}`, options)
  else form.post(base, options)
}

const removing = ref<string | null>(null)
const confirmingRemove = ref(false)

function removeConnection() {
  if (removing.value) router.delete(`/einstellungen/api-token/verbindungen/${removing.value}`, { preserveScroll: true })
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
        <FormSection v-if="secret" :title="$t('apiTokens.secret')">
          <code class="block break-all text-ui tabular-nums">{{ secret }}</code>
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('apiTokens.secretHint') }}</span>
          <div class="mt-2">
            <CopyButton :value="secret" />
          </div>
        </FormSection>

        <FormSection title="MCP">
          <div
            v-for="client in clients"
            :key="client.name"
            class="flex items-start gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
          >
            <div class="min-w-0 flex-1">
              <div class="text-ui font-semibold">{{ client.name }}</div>
              <pre class="m-0 mt-1 text-small leading-[1.5] break-all whitespace-pre-wrap text-ink-2"><code>{{ client.setup }}</code></pre>
            </div>
            <CopyButton :value="client.setup" />
          </div>
          <Link class="mt-2.5 inline-block text-small" href="/hilfe/mcp-claude-verbinden">{{ $t('apiTokens.mcpHelp') }}</Link>
        </FormSection>

        <FormSection :title="$t('apiTokens.connections')">
          <div
            v-for="connection in connections"
            :key="connection.id"
            class="flex items-start gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
          >
            <div class="min-w-0 flex-1">
              <div class="truncate text-ui font-semibold">{{ connection.name ?? connection.clientId }}</div>
              <div class="mt-1 flex items-center gap-2 text-small leading-[1.45] text-ink-2">
                <code class="break-all">{{ connection.clientId }}</code>
                <CopyButton :value="connection.clientId" />
              </div>
              <div class="text-small leading-[1.45] break-all text-faint">{{ connection.redirectUris.join(', ') }}</div>
              <div class="text-small leading-[1.45] text-ink-2">
                {{ $t('apiTokens.lastUsed') }}: {{ connection.lastUsedAt ? $d(new Date(connection.lastUsedAt)) : $t('apiTokens.never') }}
              </div>
            </div>
            <Button type="button" variant="outline" @click="editConnection(connection)">{{ $t('common.edit') }}</Button>
            <Button type="button" variant="outline" @click="removing = connection.id; confirmingRemove = true">
              {{ $t('common.delete') }}
            </Button>
          </div>
          <form class="mt-4" @submit.prevent="saveConnection">
            <div class="mb-6 flex min-w-0 flex-col gap-1.5">
              <Label for="connection-name">{{ $t('apiTokens.name') }}</Label>
              <Input id="connection-name" v-model="connectionForm.client_name" type="text" maxlength="255" />
              <p v-if="connectionForm.errors.client_name" class="text-small leading-[1.45] text-destructive">{{ connectionForm.errors.client_name }}</p>
            </div>
            <div class="mb-6 flex min-w-0 flex-col gap-1.5">
              <Label for="connection-uris">{{ $t('apiTokens.redirectUris') }}</Label>
              <Textarea id="connection-uris" v-model="connectionForm.redirect_uris" rows="3" />
              <p
                v-for="(message, key) in connectionForm.errors"
                :key="key"
                class="text-small leading-[1.45] text-destructive"
              >{{ message }}</p>
            </div>
            <div class="flex justify-end gap-2">
              <Button v-if="editing" type="button" variant="outline" @click="resetConnection">{{ $t('common.cancel') }}</Button>
              <Button type="submit" :disabled="connectionForm.processing">
                {{ editing ? $t('common.save') : $t('apiTokens.create') }}
              </Button>
            </div>
          </form>
        </FormSection>

        <FormSection :title="editingToken ? $t('apiTokens.edit') : $t('apiTokens.new')">
        <form id="api-token-form" @submit.prevent="create">
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
          <div class="flex justify-end gap-2">
            <Button v-if="editingToken" type="button" variant="outline" @click="resetToken">{{ $t('common.cancel') }}</Button>
            <Button type="submit" :disabled="form.processing">{{ editingToken ? $t('common.save') : $t('apiTokens.create') }}</Button>
          </div>
        </form>
        </FormSection>
      </div>

      <template #aside>
        <div class="rounded-[var(--r)] bg-paper px-5 py-4 shadow-card">
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
          <Button type="button" variant="outline" @click="editToken(token)">{{ $t('common.edit') }}</Button>
          <Button type="button" variant="outline" @click="revoking = token.id; confirmingRevoke = true">
            {{ $t('apiTokens.revoke') }}
          </Button>
        </div>
        </div>
      </template>
    </FormShell>

    <AlertDialog v-model:open="confirmingRemove">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('apiTokens.removeTitle') }}</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogAction class="bg-destructive" @click="removeConnection">{{ $t('common.delete') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

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
