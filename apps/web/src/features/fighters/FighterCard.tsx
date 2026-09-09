import type { Fighter } from './types'
import { classLabel, rarityLabel } from './types'

export function FighterCard({ fighter }: { fighter: Fighter }) {
  const body = fighter.visualDna.body ?? 'tin_can'

  return (
    <article className={`fighter fighter--${fighter.rarity}`}>
      <header className="fighter__head">
        <span className="fighter__avatar" aria-hidden="true">
          {VISUAL_EMOJI[body] ?? '🥫'}
        </span>
        <div>
          <h3 className="fighter__name">{fighter.name}</h3>
          <p className="fighter__meta">
            {classLabel(fighter.primaryClass)}
            {fighter.secondaryClass && ` / ${classLabel(fighter.secondaryClass)}`}
            {' · '}
            <span className="fighter__rarity">{rarityLabel(fighter.rarity)}</span>
            {' · poz. '}
            {fighter.level}
          </p>
        </div>
      </header>

      <p className="fighter__desc">{fighter.description}</p>

      <div className="chips">
        {fighter.traits.map((trait) => (
          <span key={trait} className="chip">
            {trait}
          </span>
        ))}
      </div>

      <ul className="fighter__skills">
        {fighter.suggestedSkills.map((skill, index) => (
          <li key={`${skill.skillFamily}-${index}`}>
            {skill.skillFamily}
            {skill.modifier && <em> ({skill.modifier})</em>}
          </li>
        ))}
      </ul>
    </article>
  )
}

const VISUAL_EMOJI: Record<string, string> = {
  pasta: '🍝',
  screw: '🔩',
  battery: '🔋',
  cheese: '🧀',
  sock: '🧦',
  magnet: '🧲',
  fork: '🍴',
  fish: '🐟',
  fire: '🔥',
  slime: '🟢',
  tin_can: '🥫',
  spring: '🌀',
}
