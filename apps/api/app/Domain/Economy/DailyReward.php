<?php

namespace App\Domain\Economy;

use App\Models\PlayerDaily;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Login-streak reward (spec §61 — daily). A missed day resets the streak; a
 * kept streak walks the 7-day ladder and repeats.
 */
class DailyReward
{
    public function __construct(private GrantReward $grant) {}

    /**
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        $daily = $this->rowFor($user);
        $today = now()->toDateString();
        $canClaim = $daily->last_claimed_on?->toDateString() !== $today;
        $effective = $this->effectiveStreak($daily);
        $day = ($effective % 7) + 1;

        return [
            'canClaim' => $canClaim,
            'streak' => $effective,
            'bestStreak' => $daily->best_streak,
            'day' => $day,
            'reward' => $this->ladder()[$day],
            'ladder' => $this->ladder(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function claim(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $daily = PlayerDaily::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? PlayerDaily::create(['user_id' => $user->id]);

            $today = now()->toDateString();
            if ($daily->last_claimed_on?->toDateString() === $today) {
                throw new EconomyException('Nagrodę już dziś odebrano.');
            }

            $effective = $this->effectiveStreak($daily);
            $day = ($effective % 7) + 1;
            $newStreak = $effective + 1;

            $reward = $this->grant->grant($user, $this->ladder()[$day]);

            $daily->update([
                'streak' => $newStreak,
                'best_streak' => max($daily->best_streak, $newStreak),
                'last_claimed_on' => $today,
            ]);

            return ['streak' => $newStreak, 'day' => $day, 'reward' => $reward];
        });
    }

    private function rowFor(User $user): PlayerDaily
    {
        return PlayerDaily::query()->firstOrNew(['user_id' => $user->id]);
    }

    private function effectiveStreak(PlayerDaily $daily): int
    {
        $last = $daily->last_claimed_on;
        if ($last === null) {
            return 0;
        }

        // Kept if the last claim was today or yesterday; otherwise it lapsed.
        return $last->diffInDays(now()->startOfDay()) <= 1 ? $daily->streak : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ladder(): array
    {
        return config('daily.ladder');
    }
}
