<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhChartBar, PhCheckCircle, PhCoins, PhPaperPlaneTilt, PhPercent, PhTimer } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiBarChart from '@/components/ui/UiBarChart.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiStat from '@/components/ui/UiStat.vue'
import { api } from '@/lib/api'
import { formatDate, formatMoney, formatPercent } from '@/lib/format'
import type { WeekStats } from '@/lib/quote'
import { answerTime, share } from '@/lib/stats'

type Stats = { weeks: (WeekStats & { weekStart: string })[]; total: WeekStats; thisWeek: WeekStats & { weekStart: string } }

const { t } = useI18n()
const stats = ref<Stats | null>(null)

onMounted(async () => {
  stats.value = await api.get<Stats>('/stats?weeks=12')
})

const week = computed(() => stats.value?.thisWeek ?? null)
const total = computed(() => stats.value?.total ?? null)
const decided = computed(() => (total.value ? total.value.sent - total.value.open : 0))

const outcomeSeries = computed(() => [
  { key: 'won', label: t('stats.series.won'), color: 'var(--accent-500)' },
  { key: 'declined', label: t('stats.series.declined'), color: 'color-mix(in srgb, var(--danger) 75%, var(--surface))' },
  { key: 'silent', label: t('stats.series.silent'), color: 'var(--gray-300)' },
  { key: 'open', label: t('stats.series.open'), color: 'var(--brand-400)' },
])
const outcomeRows = computed(() =>
  (stats.value?.weeks ?? []).map((w, i, all) => ({
    label: formatDate(w.weekStart, 'short'),
    highlight: i === all.length - 1,
    values: { won: w.won, declined: w.declined, silent: w.silent, open: w.open },
  })),
)
const moneySeries = computed(() => [
  { key: 'won', label: t('stats.series.wonMoney'), color: 'var(--accent-500)' },
  { key: 'rest', label: t('stats.series.notWonMoney'), color: 'var(--gray-300)' },
])
const moneyRows = computed(() =>
  (stats.value?.weeks ?? []).map((w, i, all) => ({
    label: formatDate(w.weekStart, 'short'),
    highlight: i === all.length - 1,
    values: { won: w.wonCents, rest: w.quotedCents - w.wonCents },
  })),
)
const newestFirst = computed(() => [...(stats.value?.weeks ?? [])].reverse())

function duration(hours: number | null) {
  const time = answerTime(hours)
  return time ? t(`stats.${time.unit}`, { n: time.n }) : '—'
}
</script>

<template>
  <AppPage :title="$t('stats.title')" :subtitle="$t('stats.subtitle')">
    <template v-if="!stats">
      <div class="grid-auto" style="--min: 200px"><UiSkeleton v-for="i in 4" :key="i" card :lines="2" /></div>
      <UiSkeleton card :lines="8" />
    </template>

    <template v-else-if="week && total">
      <section class="section" :aria-labelledby="'this-week'">
        <h2 id="this-week" class="section__title">{{ $t('stats.thisWeek', { date: formatDate(stats.thisWeek.weekStart, 'short') }) }}</h2>
        <div class="tiles">
          <UiStat :label="$t('stats.sent')" :value="week.sent" :icon="PhPaperPlaneTilt" :hint="$t('stats.stillOpen', { n: week.open })" />
          <UiStat :label="$t('stats.accepted')" :value="week.accepted" tone="success" :icon="PhCheckCircle" :hint="$t('stats.declinedSilent', { declined: week.declined, silent: week.silent })" />
          <UiStat :label="$t('stats.conversion')" :value="formatPercent(week.conversion)" tone="info" :icon="PhPercent" :hint="$t('stats.conversionHint')" />
          <UiStat :label="$t('stats.wonMoney')" :value="formatMoney(week.wonCents)" tone="success" :icon="PhCoins" :hint="$t('stats.ofQuoted', { money: formatMoney(week.quotedCents) })" />
        </div>
      </section>

      <section class="section" :aria-labelledby="'twelve'">
        <h2 id="twelve" class="section__title">{{ $t('stats.last12') }}</h2>
        <div class="tiles">
          <UiStat :label="$t('stats.sent')" :value="total.sent" tone="neutral" :icon="PhPaperPlaneTilt" :hint="$t('stats.perWeek', { n: Math.round((total.sent / 12) * 10) / 10 })" />
          <UiStat :label="$t('stats.conversion')" :value="formatPercent(total.conversion)" tone="success" :icon="PhPercent" :hint="$t('stats.wonOf', { won: total.won, sent: total.sent })" />
          <UiStat :label="$t('stats.wonMoney')" :value="formatMoney(total.wonCents)" tone="success" :icon="PhCoins" :hint="$t('stats.ofQuoted', { money: formatMoney(total.quotedCents) })" />
          <UiStat :label="$t('stats.medianAnswer')" :value="duration(total.medianAnswerHours)" tone="info" :icon="PhTimer" :hint="$t('stats.silentShare', { share: formatPercent(share(total.silent, decided)) })" />
        </div>
      </section>

      <div class="charts">
        <UiCard :title="$t('stats.chartOutcomes')" :subtitle="$t('stats.chartOutcomesHint')" :icon="PhChartBar">
          <UiBarChart :title="$t('stats.chartOutcomes')" :series="outcomeSeries" :rows="outcomeRows" :height="200" />
        </UiCard>
        <UiCard :title="$t('stats.chartMoney')" :subtitle="$t('stats.chartMoneyHint')" :icon="PhCoins">
          <UiBarChart :title="$t('stats.chartMoney')" :series="moneySeries" :rows="moneyRows" :height="200" :format="(n: number) => formatMoney(Math.round(n / 100) * 100, { decimals: false })" />
        </UiCard>
      </div>

      <UiCard :title="$t('stats.table')" padding="none">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>{{ $t('stats.week') }}</th>
                <th class="num r">{{ $t('stats.sent') }}</th>
                <th class="num r">{{ $t('stats.accepted') }}</th>
                <th class="num r">{{ $t('stats.declined') }}</th>
                <th class="num r">{{ $t('stats.silent') }}</th>
                <th class="num r">{{ $t('stats.quoted') }}</th>
                <th class="num r">{{ $t('stats.wonMoney') }}</th>
                <th class="num r">{{ $t('stats.conversion') }}</th>
                <th class="num r">{{ $t('stats.medianAnswer') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in newestFirst" :key="w.weekStart">
                <td class="nowrap">{{ $t('stats.weekOf', { date: formatDate(w.weekStart, 'short') }) }}</td>
                <td class="num r">{{ w.sent }}</td>
                <td class="num r">{{ w.accepted }}</td>
                <td class="num r">{{ w.declined }}</td>
                <td class="num r">{{ w.silent }}</td>
                <td class="num r">{{ formatMoney(w.quotedCents) }}</td>
                <td class="num r strong">{{ formatMoney(w.wonCents) }}</td>
                <td class="num r">{{ formatPercent(w.conversion) }}</td>
                <td class="num r">{{ duration(w.medianAnswerHours) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </UiCard>
    </template>

    <UiEmpty v-else :icon="PhChartBar" :title="$t('stats.emptyTitle')" :text="$t('stats.emptyText')" />
  </AppPage>
</template>

<style scoped>
.section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.section__title {
  font-family: var(--font-body);
  font-size: var(--text-xs);
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.section:first-of-type .section__title {
  color: var(--text-inverse-muted);
}
.tiles {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.charts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.r {
  text-align: right;
}
@media (max-width: 1000px) {
  .tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .charts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
