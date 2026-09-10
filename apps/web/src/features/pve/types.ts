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

export interface RewardLine {
  type: string
  amount?: number
  slug?: string
  name?: string
  icon?: string
  quantity?: number
}

export interface Region {
  slug: string
  name: string
  order: number
  unlocked: boolean
  timesCleared: number
}

export type TileType = 'grass' | 'rock' | 'water'
export type ObjectKind = 'enemy' | 'treasure' | 'event' | 'boss'

export interface MapObject {
  id: string
  x: number
  y: number
  kind: ObjectKind
  elite?: boolean
  budget?: number
  enemies?: { name: string; class: string }[]
}

export interface MerchantOffer {
  id: string
  label: string
  icon: string
  price: number
  bought: boolean
}

export interface OverworldView {
  runId: number
  regionSlug: string
  status: 'active' | 'cleared' | 'abandoned'
  day: number
  movementLeft: number
  movementMax: number
  hero: { x: number; y: number }
  size: { width: number; height: number }
  terrain: TileType[]
  revealed: string[]
  objects: MapObject[]
  activeMerchant: { objectId: string; offers: MerchantOffer[] } | null
}

/** Outcome of a `move` — a plain walk, an end-of-day, or a resolved object. */
export interface MoveResult {
  type: 'move' | 'day' | 'battle' | 'loot' | 'event' | 'merchant'
  event?: string
  won?: boolean
  runEnded?: boolean
  battleId?: number
  result?: BattleResult
  rewards?: RewardLine[]
  offers?: MerchantOffer[]
  path?: [number, number][]
  run: OverworldView
}

export const OBJECT_ICON: Record<ObjectKind, string> = {
  enemy: '⚔️',
  treasure: '🎁',
  event: '❓',
  boss: '👑',
}

export const OBJECT_LABEL: Record<ObjectKind, string> = {
  enemy: 'Wróg',
  treasure: 'Skarb',
  event: 'Zdarzenie',
  boss: 'Boss',
}

export function rewardText(reward: RewardLine): string {
  switch (reward.type) {
    case 'coins':
      return `${reward.amount! >= 0 ? '+' : ''}${reward.amount} monet`
    case 'xp':
      return `+${reward.amount} dośw.`
    case 'fighterXp':
      return `+${reward.amount} dośw. wojowników`
    case 'ingredient':
      return `${reward.icon ?? '🧪'} ${reward.name ?? reward.slug} ×${reward.quantity ?? 1}`
    case 'can':
      return `${reward.icon ?? '🥫'} ${reward.name ?? 'puszka'}`
    case 'equipment':
      return `${reward.icon ?? '🛠️'} ${reward.name ?? 'przedmiot'}`
    default:
      return reward.type
  }
}
