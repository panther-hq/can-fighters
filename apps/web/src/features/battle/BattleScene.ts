import Phaser from 'phaser'
import type { BattleEvent } from '../pve/types'

interface SceneUnit {
  id: number
  team: 'A' | 'B'
  name: string
  maxHp: number
  hp: number
  dead: boolean
  x: number
  y: number
  body: Phaser.GameObjects.Arc
  label: Phaser.GameObjects.Text
  barBg: Phaser.GameObjects.Rectangle
  bar: Phaser.GameObjects.Rectangle
}

export interface BattleSceneData {
  events: BattleEvent[]
  speed: number
  onComplete: (winner: string) => void
}

const W = 360
const H = 420
const BAR_W = 54
const POS_ORDER: Record<string, number> = { back: 0, middle: 1, front: 2 }

export class BattleScene extends Phaser.Scene {
  private evts: BattleEvent[] = []
  private speed = 420
  private cursor = 0
  private units = new Map<number, SceneUnit>()
  private onComplete: (winner: string) => void = () => {}

  constructor() {
    super('battle')
  }

  init(data: BattleSceneData) {
    this.evts = data.events
    this.speed = data.speed
    this.onComplete = data.onComplete
    this.cursor = 0
    this.units.clear()
  }

  create() {
    this.cameras.main.setBackgroundColor('#0f1420')
    this.add
      .text(W / 2, 12, 'PRZECIWNIK', { fontSize: '11px', color: '#8b95a7' })
      .setOrigin(0.5, 0)
    this.add
      .text(W / 2, H - 22, 'TWOJA DRUŻYNA', { fontSize: '11px', color: '#8b95a7' })
      .setOrigin(0.5, 0)

    const spawns = this.evts.filter((e) => e.type === 'spawn')
    const byTeam: Record<'A' | 'B', BattleEvent[]> = { A: [], B: [] }
    for (const s of spawns) byTeam[(s.team as 'A' | 'B') ?? 'A'].push(s)

    for (const team of ['A', 'B'] as const) {
      const list = byTeam[team]
      list.sort(
        (a, b) =>
          (POS_ORDER[(a.position as string) ?? 'middle'] ?? 1) -
          (POS_ORDER[(b.position as string) ?? 'middle'] ?? 1),
      )
      list.forEach((s, i) => {
        const gap = W / (list.length + 1)
        const x = gap * (i + 1)
        const y = team === 'B' ? 70 : H - 96
        this.spawn(Number(s.target), team, String(s.name ?? '?'), Number(s.hp ?? 1), x, y)
      })
    }

    this.time.delayedCall(this.speed, () => this.step())
  }

  private spawn(id: number, team: 'A' | 'B', name: string, maxHp: number, x: number, y: number) {
    const color = team === 'A' ? 0x3b6cff : 0xe5484d
    const body = this.add.circle(x, y, 20, color)
    const label = this.add
      .text(x, y + 26, name.length > 14 ? `${name.slice(0, 13)}…` : name, {
        fontSize: '9px',
        color: '#c8cfdb',
      })
      .setOrigin(0.5, 0)
    const barBg = this.add.rectangle(x, y - 30, BAR_W, 6, 0x232a38)
    const bar = this.add.rectangle(x - BAR_W / 2, y - 30, BAR_W, 6, 0x35c46a).setOrigin(0, 0.5)

    this.units.set(id, { id, team, name, maxHp, hp: maxHp, dead: false, x, y, body, label, barBg, bar })
  }

  private step() {
    if (this.cursor >= this.evts.length) {
      return
    }
    const event = this.evts[this.cursor++]
    this.render(event)

    if (event.type === 'battle_end') {
      this.time.delayedCall(400, () => this.onComplete(String(event.winner ?? 'draw')))
      return
    }
    this.time.delayedCall(this.speed, () => this.step())
  }

  private render(event: BattleEvent) {
    const source = event.source != null ? this.units.get(Number(event.source)) : undefined
    const target = event.target != null ? this.units.get(Number(event.target)) : undefined

    switch (event.type) {
      case 'damage': {
        if (!target) break
        const amount = Number(event.damage ?? 0)
        target.hp = Math.max(0, target.hp - amount)
        this.updateBar(target)
        this.flash(target, 0xffffff)
        this.float(target, `-${amount}`, event.crit ? '#ff5d5d' : '#ffb0b0', event.crit ? 15 : 12)
        if (source && event.cause !== 'poison' && event.cause !== 'bleed') this.lunge(source, target)
        break
      }
      case 'heal': {
        if (!target) break
        target.hp = Math.min(target.maxHp, target.hp + Number(event.amount ?? 0))
        this.updateBar(target)
        this.float(target, `+${event.amount ?? 0}`, '#43d17f', 12)
        break
      }
      case 'death':
        if (target) {
          target.dead = true
          target.body.setFillStyle(0x3a4152)
          target.body.setAlpha(0.35)
          target.label.setAlpha(0.4)
        }
        break
      case 'skill_used':
        if (source) this.float(source, String(event.skill ?? 'skill'), '#6fd0ff', 10)
        break
      case 'effect':
        if (target) this.float(target, String(event.effect ?? 'efekt'), '#f5c451', 10)
        break
      case 'stunned':
        if (target) this.float(target, 'ogłuszony', '#f5c451', 10)
        break
      default:
        break
    }
  }

  private updateBar(unit: SceneUnit) {
    const ratio = Phaser.Math.Clamp(unit.hp / unit.maxHp, 0, 1)
    unit.bar.width = BAR_W * ratio
    unit.bar.setFillStyle(ratio > 0.5 ? 0x35c46a : ratio > 0.25 ? 0xf5a623 : 0xe5484d)
  }

  private flash(unit: SceneUnit, color: number) {
    const original = unit.team === 'A' ? 0x3b6cff : 0xe5484d
    if (unit.dead) return
    unit.body.setFillStyle(color)
    this.time.delayedCall(110, () => !unit.dead && unit.body.setFillStyle(original))
  }

  private lunge(from: SceneUnit, to: SceneUnit) {
    if (from.dead) return
    const dx = Phaser.Math.Clamp((to.x - from.x) * 0.18, -10, 10)
    const dy = Phaser.Math.Clamp((to.y - from.y) * 0.18, -10, 10)
    this.tweens.add({
      targets: from.body,
      x: from.x + dx,
      y: from.y + dy,
      duration: 90,
      yoyo: true,
      onComplete: () => {
        from.body.x = from.x
        from.body.y = from.y
      },
    })
  }

  private float(unit: SceneUnit, text: string, color: string, size: number) {
    const t = this.add
      .text(unit.x, unit.y - 12, text, { fontSize: `${size}px`, color, fontStyle: 'bold' })
      .setOrigin(0.5, 1)
    this.tweens.add({
      targets: t,
      y: unit.y - 44,
      alpha: 0,
      duration: Math.max(500, this.speed * 1.4),
      onComplete: () => t.destroy(),
    })
  }
}

export function startBattleGame(parent: HTMLElement, data: BattleSceneData): Phaser.Game {
  const game = new Phaser.Game({
    type: Phaser.AUTO,
    parent,
    width: W,
    height: H,
    transparent: true,
    scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_HORIZONTALLY },
    scene: BattleScene,
    audio: { noAudio: true },
  })
  game.scene.start('battle', data)
  return game
}
