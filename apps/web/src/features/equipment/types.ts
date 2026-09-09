export type EquipSlot = 'weapon' | 'armor' | 'accessory'

export const SLOT_LABELS: Record<EquipSlot, string> = {
  weapon: 'Broń',
  armor: 'Pancerz',
  accessory: 'Akcesorium',
}

export const STAT_LABELS: Record<string, string> = {
  hp: 'HP',
  attack: 'ATK',
  defense: 'OBR',
  magic: 'MAG',
  speed: 'SZYB',
  crit: 'KRYT',
}

export interface PlayerEquipment {
  id: number
  slug: string
  name: string
  icon: string
  slot: EquipSlot
  rarity: string
  special: string | null
  rolledStats: Record<string, number>
  equippedOnId: number | null
  equippedOnName: string | null
}

export function statSummary(stats: Record<string, number>): string {
  return Object.entries(stats)
    .map(([k, v]) => `+${v} ${STAT_LABELS[k] ?? k}`)
    .join(' · ')
}
