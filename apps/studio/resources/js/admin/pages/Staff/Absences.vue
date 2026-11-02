<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { DatePicker } from '@/ui/date-picker'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  absences: App.Data.AbsenceData[]
  canManageCrew: boolean
  staff: App.Data.StaffProfileData[]
}>()
useLiveProps(['absences', 'canManageCrew', 'staff'])

// One form per row, keyed by its id — an independent draft per row, the same
// pattern Closures/Index.vue's rows use.
const rowForms = Object.fromEntries(
  props.absences.map((absence) => [
    absence.id,
    useForm({ starts_on: absence.startsOn, ends_on: absence.endsOn }),
  ]),
)

function save(id: string) {
  rowForms[id].patch(`/abwesenheiten/${id}`, { preserveScroll: true })
}

function destroy(id: string) {
  router.delete(`/abwesenheiten/${id}`, { preserveScroll: true })
}

// No reason and no kind — see AbsenceCategory's own doc comment for the
// first, and the studio's ruling for the second: away is away.
const me = (usePage().props.auth as { user: { id: string } | null }).user?.id ?? ''
const newForm = useForm({ user: me, starts_on: '', ends_on: '' })
const adding = ref(false)

function add() {
  newForm.post('/abwesenheiten', {
    preserveScroll: true,
    onSuccess: () => {
      newForm.reset()
      adding.value = false
    },
  })
}
</script>

<template>
  <AdminLayout active="abwesenheiten">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('absences.title')">
          <!-- The add form used to sit permanently at the foot of the page, below
               however many absences the list held — you scrolled past everyone
               else's holiday to book your own. As a dialog it moves to where the
               other list screens keep their create action: the header. -->
          <Dialog v-model:open="adding">
            <DialogTrigger as-child>
              <Button class="ml-auto" variant="pill" type="button">{{ $t('absences.add') }}</Button>
            </DialogTrigger>

            <DialogContent>
              <DialogHeader>
                <DialogTitle>{{ $t('absences.add') }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <form id="absence-create-form" class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4" @submit.prevent="add">
                  <div v-if="canManageCrew && staff.length > 1" class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="new-user">{{ $t('absences.person') }}</Label>
                    <Select :model-value="newForm.user" @update:model-value="(value) => (newForm.user = String(value))">
                      <SelectTrigger id="new-user">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-starts">{{ $t('absences.startsOn') }}</Label>
                    <DatePicker id="new-starts" v-model="newForm.starts_on" />
                    <p v-if="newForm.errors.starts_on" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.starts_on }}</p>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-ends">{{ $t('absences.endsOn') }}</Label>
                    <DatePicker id="new-ends" v-model="newForm.ends_on" />
                    <p v-if="newForm.errors.ends_on" class="text-small leading-[1.45] text-destructive">{{ newForm.errors.ends_on }}</p>
                  </div>
                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="absence-create-form" :disabled="newForm.processing">
                  {{ $t('absences.add') }}
                </Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </PageHead>

        <Empty v-if="!absences.length" class="mx-8 mt-7" :text="$t('absences.empty')">
          <Button variant="pill" type="button" @click="adding = true">{{ $t('absences.add') }}</Button>
        </Empty>

        <div v-else class="max-w-[900px] px-6 pt-7 pb-8">
          <div
            v-for="absence in absences"
            :key="absence.id"
            class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <span v-if="!$page.props.soloStudio" class="w-44 flex-none text-ui leading-[1.35] font-semibold tracking-[-0.015em]">{{ absence.userName }}</span>

            <!-- Whose absence it is decides whether the row is a reading or a
                 control: the dates are editable where the account may manage
                 them, and a sentence where it may not. -->
            <span v-if="!absence.canManage" class="text-small leading-[1.47] text-ink-2">
              {{ absence.startsOn }} – {{ absence.endsOn }}
            </span>

            <form v-else class="flex min-w-0 flex-1 flex-col gap-1" @submit.prevent="save(absence.id)">
              <div class="flex items-center gap-2">
                <Label class="sr-only" :for="`starts-${absence.id}`">{{ $t('absences.startsOn') }}</Label>
                <DatePicker :id="`starts-${absence.id}`" v-model="rowForms[absence.id].starts_on" size="sm" class="w-36" />
                <Label class="sr-only" :for="`ends-${absence.id}`">{{ $t('absences.endsOn') }}</Label>
                <DatePicker :id="`ends-${absence.id}`" v-model="rowForms[absence.id].ends_on" size="sm" class="w-36" />

                <!-- Die rechte Kante gehört dem Umschlag, nicht den Knöpfen:
                     verschwindet Speichern, bleibt Löschen stehen, statt an
                     dessen Stelle zu rutschen. Ein ml-auto an beiden ginge
                     nicht — Flex teilte den freien Raum unter ihnen auf. -->
                <div class="ml-auto flex items-center gap-2">
                  <Button
                    v-if="rowForms[absence.id].isDirty"
                    variant="pill"
                    type="submit"
                    :disabled="rowForms[absence.id].processing"
                  >
                    {{ $t('absences.save') }}
                  </Button>
                  <Button variant="pill-muted" type="button" @click="destroy(absence.id)">
                    {{ $t('absences.delete') }}
                  </Button>
                </div>
              </div>

              <p
                v-for="message in [rowForms[absence.id].errors.starts_on, rowForms[absence.id].errors.ends_on].filter(Boolean)"
                :key="message"
                class="text-small leading-[1.45] text-destructive"
              >
                {{ message }}
              </p>
            </form>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
