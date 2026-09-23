<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCalendarBlank, PhHourglassMedium, PhUser } from '@phosphor-icons/vue'
import { formatDate, formatMoney, formatNumber } from '@/lib/format'
import { lineTotal, type QuoteItem, type Totals } from '@/lib/quote'

/**
 * The quote as the customer reads it: on their link, in the PDF, and as the live preview
 * next to the editor. Labels follow `lang`, the customer's language.
 */
const props = withDefaults(
  defineProps<{
    business: { name: string; phone?: string | null; trade?: string | null }
    number: string
    title: string
    customerName?: string | null
    items: QuoteItem[]
    totals: Totals
    proposedFor?: string | null
    validUntil?: string | null
    date?: string | null
    notes?: string | null
    terms?: string | null
    lang?: 'en' | 'sq'
  }>(),
  { customerName: null, proposedFor: null, validUntil: null, date: null, notes: null, terms: null, lang: undefined },
)

const { t, locale } = useI18n()
const l = (key: string, params: Record<string, unknown> = {}) => t(`document.${key}`, params, { locale: props.lang ?? locale.value })
const showSubtotals = computed(() => props.totals.parts > 0 && props.totals.labour > 0)
</script>

<template>
  <article class="doc">
    <header class="doc__head">
      <div class="doc__business">
        <p class="doc__name">{{ business.name }}</p>
        <p v-if="business.trade" class="doc__trade">{{ business.trade }}</p>
      </div>
      <div class="doc__id">
        <p class="doc__label">{{ l('quote') }}</p>
        <p class="doc__number num">{{ number }}</p>
      </div>
    </header>

    <dl class="doc__meta">
      <div>
        <dt><PhUser :size="14" weight="bold" aria-hidden="true" /> {{ l('for') }}</dt>
        <dd>{{ customerName || '—' }}</dd>
      </div>
      <div>
        <dt><PhCalendarBlank :size="14" weight="bold" aria-hidden="true" /> {{ l('proposed') }}</dt>
        <dd>{{ proposedFor || l('toBeAgreed') }}</dd>
      </div>
      <div>
        <dt><PhHourglassMedium :size="14" weight="bold" aria-hidden="true" /> {{ l('validUntil') }}</dt>
        <dd>{{ validUntil ? formatDate(validUntil) : '—' }}</dd>
      </div>
    </dl>

    <h3 class="doc__title">{{ title }}</h3>

    <ul class="doc__items">
      <li v-for="(item, i) in items" :key="item.id ?? i" class="line">
        <div class="line__what">
          <span class="line__desc">{{ item.description || '—' }}</span>
          <span class="line__kind" :class="`line__kind--${item.kind.toLowerCase()}`">{{ l(`kinds.${item.kind}`) }}</span>
        </div>
        <span class="line__qty num">
          <template v-if="item.qty !== 1 || item.unit">{{ formatNumber(item.qty, 2) }}{{ item.unit ? ` ${item.unit}` : '' }} × {{ formatMoney(item.unitPriceCents) }}</template>
        </span>
        <span class="line__amount num">{{ formatMoney(lineTotal(item.qty, item.unitPriceCents)) }}</span>
      </li>
      <li v-if="!items.length" class="line line--empty">{{ l('noLines') }}</li>
    </ul>

    <div class="doc__sums">
      <template v-if="showSubtotals">
        <div class="sum"><span>{{ l('kinds.PARTS') }}</span><span class="num">{{ formatMoney(totals.parts) }}</span></div>
        <div class="sum"><span>{{ l('kinds.LABOUR') }}</span><span class="num">{{ formatMoney(totals.labour) }}</span></div>
      </template>
      <div class="sum sum--total">
        <span>{{ l('total') }}</span>
        <span class="num">{{ formatMoney(totals.total) }}</span>
      </div>
    </div>

    <section v-if="notes" class="doc__block">
      <h4>{{ l('notes') }}</h4>
      <p>{{ notes }}</p>
    </section>
    <section v-if="terms" class="doc__block doc__block--terms">
      <h4>{{ l('terms') }}</h4>
      <p>{{ terms }}</p>
    </section>
    <p v-if="date" class="doc__date">{{ l('dated', { date: formatDate(date, 'long') }) }}</p>
  </article>
</template>

<style scoped>
.doc {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px 22px 20px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md), var(--highlight);
  overflow: hidden;
}
.doc::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: linear-gradient(90deg, var(--brand-400), var(--brand-300));
}
.doc__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding-top: 4px;
}
.doc__name {
  font-family: var(--font-display);
  font-size: var(--text-lg);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.doc__trade {
  margin-top: 2px;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.doc__id {
  text-align: right;
  flex: none;
}
.doc__label {
  font-size: 10.5px;
  font-weight: 750;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--primary);
}
.doc__number {
  font-family: var(--font-display);
  font-size: var(--text-md);
  font-weight: 750;
}
.doc__meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
  padding: 12px 14px;
  background: var(--surface-muted);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.doc__meta dt {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.doc__meta dd {
  margin: 3px 0 0;
  font-size: var(--text-sm);
  font-weight: 650;
  overflow-wrap: anywhere;
}
.doc__title {
  font-size: var(--text-xl);
  font-weight: 800;
  letter-spacing: -0.025em;
}
.doc__items {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
  border-top: 1.5px solid var(--text);
}
.line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: baseline;
  gap: 4px 16px;
  padding: 11px 0;
  border-bottom: 1px solid var(--border);
}
.line--empty {
  display: block;
  color: var(--text-subtle);
  font-size: var(--text-sm);
}
.line__what {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
  min-width: 0;
}
.line__desc {
  font-weight: 600;
  font-size: var(--text-sm);
}
.line__kind {
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  padding: 1px 7px;
  border-radius: var(--radius-pill);
  background: var(--surface-sunken);
  color: var(--text-muted);
}
.line__kind--parts {
  background: var(--primary-soft);
  color: var(--primary-soft-text);
}
.line__kind--labour {
  background: var(--accent-soft);
  color: var(--accent-soft-text);
}
.line__qty {
  font-size: var(--text-xs);
  color: var(--text-subtle);
  white-space: nowrap;
}
.line__amount {
  font-weight: 700;
  font-size: var(--text-sm);
  white-space: nowrap;
  text-align: right;
}
.doc__sums {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-left: auto;
  width: min(100%, 300px);
}
.sum {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.sum--total {
  margin-top: 4px;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  background: linear-gradient(180deg, var(--primary-fill-from), var(--primary-fill-to));
  box-shadow: var(--primary-inner), 0 6px 16px -10px rgb(202 138 4 / 0.8);
  color: var(--on-primary);
  font-family: var(--font-display);
  font-size: var(--text-lg);
  font-weight: 800;
}
.doc__block h4 {
  margin-bottom: 4px;
  font-family: var(--font-body);
  font-size: 10.5px;
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.doc__block p {
  font-size: var(--text-sm);
  white-space: pre-line;
}
.doc__block--terms p {
  color: var(--text-muted);
  font-size: var(--text-xs);
  line-height: 1.6;
}
.doc__date {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
@media (max-width: 520px) {
  .doc {
    padding: 18px 16px 16px;
  }
  .doc__meta {
    grid-template-columns: 1fr 1fr;
  }
  .doc__meta > div:first-child {
    grid-column: 1 / -1;
  }
  .line {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .line__qty {
    grid-column: 1;
    grid-row: 2;
  }
  .line__amount {
    grid-column: 2;
    grid-row: 1;
  }
}
</style>
