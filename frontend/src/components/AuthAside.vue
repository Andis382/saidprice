<script setup lang="ts">
import { PhArrowDown, PhBellRinging, PhChartLineUp, PhCheckCircle, PhMicrophone, PhPlay, PhWhatsappLogo } from '@phosphor-icons/vue'
import { formatMoney } from '@/lib/format'

// The sign-in page's left panel: the promise, and what it looks like in practice.
const points = [
  { icon: PhMicrophone, key: 'aside.points.say' },
  { icon: PhWhatsappLogo, key: 'aside.points.send' },
  { icon: PhBellRinging, key: 'aside.points.nudge' },
  { icon: PhChartLineUp, key: 'aside.points.stats' },
]
const bars = [0.3, 0.55, 0.8, 0.45, 0.95, 0.6, 0.35, 0.7, 0.9, 0.5, 0.25, 0.65, 0.85, 0.4, 0.6, 0.3, 0.75, 0.5]
</script>

<template>
  <div class="aside">
    <h2 class="aside__title">{{ $t('app.tagline') }}</h2>
    <ul class="aside__points">
      <li v-for="p in points" :key="p.key">
        <span class="aside__icon"><component :is="p.icon" :size="18" weight="bold" aria-hidden="true" /></span>
        {{ $t(p.key) }}
      </li>
    </ul>

    <figure class="artifact" aria-hidden="true">
      <div class="voice">
        <span class="voice__play"><PhPlay :size="14" weight="fill" /></span>
        <span class="voice__wave">
          <span v-for="(b, i) in bars" :key="i" :style="{ height: `${Math.round(b * 100)}%` }" />
        </span>
        <span class="voice__time">0:14</span>
      </div>
      <p class="voice__said">“{{ $t('aside.artifact.said') }}”</p>
      <span class="arrow"><PhArrowDown :size="16" weight="bold" /></span>
      <div class="paper">
        <div class="paper__head">
          <span class="paper__label">{{ $t('document.quote') }}</span>
          <span class="paper__no">Q-2026-0042</span>
        </div>
        <p class="paper__title">{{ $t('aside.artifact.title') }}</p>
        <div class="paper__line"><span>{{ $t('aside.artifact.parts') }}</span><span>{{ formatMoney(8500, { currency: 'EUR' }) }}</span></div>
        <div class="paper__line"><span>{{ $t('aside.artifact.labour') }}</span><span>{{ formatMoney(6000, { currency: 'EUR' }) }}</span></div>
        <div class="paper__total"><span>{{ $t('document.total') }}</span><span>{{ formatMoney(14500, { currency: 'EUR' }) }}</span></div>
        <div class="paper__foot">
          <span>{{ $t('aside.artifact.when') }}</span>
          <span class="paper__ok"><PhCheckCircle :size="14" weight="fill" /> {{ $t('status.ACCEPTED') }}</span>
        </div>
      </div>
    </figure>
  </div>
</template>

<style scoped>
.aside {
  display: flex;
  flex-direction: column;
  gap: 26px;
}
.aside__title {
  color: var(--header-text);
  font-size: clamp(1.9rem, 1.2rem + 1.9vw, 2.8rem);
  font-weight: 800;
  letter-spacing: -0.035em;
  line-height: 1.05;
  max-width: 14ch;
}
.aside__points {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--text-inverse-muted);
  font-size: var(--text-sm);
}
.aside__points li {
  display: flex;
  gap: 12px;
  align-items: center;
}
.aside__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 9px;
  background: rgb(250 204 21 / 0.14);
  border: 1px solid rgb(250 204 21 / 0.28);
  color: var(--brand-300);
}
.artifact {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  margin: 6px 0 0;
  max-width: 340px;
}
.voice {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 232px;
  padding: 8px 12px 8px 8px;
  border-radius: 4px 16px 16px 16px;
  background: rgb(255 255 255 / 0.1);
  border: 1px solid rgb(255 255 255 / 0.14);
  backdrop-filter: blur(6px);
}
.voice__play {
  display: grid;
  place-items: center;
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--brand-400);
  color: var(--on-primary);
}
.voice__wave {
  display: flex;
  align-items: center;
  gap: 2px;
  flex: 1;
  height: 22px;
}
.voice__wave span {
  flex: 1;
  border-radius: 2px;
  background: rgb(255 255 255 / 0.55);
}
.voice__time {
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  color: var(--text-inverse-muted);
}
.voice__said {
  max-width: 300px;
  font-size: 12.5px;
  font-style: italic;
  color: var(--text-inverse-muted);
}
.arrow {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  margin-left: 16px;
  border-radius: 50%;
  background: var(--brand-400);
  color: var(--on-primary);
  box-shadow: 0 6px 16px -6px rgb(250 204 21 / 0.6);
}
.paper {
  position: relative;
  width: 100%;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--surface);
  color: var(--text);
  box-shadow: 0 24px 48px -16px rgb(0 0 0 / 0.55);
  transform: rotate(-1.2deg);
  overflow: hidden;
}
.paper::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: var(--brand-400);
}
.paper__head {
  display: flex;
  justify-content: space-between;
  font-size: 10.5px;
  font-weight: 750;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.paper__label {
  color: var(--primary);
}
.paper__no {
  color: var(--text-subtle);
  letter-spacing: 0.02em;
}
.paper__title {
  margin: 6px 0 8px;
  font-family: var(--font-display);
  font-size: var(--text-md);
  font-weight: 800;
  letter-spacing: -0.02em;
}
.paper__line,
.paper__total,
.paper__foot {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}
.paper__line {
  padding: 5px 0;
  border-bottom: 1px solid var(--border);
  color: var(--text-muted);
}
.paper__total {
  margin-top: 8px;
  padding: 7px 10px;
  border-radius: 8px;
  background: var(--brand-400);
  color: var(--on-primary);
  font-weight: 800;
}
.paper__foot {
  margin-top: 10px;
  align-items: center;
  color: var(--text-subtle);
}
.paper__ok {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--success-soft);
  color: var(--success-text);
  font-weight: 700;
}
</style>
