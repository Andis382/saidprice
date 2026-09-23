import { onBeforeUnmount, ref } from 'vue'
import { levelFromSamples, pickMimeType, pushLevel } from './audio'

export const MAX_SECONDS = 180
export const METER_BARS = 36

type State = 'idle' | 'asking' | 'recording' | 'done' | 'denied' | 'unsupported'

/**
 * Records a voice note with MediaRecorder and drives a level meter from the microphone.
 * The recording stops by itself after three minutes: a quote said at the door is short.
 */
export function useRecorder() {
  const state = ref<State>('idle')
  const seconds = ref(0)
  const levels = ref<number[]>(Array(METER_BARS).fill(0))
  const blob = ref<Blob | null>(null)
  const url = ref<string | null>(null)

  let stream: MediaStream | null = null
  let recorder: MediaRecorder | null = null
  let context: AudioContext | null = null
  let frame = 0
  let timer = 0
  let chunks: Blob[] = []

  const supported = typeof window !== 'undefined' && typeof MediaRecorder !== 'undefined' && !!navigator.mediaDevices?.getUserMedia

  async function start(): Promise<boolean> {
    if (!supported) {
      state.value = 'unsupported'
      return false
    }
    discard()
    state.value = 'asking'
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })
    } catch {
      state.value = 'denied'
      return false
    }
    const mimeType = pickMimeType((type) => MediaRecorder.isTypeSupported(type))
    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)
    chunks = []
    recorder.ondataavailable = (e) => {
      if (e.data.size) chunks.push(e.data)
    }
    recorder.onstop = () => {
      blob.value = new Blob(chunks, { type: recorder?.mimeType || mimeType || 'audio/webm' })
      url.value = URL.createObjectURL(blob.value)
      state.value = 'done'
      release()
    }
    recorder.start(250)
    meter(stream)

    const started = performance.now()
    seconds.value = 0
    timer = window.setInterval(() => {
      seconds.value = (performance.now() - started) / 1000
      if (seconds.value >= MAX_SECONDS) stop()
    }, 200)
    state.value = 'recording'
    return true
  }

  function meter(source: MediaStream) {
    try {
      context = new AudioContext()
      const analyser = context.createAnalyser()
      analyser.fftSize = 1024
      context.createMediaStreamSource(source).connect(analyser)
      const samples = new Uint8Array(analyser.fftSize)
      let last = 0
      const tick = (now: number) => {
        if (now - last > 60) {
          analyser.getByteTimeDomainData(samples)
          levels.value = pushLevel(levels.value, levelFromSamples(samples), METER_BARS)
          last = now
        }
        frame = requestAnimationFrame(tick)
      }
      frame = requestAnimationFrame(tick)
    } catch {
      // The meter is decoration; recording works without it.
    }
  }

  function stop() {
    window.clearInterval(timer)
    cancelAnimationFrame(frame)
    if (recorder && recorder.state !== 'inactive') recorder.stop()
    else release()
  }

  function release() {
    stream?.getTracks().forEach((t) => t.stop())
    stream = null
    context?.close().catch(() => undefined)
    context = null
    levels.value = Array(METER_BARS).fill(0)
  }

  /** Throws the recording away and goes back to the start. */
  function discard() {
    stop()
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = null
    blob.value = null
    seconds.value = 0
    state.value = 'idle'
  }

  onBeforeUnmount(() => {
    stop()
    if (url.value) URL.revokeObjectURL(url.value)
  })

  return { state, seconds, levels, blob, url, supported, start, stop, discard }
}
