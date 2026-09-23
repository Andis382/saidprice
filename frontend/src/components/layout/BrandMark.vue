<script setup lang="ts">
// The mark: a speech bubble whose right end is cut like a price tag, with a tick inside.
// What was said becomes a price that was agreed.
withDefaults(defineProps<{ size?: number; withName?: boolean; inverse?: boolean }>(), { size: 34, withName: true, inverse: true })
</script>

<template>
  <span class="brand" :class="{ 'brand--inverse': inverse }">
    <svg :width="size" :height="size" viewBox="0 0 40 40" aria-hidden="true" class="brand__mark">
      <defs>
        <linearGradient id="sp-tile" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#fde047" />
          <stop offset="1" stop-color="#facc15" />
        </linearGradient>
      </defs>
      <rect x="1" y="1" width="38" height="38" rx="10" fill="url(#sp-tile)" />
      <rect x="1.5" y="1.5" width="37" height="37" rx="9.5" fill="none" stroke="#fff" stroke-opacity=".55" />
      <path d="M12 10.5h12.6c.7 0 1.4.3 1.9.8l6.4 7c.7.8.7 1.9 0 2.7l-6.4 7c-.5.5-1.2.8-1.9.8H16.2l-5 4.2c-.6.5-1.4.1-1.4-.7v-3.8A3 3 0 0 1 7 26.3V13.5a3 3 0 0 1 3-3h2z" fill="#17171a" />
      <circle cx="27.6" cy="19.7" r="1.9" fill="#facc15" />
      <path d="M12.6 19.6l3.3 3.3 6.2-6.6" fill="none" stroke="#facc15" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span v-if="withName" class="brand__name">Said<span class="brand__accent">Price</span></span>
  </span>
</template>

<style scoped>
.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
  text-decoration: none;
}
.brand--inverse {
  color: var(--header-text);
}
.brand__mark {
  flex: none;
  filter: drop-shadow(0 4px 10px rgb(0 0 0 / 0.28));
}
.brand__name {
  font-family: var(--font-display);
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.brand__accent {
  color: var(--primary);
}
.brand--inverse .brand__accent {
  color: var(--brand-400);
}
</style>
