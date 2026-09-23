<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhAddressBook, PhUserPlus } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiAvatar from '@/components/ui/UiAvatar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSearch from '@/components/ui/UiSearch.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import CustomerDialog from '@/components/quote/CustomerDialog.vue'
import { api, query } from '@/lib/api'
import { formatMoney, formatPhone, formatRelative } from '@/lib/format'
import type { CustomerRow } from '@/lib/quote'

const router = useRouter()
const customers = ref<CustomerRow[] | null>(null)
const search = ref('')
const adding = ref(false)

let timer = 0
async function load() {
  customers.value = await api.get<CustomerRow[]>('/customers' + query({ q: search.value.trim() }))
}
watch(search, () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(load, 250)
})
onMounted(load)
</script>

<template>
  <AppPage :title="$t('customers.title')" :subtitle="$t('customers.subtitle')">
    <template #actions>
      <UiButton :icon="PhUserPlus" @click="adding = true">{{ $t('customers.add') }}</UiButton>
    </template>

    <UiSearch v-model="search" :placeholder="$t('customers.search')" class="search" />

    <UiCard v-if="!customers" padding="lg"><UiSkeleton :lines="8" /></UiCard>
    <UiEmpty v-else-if="!customers.length" :icon="PhAddressBook" :title="search ? $t('customers.noMatch') : $t('customers.emptyTitle')" :text="search ? undefined : $t('customers.emptyText')" />
    <UiCard v-else padding="none">
      <ul class="list">
        <li v-for="c in customers" :key="c.id">
          <RouterLink :to="{ name: 'customer', params: { id: c.id } }" class="row">
            <UiAvatar :name="c.name" :size="40" />
            <span class="row__who">
              <span class="row__name">{{ c.name }}</span>
              <span class="row__sub">{{ [formatPhone(c.phone), c.address].filter((x) => x && x !== '—').join(' · ') }}</span>
            </span>
            <span class="row__col num">
              <span class="row__big">{{ $t('customers.quotesCount', { n: c.quotesCount }, c.quotesCount) }}</span>
              <span class="row__sub">{{ c.lastQuoteAt ? $t('customers.last', { when: formatRelative(c.lastQuoteAt) }) : $t('customers.none') }}</span>
            </span>
            <span class="row__col row__col--won num">
              <span class="row__big">{{ formatMoney(c.wonCents) }}</span>
              <span class="row__sub">{{ $t('customers.wonOf', { won: c.wonCount, total: c.quotesCount }) }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>
    </UiCard>

    <CustomerDialog v-model:open="adding" @saved="(c) => router.push({ name: 'customer', params: { id: c.id } })" />
  </AppPage>
</template>

<style scoped>
.search {
  width: min(100%, 420px);
}
.list {
  margin: 0;
  padding: 6px;
  list-style: none;
}
.row {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) 150px 150px;
  align-items: center;
  gap: 14px;
  width: 100%;
  min-height: 64px;
  padding: 10px 12px;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--text);
  text-align: left;
  text-decoration: none;
  transition: background-color var(--duration) var(--ease);
}
.row:hover {
  color: var(--text);
  background: var(--surface-hover);
}
.row:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--focus-ring);
}
li + li .row {
  border-top: 1px solid var(--border);
  border-radius: 0;
}
.row__who,
.row__col {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.row__col {
  align-items: flex-end;
  text-align: right;
}
.row__name {
  font-weight: 700;
}
.row__big {
  font-weight: 650;
  font-size: var(--text-sm);
}
.row__col--won .row__big {
  color: var(--success-text);
}
.row__sub {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
@media (max-width: 720px) {
  .row {
    grid-template-columns: 40px minmax(0, 1fr) auto;
  }
  .row__col:not(.row__col--won) {
    display: none;
  }
}
</style>
