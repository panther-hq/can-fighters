import type Phaser from 'phaser'

/**
 * Hand-drawn pixel art for the exploration map, rasterised into Phaser textures
 * at runtime — no external asset files. Sprites are 16x16 art units painted at
 * PX device-pixels each (so 32x32 textures); terrain tiles are drawn
 * procedurally for per-tile variation.
 */

export const TILE = 32
const PX = 2

// Shared palette: one character per colour, '.' = transparent.
const PALETTE: Record<string, number> = {
  // hero
  o: 0x241a12,
  s: 0xf2c9a0,
  c: 0xd64535,
  C: 0x9c2f22,
  t: 0xe0a43a,
  T: 0xa9781f,
  p: 0x37589c,
  P: 0x26407a,
  b: 0x5a3a22,
  k: 0x6b4a2c,
  n: 0x1a1a1a,
  // chest
  x: 0x8a5a2c,
  X: 0x5f3d1c,
  y: 0xf2c14e,
  Y: 0xffe08a,
  m: 0xc9a24a,
  // crystal
  q: 0x7be0d0,
  Q: 0x3aa89a,
  e: 0xd8fff7,
  E: 0x1c5c54,
  // slime
  z: 0x7cae3a,
  Z: 0x4f7d22,
  M: 0xc23a2a,
  // boss skull
  u: 0xe8e8ee,
  U: 0xb8b8c4,
  h: 0x2a2a30,
}

const HERO_DOWN_0 = [
  '................',
  '................',
  '......oooo......',
  '.....occcco.....',
  '.....cCCCCc.....',
  '....osssssso....',
  '....osnssnso....',
  '....osssssso....',
  '.....osssso.....',
  '....kttttttk....',
  '...kttttttttk...',
  '...kttttttttk...',
  '....TTTTTTTT....',
  '....pppppppp....',
  '....ppp..ppp....',
  '....bbb..bbb....',
]

const HERO_DOWN_1 = [
  '................',
  '................',
  '......oooo......',
  '.....occcco.....',
  '.....cCCCCc.....',
  '....osssssso....',
  '....osnssnso....',
  '....osssssso....',
  '.....osssso.....',
  '....kttttttk....',
  '...kttttttttk...',
  '...kttttttttk...',
  '....TTTTTTTT....',
  '....pppppppp....',
  '...ppp....pp....',
  '...bbb....bb....',
]

const HERO_UP_0 = [
  '................',
  '................',
  '......oooo......',
  '.....occcco.....',
  '.....cCCCCc.....',
  '.....CCCCCC.....',
  '.....CCCCCC.....',
  '.....oCCCCo.....',
  '....kkttttkk....',
  '...kkttttkkkk...',
  '...kkttttkkkk...',
  '....TTTTTTTT....',
  '....pppppppp....',
  '....pppppppp....',
  '....ppp..ppp....',
  '....bbb..bbb....',
]

const HERO_UP_1 = [
  '................',
  '................',
  '......oooo......',
  '.....occcco.....',
  '.....cCCCCc.....',
  '.....CCCCCC.....',
  '.....CCCCCC.....',
  '.....oCCCCo.....',
  '....kkttttkk....',
  '...kkttttkkkk...',
  '...kkttttkkkk...',
  '....TTTTTTTT....',
  '....pppppppp....',
  '....pppppppp....',
  '...ppp....pp....',
  '...bbb....bb....',
]

const HERO_SIDE_0 = [
  '................',
  '................',
  '......oooo......',
  '.....occccc.....',
  '.....cCCCCcc....',
  '.....osssso.....',
  '.....osnsso.....',
  '.....ossssos....',
  '......ossso.....',
  '....tttttttk....',
  '...ktttttttk....',
  '...kttttttt.....',
  '....TTTTTTT.....',
  '....pppppp......',
  '....ppp.pp......',
  '....bb..bb......',
]

const HERO_SIDE_1 = [
  '................',
  '................',
  '......oooo......',
  '.....occccc.....',
  '.....cCCCCcc....',
  '.....osssso.....',
  '.....osnsso.....',
  '.....ossssos....',
  '......ossso.....',
  '....tttttttk....',
  '...ktttttttk....',
  '...kttttttt.....',
  '....TTTTTTT.....',
  '....pppppp......',
  '...pp..ppp......',
  '...bb...bbb.....',
]

const CHEST = [
  '................',
  '................',
  '................',
  '................',
  '...xxxxxxxxxx...',
  '..xXXXXXXXXXXx..',
  '..xXyYYYYYyyXx..',
  '..xXXXXmmXXXXx..',
  '..xxxxxmmxxxxx..',
  '..xXXXXXXXXXXx..',
  '..xXxxxxxxxxXx..',
  '..xXXXXXXXXXXx..',
  '..xxxxxxxxxxxx..',
  '..X..........X..',
  '................',
  '................',
]

const CRYSTAL_0 = [
  '................',
  '................',
  '................',
  '.......qq.......',
  '......qQQq......',
  '.....qQeeQq.....',
  '.....qeeeeq.....',
  '.....QeeeeQ.....',
  '......QeeQ......',
  '.......QQ.......',
  '......EQQE......',
  '.....EEEEEE.....',
  '....EE....EE....',
  '................',
  '................',
  '................',
]

const CRYSTAL_1 = [
  '................',
  '................',
  '..........e.....',
  '.......qq.......',
  '......qeeq......',
  '.....qeeeeq.....',
  '.....eeeeee.....',
  '.....eeeeee.....',
  '......QeeQ......',
  '.......QQ.......',
  '......EQQE......',
  '.....EEEEEE.....',
  '....EE....EE....',
  '.....e..........',
  '................',
  '................',
]

const SLIME_0 = [
  '................',
  '................',
  '................',
  '................',
  '................',
  '................',
  '.....zzzzzz.....',
  '....zzzzzzzz....',
  '...zzzzzzzzzz...',
  '...zznzzznzzz...',
  '...zzzzzzzzzz...',
  '...zzzMMzzzzz...',
  '...ZZZZZZZZZZ...',
  '....Z.Z..Z.Z....',
  '................',
  '................',
]

const SLIME_1 = [
  '................',
  '................',
  '................',
  '................',
  '................',
  '................',
  '................',
  '...zzzzzzzzzz...',
  '..zzzzzzzzzzzz..',
  '..zznzzzznzzzz..',
  '..zzzzMMzzzzzz..',
  '..zzzzzzzzzzzz..',
  '..ZZZZZZZZZZZZ..',
  '...Z..Z..Z..Z...',
  '................',
  '................',
]

const BOSS_0 = [
  '................',
  '....y.y.y.y.....',
  '....yYYYYYY.....',
  '.....uuuuuu.....',
  '....uuuuuuuu....',
  '...uuuuuuuuuu...',
  '...uuhhuuhhuu...',
  '...uuhhuuhhuu...',
  '...uuuuuuuuuu...',
  '....uuuMMuuu....',
  '....uUuuuuUu....',
  '.....uuuuuu.....',
  '.....u.u.u.u....',
  '....UUUUUUUU....',
  '....U.U..U.U....',
  '................',
]

function paint(scene: Phaser.Scene, key: string, rows: string[]): void {
  if (scene.textures.exists(key)) return
  const g = scene.make.graphics({ x: 0, y: 0 })
  for (let r = 0; r < rows.length; r++) {
    const row = rows[r]
    for (let c = 0; c < row.length; c++) {
      const color = PALETTE[row[c]]
      if (color === undefined) continue
      g.fillStyle(color, 1)
      g.fillRect(c * PX, r * PX, PX, PX)
    }
  }
  g.generateTexture(key, rows[0].length * PX, rows.length * PX)
  g.destroy()
}

function lcg(seed: number): () => number {
  let s = seed >>> 0 || 1
  return () => {
    s = (Math.imul(s, 1664525) + 1013904223) >>> 0
    return s / 4294967296
  }
}

function bakeGrass(scene: Phaser.Scene, key: string, seed: number): void {
  if (scene.textures.exists(key)) return
  const g = scene.make.graphics({ x: 0, y: 0 })
  g.fillStyle(0x4f9d54, 1)
  g.fillRect(0, 0, TILE, TILE)
  const rnd = lcg(seed)
  for (let i = 0; i < 48; i++) {
    const x = Math.floor(rnd() * 16) * PX
    const y = Math.floor(rnd() * 16) * PX
    const t = rnd()
    if (t < 0.5) {
      g.fillStyle(0x3f8a46, 1)
      g.fillRect(x, y, PX, PX)
    } else if (t < 0.82) {
      g.fillStyle(0x68b85c, 1)
      g.fillRect(x, y, PX, PX)
    } else {
      g.fillStyle(0x2f6b39, 1)
      g.fillRect(x, y, PX, PX * 2)
    }
  }
  g.generateTexture(key, TILE, TILE)
  g.destroy()
}

function bakeRock(scene: Phaser.Scene): void {
  const key = 'tile-rock'
  if (scene.textures.exists(key)) return
  const g = scene.make.graphics({ x: 0, y: 0 })
  g.fillStyle(0x4f9d54, 1)
  g.fillRect(0, 0, TILE, TILE)
  g.fillStyle(0x3f8a46, 1)
  g.fillRect(2, 24, 28, 6)
  g.fillStyle(0x3f3f47, 1)
  g.fillRect(6, 12, 22, 16)
  g.fillStyle(0x54545e, 1)
  g.fillRect(6, 6, 20, 20)
  g.fillStyle(0x7a7a86, 1)
  g.fillRect(8, 8, 15, 14)
  g.fillStyle(0x9a9aa6, 1)
  g.fillRect(9, 9, 6, 5)
  g.fillStyle(0x3f3f47, 1)
  g.fillRect(15, 10, 2, 13)
  g.fillRect(12, 18, 9, 2)
  g.generateTexture(key, TILE, TILE)
  g.destroy()
}

function bakeWater(scene: Phaser.Scene, key: string, phase: number): void {
  if (scene.textures.exists(key)) return
  const g = scene.make.graphics({ x: 0, y: 0 })
  g.fillStyle(0x2f6096, 1)
  g.fillRect(0, 0, TILE, TILE)
  for (const [baseY, tone] of [
    [9, 0x3f7cb6],
    [21, 0x356ba0],
  ] as const) {
    g.fillStyle(tone, 1)
    for (let x = 0; x < TILE; x += 2) {
      const y = baseY + Math.round(Math.sin((x + phase * 6) / 5) * 2)
      g.fillRect(x, y, 2, 2)
    }
  }
  g.fillStyle(0xbfe3f2, 1)
  const rnd = lcg(phase + 17)
  for (let i = 0; i < 5; i++) {
    g.fillRect(Math.floor(rnd() * 15) * PX, Math.floor(rnd() * 15) * PX, PX, PX)
  }
  g.generateTexture(key, TILE, TILE)
  g.destroy()
}

function bakeRing(scene: Phaser.Scene): void {
  if (scene.textures.exists('ring')) return
  const g = scene.make.graphics({ x: 0, y: 0 })
  g.lineStyle(2, 0xf2c14e, 1)
  g.strokeCircle(TILE / 2, TILE / 2, TILE / 2 - 3)
  g.generateTexture('ring', TILE, TILE)
  g.destroy()
}

function bakeFogBrush(scene: Phaser.Scene): void {
  const key = 'fogbrush'
  if (scene.textures.exists(key)) return
  const size = TILE * 5
  const tex = scene.textures.createCanvas(key, size, size)
  if (!tex) return
  const ctx = tex.getContext()
  const grd = ctx.createRadialGradient(
    size / 2,
    size / 2,
    size * 0.1,
    size / 2,
    size / 2,
    size / 2,
  )
  grd.addColorStop(0, 'rgba(255,255,255,1)')
  grd.addColorStop(0.62, 'rgba(255,255,255,0.6)')
  grd.addColorStop(1, 'rgba(255,255,255,0)')
  ctx.fillStyle = grd
  ctx.fillRect(0, 0, size, size)
  tex.refresh()
}

export function bakeTextures(scene: Phaser.Scene): void {
  bakeGrass(scene, 'tile-grass-0', 101)
  bakeGrass(scene, 'tile-grass-1', 202)
  bakeGrass(scene, 'tile-grass-2', 303)
  bakeRock(scene)
  bakeWater(scene, 'tile-water-0', 0)
  bakeWater(scene, 'tile-water-1', 1)
  bakeRing(scene)
  bakeFogBrush(scene)

  paint(scene, 'hero-down-0', HERO_DOWN_0)
  paint(scene, 'hero-down-1', HERO_DOWN_1)
  paint(scene, 'hero-up-0', HERO_UP_0)
  paint(scene, 'hero-up-1', HERO_UP_1)
  paint(scene, 'hero-side-0', HERO_SIDE_0)
  paint(scene, 'hero-side-1', HERO_SIDE_1)
  paint(scene, 'chest', CHEST)
  paint(scene, 'crystal-0', CRYSTAL_0)
  paint(scene, 'crystal-1', CRYSTAL_1)
  paint(scene, 'slime-0', SLIME_0)
  paint(scene, 'slime-1', SLIME_1)
  paint(scene, 'boss-0', BOSS_0)
}

export function registerAnims(scene: Phaser.Scene): void {
  const add = (key: string, frames: string[], frameRate: number) => {
    if (scene.anims.exists(key)) return
    scene.anims.create({
      key,
      frames: frames.map((f) => ({ key: f })),
      frameRate,
      repeat: -1,
    })
  }
  add('hero-walk-down', ['hero-down-0', 'hero-down-1'], 6)
  add('hero-walk-up', ['hero-up-0', 'hero-up-1'], 6)
  add('hero-walk-side', ['hero-side-0', 'hero-side-1'], 6)
  add('crystal-glow', ['crystal-0', 'crystal-1'], 2)
  add('slime-idle', ['slime-0', 'slime-1'], 3)
}
