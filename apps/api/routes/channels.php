<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Per-player event stream (spec §44): inventory, mixer, equipment, arena, …
Broadcast::channel('player.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
