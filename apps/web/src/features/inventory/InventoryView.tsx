import { useEquipment } from '../equipment/hooks'
import { SLOT_LABELS, statSummary } from '../equipment/types'
import { rarityLabel } from '../fighters/types'
import { useInventory } from './hooks'

export function InventoryView() {
  const inventory = useInventory()
  const equipment = useEquipment()

  if (inventory.isLoading) {
    return <p className="muted">Wczytywanie plecaka…</p>
  }
  if (inventory.isError) {
    return <p className="muted muted--bad">Nie udało się wczytać plecaka.</p>
  }

  const ingredients = inventory.data?.ingredients ?? []
  const gear = equipment.data ?? []
  const empty = ingredients.length === 0 && gear.length === 0

  if (empty) {
    return (
      <p className="muted">
        Plecak jest pusty. Otwórz puszkę w zakładce „Puszki”.
      </p>
    )
  }

  return (
    <div className="backpack">
      {ingredients.length > 0 && (
        <>
          <h3 className="backpack__head">Składniki</h3>
          <ul className="ingredients">
            {ingredients.map((ingredient) => (
              <li key={ingredient.slug} className="ingredient">
                <span className="ingredient__icon" aria-hidden="true">
                  {ingredient.icon}
                </span>
                <span className="ingredient__name">{ingredient.name}</span>
                <span className="ingredient__qty">×{ingredient.quantity}</span>
              </li>
            ))}
          </ul>
        </>
      )}

      {gear.length > 0 && (
        <>
          <h3 className="backpack__head">Ekwipunek</h3>
          <ul className="cards">
            {gear.map((piece) => (
              <li key={piece.id} className="card">
                <span className="card__icon" aria-hidden="true">
                  {piece.icon}
                </span>
                <div className="card__body">
                  <span className="card__name">
                    {piece.name}{' '}
                    <span className="muted">
                      · {SLOT_LABELS[piece.slot]} · {rarityLabel(piece.rarity)}
                    </span>
                  </span>
                  <span className="muted">{statSummary(piece.rolledStats)}</span>
                </div>
                <span className="muted">
                  {piece.equippedOnId ? piece.equippedOnName : 'wolne'}
                </span>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}
