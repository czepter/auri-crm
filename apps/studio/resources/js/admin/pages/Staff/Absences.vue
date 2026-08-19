<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
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
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('absences.title') }}</h1>

      <!-- The add form used to sit permanently at the foot of the page, below
           however many absences the list held — you scrolled past everyone
           else's holiday to book your own. As a dialog it moves to where the
           other list screens keep their create action: the header. -->
      <Dialog v-model:open="adding">
        <DialogTrigger as-child>
          <Button type="button">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('absences.add') }}
          </Button>
        </DialogTrigger>

        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('absences.add') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="absence-create-form" class="fgrid" @submit.prevent="add">
              <div v-if="canManageCrew" class="fld wide">
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
              <div class="fld wide">
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
              <div class="fld">
                <Label for="new-starts">{{ $t('absences.startsOn') }}</Label>
                <DatePicker id="new-starts" v-model="newForm.starts_on" />
                <p v-if="newForm.errors.starts_on" class="err">{{ newForm.errors.starts_on }}</p>
              </div>
              <div class="fld">
                <Label for="new-ends">{{ $t('absences.endsOn') }}</Label>
                <DatePicker id="new-ends" v-model="newForm.ends_on" />
                <p v-if="newForm.errors.ends_on" class="err">{{ newForm.errors.ends_on }}</p>
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
    </div>


    <section class="dsec" style="margin-top: 0">
      <div v-if="absences.length" class="panel">
        <div v-for="absence in absences" :key="absence.id" class="trow" style="align-items: flex-start">
          <span class="pavatar">{{ absence.userName.slice(0, 1) }}</span>
          <span class="pinfo">
            <span class="pname">{{ absence.userName }}</span>
            <span v-if="!absence.canManage" class="psub">{{ absence.categoryLabel }} · {{ absence.startsOn }} – {{ absence.endsOn }}</span>
          </span>

          <template v-if="absence.canManage">
            <form class="fgrid grp" style="flex: 1" @submit.prevent="save(absence.id)">
              <div class="fld">
                <Label :for="`category-${absence.id}`">{{ $t('absences.category') }}</Label>
                <Select
                  :model-value="rowForms[absence.id].category"
                  @update:model-value="(value) => (rowForms[absence.id].category = String(value))"
                >
                  <SelectTrigger :id="`category-${absence.id}`" size="sm">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="category in categories" :key="category.value" :value="category.value">
                      {{ category.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="fld">
                <Label :for="`starts-${absence.id}`">{{ $t('absences.startsOn') }}</Label>
                <DatePicker :id="`starts-${absence.id}`" v-model="rowForms[absence.id].starts_on" />
                <p v-if="rowForms[absence.id].errors.starts_on" class="err">{{ rowForms[absence.id].errors.starts_on }}</p>
              </div>
              <div class="fld">
                <Label :for="`ends-${absence.id}`">{{ $t('absences.endsOn') }}</Label>
                <DatePicker :id="`ends-${absence.id}`" v-model="rowForms[absence.id].ends_on" />
                <p v-if="rowForms[absence.id].errors.ends_on" class="err">{{ rowForms[absence.id].errors.ends_on }}</p>
              </div>
              <div class="fld" style="justify-content: flex-end; flex-direction: row; gap: var(--s2)">
                <Button
                  type="submit"
                  :disabled="!rowForms[absence.id].isDirty || rowForms[absence.id].processing"
                  :style="!rowForms[absence.id].isDirty ? 'opacity:.4' : undefined"
                >
                  {{ $t('absences.save') }}
                </Button>
                <Button type="button" variant="outline" tone="accent" @click="destroy(absence.id)">
                  {{ $t('absences.delete') }}
                </Button>
              </div>
            </form>
          </template>
        </div>
      </div>
    </section>

  </AdminLayout>
</template>
