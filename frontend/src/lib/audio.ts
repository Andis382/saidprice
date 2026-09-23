/** Small pure helpers for the voice recorder. */

/**
 * Loudness of one analyser frame (unsigned 8-bit samples centred on 128) as 0..1.
 * The square root lifts quiet speech so the meter moves when someone talks normally.
 */
export function levelFromSamples(samples: ArrayLike<number>): number {
  if (!samples.length) return 0
  let sum = 0
  for (let i = 0; i < samples.length; i++) {
    const v = ((samples[i] ?? 128) - 128) / 128
    sum += v * v
  }
  const rms = Math.sqrt(sum / samples.length)
  return Math.min(1, Math.sqrt(rms) * 1.4)
}

/** Keeps the last `size` levels, newest last: the moving "waveform" under the button. */
export function pushLevel(history: number[], level: number, size: number): number[] {
  const next = [...history, level]
  return next.length > size ? next.slice(next.length - size) : next
}

/** 7 -> "0:07", 65 -> "1:05". */
export function formatDuration(totalSeconds: number): string {
  const s = Math.max(0, Math.floor(totalSeconds))
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

/** The first recording format this browser can make: Opus in WebM (Chrome, Firefox), else MP4 (Safari). */
export function pickMimeType(isSupported: (type: string) => boolean): string {
  const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']
  return candidates.find((type) => isSupported(type)) ?? ''
}

export function extensionFor(mimeType: string): string {
  if (mimeType.includes('mp4')) return 'm4a'
  if (mimeType.includes('ogg')) return 'ogg'
  return 'webm'
}
