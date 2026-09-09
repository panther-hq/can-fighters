<?php

namespace App\Domain\Arena;

class LeagueTable
{
    public static function forRating(int $rating): string
    {
        $league = 'Brąz';
        foreach (config('arena.leagues') as $floor => $name) {
            if ($rating >= $floor) {
                $league = $name;
            }
        }

        return $league;
    }
}
