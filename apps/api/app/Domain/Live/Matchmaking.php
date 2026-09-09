<?php

namespace App\Domain\Live;

use Illuminate\Support\Facades\Cache;

/**
 * FIFO matchmaking queue. Cache-backed (Redis in dev, array in tests) with a
 * lock around the read-modify-write so two simultaneous joins can't grab the
 * same partner.
 */
class Matchmaking
{
    private const KEY = 'live:matchmaking:queue';

    private const LOCK = 'live:matchmaking:lock';

    /**
     * Add a player and, if someone else is already waiting, return their id
     * (both are removed from the queue). Returns null when the player is just
     * queued.
     */
    public function pair(int $userId): ?int
    {
        return Cache::lock(self::LOCK, 5)->block(3, function () use ($userId): ?int {
            /** @var list<array{id: int, at: int}> $queue */
            $queue = Cache::get(self::KEY, []);
            $queue = array_values(array_filter($queue, fn ($e) => $e['id'] !== $userId));

            foreach ($queue as $index => $entry) {
                unset($queue[$index]);
                Cache::put(self::KEY, array_values($queue), now()->addMinutes(10));

                return $entry['id'];
            }

            $queue[] = ['id' => $userId, 'at' => time()];
            Cache::put(self::KEY, array_values($queue), now()->addMinutes(10));

            return null;
        });
    }

    public function leave(int $userId): void
    {
        Cache::lock(self::LOCK, 5)->block(3, function () use ($userId): void {
            $queue = Cache::get(self::KEY, []);
            Cache::put(
                self::KEY,
                array_values(array_filter($queue, fn ($e) => $e['id'] !== $userId)),
                now()->addMinutes(10),
            );
        });
    }

    public function isQueued(int $userId): bool
    {
        foreach (Cache::get(self::KEY, []) as $entry) {
            if ($entry['id'] === $userId) {
                return true;
            }
        }

        return false;
    }
}
