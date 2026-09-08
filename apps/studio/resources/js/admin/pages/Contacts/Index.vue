<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { groupContacts } from '@/admin/lib/contactQueue'

const props = defineProps<{ contacts: App.Crm.Data.ContactRowData[] }>()

// C-15 (#251): grouped by standing, not alphabetically. Who the studio is
// working with right now is the question this screen is opened with.
const groups = computed(() => groupContacts(props.contacts))

const newForm = useForm({ name: '', email: '', phone: '' })

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */

/* crm.css .hrow und die Ueberschrift, die darin steht. */

/* crm.css .btn — die gefuellte Aktion. */
</script>

<template>
  <AdminLayout active="kontakte">
    <div data-tokens>
      <PageHead :title="$t('contacts.title')">
        <Dialog>
          <DialogTrigger as-child>
            <Button class="ml-auto" variant="pill" type="button">{{ $t('contacts.create') }}</Button>
          </DialogTrigger>

          <DialogContent>
            <DialogHeader>
              <DialogTitle>{{ $t('contacts.create') }}</DialogTitle>
            </DialogHeader>

            <DialogBody>
              <form id="contact-create-form" class="grid grid-cols-2 gap-x-6 gap-y-4" @submit.prevent="newForm.post('/kontakte')">
                <Field wide :label="$t('contacts.name')" :error="newForm.errors.name" v-slot="{ id }">
                  <Input :id v-model="newForm.name" type="text" />
                </Field>
                <Field :error="newForm.errors.email" v-slot="{ id }">
                  <Label :for="id">{{ $t('contacts.email') }} <em>{{ $t('settings.optional') }}</em></Label>
                  <Input :id v-model="newForm.email" type="email" />
                </Field>
                <Field :error="newForm.errors.phone" v-slot="{ id }">
                  <Label :for="id">{{ $t('contacts.phone') }} <em>{{ $t('settings.optional') }}</em></Label>
                  <Input :id v-model="newForm.phone" type="tel" />
                </Field>
                <span class="col-span-full text-[11.5px] leading-[1.45] text-faint">{{ $t('leads.noEmailHint') }}</span>
              </form>
            </DialogBody>

            <DialogFooter>
              <Button type="submit" form="contact-create-form" :disabled="newForm.processing">
                {{ $t('contacts.create') }}
              </Button>
              <DialogClose as-child>
                <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </PageHead>

      <Table v-if="contacts.length">
        <thead>
          <tr>
            <TableHead>{{ $t('contacts.colName') }}</TableHead>
            <TableHead>{{ $t('contacts.colReach') }}</TableHead>
            <TableHead>{{ $t('contacts.colProjects') }}</TableHead>
            <TableHead align="right">{{ $t('contacts.colNext') }}</TableHead>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groups" :key="group.key">
            <TableGroupRow :colspan="4">{{ $t(`contacts.group.${group.key}`) }}</TableGroupRow>
            <TableRow v-for="contact in group.rows" :key="contact.id">
              <TableCell tone="name"><Link :href="`/kontakte/${contact.id}`">{{ contact.name }}</Link></TableCell>
              <TableCell tone="muted">{{ [contact.email, contact.phone].filter(Boolean).join(' · ') || '—' }}</TableCell>
              <TableCell tone="muted">
                {{ contact.latestProject ?? '—' }}
                <template v-if="contact.projectCount > 1"> · {{ $t('contacts.projectCount', contact.projectCount) }}</template>
              </TableCell>
              <TableCell align="right">{{ contact.nextDate ? day(contact.nextDate) : '—' }}</TableCell>
            </TableRow>
          </template>
        </tbody>
      </Table>

      <Button v-else :as="Link" variant="pill" class="mt-5" href="/anfragen/neu">
        {{ $t('leads.createKicker') }}
      </Button>
    </div>
  </AdminLayout>
</template>
