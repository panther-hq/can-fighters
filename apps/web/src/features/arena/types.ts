import type { BattleResult } from '../pve/types'

export interface ArenaSummary {
  rating: number
  league: string
  rank: number
}

export interface Opponent {
  id: number
  name: string
  rating: number
  league: string
  teamPower: number
}

export interface RankRow {
  rank: number
  name: string
  rating: number
  league: string
}

export interface HistoryRow {
  battleId: number
  role: 'attack' | 'defense'
  opponent: string
  won: boolean
  ratingDelta: number
  at: string | null
}

export interface ChallengeOutcome {
  battleId: number
  won: boolean
  result: BattleResult
  rating: { before: number; after: number; delta: number }
  defender: { id: number; name: string; rating: number; league: string }
}
