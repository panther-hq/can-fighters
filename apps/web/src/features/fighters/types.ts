export interface SuggestedSkill {
  skillFamily: string
  modifier: string | null
}

export interface Fighter {
  id: number
  name: string
  description: string
  primaryClass: string
  secondaryClass: string | null
  rarity: string
  personality: string | null
  level: number
  xp: number
  traits: string[]
  visualDna: Record<string, string>
  suggestedSkills: SuggestedSkill[]
  createdAt: string | null
}

export const CLASS_LABELS_PL: Record<string, string> = {
  tank: 'Obrońca',
  fighter: 'Wojownik',
  assassin: 'Zabójca',
  ranged: 'Strzelec',
  mage: 'Mag',
  support: 'Wsparcie',
  debuffer: 'Osłabiacz',
  summoner: 'Przywoływacz',
  engineer: 'Inżynier',
  crafter: 'Rzemieślnik',
}

export const RARITY_LABELS_PL: Record<string, string> = {
  common: 'Zwykły',
  uncommon: 'Nietypowy',
  rare: 'Rzadki',
  epic: 'Epicki',
  legendary: 'Legendarny',
}

export function classLabel(value: string): string {
  return CLASS_LABELS_PL[value] ?? value
}

export function rarityLabel(value: string): string {
  return RARITY_LABELS_PL[value] ?? value
}
