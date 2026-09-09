export interface Ingredient {
  slug: string
  name: string
  icon: string
  rarity: string
  tags: string[]
}

export interface PlayerIngredient extends Ingredient {
  quantity: number
}

export interface PlayerCan {
  id: number
  slug: string
  name: string
  icon: string
  rarity: string
  quantity: number
}

export interface Inventory {
  ingredients: PlayerIngredient[]
  cans: PlayerCan[]
}

export interface OpenCanResult {
  openingId: number
  seed: number
  received: { slug: string; name: string; icon: string; quantity: number }[]
}
