<?php

use App\Models\LiveBattle;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Per-player event stream (spec §44): inventory, mixer, equipment, arena, …
Broadcast::channel('player.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

// Live battle room (spec §45) — only the two participants.
Broadcast::channel('battle.{battleId}', function (User $user, int $battleId) {
    return LiveBattle::query()
        ->whereKey($battleId)
        ->where(fn ($q) => $q->where('player_a_id', $user->id)->orWhere('player_b_id', $user->id))
        ->exists();
});
