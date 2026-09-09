<?php

namespace App\Support;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Replay protection for economic actions (spec §58). First call with a given
 * key runs the work and stores its response; later calls with the same key
 * return that stored response without re-running.
 *
 * Note: protects sequential retries (double-click, dropped connection). True
 * concurrent same-key requests are additionally serialised by the row locks
 * inside each action; hardening beyond that can come later.
 */
class Idempotency
{
    /**
     * @param  callable(): array{0: int, 1: array<string, mixed>}  $work  returns [status, body]
     * @return array{status: int, body: array<string, mixed>, replayed: bool}
     */
    public static function run(User $user, string $scope, ?string $key, callable $work): array
    {
        $key = $key !== null ? trim($key) : '';

        if ($key === '') {
            [$status, $body] = $work();

            return ['status' => $status, 'body' => $body, 'replayed' => false];
        }

        return DB::transaction(function () use ($user, $scope, $key, $work): array {
            $existing = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('scope', $scope)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'status' => $existing->response_status,
                    'body' => $existing->response_body,
                    'replayed' => true,
                ];
            }

            [$status, $body] = $work();

            try {
                IdempotencyKey::create([
                    'user_id' => $user->id,
                    'key' => $key,
                    'scope' => $scope,
                    'response_status' => $status,
                    'response_body' => $body,
                ]);
            } catch (UniqueConstraintViolationException) {
                $winner = IdempotencyKey::query()
                    ->where('user_id', $user->id)
                    ->where('scope', $scope)
                    ->where('key', $key)
                    ->firstOrFail();

                return [
                    'status' => $winner->response_status,
                    'body' => $winner->response_body,
                    'replayed' => true,
                ];
            }

            return ['status' => $status, 'body' => $body, 'replayed' => false];
        });
    }
}
