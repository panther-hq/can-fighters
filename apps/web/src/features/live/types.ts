export interface LiveSkill {
  slot: number
  family: string
  params: Record<string, number>
}

export interface LiveUnit {
  id: number
  name: string
  team: 'A' | 'B'
  position: string
  class: string
  maxHp: number
  hp: number
  energy: number
  atk: number
  def: number
  mag: number
  spd: number
  crit: number
  shield: number
  stunUntilRound: number
  cooldowns: Record<string, number>
  effects: { kind: string; label?: string; untilRound?: number }[]
  skills: LiveSkill[]
}

export interface LiveBattleView {
  battleId: number
  round: number
  status: 'active' | 'finished'
  winner: 'A' | 'B' | 'draw' | null
  yourTeam: 'A' | 'B' | null
  waitingOn: number[]
  units: LiveUnit[]
}

export interface LiveActionResult {
  status: 'waiting' | 'resolved' | 'finished'
  events?: { type: string; [k: string]: unknown }[]
  battle: LiveBattleView
}

export interface QueueResult {
  status: 'queued' | 'matched'
  battleId?: number
}

export interface LiveAction {
  actorId: number
  type: 'attack' | 'skill'
  slot?: number
  targetId?: number
}
