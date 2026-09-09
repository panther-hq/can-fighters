import { useState } from 'react'
import type { Fighter } from '../fighters/types'
import { rarityLabel } from '../fighters/types'
import { useEquipFighter, useEquipment, useUnequipFighter } from './hooks'
import { SLOT_LABELS, statSummary, type EquipSlot } from './types'

const SLOTS: EquipSlot[] = ['weapon', 'armor', 'accessory']

export function FighterEquipment({ fighter }: { fighter: Fighter }) {
  const inventory = useEquipment()
  const equip = useEquipFighter()
  const unequip = useUnequipFighter()
  const [pickingSlot, setPickingSlot] = useState<EquipSlot | null>(null)

  const worn = new Map((fighter.equipment ?? []).map((w) => [w.slot, w]))
  const owned = inventory.data ?? []

  return (
    <section className="detail__mutate">
      <h3>Ekwipunek</h3>
      <ul className="equip-slots">
        {SLOTS.map((slot) => {
          const piece = worn.get(slot)
          return (
            <li key={slot} className="equip-slot">
              <span className="equip-slot__label">{SLOT_LABELS[slot]}</span>
              {piece ? (
                <div className="equip-slot__body">
                  <span>
                    {piece.icon} {piece.name}
                  </span>
                  <span className="muted">{statSummary(piece.rolledStats)}</span>
                </div>
              ) : (
                <span className="muted equip-slot__body">— puste —</span>
              )}
              {piece ? (
                <button
                  type="button"
                  className="btn btn--ghost"
                  onClick={() => unequip.mutate({ fighterId: fighter.id, slot })}
                  disabled={unequip.isPending}
                >
                  Zdejmij
                </button>
              ) : (
                <button
                  type="button"
                  className="btn"
                  onClick={() => setPickingSlot(pickingSlot === slot ? null : slot)}
                >
                  Załóż
                </button>
              )}
            </li>
          )
        })}
      </ul>

      {pickingSlot && (
        <ul className="cards equip-picker">
          {owned.filter((p) => p.slot === pickingSlot).length === 0 && (
            <li className="muted">Brak przedmiotów typu {SLOT_LABELS[pickingSlot]}.</li>
          )}
          {owned
            .filter((p) => p.slot === pickingSlot)
            .map((p) => (
              <li key={p.id} className="card">
                <span className="card__icon" aria-hidden="true">
                  {p.icon}
                </span>
                <div className="card__body">
                  <span className="card__name">
                    {p.name} <span className="muted">({rarityLabel(p.rarity)})</span>
                  </span>
                  <span className="muted">{statSummary(p.rolledStats)}</span>
                  {p.equippedOnId && p.equippedOnId !== fighter.id && (
                    <span className="muted">nosi: {p.equippedOnName}</span>
                  )}
                </div>
                <button
                  type="button"
                  className="btn btn--primary"
                  disabled={equip.isPending}
                  onClick={() =>
                    equip.mutate(
                      { fighterId: fighter.id, playerEquipmentId: p.id },
                      { onSuccess: () => setPickingSlot(null) },
                    )
                  }
                >
                  Załóż
                </button>
              </li>
            ))}
        </ul>
      )}
    </section>
  )
}
