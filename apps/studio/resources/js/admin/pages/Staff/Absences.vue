<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { DatePicker } from '@/ui/date-picker'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

const props = defineProps<{
  absences: App.Data.AbsenceData[]
  canManageCrew: boolean
  staff: App.Data.StaffProfileData[]
  categories: { value: string; label: string }[]
}>()

// One form per row, keyed by its id — an independent draft per row, the same
// pattern Settings/Availability.vue's closures editor already uses.
const rowForms = Object.fromEntries(
  props.absences.map((absence) => [
    absence.id,
    useForm({ category: absence.category, starts_on: absence.startsOn, ends_on: absence.endsOn }),
  ]),
)

function save(id: string) {
  rowForms[id].patch(`/abwesenheiten/${id}`, { preserveScroll: true })
}

function destroy(id: string) {
  router.delete(`/abwesenheiten/${id}`, { preserveScroll: true })
}

// No reason field — see AbsenceCategory's own doc comment. 'user' stays
// unset for the ordinary case: recording your own time away names nobody,
// and AbsenceController falls back to the signed-in account.
const newForm = useForm({ user: '', category: props.categories[0]?.value ?? '', starts_on: '', ends_on: '' })
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
                  <div v-if="canManageCrew" class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="new-user">{{ $t('absences.person') }}</Label>
                    <Select
                      :model-value="toOption(newForm.user)"
                      @update:model-value="(value) => (newForm.user = fromOption(value))"
                    >
                      <SelectTrigger id="new-user">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem :value="NONE">{{ $t('absences.myself') }}</SelectItem>
                        <SelectItem v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                    <Label for="new-category">{{ $t('absences.category') }}</Label>
                    <Select :model-value="newForm.category" @update:model-value="(value) => (newForm.category = String(value))">
                      <SelectTrigger id="new-category">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="category in categories" :key="category.value" :value="category.value">
                          {{ category.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-starts">{{ $t('absences.startsOn') }}</Label>
                    <DatePicker id="new-starts" v-model="newForm.starts_on" />
                    <p v-if="newForm.errors.starts_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ newForm.errors.starts_on }}</p>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="new-ends">{{ $t('absences.endsOn') }}</Label>
                    <DatePicker id="new-ends" v-model="newForm.ends_on" />
                    <p v-if="newForm.errors.ends_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ newForm.errors.ends_on }}</p>
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

        <div v-if="absences.length" class="max-w-[900px] px-8 pt-7 pb-8">
          <div
            v-for="absence in absences"
            :key="absence.id"
            class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <span class="w-44 flex-none text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em]">{{ absence.userName }}</span>

            <!-- Whose absence it is decides whether the row is a reading or a
                 control: the dates are editable where the account may manage
                 them, and a sentence where it may not. -->
            <span v-if="!absence.canManage" class="text-[12.5px] leading-[1.47] text-ink-2">
              {{ absence.categoryLabel }} · {{ absence.startsOn }} – {{ absence.endsOn }}
            </span>

            <form v-else class="flex min-w-0 flex-1 flex-col gap-1" @submit.prevent="save(absence.id)">
              <div class="flex items-center gap-2">
                <Label class="sr-only" :for="`category-${absence.id}`">{{ $t('absences.category') }}</Label>
                <Select
                  :model-value="rowForms[absence.id].category"
                  @update:model-value="(value) => (rowForms[absence.id].category = String(value))"
                >
                  <SelectTrigger :id="`category-${absence.id}`" size="sm" class="w-40">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="category in categories" :key="category.value" :value="category.value">
                      {{ category.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>

                <Label class="sr-only" :for="`starts-${absence.id}`">{{ $t('absences.startsOn') }}</Label>
                <DatePicker :id="`starts-${absence.id}`" v-model="rowForms[absence.id].starts_on" size="sm" class="w-36" />
                <Label class="sr-only" :for="`ends-${absence.id}`">{{ $t('absences.endsOn') }}</Label>
                <DatePicker :id="`ends-${absence.id}`" v-model="rowForms[absence.id].ends_on" size="sm" class="w-36" />

                <Button
                  class="ml-auto"
                  variant="pill"
                  type="submit"
                  :disabled="!rowForms[absence.id].isDirty || rowForms[absence.id].processing"
                >
                  {{ $t('absences.save') }}
                </Button>
                <Button variant="pill-muted" type="button" @click="destroy(absence.id)">
                  {{ $t('absences.delete') }}
                </Button>
              </div>

              <p
                v-for="message in [rowForms[absence.id].errors.starts_on, rowForms[absence.id].errors.ends_on].filter(Boolean)"
                :key="message"
                class="text-[11.5px] leading-[1.45] text-destructive"
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
