<?php

namespace App\Domain\Battle;

/**
 * Applies one skill's effect to the battle (spec §17). The AI only ever picked
 * the family + a modifier word; the numbers come from the Balance Engine and
 * the behaviour lives here.
 */
class SkillResolver
{
    private const TURN_MS = 1000;

    private const AOE_FALLOFF = 0.85;

    private const EXECUTE_MULTIPLIER = 2.5;

    /**
     * @param  array{slot: int, family: string, parameters: array<string, int>}  $skill
     */
    public function apply(BattleUnit $actor, array $skill, BattleState $state): void
    {
        $family = $skill['family'];
        $p = $skill['parameters'];
        $time = $state->time;
        $power = (int) ($p['power'] ?? 0);
        $duration = (int) ($p['duration'] ?? 2);
        $expires = $time + $duration * self::TURN_MS;

        match ($family) {
            'direct_damage' => $this->singleDamage($actor, $state, $power, $family),
            'area_damage' => $this->areaDamage($actor, $state, $power, (int) ($p['targets'] ?? 3), $family),
            'execute' => $this->execute($actor, $state, $power, (int) ($p['threshold'] ?? 25), $family),
            'lifesteal' => $this->lifesteal($actor, $state, $power, (int) ($p['ratio'] ?? 40), $family),
            'poison', 'bleed' => $this->dot($actor, $state, $power, $expires, $family),
            'heal' => $this->heal($actor, $state, $power),
            'shield' => $this->shield($actor, $state, $power, $expires),
            'taunt' => $this->taunt($actor, $state, $expires),
            'stun' => $this->stun($actor, $state, (int) ($p['chance'] ?? 25), $expires),
            'slow' => $this->debuff($actor, $state, 'speed', -(int) ($p['amount'] ?? 25), $expires, 'slow'),
            'silence' => $this->silence($actor, $state, $expires),
            'buff_attack' => $this->selfBuff($actor, $state, 'attack', (int) ($p['amount'] ?? 20), $expires),
            'buff_defense' => $this->selfBuff($actor, $state, 'defense', (int) ($p['amount'] ?? 20), $expires),
            'buff_speed' => $this->selfBuff($actor, $state, 'speed', (int) ($p['amount'] ?? 20), $expires),
            'debuff_attack' => $this->debuff($actor, $state, 'attack', -(int) ($p['amount'] ?? 20), $expires, 'debuff_attack'),
            'debuff_defense' => $this->debuff($actor, $state, 'defense', -(int) ($p['amount'] ?? 20), $expires, 'debuff_defense'),
            'cleanse' => $this->cleanse($actor, $state),
            'revive' => $this->revive($actor, $state, (int) ($p['power'] ?? 40)),
            'summon' => $this->areaDamage($actor, $state, max(6, (int) ($power / 2)), 2, 'summon'),
            default => $this->singleDamage($actor, $state, max(6, $power), $family),
        };
    }

    private function singleDamage(BattleUnit $actor, BattleState $state, int $power, string $skill): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $state->hit($actor, $target, $power, ['skill' => $skill, 'cause' => 'skill']);
    }

    private function areaDamage(BattleUnit $actor, BattleState $state, int $power, int $max, string $skill): void
    {
        $targets = $state->targets->enemyCluster($actor, $state->units, $max, $state->time);
        $each = max(1, (int) round($power * self::AOE_FALLOFF));

        foreach ($targets as $target) {
            $state->hit($actor, $target, $each, ['skill' => $skill, 'cause' => 'skill']);
        }
    }

    private function execute(BattleUnit $actor, BattleState $state, int $power, int $thresholdPct, string $skill): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $low = $target->hp * 100 <= $target->maxHp * $thresholdPct;
        $damage = $low ? (int) round($power * self::EXECUTE_MULTIPLIER) : $power;
        $state->hit($actor, $target, $damage, ['skill' => $skill, 'cause' => 'skill']);
    }

    private function lifesteal(BattleUnit $actor, BattleState $state, int $power, int $ratioPct, string $skill): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $result = $state->hit($actor, $target, $power, ['skill' => $skill, 'cause' => 'skill']);
        $healed = $actor->heal((int) round($result['dealt'] * $ratioPct / 100));
        if ($healed > 0) {
            $state->emit('heal', $actor->id(), $actor->id(), ['amount' => $healed, 'skill' => $skill]);
        }
    }

    private function dot(BattleUnit $actor, BattleState $state, int $power, int $expires, string $kind): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $target->addDot(max(1, $power), $expires, $actor->id(), $kind, $state->time);
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => $kind, 'expiresAt' => $expires]);
    }

    private function heal(BattleUnit $actor, BattleState $state, int $power): void
    {
        $target = $state->targets->lowestHpAlly($actor, $state->units);
        if ($target === null) {
            return;
        }

        $healed = $target->heal($power);
        $state->emit('heal', $actor->id(), $target->id(), ['amount' => $healed]);
    }

    private function shield(BattleUnit $actor, BattleState $state, int $power, int $expires): void
    {
        $target = $state->targets->lowestHpAlly($actor, $state->units) ?? $actor;
        $target->addShield($power, $expires);
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => 'shield', 'amount' => $power, 'expiresAt' => $expires]);
    }

    private function taunt(BattleUnit $actor, BattleState $state, int $expires): void
    {
        foreach ($state->units as $unit) {
            if ($unit->team() !== $actor->team() && $unit->isAlive()) {
                $unit->taunt($actor->id(), $expires);
            }
        }
        $state->emit('effect', $actor->id(), null, ['effect' => 'taunt', 'expiresAt' => $expires]);
    }

    private function stun(BattleUnit $actor, BattleState $state, int $chance, int $expires): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $applied = $state->rng->int(100) < $chance;
        if ($applied) {
            $target->stun($expires);
        }
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => 'stun', 'applied' => $applied, 'expiresAt' => $expires]);
    }

    private function silence(BattleUnit $actor, BattleState $state, int $expires): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $target->silence($expires);
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => 'silence', 'expiresAt' => $expires]);
    }

    private function selfBuff(BattleUnit $actor, BattleState $state, string $stat, int $pct, int $expires): void
    {
        $actor->addStatMod($stat, $pct, $expires);
        $state->emit('effect', $actor->id(), $actor->id(), ['effect' => "buff_{$stat}", 'pct' => $pct, 'expiresAt' => $expires]);
    }

    private function debuff(BattleUnit $actor, BattleState $state, string $stat, int $pct, int $expires, string $label): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $target->addStatMod($stat, $pct, $expires);
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => $label, 'pct' => $pct, 'expiresAt' => $expires]);
    }

    private function cleanse(BattleUnit $actor, BattleState $state): void
    {
        $target = $state->targets->lowestHpAlly($actor, $state->units);
        if ($target === null) {
            return;
        }

        $target->cleanseDebuffs($state->time);
        $state->emit('effect', $actor->id(), $target->id(), ['effect' => 'cleanse']);
    }

    private function revive(BattleUnit $actor, BattleState $state, int $pct): void
    {
        $target = $state->targets->deadAlly($actor, $state->units);
        if ($target === null) {
            $this->singleDamage($actor, $state, 8, 'revive');

            return;
        }

        $target->hp = max(1, (int) round($target->maxHp * $pct / 100));
        $state->emit('revive', $actor->id(), $target->id(), ['hp' => $target->hp]);
    }
}
