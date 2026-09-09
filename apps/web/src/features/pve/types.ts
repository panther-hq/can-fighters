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

export type NodeType = 'battle' | 'elite' | 'loot' | 'merchant' | 'event' | 'boss'

export interface MapNode {
  id: string
  row: number
  col: number
  type: NodeType
  edges: string[]
}

export interface MapRow {
  row: number
  nodes: MapNode[]
}

export interface MerchantOffer {
  id: string
  label: string
  icon: string
  price: number
  bought: boolean
}

export interface RunView {
  runId: number
  regionSlug: string
  status: 'active' | 'cleared' | 'abandoned'
  currentRow: number
  clearedNodeIds: string[]
  reachableNodeIds: string[]
  activeMerchant: { nodeId: string; offers: MerchantOffer[] } | null
  map: { regionSlug: string; seed: number; rows: MapRow[] }
}

export interface VisitResult {
  type: string
  event?: string
  won?: boolean
  runEnded?: boolean
  battleId?: number
  result?: BattleResult
  rewards?: RewardLine[]
  offers?: MerchantOffer[]
  run: RunView
}

export const NODE_ICON: Record<NodeType, string> = {
  battle: '⚔️',
  elite: '💀',
  loot: '🎁',
  merchant: '🛒',
  event: '❓',
  boss: '👑',
}

export const NODE_LABEL: Record<NodeType, string> = {
  battle: 'Walka',
  elite: 'Elita',
  loot: 'Skrzynia',
  merchant: 'Kupiec',
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
