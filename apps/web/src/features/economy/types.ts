export interface RewardLine {
  type: 'coins' | 'xp' | 'ingredient' | 'ingredients' | 'can' | 'equipment'
  amount?: number
  slug?: string
  name?: string
  icon?: string
  quantity?: number
  slot?: string
  rarity?: string
  items?: RewardLine[]
}

export interface DailyStatus {
  canClaim: boolean
  streak: number
  bestStreak: number
  day: number
  reward: RewardLine
  ladder: Record<string, RewardLine>
}

export interface DailyClaimResult {
  streak: number
  day: number
  reward: RewardLine
}

export interface ShopOffer {
  id: string
  kind: 'can' | 'ingredient' | 'equipment'
  label: string
  icon: string
  price: number
  bought: boolean
}

export interface ShopState {
  restockedOn: string
  offers: ShopOffer[]
}

export interface BuyResult {
  offer: ShopOffer
  reward: RewardLine
  coins: number
}

export interface Collection {
  ingredientsFound: number
  ingredientsTotal: number
  fighters: number
  regionsCleared: number
  regionsTotal: number
  bestDailyStreak: number
  arenaRating: number
  league: string
}

export function rewardLabel(reward: RewardLine): string {
  switch (reward.type) {
    case 'coins':
      return `+${reward.amount} monet`
    case 'xp':
      return `+${reward.amount} dośw.`
    case 'ingredient':
      return `${reward.icon ?? ''} ${reward.name ?? reward.slug} ×${reward.quantity ?? 1}`
    case 'ingredients':
      return (reward.items ?? []).map(rewardLabel).join(', ') || 'składniki'
    case 'can':
      return `${reward.icon ?? '🥫'} ${reward.name ?? 'puszka'}`
    case 'equipment':
      return `${reward.icon ?? '🛠️'} ${reward.name ?? 'przedmiot'}`
    default:
      return 'nagroda'
  }
}
