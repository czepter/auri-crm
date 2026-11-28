<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import ProfileNav from '@/admin/components/settings/ProfileNav.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

const props = defineProps<{
  absences: App.Data.AbsenceData[]
  canManageCrew: boolean
  staff: App.Data.StaffProfileData[]
}>()

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

function add() {
  newForm.post('/abwesenheiten', {
    preserveScroll: true,
    onSuccess: () => newForm.reset(),
  })
}
</script>

<template>
  <AdminLayout active="abwesenheiten">
    <FormShell :kicker="$t('profile.kicker')" :title="$t('absences.title')">
      <template #nav><ProfileNav active="abwesenheiten" /></template>

        <div v-if="absences.length">
          <div class="flex items-center gap-3 pb-2 text-small text-ink-2">
            <span v-if="!$page.props.soloStudio" class="w-44 flex-none">{{ $t('absences.person') }}</span>
            <span class="w-36">{{ $t('absences.startsOn') }}</span>
            <span class="w-36">{{ $t('absences.endsOn') }}</span>
          </div>
          <div
            v-for="absence in absences"
            :key="absence.id"
            class="flex items-center gap-3 py-2.5 border-t border-rule first:border-t-0"
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
                    variant="default"
                    type="submit"
                    :disabled="rowForms[absence.id].processing"
                  >
                    {{ $t('absences.save') }}
                  </Button>
                  <Button variant="secondary" type="button" @click="destroy(absence.id)">
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

        <template #aside>
          <form class="grid gap-4" @submit.prevent="add">
            <h2 class="text-ui font-semibold">{{ $t('absences.add') }}</h2>
            <div v-if="canManageCrew && staff.length > 1" class="flex min-w-0 flex-col gap-1.5">
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
            <div>
              <Button class="ml-auto" type="submit" :disabled="newForm.processing">{{ $t('absences.add') }}</Button>
            </div>
          </form>
        </template>
    </FormShell>
  </AdminLayout>
</template>
