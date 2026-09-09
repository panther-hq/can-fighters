import { useInventory } from './hooks'

export function InventoryView() {
  const inventory = useInventory()

  if (inventory.isLoading) {
    return <p className="muted">Wczytywanie plecaka…</p>
  }

  if (inventory.isError) {
    return <p className="muted muted--bad">Nie udało się wczytać plecaka.</p>
  }

  const ingredients = inventory.data?.ingredients ?? []

  if (ingredients.length === 0) {
    return (
      <p className="muted">
        Plecak jest pusty. Otwórz puszkę w zakładce „Puszki”.
      </p>
    )
  }

  return (
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
  )
}
