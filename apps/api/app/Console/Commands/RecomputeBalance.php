<?php

namespace App\Console\Commands;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use Illuminate\Console\Command;

class RecomputeBalance extends Command
{
    protected $signature = 'fighters:recompute-balance {--check : Report drift without writing}';

    protected $description = 'Re-run the Balance Engine over every fighter (after tuning or a version bump).';

    public function handle(StandardBalanceEngine $balance): int
    {
        $check = (bool) $this->option('check');
        $count = 0;
        $drift = 0;
        $illegal = 0;

        Fighter::with(['skills', 'stats'])->chunkById(200, function ($fighters) use ($balance, $check, &$count, &$drift, &$illegal) {
            foreach ($fighters as $fighter) {
                $target = $balance->stats($fighter);

                if ($check) {
                    $current = $fighter->stats?->power_score ?? 0;
                    if (abs($current - $target['power_score']) > 2) {
                        $drift++;
                        $this->line("  drift  #{$fighter->id} {$fighter->name}: {$current} -> {$target['power_score']}");
                    }
                    if (! $target['pvp_legal']) {
                        $illegal++;
                    }
                } else {
                    $balance->apply($fighter);
                }

                $count++;
            }
        });

        if ($check) {
            $this->info("Checked {$count} fighter(s): {$drift} drifting, {$illegal} would be PvP-illegal.");
        } else {
            $this->info("Recomputed balance for {$count} fighter(s).");
        }

        return self::SUCCESS;
    }
}
