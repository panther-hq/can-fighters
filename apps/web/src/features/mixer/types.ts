import type { Fighter, SuggestedSkill } from '../fighters/types'

export interface MixConcept {
  name: string
  description: string
  primaryClass: string
  secondaryClass: string | null
  rarity: string
  traits: string[]
  personality: string | null
  visualDna: Record<string, string>
  suggestedSkills: SuggestedSkill[]
}

export type MixStatus = 'processing' | 'completed' | 'failed'

export interface MixRequestView {
  mixId: number
  status: MixStatus
  error: string | null
  fighter: Fighter | null
}

export interface MixIngredient {
  slug: string
  quantity: number
}
