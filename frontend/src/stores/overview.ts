import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { WeekStats } from '@/lib/quote'

type Overview = { followUps: number; drafts: number; thisWeek: WeekStats }

/** Small counts for the menu badges and the pipeline header, refreshed after actions. */
export const useOverview = defineStore('overview', () => {
  const followUps = ref(0)
  const drafts = ref(0)
  const thisWeek = ref<WeekStats | null>(null)
  let loading: Promise<void> | null = null

  function refresh(): Promise<void> {
    loading ??= api
      .get<Overview>('/overview')
      .then((o) => {
        followUps.value = o.followUps
        drafts.value = o.drafts
        thisWeek.value = o.thisWeek
      })
      .catch(() => undefined)
      .finally(() => {
        loading = null
      })
    return loading
  }

  return { followUps, drafts, thisWeek, refresh }
})
