export interface StageEnemy {
  name: string
  class: string
  position: string
}

export interface PveStage {
  slug: string
  region: string
  name: string
  order: number
  isBoss: boolean
  enemies: StageEnemy[]
  rewards: { coins: number; xp: number }
  stars: number
  cleared: boolean
  unlocked: boolean
}

export interface BattleEvent {
  sequence: number
  time: number
  type: string
  source?: number
  target?: number
  [key: string]: unknown
}

export interface BattleResult {
  winner: 'A' | 'B' | 'draw'
  duration: number
  seed: number
  version: number
  survivors: { A: number[]; B: number[] }
  events: BattleEvent[]
}

export interface RewardBundle {
  coins: number
  xp: number
  ingredients: { slug: string; name: string; icon: string; quantity: number }[]
  cans: { slug: string; name: string; icon: string; quantity: number }[]
  equipment: { id: number; slug: string; name: string; icon: string; rarity: string }[]
}

export interface FightOutcome {
  battleId: number
  won: boolean
  stars: number
  rewards: RewardBundle
  result: BattleResult
}
