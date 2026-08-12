<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

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

function add() {
  newForm.post('/abwesenheiten', {
    preserveScroll: true,
    onSuccess: () => newForm.reset(),
  })
}
</script>

<template>
  <AdminLayout active="abwesenheiten">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('absences.title') }}</h1>
    </div>


    <section class="dsec" style="margin-top: 0">
      <div class="panel">
        <p v-if="absences.length === 0" class="hint" style="padding: var(--s3)">{{ $t('absences.empty') }}</p>

        <div v-for="absence in absences" :key="absence.id" class="trow" style="align-items: flex-start">
          <span class="pavatar">{{ absence.userName.slice(0, 1) }}</span>
          <span class="pinfo">
            <span class="pname">{{ absence.userName }}</span>
            <span v-if="!absence.canManage" class="psub">{{ absence.categoryLabel }} · {{ absence.startsOn }} – {{ absence.endsOn }}</span>
          </span>

          <template v-if="absence.canManage">
            <form class="fgrid grp" style="flex: 1" @submit.prevent="save(absence.id)">
              <div class="fld">
                <label :for="`category-${absence.id}`">{{ $t('absences.category') }}</label>
                <select :id="`category-${absence.id}`" v-model="rowForms[absence.id].category" class="inp sm">
                  <option v-for="category in categories" :key="category.value" :value="category.value">
                    {{ category.label }}
                  </option>
                </select>
              </div>
              <div class="fld">
                <label :for="`starts-${absence.id}`">{{ $t('absences.startsOn') }}</label>
                <input :id="`starts-${absence.id}`" v-model="rowForms[absence.id].starts_on" class="inp sm" type="date" />
                <p v-if="rowForms[absence.id].errors.starts_on" class="err">{{ rowForms[absence.id].errors.starts_on }}</p>
              </div>
              <div class="fld">
                <label :for="`ends-${absence.id}`">{{ $t('absences.endsOn') }}</label>
                <input :id="`ends-${absence.id}`" v-model="rowForms[absence.id].ends_on" class="inp sm" type="date" />
                <p v-if="rowForms[absence.id].errors.ends_on" class="err">{{ rowForms[absence.id].errors.ends_on }}</p>
              </div>
              <div class="fld" style="justify-content: flex-end; flex-direction: row; gap: var(--s2)">
                <button
                  class="btn"
                  type="submit"
                  :disabled="!rowForms[absence.id].isDirty || rowForms[absence.id].processing"
                  :style="!rowForms[absence.id].isDirty ? 'opacity:.4' : undefined"
                >
                  {{ $t('absences.save') }}
                </button>
                <button type="button" class="abtn hot" @click="destroy(absence.id)">
                  {{ $t('absences.delete') }}
                </button>
              </div>
            </form>
          </template>
        </div>
      </div>
    </section>

    <section class="dsec">
      <div class="dlab">{{ $t('absences.add') }}</div>
      <form class="fgrid grp" @submit.prevent="add">
        <div v-if="canManageCrew" class="fld">
          <label for="new-user">{{ $t('absences.person') }}</label>
          <select id="new-user" v-model="newForm.user" class="inp">
            <option value="">{{ $t('absences.myself') }}</option>
            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
          </select>
        </div>
        <div class="fld">
          <label for="new-category">{{ $t('absences.category') }}</label>
          <select id="new-category" v-model="newForm.category" class="inp">
            <option v-for="category in categories" :key="category.value" :value="category.value">
              {{ category.label }}
            </option>
          </select>
        </div>
        <div class="fld">
          <label for="new-starts">{{ $t('absences.startsOn') }}</label>
          <input id="new-starts" v-model="newForm.starts_on" class="inp" type="date" />
          <p v-if="newForm.errors.starts_on" class="err">{{ newForm.errors.starts_on }}</p>
        </div>
        <div class="fld">
          <label for="new-ends">{{ $t('absences.endsOn') }}</label>
          <input id="new-ends" v-model="newForm.ends_on" class="inp" type="date" />
          <p v-if="newForm.errors.ends_on" class="err">{{ newForm.errors.ends_on }}</p>
        </div>
        <div class="fld wide">
          <div class="formfoot" style="margin-top: 0">
            <button class="btn" type="submit" :disabled="newForm.processing">
              {{ $t('absences.add') }}
            </button>
          </div>
        </div>
      </form>
    </section>
  </AdminLayout>
</template>
