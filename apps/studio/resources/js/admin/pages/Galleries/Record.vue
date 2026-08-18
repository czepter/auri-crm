<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{
  gallery: {
    id: string
    title: string | null
    url: string
    viewPassword: string | null
    downloadPin: string | null
    requiresVerifiedEmail: boolean
    expiresAt: string | null
    balanceSettled: boolean
    requiresSettledBalance: boolean
    design: Record<string, string> | null
  }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
  figures: {
    published: number | null
    assets: number
    expiresAt: string | null
    marked: number
    downloaded: number
    viewed: number
  }
  sets: { name: string | null; count: number }[]
  assets: { key: string; set: string | null; src: string | null }[]
  versions: { version: number; publishedAt: string | null; assetCount: number }[]
  builds: { id: string; size: string; status: string; parts: number; bytes: number }[]
  lists: { id: string; name: string | null; owner: string | null; count: number }[]
  activity: {
    downloads: { who: string; what: string | null; size: string | null; on: string | null }[]
    marks: { who: string | null; what: string; on: string }[]
    comments: { who: string | null; what: string; on: string }[]
    views: { who: string | null; on: string }[]
  }
}>()

// C-19 (#255): five motions, five tabs. Four surfaces on one screen would not
// be management but a heap — and the pictures need the whole width.
const TABS = ['bilder', 'gestaltung', 'download', 'freigaben', 'aktivitaet'] as const
const tab = ref<(typeof TABS)[number]>('bilder')

// C-20 (#257): three sheets, because the questions differ — who took
// something, who marked something, who was here at all.
const SHEETS = ['downloads', 'marks', 'views'] as const
const sheet = ref<(typeof SHEETS)[number]>('downloads')

const activeSet = ref<string | null>(null)

const secrets = useForm({
  view_password: props.gallery.viewPassword ?? '',
  download_pin: props.gallery.downloadPin ?? '',
})

function saveSecrets() {
  secrets.transform((data) => ({
    view_password: data.view_password === '' ? null : data.view_password,
    download_pin: data.download_pin === '' ? null : data.download_pin,
  })).patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const design = useForm({ design: props.gallery.design ?? { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' } })

function saveDesign() {
  design.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const COVERS = ['full', 'split', 'plain']
const COLOURS = ['light', 'dark', 'warm']
const GRIDS = ['wide', 'dense', 'single']

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}

function megabytes(bytes: number) {
  return `${Math.round(bytes / 1_000_000)} MB`
}

function shown() {
  return activeSet.value === null ? props.assets : props.assets.filter((asset) => asset.set === activeSet.value)
}
</script>

<template>
  <AdminLayout active="projekte">
    <div data-theme="crm">
      <div class="sheet" style="max-width: none; padding-bottom: 0">
        <div class="head"><h1>{{ gallery.title ?? '—' }}</h1></div>

        <!-- Link, password and code stand in clear and are set here: both are
             handed to guests anyway, and a studio that cannot look one up
             sets a new one on every enquiry (C-17, #253). -->
        <div class="access">
          <div class="col wide">
            <div class="lab">{{ $t('galleryRecord.link') }}</div>
            <div class="val"><a class="link" :href="gallery.url">{{ gallery.url }}</a></div>

            <div class="siblings">
              <Link
                v-for="sibling in siblings"
                :key="sibling.id"
                :class="{ on: sibling.open }"
                :href="`/galerien/${sibling.id}`"
              >
                {{ sibling.title ?? '—' }}<span>{{ sibling.assetCount }}</span>
              </Link>
            </div>
          </div>

          <div class="col">
            <div class="lab">{{ $t('gallery.gateView') }}</div>
            <div class="val"><input v-model="secrets.view_password" type="text" @blur="saveSecrets" /></div>
          </div>

          <div class="col">
            <div class="lab">{{ $t('gallery.gateDownload') }}</div>
            <div class="val"><input v-model="secrets.download_pin" class="s" type="text" @blur="saveSecrets" /></div>
          </div>
        </div>

        <div class="band">
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.published') }}</div>
            <div class="val">{{ figures.published ? $t('galleryRecord.version', { version: figures.published }) : '—' }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.images') }}</div>
            <div class="val">{{ figures.assets }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.expiry') }}</div>
            <div class="val plain">{{ day(figures.expiresAt) }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.marked') }}</div>
            <div class="val">{{ figures.marked }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.downloaded') }}</div>
            <div class="val">{{ figures.downloaded }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('galleryRecord.viewed') }}</div>
            <div class="val">{{ figures.viewed }}</div>
          </div>
        </div>

        <!-- balance_settled is a state out of the payments, never a switch —
             it stands as a value beside the block it causes. -->
        <p v-if="gallery.requiresSettledBalance && !gallery.balanceSettled" class="hint">
          {{ $t('galleryRecord.blockedByBalance') }}
        </p>
      </div>

      <div class="tabs">
        <button v-for="name in TABS" :key="name" type="button" :class="{ on: tab === name }" @click="tab = name">
          {{ $t(`galleryRecord.tab.${name}`) }}
        </button>
      </div>

      <!-- Bilder: sets on the left, grid on the right, versions underneath —
           a version is a published selection, so it is a statement about
           pictures and belongs here, not with the permissions. -->
      <div v-if="tab === 'bilder'" style="display: flex">
        <div class="sets">
          <a href="#" :class="{ on: activeSet === null }" @click.prevent="activeSet = null">
            {{ $t('gallery.all') }}<span class="ct">{{ figures.assets }}</span>
          </a>
          <a
            v-for="set in sets.filter((entry) => entry.name !== null)"
            :key="set.name!"
            href="#"
            :class="{ on: activeSet === set.name }"
            @click.prevent="activeSet = set.name"
          >
            {{ set.name }}<span class="ct">{{ set.count }}</span>
          </a>
        </div>

        <div class="shelf">
          <div class="bar">
            <h2>{{ $t('galleryRecord.images') }}</h2>
            <span class="n">{{ shown().length }}</span>
            <Link class="btn-2" :href="`/galerien/${gallery.id}/medien`" style="margin-left: auto">
              {{ $t('galleryRecord.ingest') }}
            </Link>
          </div>

          <div class="grid-photos">
            <figure v-for="asset in shown()" :key="asset.key">
              <img v-if="asset.src" :src="`${asset.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
              <span v-if="asset.set" class="mark"><span>{{ asset.set }}</span></span>
            </figure>
          </div>

          <h2 style="margin-top: var(--s6)">{{ $t('galleryRecord.versions') }}</h2>
          <div class="list">
            <div v-for="version in versions" :key="version.version" class="row">
              <span class="when">{{ $t('galleryRecord.version', { version: version.version }) }}</span>
              <span class="n">{{ $t('contacts.assetCount', version.assetCount) }}</span>
              <span class="m">{{ day(version.publishedAt) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Gestaltung: choosing without seeing is guessing, so the choice is on
           the left and the result on the right. -->
      <div v-else-if="tab === 'gestaltung'" style="display: flex">
        <div class="sets" style="width: 320px">
          <div class="field" style="margin-bottom: var(--s4)">
            <span>{{ $t('galleryRecord.cover') }}</span>
            <div class="choice">
              <label v-for="option in COVERS" :key="option">
                <input v-model="design.design.cover" type="radio" :value="option" @change="saveDesign" />
                <span>{{ $t(`galleryRecord.cover_${option}`) }}</span>
              </label>
            </div>
          </div>

          <div class="field" style="margin-bottom: var(--s4)">
            <span>{{ $t('galleryRecord.colour') }}</span>
            <div class="choice">
              <label v-for="option in COLOURS" :key="option">
                <input v-model="design.design.colour" type="radio" :value="option" @change="saveDesign" />
                <span>{{ $t(`galleryRecord.colour_${option}`) }}</span>
              </label>
            </div>
          </div>

          <div class="field">
            <span>{{ $t('galleryRecord.grid') }}</span>
            <div class="choice">
              <label v-for="option in GRIDS" :key="option">
                <input v-model="design.design.grid" type="radio" :value="option" @change="saveDesign" />
                <span>{{ $t(`galleryRecord.grid_${option}`) }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="preview-pane">
          <div class="frame">
            <div class="cover">
              <img v-if="assets[0]?.src" :src="`${assets[0].src}?w=1200&fmt=jpeg`" alt="" />
              <div class="title">
                <b>{{ gallery.title }}</b>
                <span>{{ $t('contacts.assetCount', figures.assets) }}</span>
              </div>
            </div>
            <div class="thumbs">
              <img
                v-for="asset in assets.slice(1, 7)"
                :key="asset.key"
                :src="`${asset.src}?w=400&fmt=jpeg`"
                alt=""
                loading="lazy"
              />
            </div>
          </div>

          <div class="pv-foot">
            <a class="link" :href="gallery.url">{{ $t('galleryRecord.openLive') }}</a>
          </div>
        </div>
      </div>

      <!-- Download: the archives are the fact, so the tab shows them rather
           than a promise about them. -->
      <div v-else-if="tab === 'download'" class="sheet">
        <table v-if="builds.length">
          <thead>
            <tr>
              <th>{{ $t('gallery.sizeLabel') }}</th>
              <th>{{ $t('galleryRecord.parts') }}</th>
              <th class="r">{{ $t('galleryRecord.size') }}</th>
              <th>{{ $t('galleryRecord.state') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="build in builds" :key="build.id">
              <td class="nm">{{ $t(`gallery.size.${build.size}`) }}</td>
              <td class="mu">{{ build.parts }}</td>
              <td class="r">{{ megabytes(build.bytes) }}</td>
              <td class="mu">{{ build.status }}</td>
            </tr>
          </tbody>
        </table>

        <p v-else class="hint">{{ $t('galleryRecord.noBuilds') }}</p>
      </div>

      <!-- Freigaben: lists carry their owner, because a list always belongs
           to somebody — the couple or a guest who identified themselves. -->
      <div v-else-if="tab === 'freigaben'" class="sheet">
        <div class="setting">
          <div class="t">
            {{ $t('gallery.gateView') }}
            <div class="s">{{ gallery.viewPassword ?? $t('gallery.gateOff') }}</div>
          </div>
        </div>
        <div class="setting">
          <div class="t">
            {{ $t('gallery.gateDownload') }}
            <div class="s">{{ gallery.downloadPin ?? $t('gallery.gateOff') }}</div>
          </div>
        </div>
        <div class="setting">
          <div class="t">
            {{ $t('galleryRecord.emailGate') }}
            <div class="s">{{ gallery.requiresVerifiedEmail ? $t('gallery.gateOn') : $t('gallery.gateOff') }}</div>
          </div>
        </div>

        <h2 style="margin-top: var(--s6)">{{ $t('gallery.listsTitle') }}</h2>
        <div class="list">
          <div v-for="list in lists" :key="list.id" class="row">
            <span class="n">{{ list.name ?? $t('gallery.heart') }}</span>
            <span class="tag">{{ list.owner ?? $t('galleryRecord.guest') }}</span>
            <span class="m">{{ $t('contacts.assetCount', list.count) }}</span>
          </div>
          <p v-if="!lists.length" class="hint">{{ $t('gallery.listEmpty') }}</p>
        </div>
      </div>

      <div v-else class="sheet">
        <span class="seg">
          <button v-for="name in SHEETS" :key="name" type="button" :class="{ on: sheet === name }" @click="sheet = name">
            {{ $t(`galleryRecord.sheet.${name}`) }}
          </button>
        </span>

        <table style="margin-top: var(--s4)">
          <thead>
            <tr>
              <th>{{ $t('galleryRecord.who') }}</th>
              <th>{{ $t('galleryRecord.what') }}</th>
              <th class="r">{{ $t('galleryRecord.when') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="sheet === 'downloads'">
              <tr v-for="(row, index) in activity.downloads" :key="index">
                <td class="nm">{{ row.who }}</td>
                <td class="mu">{{ row.what ?? $t('gallery.everything') }} · {{ $t(`gallery.size.${row.size}`) }}</td>
                <td class="r">{{ day(row.on) }}</td>
              </tr>
            </template>

            <template v-else-if="sheet === 'marks'">
              <tr v-for="(row, index) in activity.marks" :key="index">
                <td class="nm">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="mu">{{ row.what }}</td>
                <td class="r">{{ day(row.on) }}</td>
              </tr>
              <tr v-for="(row, index) in activity.comments" :key="`c${index}`">
                <td class="nm">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="mu">{{ row.what }}</td>
                <td class="r">{{ day(row.on) }}</td>
              </tr>
            </template>

            <template v-else>
              <tr v-for="(row, index) in activity.views" :key="index">
                <td class="nm">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="mu">—</td>
                <td class="r">{{ day(row.on) }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
