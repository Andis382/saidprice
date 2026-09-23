<script setup lang="ts">
/** Bars that follow the microphone: proof it is listening, not a real waveform. */
defineProps<{ levels: number[]; active: boolean }>()
</script>

<template>
  <div class="meter" :class="{ 'meter--active': active }" aria-hidden="true">
    <span v-for="(level, i) in levels" :key="i" class="meter__bar" :style="{ '--h': `${Math.max(0.08, level)}` }" />
  </div>
</template>

<style scoped>
.meter {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
  height: 52px;
  width: 100%;
  max-width: 300px;
}
.meter__bar {
  flex: 1;
  max-width: 5px;
  height: calc(var(--h) * 100%);
  min-height: 4px;
  border-radius: 3px;
  background: var(--gray-300);
  transition: height 90ms linear;
}
.meter--active .meter__bar {
  background: linear-gradient(180deg, var(--brand-400), var(--brand-600));
}
@media (prefers-reduced-motion: reduce) {
  .meter__bar {
    transition: none;
  }
}
</style>
