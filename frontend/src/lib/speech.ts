import { onBeforeUnmount, ref } from 'vue'

/**
 * Live transcript with the browser's Web Speech API (Chrome, Edge, Safari). Where it is
 * missing the recorder still records, and the transcript is typed instead.
 */

type Alternative = { transcript: string }
type Result = { isFinal: boolean; 0: Alternative; length: number }
type ResultEvent = { resultIndex: number; results: ArrayLike<Result> }
type Recognition = {
  lang: string
  continuous: boolean
  interimResults: boolean
  start(): void
  stop(): void
  abort(): void
  onresult: ((event: ResultEvent) => void) | null
  onend: (() => void) | null
  onerror: ((event: { error: string }) => void) | null
}
type RecognitionConstructor = new () => Recognition

function recognitionClass(): RecognitionConstructor | null {
  const w = window as unknown as { SpeechRecognition?: RecognitionConstructor; webkitSpeechRecognition?: RecognitionConstructor }
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null
}

/** BCP 47 tag for the language the tradesperson speaks in. */
export function speechLang(locale: 'en' | 'sq'): string {
  return locale === 'sq' ? 'sq-AL' : 'en-GB'
}

/**
 * Adds a finished phrase to what is already there: one space between phrases, the first
 * letter capitalised, and what the person typed in between kept as it is.
 */
export function appendTranscript(current: string, phrase: string): string {
  const addition = phrase.trim().replace(/\s+/g, ' ')
  if (!addition) return current
  const base = current.replace(/\s+$/, '')
  if (!base) return addition.charAt(0).toUpperCase() + addition.slice(1)
  const joiner = /[,.;:!?]$/.test(base) ? ' ' : ', '
  return base + joiner + addition
}

export function useSpeech() {
  const supported = typeof window !== 'undefined' && recognitionClass() !== null
  const listening = ref(false)
  const interim = ref('')
  const error = ref<'denied' | 'failed' | null>(null)
  let recognition: Recognition | null = null
  let wanted = false

  /** Calls onPhrase with each finished phrase until stop(). Chrome stops after a pause; we restart it. */
  function start(locale: 'en' | 'sq', onPhrase: (phrase: string) => void) {
    const Recognition = recognitionClass()
    if (!Recognition) return
    stop()
    error.value = null
    recognition = new Recognition()
    recognition.lang = speechLang(locale)
    recognition.continuous = true
    recognition.interimResults = true
    recognition.onresult = (event) => {
      let pending = ''
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const result = event.results[i]
        if (!result) continue
        if (result.isFinal) onPhrase(result[0].transcript)
        else pending += result[0].transcript
      }
      interim.value = pending
    }
    recognition.onerror = (event) => {
      if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
        wanted = false
        error.value = 'denied'
      } else if (event.error !== 'no-speech' && event.error !== 'aborted') {
        error.value = 'failed'
      }
    }
    recognition.onend = () => {
      if (wanted && recognition) {
        try {
          recognition.start()
          return
        } catch {
          wanted = false
        }
      }
      listening.value = false
      interim.value = ''
    }
    wanted = true
    try {
      recognition.start()
      listening.value = true
    } catch {
      wanted = false
      error.value = 'failed'
    }
  }

  function stop() {
    wanted = false
    interim.value = ''
    if (recognition) {
      recognition.onresult = null
      recognition.stop()
    }
    recognition = null
    listening.value = false
  }

  onBeforeUnmount(stop)

  return { supported, listening, interim, error, start, stop }
}
