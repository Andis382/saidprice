<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhHammer, PhPlus, PhTrash, PhWrench } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiIconButton from '@/components/ui/UiIconButton.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import DecimalInput from './DecimalInput.vue'
import { currencySymbol, formatMoney } from '@/lib/format'
import { blankItem, lineTotal, totalsOf, type ItemKind, type QuoteItem } from '@/lib/quote'

/** The quote's lines: what, how many, at what price, parts or labour. Totals update as you type. */
const items = defineModel<QuoteItem[]>({ required: true })
const props = withDefaults(defineProps<{ errors?: Record<string, string[]> }>(), { errors: () => ({}) })

const { t } = useI18n()
const totals = computed(() => totalsOf(items.value))
const symbol = computed(() => currencySymbol())
const kinds = computed(() => (['PARTS', 'LABOUR', 'OTHER'] as ItemKind[]).map((k) => ({ value: k, label: t(`kinds.${k}`) })))

function add(kind: ItemKind) {
  items.value = [...items.value, blankItem(kind, kind === 'LABOUR' ? t('editor.items.labourDefault') : '')]
}

function remove(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}

function error(index: number, field: string): string | undefined {
  return props.errors[`items.${index}.${field}`]?.[0]
}
</script>

<template>
  <div class="items">
    <div v-if="items.length" class="items__head" aria-hidden="true">
      <span>{{ $t('editor.items.description') }}</span>
      <span>{{ $t('editor.items.kind') }}</span>
      <span class="r">{{ $t('editor.items.qty') }}</span>
      <span>{{ $t('editor.items.unit') }}</span>
      <span class="r">{{ $t('editor.items.unitPrice') }}</span>
      <span class="r">{{ $t('editor.items.amount') }}</span>
      <span />
    </div>

    <ul class="items__list">
      <li v-for="(item, i) in items" :key="i" class="row" :class="`row--${item.kind.toLowerCase()}`">
        <div class="row__desc">
          <label class="visually-hidden" :for="`it-${i}-desc`">{{ $t('editor.items.description') }}</label>
          <UiInput :id="`it-${i}-desc`" v-model="item.description" :invalid="!!error(i, 'description')" :placeholder="$t('editor.items.descriptionPlaceholder')" />
        </div>
        <div class="row__kind">
          <label class="visually-hidden" :for="`it-${i}-kind`">{{ $t('editor.items.kind') }}</label>
          <UiSelect :id="`it-${i}-kind`" v-model="item.kind" :options="kinds" />
        </div>
        <div class="row__qty">
          <label class="row__label" :for="`it-${i}-qty`">{{ $t('editor.items.qty') }}</label>
          <DecimalInput :id="`it-${i}-qty`" :model-value="item.qty" :invalid="!!error(i, 'qty')" @update:model-value="item.qty = $event ?? 0" />
        </div>
        <div class="row__unit">
          <label class="row__label" :for="`it-${i}-unit`">{{ $t('editor.items.unit') }}</label>
          <UiInput :id="`it-${i}-unit`" v-model="item.unit" :placeholder="$t('editor.items.unitPlaceholder')" maxlength="16" />
        </div>
        <div class="row__price">
          <label class="row__label" :for="`it-${i}-price`">{{ $t('editor.items.unitPrice') }}</label>
          <DecimalInput :id="`it-${i}-price`" :model-value="item.unitPriceCents" cents @update:model-value="item.unitPriceCents = $event ?? 0" :prefix="symbol" :invalid="!!error(i, 'unitPriceCents')" />
        </div>
        <output class="row__amount num" :for="`it-${i}-qty it-${i}-price`">{{ formatMoney(lineTotal(item.qty, item.unitPriceCents)) }}</output>
        <UiIconButton class="row__remove" :icon="PhTrash" :label="$t('editor.items.remove', { n: i + 1 })" size="sm" @click="remove(i)" />
        <p v-if="error(i, 'description') || error(i, 'qty') || error(i, 'unitPriceCents')" class="row__error">
          {{ error(i, 'description') ?? error(i, 'qty') ?? error(i, 'unitPriceCents') }}
        </p>
      </li>
    </ul>

    <p v-if="!items.length" class="items__empty">{{ $t('editor.items.empty') }}</p>

    <div class="items__add">
      <UiButton variant="soft" size="sm" :icon="PhWrench" @click="add('PARTS')">{{ $t('editor.items.addParts') }}</UiButton>
      <UiButton variant="soft" size="sm" :icon="PhHammer" @click="add('LABOUR')">{{ $t('editor.items.addLabour') }}</UiButton>
      <UiButton variant="ghost" size="sm" :icon="PhPlus" @click="add('OTHER')">{{ $t('editor.items.addOther') }}</UiButton>
    </div>

    <div class="items__sums">
      <div class="sum"><span>{{ $t('kinds.PARTS') }}</span><span class="num">{{ formatMoney(totals.parts) }}</span></div>
      <div class="sum"><span>{{ $t('kinds.LABOUR') }}</span><span class="num">{{ formatMoney(totals.labour) }}</span></div>
      <div v-if="totals.other" class="sum"><span>{{ $t('kinds.OTHER') }}</span><span class="num">{{ formatMoney(totals.other) }}</span></div>
      <div class="sum sum--total"><span>{{ $t('editor.items.total') }}</span><span class="num">{{ formatMoney(totals.total) }}</span></div>
    </div>
  </div>
</template>

<style scoped>
.items {
  container-type: inline-size;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.items__head,
.row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 120px 80px 76px 118px 96px 36px;
  gap: 8px;
  align-items: center;
}
.items__head {
  padding: 0 4px 0 10px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.items__head .r {
  text-align: right;
}
.items__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.row {
  position: relative;
  padding: 6px 4px 6px 10px;
  border-radius: var(--radius);
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.row::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--gray-300);
}
.row--parts::before {
  background: var(--brand-400);
}
.row--labour::before {
  background: var(--accent-500);
}
.row__label {
  display: none;
}
.row__amount {
  font-weight: 700;
  text-align: right;
  font-size: var(--text-sm);
  padding-right: 2px;
}
.row__error {
  grid-column: 1 / -1;
  margin: 0;
  font-size: var(--text-xs);
  color: var(--danger-text);
}
.items__empty {
  padding: 18px;
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--radius);
  color: var(--text-subtle);
  font-size: var(--text-sm);
  text-align: center;
}
.items__add {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.items__sums {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-left: auto;
  width: min(100%, 280px);
  padding-top: 8px;
  border-top: 1px solid var(--border);
}
.sum {
  display: flex;
  justify-content: space-between;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.sum--total {
  margin-top: 4px;
  font-family: var(--font-display);
  font-size: var(--text-xl);
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text);
}

/* Medium: what and kind on one line, the numbers on the next */
@container (max-width: 860px) {
  .items__head {
    display: none;
  }
  .row {
    grid-template-columns: 88px 84px minmax(0, 1fr) minmax(0, 1fr) 36px;
    gap: 8px;
    padding: 10px 10px 10px 14px;
  }
  .row__desc {
    grid-column: 1 / 4;
  }
  .row__kind {
    grid-column: 4 / 6;
  }
  .row__label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 650;
    color: var(--text-subtle);
  }
  .row__amount {
    align-self: end;
    padding-bottom: 12px;
    font-size: var(--text-md);
  }
  .row__remove {
    align-self: end;
    margin-bottom: 4px;
  }
}

/* Narrow (phones): stacked */
@container (max-width: 480px) {
  .row {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    padding: 12px 12px 12px 14px;
  }
  .row__desc {
    grid-column: 1 / 6;
  }
  .row__remove {
    grid-column: 6;
    grid-row: 1;
    justify-self: end;
    align-self: center;
    margin: 0;
  }
  .row__kind {
    grid-column: 1 / 4;
  }
  .row__qty {
    grid-column: 4 / 5;
  }
  .row__unit {
    grid-column: 5 / 7;
  }
  .row__price {
    grid-column: 1 / 4;
  }
  .row__amount {
    grid-column: 4 / 7;
  }
  .row__kind {
    align-self: end;
  }
}
</style>
