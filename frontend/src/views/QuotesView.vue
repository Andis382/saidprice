<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhMicrophone, PhNotepad, PhPencilSimpleLine } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSearch from '@/components/ui/UiSearch.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTabs from '@/components/ui/UiTabs.vue'
import QuoteCard from '@/components/quote/QuoteCard.vue'
import { api, query } from '@/lib/api'
import { formatMoney, formatPercent } from '@/lib/format'
import { BOARD_COLUMNS, type BoardColumn, type QuoteSummary } from '@/lib/quote'
import { useOverview } from '@/stores/overview'

type Column = { key: BoardColumn; count: number; totalCents: number; quotes: QuoteSummary[] }

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const overview = useOverview()

const columns = ref<Column[] | null>(null)
const search = ref(typeof route.query.q === 'string' ? route.query.q : '')
const tab = ref<BoardColumn>(BOARD_COLUMNS.includes(route.query.tab as BoardColumn) ? (route.query.tab as BoardColumn) : 'SENT')

const tabs = computed(() => (columns.value ?? []).map((c) => ({ value: c.key, label: t(`columns.${c.key}`), count: c.count })))
const active = computed(() => columns.value?.find((c) => c.key === tab.value) ?? null)
const total = computed(() => columns.value?.reduce((sum, c) => sum + c.count, 0) ?? 0)

const subtitle = computed(() => {
  const w = overview.thisWeek
  if (!w || !w.sent) return t('quotes.subtitleQuiet')
  return t('quotes.subtitleWeek', {
    sent: w.sent,
    won: w.won,
    money: formatMoney(w.wonCents),
    rate: formatPercent(w.conversion ?? 0),
  })
})

let timer = 0
async function load() {
  const data = await api.get<{ columns: Column[] }>('/quotes' + query({ q: search.value.trim() }))
  columns.value = data.columns
}

watch(search, () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(() => {
    router.replace({ query: { ...route.query, q: search.value || undefined } })
    load()
  }, 250)
})
watch(tab, (value) => router.replace({ query: { ...route.query, tab: value } }))

onMounted(load)
</script>

<template>
  <AppPage :title="$t('quotes.title')" :subtitle="subtitle">
    <template #actions>
      <UiButton :to="{ name: 'quote-new' }" :icon="PhMicrophone" size="lg">{{ $t('nav.newQuote') }}</UiButton>
    </template>

    <div class="toolbar">
      <UiSearch v-model="search" :placeholder="$t('quotes.search')" class="toolbar__search" />
      <p v-if="columns && search" class="small muted" role="status">{{ $t('quotes.found', { n: total }) }}</p>
    </div>

    <div v-if="!columns" class="board board--loading" aria-busy="true">
      <div v-for="i in 6" :key="i" class="col">
        <UiSkeleton :lines="1" height="18px" />
        <UiSkeleton card :lines="3" />
        <UiSkeleton card :lines="3" />
      </div>
    </div>

    <UiEmpty
      v-else-if="!total && !search"
      :icon="PhNotepad"
      :title="$t('quotes.emptyTitle')"
      :text="$t('quotes.emptyText')"
    >
      <UiButton :to="{ name: 'quote-new' }" :icon="PhMicrophone">{{ $t('nav.newQuote') }}</UiButton>
    </UiEmpty>

    <template v-else>
      <!-- Desktop: the whole pipeline at a glance -->
      <div class="board" role="list" :aria-label="$t('quotes.title')">
        <section v-for="c in columns" :key="c.key" class="col" :class="`col--${c.key.toLowerCase()}`" role="listitem" :aria-labelledby="`col-${c.key}`">
          <header class="col__head">
            <h2 :id="`col-${c.key}`" class="col__title"><span class="col__dot" aria-hidden="true" />{{ $t(`columns.${c.key}`) }}</h2>
            <span class="col__count num">{{ c.count }}</span>
          </header>
          <p class="col__value num">{{ formatMoney(c.totalCents) }}</p>
          <div class="col__cards">
            <QuoteCard v-for="q in c.quotes" :key="q.id" :quote="q" />
            <p v-if="!c.quotes.length" class="col__empty">{{ $t(`quotes.emptyColumn.${c.key}`) }}</p>
            <p v-if="c.count > c.quotes.length" class="col__more">{{ $t('quotes.showingLatest', { n: c.quotes.length, total: c.count }) }}</p>
          </div>
        </section>
      </div>

      <!-- Phones and tablets: one column at a time -->
      <div class="stacked">
        <UiTabs v-model="tab" :tabs="tabs" :label="$t('quotes.title')" />
        <div v-if="active" class="stacked__meta">
          <span>{{ $t(`columns.${active.key}`) }}</span>
          <span class="num strong">{{ formatMoney(active.totalCents) }}</span>
        </div>
        <div class="stacked__cards">
          <QuoteCard v-for="q in active?.quotes ?? []" :key="q.id" :quote="q" />
          <UiEmpty v-if="active && !active.quotes.length" compact :icon="PhPencilSimpleLine" :title="$t(`quotes.emptyColumn.${active.key}`)" />
        </div>
      </div>
    </template>
  </AppPage>
</template>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px 16px;
}
.toolbar__search {
  width: min(100%, 380px);
}
.board {
  display: grid;
  grid-template-columns: repeat(6, minmax(188px, 1fr));
  gap: 12px;
  align-items: start;
}
.col {
  --dot: var(--gray-400);
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  padding: 12px 10px 10px;
  background: color-mix(in srgb, var(--surface-sunken) 55%, transparent);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: inset 0 1px 2px rgb(23 23 26 / 0.04);
}
.board--loading .col {
  padding: 14px;
}
.col--sent {
  --dot: var(--brand-500);
}
.col--viewed {
  --dot: var(--info);
}
.col--accepted {
  --dot: var(--accent-500);
}
.col--declined {
  --dot: var(--danger);
}
.col__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 4px;
}
.col__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-body);
  font-size: var(--text-sm);
  font-weight: 700;
  letter-spacing: 0;
}
.col__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--dot);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dot) 20%, transparent);
}
.col__count {
  min-width: 26px;
  height: 22px;
  padding: 0 7px;
  border-radius: var(--radius-pill);
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: 12px;
  font-weight: 700;
  line-height: 20px;
  text-align: center;
}
.col__value {
  margin-top: -6px;
  padding: 0 4px;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--text-subtle);
}
.col__cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 4px;
}
.col__empty,
.col__more {
  padding: 14px 8px;
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--radius);
  color: var(--text-subtle);
  font-size: var(--text-xs);
  text-align: center;
}
.col__more {
  border: 0;
  padding: 4px;
}
.stacked {
  display: none;
  flex-direction: column;
  gap: 14px;
}
.stacked__meta {
  display: flex;
  justify-content: space-between;
  padding: 0 4px;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.stacked__cards {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}
@media (max-width: 1100px) {
  .board:not(.board--loading) {
    display: none;
  }
  .board--loading {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .stacked {
    display: flex;
  }
}
</style>
