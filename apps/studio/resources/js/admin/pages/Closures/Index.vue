<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  closures: App.Settings.Data.StudioClosureData[]
}>()
useLiveProps(['closures'])

const rowForms = Object.fromEntries(
  props.closures.map((closure) => [
    closure.id,
    useForm({ label: closure.label, starts_on: closure.startsOn, ends_on: closure.endsOn, message: closure.message ?? '' }),
  ]),
)

function save(id: string) {
  rowForms[id].patch(`/schliesszeiten/${id}`, { preserveScroll: true })
}

function destroy(id: string) {
  router.delete(`/schliesszeiten/${id}`, { preserveScroll: true })
}

const newForm = useForm({ label: '', starts_on: '', ends_on: '', message: '' })
const adding = ref(false)

function add() {
  newForm.post('/schliesszeiten', {
    preserveScroll: true,
    onSuccess: () => {
      newForm.reset()
      adding.value = false
    },
  })
}
</script>

<template>
  <AdminLayout active="schliesszeiten">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('settings.closuresLabel')">
          <Dialog v-model:open="adding">
            <DialogTrigger as-child>
              <Button class="ml-auto" variant="pill" type="button">{{ $t('settings.closureAdd') }}</Button>
            </DialogTrigger>

            <DialogContent>
              <DialogHeader>
                <DialogTitle>{{ $t('settings.closureAdd') }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <form id="closure-create-form" class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4" @submit.prevent="add">
                  <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="new-label">{{ $t('settings.closureLabelField') }}</Label>
                    <Input id="new-label" v-model="newForm.label" type="text" maxlength="255" />
                    <p v-if="newForm.errors.label" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.label }}</p>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-starts">{{ $t('settings.closureStartsOn') }}</Label>
                    <DatePicker id="new-starts" v-model="newForm.starts_on" />
                    <p v-if="newForm.errors.starts_on" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.starts_on }}</p>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-ends">{{ $t('settings.closureEndsOn') }}</Label>
                    <DatePicker id="new-ends" v-model="newForm.ends_on" />
                    <p v-if="newForm.errors.ends_on" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.ends_on }}</p>
                  </div>
                  <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="new-message">{{ $t('settings.closureMessageField') }} <em>{{ $t('settings.optional') }}</em></Label>
                    <Textarea id="new-message" v-model="newForm.message" rows="2" maxlength="2000" />
                    <p v-if="newForm.errors.message" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.message }}</p>
                  </div>
                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="closure-create-form" :disabled="newForm.processing">
                  {{ $t('settings.closureAdd') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </PageHead>

        <Empty v-if="!closures.length" class="mx-8 mt-7" :text="$t('settings.closuresEmpty')">
          <Button variant="pill" type="button" @click="adding = true">{{ $t('settings.closureAdd') }}</Button>
        </Empty>

        <div v-else class="max-w-[900px] px-6 pt-7 pb-8">
          <form
            v-for="closure in closures"
            :key="closure.id"
            class="flex flex-col gap-2 py-3 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
            @submit.prevent="save(closure.id)"
          >
            <div class="flex items-center gap-2">
              <Label class="sr-only" :for="`label-${closure.id}`">{{ $t('settings.closureLabelField') }}</Label>
              <Input :id="`label-${closure.id}`" v-model="rowForms[closure.id].label" class="min-w-0 flex-1" type="text" maxlength="255" />
              <Label class="sr-only" :for="`starts-${closure.id}`">{{ $t('settings.closureStartsOn') }}</Label>
              <DatePicker :id="`starts-${closure.id}`" v-model="rowForms[closure.id].starts_on" size="sm" class="w-36" />
              <Label class="sr-only" :for="`ends-${closure.id}`">{{ $t('settings.closureEndsOn') }}</Label>
              <DatePicker :id="`ends-${closure.id}`" v-model="rowForms[closure.id].ends_on" size="sm" class="w-36" />

              <div class="flex items-center gap-2">
                <Button v-if="rowForms[closure.id].isDirty" variant="pill" type="submit" :disabled="rowForms[closure.id].processing">
                  {{ $t('settings.numberingSave') }}
                </Button>
                <Button variant="pill-muted" type="button" @click="destroy(closure.id)">
                  {{ $t('settings.closureDelete') }}
                </Button>
              </div>
            </div>

            <Label class="sr-only" :for="`message-${closure.id}`">{{ $t('settings.closureMessageField') }}</Label>
            <Input
              :id="`message-${closure.id}`"
              v-model="rowForms[closure.id].message"
              type="text"
              maxlength="2000"
              :placeholder="$t('settings.closureMessageField')"
            />

            <p
              v-for="message in Object.values(rowForms[closure.id].errors).filter(Boolean)"
              :key="message"
              class="text-small leading-[1.45] text-destructive"
            >
              {{ message }}
            </p>
          </form>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
