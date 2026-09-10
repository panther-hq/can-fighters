import Phaser from 'phaser'
import { bakeTextures, registerAnims, TILE } from './pixelart'
import type { OverworldView } from './types'

export interface OverworldSceneData {
  view: OverworldView
  walkPath: [number, number][] | null
  onTileClick: (x: number, y: number) => void
  onWalkDone: () => void
}

type Facing = 'up' | 'down' | 'left' | 'right'

const DIRS: [number, number][] = [
  [1, 0],
  [-1, 0],
  [0, 1],
  [0, -1],
]

export class OverworldScene extends Phaser.Scene {
  private opts!: OverworldSceneData
  private hero!: Phaser.GameObjects.Container
  private heroSprite!: Phaser.GameObjects.Sprite
  private walking = false
  private waterTiles: Phaser.GameObjects.Image[] = []
  private waterFrame = 0
  private targets = new Set<string>()
  private lastDir: Facing = 'down'

  constructor() {
    super('overworld')
  }

  init(opts: OverworldSceneData) {
    this.opts = opts
    this.walking = false
    this.waterTiles = []
    this.waterFrame = 0
    this.targets = new Set()
    this.lastDir = 'down'
  }

  create() {
    bakeTextures(this)
    registerAnims(this)

    const { view, walkPath } = this.opts
    const { width, height } = view.size
    const revealed = new Set(view.revealed)
    this.cameras.main.setBounds(0, 0, width * TILE, height * TILE)

    // --- terrain (only revealed tiles; fog covers the rest) ---
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width; x++) {
        if (!revealed.has(`${x},${y}`)) continue
        const t = view.terrain[y * width + x]
        const key =
          t === 'rock'
            ? 'tile-rock'
            : t === 'water'
              ? 'tile-water-0'
              : `tile-grass-${(x * 3 + y * 7) % 3}`
        const img = this.add.image(x * TILE, y * TILE, key).setOrigin(0, 0).setDepth(0)
        if (t === 'water') this.waterTiles.push(img)
      }
    }

    // --- soft fog of war ---
    const fog = this.add
      .renderTexture(0, 0, width * TILE, height * TILE)
      .setOrigin(0, 0)
      .setDepth(5)
    fog.fill(0x070a12, 0.94)
    for (const key of revealed) {
      const [rx, ry] = key.split(',').map(Number)
      fog.erase('fogbrush', rx * TILE + TILE / 2, ry * TILE + TILE / 2)
    }

    // --- movement-range hint ---
    if (!walkPath && !view.activeMerchant) {
      const { reach, targets } = this.computeReach(view)
      this.targets = targets
      const hl = this.add.graphics().setDepth(2)
      for (const key of reach) {
        const [rx, ry] = key.split(',').map(Number)
        hl.fillStyle(0x9fd8ff, 0.13)
        hl.fillRect(rx * TILE, ry * TILE, TILE, TILE)
        hl.lineStyle(1, 0x9fd8ff, 0.26)
        hl.strokeRect(rx * TILE + 1, ry * TILE + 1, TILE - 2, TILE - 2)
      }
    }

    // --- objects ---
    for (const obj of view.objects) {
      if (!revealed.has(`${obj.x},${obj.y}`)) continue
      const cx = obj.x * TILE + TILE / 2
      const cy = obj.y * TILE + TILE / 2

      this.add
        .ellipse(cx, cy + TILE * 0.3, TILE * 0.5, TILE * 0.2, 0x000000, 0.25)
        .setDepth(8)

      if (this.targets.has(`${obj.x},${obj.y}`)) {
        const ring = this.add.image(cx, cy, 'ring').setDepth(9)
        this.tweens.add({
          targets: ring,
          scale: { from: 0.92, to: 1.12 },
          alpha: { from: 0.9, to: 0.4 },
          duration: 760,
          yoyo: true,
          repeat: -1,
          ease: 'Sine.easeInOut',
        })
      }

      let sprite: Phaser.GameObjects.Image | Phaser.GameObjects.Sprite
      if (obj.kind === 'treasure') {
        sprite = this.add.image(cx, cy, 'chest')
      } else if (obj.kind === 'event') {
        sprite = this.add.sprite(cx, cy, 'crystal-0').play('crystal-glow')
      } else if (obj.kind === 'boss') {
        sprite = this.add.image(cx, cy, 'boss-0').setScale(1.35)
      } else {
        const slime = this.add.sprite(cx, cy, 'slime-0').play('slime-idle')
        if (obj.elite) slime.setTint(0xc46bd6).setScale(1.15)
        sprite = slime
      }
      sprite.setDepth(10)
      this.tweens.add({
        targets: sprite,
        y: cy - 2,
        duration: 1000 + ((obj.x * 53 + obj.y * 29) % 500),
        yoyo: true,
        repeat: -1,
        ease: 'Sine.easeInOut',
      })
    }

    // --- hero ---
    const startCell =
      walkPath && walkPath.length > 0 ? walkPath[0] : [view.hero.x, view.hero.y]
    const hx = startCell[0] * TILE + TILE / 2
    const hy = startCell[1] * TILE + TILE / 2
    const shadow = this.add.ellipse(0, TILE * 0.3, TILE * 0.5, TILE * 0.2, 0x000000, 0.3)
    this.heroSprite = this.add.sprite(0, 0, 'hero-down-0')
    this.hero = this.add.container(hx, hy, [shadow, this.heroSprite]).setDepth(20)
    this.faceIdle('down')

    // --- input ---
    this.input.on('pointerdown', (p: Phaser.Input.Pointer) => {
      if (this.walking) return
      const tx = Math.floor(p.worldX / TILE)
      const ty = Math.floor(p.worldY / TILE)
      if (tx < 0 || ty < 0 || tx >= width || ty >= height) return
      this.opts.onTileClick(tx, ty)
    })

    // --- water shimmer ---
    if (this.waterTiles.length > 0) {
      this.time.addEvent({
        delay: 520,
        loop: true,
        callback: () => {
          this.waterFrame ^= 1
          const key = this.waterFrame ? 'tile-water-1' : 'tile-water-0'
          for (const w of this.waterTiles) w.setTexture(key)
        },
      })
    }

    if (walkPath && walkPath.length > 1) {
      this.walkAlong(walkPath[0], walkPath.slice(1))
    }
  }

  private walkAlong(from: [number, number], steps: [number, number][]) {
    this.walking = true
    let prev = from
    let i = 0
    const next = () => {
      if (i >= steps.length) {
        this.walking = false
        this.faceIdle(this.lastDir)
        this.opts.onWalkDone()
        return
      }
      const cell = steps[i++]
      const dir = this.dirOf(prev, cell)
      this.lastDir = dir
      this.playWalk(dir)
      prev = cell
      this.tweens.add({
        targets: this.hero,
        x: cell[0] * TILE + TILE / 2,
        y: cell[1] * TILE + TILE / 2,
        duration: 105,
        ease: 'Linear',
        onComplete: next,
      })
    }
    next()
  }

  private dirOf(from: [number, number], to: [number, number]): Facing {
    const dx = to[0] - from[0]
    const dy = to[1] - from[1]
    if (dx > 0) return 'right'
    if (dx < 0) return 'left'
    if (dy < 0) return 'up'
    return 'down'
  }

  private playWalk(dir: Facing) {
    if (dir === 'up') this.heroSprite.play('hero-walk-up', true)
    else if (dir === 'down') this.heroSprite.play('hero-walk-down', true)
    else {
      this.heroSprite.play('hero-walk-side', true)
      this.heroSprite.setFlipX(dir === 'left')
    }
  }

  private faceIdle(dir: Facing) {
    this.heroSprite.stop()
    if (dir === 'up') this.heroSprite.setTexture('hero-up-0')
    else if (dir === 'down') this.heroSprite.setTexture('hero-down-0')
    else {
      this.heroSprite.setTexture('hero-side-0')
      this.heroSprite.setFlipX(dir === 'left')
    }
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
    width: width * TILE,
    height: height * TILE,
    backgroundColor: '#0b0e14',
    scale: { mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_HORIZONTALLY },
    render: { pixelArt: true, roundPixels: true },
    scene: OverworldScene,
    audio: { noAudio: true },
  })
  game.scene.start('overworld', data)
  return game
}
