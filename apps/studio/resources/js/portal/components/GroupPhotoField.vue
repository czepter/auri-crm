<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Input } from '@/ui/input'

/*
 * Die Gruppenfotos-Frage, von beiden Oberflächen benutzt: das Portal, wo das
 * Paar sie füllt, und Projects/Forms.vue, wo das Studio sie am Telefon
 * mitschreibt. Deshalb nur Tokens, die beide Themes setzen — --r-pill trägt
 * seinen Fallback, weil ihn allein das Portal-Theme kennt.
 *
 * Erst die Personen, dann die Bilder daraus. Sie
 * hängt an nichts — kein Shoot, kein Termin — und lebt vollständig in der
 * Antwort, die als JSON durch dieselbe Textspalte reist wie jede andere.
 * App\Crm\Models\FormInstanceField::normaliseGroupPhotos() ist das Gegenstück
 * auf dem Server und hat das letzte Wort über die Form.
 */
type Shot = { name: string; members: string[] }

const props = defineProps<{
  suggestions: string[]
  minutesPerGroup: number
}>()

const value = defineModel<string>({ required: true })

const people = ref<string[]>([])
const shots = ref<Shot[]>([])
const active = ref(0)
const personDraft = ref('')
const shotDraft = ref('')

// Der Server ist die Quelle, solange niemand tippt: eine Antwort kommt als
// JSON zurück und wird hier einmal aufgeklappt.
watch(
  value,
  (json) => {
    const parsed = parse(json)
    if (JSON.stringify(parsed) === JSON.stringify({ people: people.value, groups: shots.value })) return

    people.value = parsed.people
    shots.value = parsed.groups
    active.value = Math.min(active.value, Math.max(0, parsed.groups.length - 1))
  },
  { immediate: true },
)

function parse(json: string): { people: string[]; groups: Shot[] } {
  try {
    const decoded = JSON.parse(json || '{}')
    return {
      people: Array.isArray(decoded.people) ? decoded.people.filter((p: unknown) => typeof p === 'string') : [],
      groups: Array.isArray(decoded.groups)
        ? decoded.groups
            .filter((g: unknown): g is Shot => typeof g === 'object' && g !== null && typeof (g as Shot).name === 'string')
            .map((g: Shot) => ({ name: g.name, members: Array.isArray(g.members) ? g.members : [] }))
        : [],
    }
  } catch {
    return { people: [], groups: [] }
  }
}

function push() {
  value.value = JSON.stringify({ people: people.value, groups: shots.value })
}

function addPerson() {
  const name = personDraft.value.trim()
  if (!name || people.value.includes(name)) return

  people.value = [...people.value, name]
  personDraft.value = ''
  push()
}

function addShot(name: string) {
  const clean = name.trim()
  if (!clean) return

  shots.value = [...shots.value, { name: clean, members: [] }]
  active.value = shots.value.length - 1
  shotDraft.value = ''
  push()
}

function removeShot(index: number) {
  shots.value = shots.value.filter((_, i) => i !== index)
  active.value = Math.max(0, Math.min(active.value, shots.value.length - 1))
  push()
}

// Eine Person antippen setzt sie in das ausgewählte Bild oder nimmt sie
// wieder heraus — dieselbe Geste in beide Richtungen.
function toggle(name: string) {
  const shot = shots.value[active.value]
  if (!shot) return

  shots.value = shots.value.map((s, i) =>
    i === active.value
      ? { name: s.name, members: s.members.includes(name) ? s.members.filter((m) => m !== name) : [...s.members, name] }
      : s,
  )
  push()
}

function inActive(name: string): boolean {
  return shots.value[active.value]?.members.includes(name) ?? false
}

// Ein Bild kostet die eingestellte Grundzeit, ab der fünften Person eine
// halbe Minute mehr, bei fünf Minuten ist Schluss: eine große Gruppe wird
// gestellt, nicht abgezählt.
function minutesOf(shot: Shot): number {
  return Math.min(5, Math.round(props.minutesPerGroup + Math.max(0, shot.members.length - 4) * 0.5))
}

const total = computed(() => shots.value.reduce((sum, shot) => sum + minutesOf(shot), 0))

const open = computed(() => props.suggestions.filter((name) => !shots.value.some((shot) => shot.name === name)))
</script>

<template>
  <div role="group" class="flex flex-col gap-7">
    <div>
      <div class="mb-2.5 flex items-baseline justify-between gap-4">
        <span class="text-[12px] leading-none font-semibold tracking-[0.14em] uppercase text-muted-foreground">
          {{ $t('groupPhotos.people') }}
        </span>
        <span class="text-[12.5px] leading-none text-faint">{{ people.length }}</span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="name in people"
          :key="name"
          type="button"
          :class="[
            'flex items-center gap-2 rounded-[var(--r-pill,999px)] border px-3.5 py-2 text-[13.5px] leading-tight',
            inActive(name) ? 'border-foreground bg-foreground text-background' : 'border-line-hover bg-background',
          ]"
          :disabled="!shots.length"
          @click="toggle(name)"
        >
          <span>{{ name }}</span>
          <span :class="inActive(name) ? 'text-background/60' : 'text-faint'">{{ inActive(name) ? '−' : '+' }}</span>
        </button>

        <Input
          v-model="personDraft"
          class="w-[178px] rounded-[var(--r-pill,999px)] border-dashed"
          type="text"
          maxlength="120"
          :placeholder="$t('groupPhotos.name')"
          @keydown.enter.prevent="addPerson"
        />
      </div>
    </div>

    <div>
      <div class="mb-2.5 flex items-baseline justify-between gap-4">
        <span class="text-[12px] leading-none font-semibold tracking-[0.14em] uppercase text-muted-foreground">
          {{ $t('groupPhotos.shots') }}
        </span>
        <span class="text-[12.5px] leading-none text-faint">{{ shots.length }}</span>
      </div>

      <div class="flex flex-col gap-2.5">
        <div
          v-for="(shot, index) in shots"
          :key="index"
          :class="[
            'cursor-pointer rounded-[var(--r)] border p-4',
            index === active ? 'border-foreground' : 'border-border',
          ]"
          @click="active = index"
        >
          <div class="flex items-baseline gap-3">
            <span class="grow text-[15px] leading-snug font-medium">{{ shot.name }}</span>
            <span class="text-[12.5px] leading-snug whitespace-nowrap text-faint">
              {{ $t('groupPhotos.shotMeta', { count: shot.members.length, minutes: minutesOf(shot) }) }}
            </span>
            <button type="button" class="text-[15px] leading-tight text-faint" @click.stop="removeShot(index)">&times;</button>
          </div>

          <div v-if="shot.members.length" class="mt-3 flex flex-wrap gap-1.5">
            <button
              v-for="member in shot.members"
              :key="member"
              type="button"
              class="flex items-center gap-1.5 rounded-[var(--r-pill,999px)] border border-border bg-sunk py-1.5 pr-2.5 pl-3 text-[13px] leading-tight"
              @click.stop="active = index, toggle(member)"
            >
              <span>{{ member }}</span>
              <span class="text-faint">&times;</span>
            </button>
          </div>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button
          v-for="name in open"
          :key="name"
          type="button"
          class="rounded-[var(--r-pill,999px)] border border-line-hover bg-background px-3.5 py-2 text-[13px] leading-tight text-muted-foreground"
          @click="addShot(name)"
        >
          + {{ name }}
        </button>

        <Input
          v-model="shotDraft"
          class="w-[158px] rounded-[var(--r-pill,999px)] border-dashed"
          type="text"
          maxlength="120"
          :placeholder="$t('groupPhotos.ownShot')"
          @keydown.enter.prevent="addShot(shotDraft)"
        />
      </div>
    </div>

    <div v-if="shots.length" class="border-t border-border pt-4">
      <div class="text-[20px] leading-tight font-medium">
        {{ $t('groupPhotos.total', { minutes: total }) }}
      </div>
      <div class="mt-1.5 text-[12.5px] leading-[1.45] text-faint">
        {{ $t('groupPhotos.counts', { people: people.length, shots: shots.length }) }}
      </div>
    </div>
  </div>
</template>
