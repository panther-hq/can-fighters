import type { Fighter } from '../fighters/types'

export type TeamPosition = 'front' | 'middle' | 'back'

export const POSITIONS: { id: TeamPosition; label: string }[] = [
  { id: 'front', label: 'Przód' },
  { id: 'middle', label: 'Środek' },
  { id: 'back', label: 'Tył' },
]

export interface TeamMember {
  position: TeamPosition
  fighter: Fighter
}

export interface Team {
  id: number
  type: string
  name: string | null
  members: TeamMember[]
}
