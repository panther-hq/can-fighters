import Phaser from 'phaser'
import { OBJECT_ICON, type OverworldView } from './types'

export interface OverworldSceneData {
  view: OverworldView
  walkPath: [number, number][] | null
  onTileClick: (x: number, y: number) => void
  onWalkDone: () => void
}

const TS = 34 // tile size in px
const DIRS: [number, number][] = [
  [1, 0],
  [-1, 0],
  [0, 1],
  [0, -1],
]

export class OverworldScene extends Phaser.Scene {
  private opts!: OverworldSceneData
  private hero!: Phaser.GameObjects.Text
  private walking = false

  constructor() {
    super('overworld')
  }

  init(opts: OverworldSceneData) {
    this.opts = opts
    this.walking = false
  }

  create() {
    const { view, walkPath } = this.opts
    const { width, height } = view.size
    this.cameras.main.setBackgroundColor('#0b0e14')

    const revealed = new Set(view.revealed)
    const { reach, targets } = this.computeReach(view)
    const showHints = !walkPath

    const g = this.add.graphics()
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width; x++) {
        const px = x * TS
        const py = y * TS
        const key = `${x},${y}`
        if (!revealed.has(key)) {
          g.fillStyle(0x0b0e14, 1)
          g.fillRect(px, py, TS, TS)
          g.lineStyle(1, 0x000000, 0.25)
          g.strokeRect(px, py, TS, TS)
          continue
        }
        const tile = view.terrain[y * width + x]
        const base =
          tile === 'rock'
            ? 0x3d3d46
            : tile === 'water'
              ? 0x1f3f5e
              : (x + y) % 2 === 0
                ? 0x24503a
                : 0x2b5a42
        g.fillStyle(base, 1)
        g.fillRect(px, py, TS, TS)
        if (showHints && reach.has(key)) {
          g.fillStyle(0xffffff, 0.1)
          g.fillRect(px, py, TS, TS)
        }
        g.lineStyle(1, 0x000000, 0.16)
        g.strokeRect(px, py, TS, TS)
        if (showHints && targets.has(key)) {
          g.lineStyle(2, 0xf2c14e, 0.95)
          g.strokeRect(px + 1.5, py + 1.5, TS - 3, TS - 3)
        }
      }
    }

    for (const obj of view.objects) {
      if (!revealed.has(`${obj.x},${obj.y}`)) continue
      const icon = obj.kind === 'enemy' && obj.elite ? '💀' : OBJECT_ICON[obj.kind]
      this.add
        .text(obj.x * TS + TS / 2, obj.y * TS + TS / 2, icon, { fontSize: '19px' })
        .setOrigin(0.5)
    }

    const startCell = walkPath && walkPath.length > 0 ? walkPath[0] : [view.hero.x, view.hero.y]
    this.hero = this.add
      .text(startCell[0] * TS + TS / 2, startCell[1] * TS + TS / 2, '🧙', { fontSize: '22px' })
      .setOrigin(0.5)
      .setDepth(10)

    this.input.on('pointerdown', (p: Phaser.Input.Pointer) => {
      if (this.walking) return
      const tx = Math.floor(p.worldX / TS)
      const ty = Math.floor(p.worldY / TS)
      if (tx < 0 || ty < 0 || tx >= width || ty >= height) return
      this.opts.onTileClick(tx, ty)
    })

    if (walkPath && walkPath.length > 1) {
      this.walkAlong(walkPath.slice(1))
    }
  }

  private walkAlong(steps: [number, number][]) {
    this.walking = true
    let i = 0
    const next = () => {
      if (i >= steps.length) {
        this.walking = false
        this.opts.onWalkDone()
        return
      }
      const [sx, sy] = steps[i++]
      this.tweens.add({
        targets: this.hero,
        x: sx * TS + TS / 2,
        y: sy * TS + TS / 2,
        duration: 95,
        ease: 'Linear',
        onComplete: next,
      })
    }
    next()
  }

  /** Tiles the hero can still step to this day, and object tiles reachable as an endpoint. */
  private computeReach(view: OverworldView): { reach: Set<string>; targets: Set<string> } {
    const { width, height } = view.size
    const revealed = new Set(view.revealed)
    const blocked = new Set(view.objects.map((o) => `${o.x},${o.y}`))
    const passable = (x: number, y: number) => {
      if (x < 0 || y < 0 || x >= width || y >= height) return false
      const t = view.terrain[y * width + x]
      return t !== 'rock' && t !== 'water'
    }

    const start = `${view.hero.x},${view.hero.y}`
    const dist = new Map<string, number>([[start, 0]])
    const reach = new Set<string>()
    const queue: [number, number][] = [[view.hero.x, view.hero.y]]
    while (queue.length > 0) {
      const [cx, cy] = queue.shift()!
      const d = dist.get(`${cx},${cy}`)!
      if (d >= view.movementLeft) continue
      for (const [dx, dy] of DIRS) {
        const nx = cx + dx
        const ny = cy + dy
        const k = `${nx},${ny}`
        if (dist.has(k) || !passable(nx, ny) || blocked.has(k) || !revealed.has(k)) continue
        dist.set(k, d + 1)
        reach.add(k)
        queue.push([nx, ny])
      }
    }

    const targets = new Set<string>()
    for (const obj of view.objects) {
      for (const [dx, dy] of DIRS) {
        const nk = `${obj.x + dx},${obj.y + dy}`
        const nd = nk === start ? 0 : dist.get(nk)
        if (nd !== undefined && nd + 1 <= view.movementLeft) {
          targets.add(`${obj.x},${obj.y}`)
          break
        }
      }
    }
    return { reach, targets }
  }
}

export function startOverworldGame(
  parent: HTMLElement,
  data: OverworldSceneData,
): Phaser.Game {
  const { width, height } = data.view.size
  const game = new Phaser.Game({
    type: Phaser.AUTO,
    parent,
    width: width * TS,
    height: height * TS,
    transparent: true,
    scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_HORIZONTALLY },
    scene: OverworldScene,
    audio: { noAudio: true },
  })
  game.scene.start('overworld', data)
  return game
}
