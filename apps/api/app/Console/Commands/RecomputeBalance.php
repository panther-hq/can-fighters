<?php

namespace App\Console\Commands;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use Illuminate\Console\Command;

class RecomputeBalance extends Command
{
    protected $signature = 'fighters:recompute-balance';

    protected $description = 'Re-run the Balance Engine over every fighter (after tuning or a version bump).';

    public function handle(StandardBalanceEngine $balance): int
    {
        $count = 0;

        Fighter::with('skills')->chunkById(200, function ($fighters) use ($balance, &$count) {
            foreach ($fighters as $fighter) {
                $balance->apply($fighter);
                $count++;
            }
        });

        $this->info("Recomputed balance for {$count} fighter(s).");

        return self::SUCCESS;
    }
}
