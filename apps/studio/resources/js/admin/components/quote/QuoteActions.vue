<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { useI18n } from 'vue-i18n'
import { EllipsisVertical } from 'lucide-vue-next'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  canManageQuotes: boolean
}>()

const { t } = useI18n()
const page = usePage()

const packageForm = useForm({ name: '' })

function saveAsPackage() {
  packageForm.post(`/angebote/${props.quote.id}/als-paket`, {
    preserveScroll: true,
    onSuccess: () => packageForm.reset(),
  })
}

function addendum() {
  router.post(`/auftraege/${props.projectId}/angebote`, { addendum_to: props.quote.id }, { preserveScroll: true })
}

function send() {
  router.visit(`/angebote/${props.quote.id}/senden`)
}

// Die Ablehnung kommt als errors.quote, ohne Feld, an dem sie stehen könnte.
const onError = (errors: Record<string, string>) => toast.error(errors.quote)

function markSent() {
  router.post(`/angebote/${props.quote.id}/versendet`, {}, { preserveScroll: true, onError })
}

function markAccepted() {
  router.post(`/angebote/${props.quote.id}/angenommen`, {}, { preserveScroll: true, onError })
}

function duplicate() {
  router.post(`/angebote/${props.quote.id}/duplizieren`, {})
}

const deleting = ref(false)

function destroy() {
  router.delete(`/angebote/${props.quote.id}`)
}

function copyLink() {
  if (!navigator.clipboard) {
    toast.error(t('quotes.copyLinkFailed'))
    return
  }

  navigator.clipboard.writeText(props.quote.portalUrl!).then(
    () => toast.success(t('quotes.copied')),
    () => toast.error(t('quotes.copyLinkFailed')),
  )
}

type ActionKey = 'download' | 'copy_link' | 'send_by_mail'

// send_by_mail only while there is still something to send — gone the
// moment a quote reads sent, same gate the draft-only dropdown item had.
const available = computed<ActionKey[]>(() => {
  const keys: ActionKey[] = ['download', 'copy_link']

  if (props.canManageQuotes && props.quote.status === 'draft') {
    keys.push('send_by_mail')
  }

  return keys
})

// Merkt sich auf dem Nutzer (chooseLastAction), nicht im Cookie — überlebt
// Zweitgerät und Neuinstallation, wie lead_view. Passt der Merker nicht mehr
// zum Status (z. B. "senden" an einem bereits versendeten Angebot), fällt
// die Wahl auf send_by_mail zurück, solange es das noch gibt, sonst auf
// download.
const primaryKey = computed<ActionKey>(() => {
  const last = page.props.auth.user?.lastQuoteAction as ActionKey | null

  if (last && available.value.includes(last)) {
    return last
  }

  return available.value.includes('send_by_mail') ? 'send_by_mail' : 'download'
})

const dropdownKeys = computed(() => available.value.filter((key) => key !== primaryKey.value))

const labels: Record<ActionKey, string> = {
  download: t('quotes.download'),
  copy_link: t('quotes.copyLink'),
  send_by_mail: t('quotes.sendByMail'),
}

function remember(key: ActionKey) {
  router.post('/angebote/letzte-aktion', { action: key }, { preserveScroll: true, preserveState: true, only: [] })
}

function run(key: ActionKey) {
  remember(key)

  if (key === 'copy_link') copyLink()
  else if (key === 'send_by_mail') send()
  // 'download' navigates through its own href — nothing left to run.
}
</script>

<template>
  <!-- .stop: in einer Card liegen die Knöpfe in der Kopfzeile, die selbst umschaltet. -->
  <div class="ml-auto flex items-center gap-1.5">
    <Button v-if="canManageQuotes && quote.status === 'accepted'" type="button" variant="outline" @click.stop="addendum">
      {{ $t('quotes.addendum') }}
    </Button>

    <Dialog v-if="canManageQuotes">
      <DialogTrigger as-child>
        <Button variant="outline" type="button" @click.stop>{{ $t('quotes.saveAsPackage') }}</Button>
      </DialogTrigger>

      <DialogContent class="max-w-[420px]">
        <DialogHeader>
          <DialogTitle>{{ $t('quotes.saveAsPackage') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form :id="`as-package-${quote.id}`" class="flex flex-col gap-1.5" @submit.prevent="saveAsPackage">
            <Label :for="`package-name-${quote.id}`">{{ $t('quotes.packageName') }}</Label>
            <Input :id="`package-name-${quote.id}`" v-model="packageForm.name" type="text" required />
            <p v-if="packageForm.errors.name" class="text-small text-destructive">{{ packageForm.errors.name }}</p>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button type="submit" :form="`as-package-${quote.id}`" :disabled="packageForm.processing">
            {{ $t('common.save') }}
          </Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Der Splitbutton: die zuletzt genutzte der drei Aktionen vorn, die
         übrigen — plus "Versendet" im Entwurf — im Menü dahinter. -->
    <div class="flex">
      <Button
        v-if="primaryKey === 'download'"
        as="a"
        variant="pill"
        class="rounded-r-none"
        :href="`/angebote/${quote.id}/pdf`"
        target="_blank"
        @click.stop="remember('download')"
      >{{ labels.download }}</Button>
      <Button
        v-else
        type="button"
        variant="pill"
        class="rounded-r-none"
        @click.stop="run(primaryKey)"
      >{{ labels[primaryKey] }}</Button>

      <DropdownMenu>
        <DropdownMenuTrigger
          class="flex items-center rounded-r-[var(--r-sm)] border-l border-white/20 bg-action px-2 text-action-foreground"
          :aria-label="$t('quotes.moreActions')"
          @click.stop
        >
          <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem v-if="dropdownKeys.includes('download')" as-child>
            <a :href="`/angebote/${quote.id}/pdf`" target="_blank" @click="remember('download')">{{ labels.download }}</a>
          </DropdownMenuItem>
          <DropdownMenuItem v-if="dropdownKeys.includes('copy_link')" @select="run('copy_link')">
            {{ labels.copy_link }}
          </DropdownMenuItem>
          <DropdownMenuItem v-if="dropdownKeys.includes('send_by_mail')" @select="run('send_by_mail')">
            {{ labels.send_by_mail }}
          </DropdownMenuItem>
          <DropdownMenuItem v-if="canManageQuotes && quote.status === 'draft'" @select="markSent">
            {{ $t('quotes.markSent') }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>

    <DropdownMenu v-if="canManageQuotes">
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" type="button" class="rounded-[6px] px-2 hover:bg-sunk-2" :aria-label="$t('project.more')" @click.stop>
          <EllipsisVertical />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem v-if="quote.status === 'sent'" @select="markAccepted">{{ $t('quotes.markAccepted') }}</DropdownMenuItem>
        <DropdownMenuItem @select="duplicate">{{ $t('quotes.duplicate') }}</DropdownMenuItem>
        <DropdownMenuItem v-if="quote.status === 'draft'" class="text-destructive" @select="deleting = true">
          {{ $t('quotes.deleteMenu') }}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <AlertDialog v-model:open="deleting">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('quotes.deleteTitle') }}</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogAction tone="destructive" @click="destroy">{{ $t('common.delete') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
