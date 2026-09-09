import { isAxiosError } from 'axios'
import { useBuyOffer, useShop } from './hooks'
import { rewardLabel } from './types'

function errorText(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return 'Nie udało się kupić.'
}

export function ShopView() {
  const shop = useShop()
  const buy = useBuyOffer()

  if (shop.isLoading) return <p className="muted">Wczytywanie sklepu…</p>
  if (shop.isError) return <p className="muted muted--bad">Nie udało się wczytać sklepu.</p>

  const offers = shop.data?.offers ?? []

  return (
    <section className="shop">
      <p className="muted">Odświeża się codziennie o północy.</p>

      <ul className="cards">
        {offers.map((offer) => (
          <li key={offer.id} className={`card ${offer.bought ? 'card--spent' : ''}`}>
            <span className="card__icon" aria-hidden="true">
              {offer.icon}
            </span>
            <div className="card__body">
              <span className="card__name">{offer.label}</span>
              <span className="muted">{offer.price} monet</span>
            </div>
            <button
              type="button"
              className="btn btn--primary"
              disabled={offer.bought || buy.isPending}
              onClick={() => buy.mutate(offer.id)}
            >
              {offer.bought
                ? 'Kupione'
                : buy.isPending && buy.variables === offer.id
                  ? 'Kupowanie…'
                  : 'Kup'}
            </button>
          </li>
        ))}
      </ul>

      {buy.isSuccess && !buy.isPending && (
        <p className="muted">Kupiono: {rewardLabel(buy.data.reward)}</p>
      )}
      {buy.isError && <p className="muted muted--bad">{errorText(buy.error)}</p>}
    </section>
  )
}
