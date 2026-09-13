<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'

const props = defineProps<{
  projectId: string
  quote: App.Quotes.Data.QuoteData
  canManageQuotes: boolean
}>()

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
  router.post(`/angebote/${props.quote.id}/versenden`, {}, { preserveScroll: true })
}

function markSent() {
  router.post(`/angebote/${props.quote.id}/versendet`, {}, { preserveScroll: true })
}

function copyLink(portalUrl: string) {
  navigator.clipboard?.writeText(portalUrl)
}
</script>

<template>
  <!-- .stop: in einer Card liegen die Knöpfe in der Kopfzeile, die selbst umschaltet. -->
  <div class="ml-auto flex items-center gap-1.5">
    <Button as="a" variant="outline" :href="`/angebote/${quote.id}/pdf`" target="_blank" @click.stop>
      {{ $t('quotes.download') }}
    </Button>

    <!-- Q-06 sets this the moment a quote is sent; the draft dropdown below
         had it, but only while canManageQuotes and status === draft — gone
         from view the moment a quote reads sent, exactly where it matters
         most. Portal-visibility is the one real gate: no visible link, no
         page for it to open. -->
    <Button v-if="quote.portalUrl" type="button" variant="outline" @click.stop="copyLink(quote.portalUrl!)">
      {{ $t('quotes.copyLink') }}
    </Button>

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
            <p v-if="packageForm.errors.name" class="text-[12px] text-destructive">{{ packageForm.errors.name }}</p>
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

    <div v-if="canManageQuotes && quote.status === 'draft'" class="flex">
      <Button type="button" variant="pill" class="rounded-r-none" @click.stop="send">
        {{ $t('quotes.sendByMail') }}
      </Button>
      <DropdownMenu>
        <DropdownMenuTrigger
          class="flex items-center rounded-r-[var(--r-pill)] border-l border-white/20 bg-foreground px-2 text-paper"
          :aria-label="$t('quotes.sendOptions')"
          @click.stop
        >
          <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem @select="markSent">{{ $t('quotes.markSent') }}</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </div>
</template>
