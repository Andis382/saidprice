<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  PhArrowCounterClockwise,
  PhFilePlus,
  PhKeyboard,
  PhMicrophone,
  PhMicrophoneSlash,
  PhPaperPlaneRight,
  PhSparkle,
  PhStop,
  PhUserSound,
  PhWaveform,
} from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSpinner from '@/components/ui/UiSpinner.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import LevelMeter from '@/components/quote/LevelMeter.vue'
import { api, ApiError } from '@/lib/api'
import { extensionFor, formatDuration } from '@/lib/audio'
import type { QuoteDetail } from '@/lib/quote'
import { useRecorder } from '@/lib/recorder'
import { appendTranscript, useSpeech } from '@/lib/speech'
import { currentLocale, type Locale } from '@/i18n'
import { useToasts } from '@/stores/toasts'

const { t } = useI18n()
const router = useRouter()
const toasts = useToasts()
const recorder = useRecorder()
const speech = useSpeech()

const speakIn = ref<Locale>(currentLocale())
const transcript = ref('')
const typing = ref(false)
const busy = ref(false)
const textarea = ref<HTMLElement | null>(null)

const recording = computed(() => recorder.state.value === 'recording')
const canSubmit = computed(() => !busy.value && !recording.value && (transcript.value.trim() !== '' || recorder.blob.value !== null))
const languages = [
  { value: 'sq' as Locale, label: 'Shqip' },
  { value: 'en' as Locale, label: 'English' },
]
const statusText = computed(() => {
  switch (recorder.state.value) {
    case 'asking':
      return t('capture.asking')
    case 'recording':
      return speech.supported ? t('capture.listening') : t('capture.recordingOnly')
    case 'done':
      return t('capture.recorded', { time: formatDuration(recorder.seconds.value) })
    default:
      return t('capture.tapToRecord')
  }
})

async function toggle() {
  if (recording.value) {
    recorder.stop()
    speech.stop()
    return
  }
  typing.value = false
  const started = await recorder.start()
  if (started && speech.supported) {
    speech.start(speakIn.value, (phrase) => {
      transcript.value = appendTranscript(transcript.value, phrase)
    })
  }
}

async function typeInstead() {
  if (recording.value) {
    recorder.stop()
    speech.stop()
  }
  typing.value = true
  await nextTick()
  textarea.value?.querySelector('textarea')?.focus()
}

function again() {
  recorder.discard()
  speech.stop()
  transcript.value = ''
}

async function submit(blank = false) {
  busy.value = true
  try {
    const form = new FormData()
    const audio = recorder.blob.value
    if (!blank) {
      form.append('transcript', transcript.value.trim())
      form.append('locale', speakIn.value)
      form.append('source', audio || !typing.value ? 'VOICE' : 'TYPED')
      if (audio) form.append('audio', new File([audio], `voice-note.${extensionFor(audio.type)}`, { type: audio.type || 'audio/webm' }))
    }
    const quote = await api.upload<QuoteDetail>('/quotes', form)
    await router.push({ name: 'quote-edit', params: { id: quote.id }, query: blank ? {} : { from: 'capture' } })
  } catch (e) {
    toasts.error(e instanceof ApiError && e.status !== 0 ? e.message : t('errors.network'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AppPage :title="$t('capture.title')" :subtitle="$t('capture.subtitle')" :back="{ name: 'quotes' }" :back-label="$t('nav.quotes')">
    <div class="capture">
      <UiCard padding="lg" class="capture__card">
        <div class="lang">
          <span class="lang__label"><PhUserSound :size="18" weight="bold" aria-hidden="true" /> {{ $t('capture.speakIn') }}</span>
          <UiSegmented v-model="speakIn" :options="languages" :label="$t('capture.speakIn')" />
        </div>

        <div v-if="!typing" class="recorder">
          <button
            type="button"
            class="rec"
            :class="{ 'rec--on': recording, 'rec--done': recorder.state.value === 'done' }"
            :aria-pressed="recording"
            :aria-label="recording ? $t('capture.stop') : $t('capture.record')"
            :disabled="recorder.state.value === 'asking' || busy"
            @click="toggle"
          >
            <span class="rec__halo" :style="{ '--lvl': recorder.levels.value[recorder.levels.value.length - 1] ?? 0 }" aria-hidden="true" />
            <UiSpinner v-if="recorder.state.value === 'asking'" size="34px" />
            <PhStop v-else-if="recording" :size="40" weight="fill" aria-hidden="true" />
            <PhMicrophoneSlash v-else-if="recorder.state.value === 'denied'" :size="44" weight="fill" aria-hidden="true" />
            <PhMicrophone v-else :size="46" weight="fill" aria-hidden="true" />
          </button>
          <p class="rec__time num" aria-live="off">{{ formatDuration(recorder.seconds.value) }}</p>
          <p class="rec__status" role="status">{{ statusText }}</p>
          <LevelMeter :levels="recorder.levels.value" :active="recording" />
        </div>

        <UiNotice v-if="recorder.state.value === 'denied'" tone="warning" :title="$t('capture.deniedTitle')">
          {{ $t('capture.deniedText') }}
        </UiNotice>
        <UiNotice v-else-if="recorder.state.value === 'unsupported'" tone="info">{{ $t('capture.unsupportedRecording') }}</UiNotice>
        <UiNotice v-else-if="!speech.supported && !typing" tone="info">{{ $t('capture.unsupportedSpeech') }}</UiNotice>

        <div v-if="recorder.url.value" class="playback">
          <PhWaveform :size="20" weight="bold" aria-hidden="true" class="playback__icon" />
          <audio :src="recorder.url.value" controls preload="metadata" class="playback__audio" :aria-label="$t('capture.playback')" />
          <UiButton variant="ghost" size="sm" :icon="PhArrowCounterClockwise" @click="again">{{ $t('capture.again') }}</UiButton>
        </div>

        <div ref="textarea" class="said">
          <label for="f-transcript" class="said__label">{{ $t('capture.transcriptLabel') }}</label>
          <UiTextarea id="f-transcript" v-model="transcript" :rows="typing ? 7 : 4" :placeholder="$t('capture.transcriptPlaceholder')" describedby="f-transcript-hint" />
          <p v-if="speech.interim.value" class="said__interim" aria-live="polite">{{ speech.interim.value }}…</p>
          <p id="f-transcript-hint" class="said__hint">{{ $t('capture.transcriptHint') }}</p>
        </div>

        <div class="actions">
          <UiButton size="lg" block :icon="PhSparkle" :loading="busy" :disabled="!canSubmit" @click="submit()">
            {{ busy ? $t('capture.making') : $t('capture.make') }}
          </UiButton>
          <div class="actions__row">
            <UiButton v-if="!typing" variant="secondary" :icon="PhKeyboard" @click="typeInstead">{{ $t('capture.typeInstead') }}</UiButton>
            <UiButton v-else variant="secondary" :icon="PhMicrophone" @click="typing = false">{{ $t('capture.backToVoice') }}</UiButton>
            <UiButton variant="ghost" :icon="PhFilePlus" :disabled="busy" @click="submit(true)">{{ $t('capture.blank') }}</UiButton>
          </div>
        </div>
      </UiCard>

      <aside class="guide">
        <UiCard :title="$t('capture.exampleTitle')" :icon="PhUserSound">
          <p class="example">
            <span class="seg seg--who">{{ $t(`capture.example.${speakIn}.who`) }}</span>
            <span class="seg seg--where">{{ $t(`capture.example.${speakIn}.where`) }}</span>
            <span class="seg seg--what">{{ $t(`capture.example.${speakIn}.what`) }}</span>
            <span class="seg seg--price">{{ $t(`capture.example.${speakIn}.price`) }}</span>
            <span class="seg seg--when">{{ $t(`capture.example.${speakIn}.when`) }}</span>
          </p>
          <ul class="legend">
            <li><span class="key key--who" />{{ $t('capture.legend.who') }}</li>
            <li><span class="key key--where" />{{ $t('capture.legend.where') }}</li>
            <li><span class="key key--what" />{{ $t('capture.legend.what') }}</li>
            <li><span class="key key--price" />{{ $t('capture.legend.price') }}</li>
            <li><span class="key key--when" />{{ $t('capture.legend.when') }}</li>
          </ul>
        </UiCard>
        <ol class="steps">
          <li>
            <span class="steps__n">1</span>
            <div><p class="strong">{{ $t('capture.steps.one') }}</p><p class="small muted">{{ $t('capture.steps.oneText') }}</p></div>
          </li>
          <li>
            <span class="steps__n">2</span>
            <div><p class="strong">{{ $t('capture.steps.two') }}</p><p class="small muted">{{ $t('capture.steps.twoText') }}</p></div>
          </li>
          <li>
            <span class="steps__n"><PhPaperPlaneRight :size="14" weight="fill" aria-hidden="true" /></span>
            <div><p class="strong">{{ $t('capture.steps.three') }}</p><p class="small muted">{{ $t('capture.steps.threeText') }}</p></div>
          </li>
        </ol>
      </aside>
    </div>
  </AppPage>
</template>

<style scoped>
.capture {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
}
.capture__card :deep(.card__body) {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.lang {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.lang__label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-sm);
  font-weight: 650;
  color: var(--text-muted);
}
.recorder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 0 4px;
}
.rec {
  position: relative;
  display: grid;
  place-items: center;
  width: 128px;
  height: 128px;
  border: 0;
  border-radius: 50%;
  color: var(--on-primary);
  background: radial-gradient(circle at 50% 32%, var(--primary-fill-from), var(--primary-fill-to) 62%, var(--brand-500));
  box-shadow:
    0 0 0 1px var(--primary-edge),
    inset 0 2px 0 rgb(255 255 255 / 0.55),
    inset 0 -6px 14px rgb(161 98 7 / 0.25),
    0 18px 36px -14px rgb(202 138 4 / 0.85);
  transition:
    transform var(--duration) var(--ease),
    background var(--duration) var(--ease);
}
.rec:hover:not(:disabled) {
  transform: scale(1.03);
}
.rec:active:not(:disabled) {
  transform: scale(0.98);
}
.rec:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 1px var(--primary-edge),
    0 0 0 6px var(--focus-ring),
    0 18px 36px -14px rgb(202 138 4 / 0.85);
}
.rec:disabled {
  cursor: progress;
}
.rec--on {
  color: var(--brand-300);
  background: radial-gradient(circle at 50% 30%, var(--gray-700), var(--gray-900) 70%);
  box-shadow:
    0 0 0 1px var(--gray-900),
    inset 0 1px 0 rgb(255 255 255 / 0.15),
    0 18px 36px -14px rgb(0 0 0 / 0.6);
}
.rec__halo {
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 3px solid var(--brand-400);
  opacity: 0;
  transform: scale(1);
  pointer-events: none;
}
.rec--on .rec__halo {
  opacity: 0.85;
  transform: scale(calc(1 + var(--lvl) * 0.22));
  transition: transform 90ms linear;
}
.rec--on::after {
  content: '';
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 2px solid var(--brand-400);
  animation: ripple 1.6s var(--ease) infinite;
}
@keyframes ripple {
  from {
    opacity: 0.6;
    transform: scale(1);
  }
  to {
    opacity: 0;
    transform: scale(1.35);
  }
}
.rec__time {
  margin-top: 14px;
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  line-height: 1;
}
.rec__status {
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.playback {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 8px 8px 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface-muted);
}
.playback__icon {
  flex: none;
  color: var(--primary);
}
.playback__audio {
  flex: 1;
  min-width: 0;
  height: 40px;
}
.said {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.said__label {
  font-size: var(--text-sm);
  font-weight: 650;
}
.said__interim {
  font-size: var(--text-sm);
  font-style: italic;
  color: var(--text-subtle);
}
.said__hint {
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.actions__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}
.guide {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.example {
  font-size: var(--text-md);
  line-height: 2;
}
.seg {
  padding: 2px 6px;
  border-radius: 6px;
  box-decoration-break: clone;
  -webkit-box-decoration-break: clone;
  font-weight: 600;
}
.seg--who,
.key--who {
  background: var(--info-soft);
}
.seg--where,
.key--where {
  background: var(--surface-sunken);
}
.seg--what,
.key--what {
  background: var(--primary-soft);
}
.seg--price,
.key--price {
  background: var(--accent-soft);
}
.seg--when,
.key--when {
  background: var(--warning-soft);
}
.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin: 14px 0 0;
  padding: 0;
  list-style: none;
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.legend li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.key {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  border: 1px solid var(--border-strong);
}
.steps {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 0;
  padding: 4px 6px;
  list-style: none;
}
.steps li {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}
.steps__n {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--gray-900);
  color: var(--brand-300);
  font-size: var(--text-xs);
  font-weight: 800;
}
@media (max-width: 900px) {
  .capture {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .rec--on::after {
    animation: none;
  }
}
</style>
