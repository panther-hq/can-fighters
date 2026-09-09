<?php

namespace Tests\Feature\Equipment;

use App\Domain\Equipment\EquipmentRoller;
use App\Models\EquipmentDefinition;
use App\Models\PlayerEquipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class EquipTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    private function give(User $user, string $slug, string $rarity = 'common'): PlayerEquipment
    {
        return app(EquipmentRoller::class)->roll(
            $user,
            EquipmentDefinition::firstWhere('slug', $slug),
            $rarity,
            1234,
        );
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/equipment')->assertUnauthorized();
        $this->postJson('/api/fighters/1/equip')->assertUnauthorized();
    }

    public function test_equipment_list_shows_where_each_piece_is_worn(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $weapon = $this->give($user, 'tin-sword');
        $this->give($user, 'can-armor');

        $this->actingAs($user)->postJson("/api/fighters/{$fighter->id}/equip", [
            'playerEquipmentId' => $weapon->id,
        ])->assertOk();

        $this->actingAs($user)->getJson('/api/equipment')
            ->assertOk()
            ->assertJsonCount(2, 'equipment')
            ->assertJsonPath('equipment.0.equippedOnId', $fighter->id)
            ->assertJsonPath('equipment.1.equippedOnId', null);
    }

    public function test_equipping_raises_power_and_shows_on_the_fighter(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user, ['primary_class' => 'fighter']);
        $before = $fighter->stats->power_score;
        $weapon = $this->give($user, 'tin-sword');

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $weapon->id])
            ->assertOk()
            ->assertJsonPath('data.equipment.0.slot', 'weapon')
            ->assertJsonPath('data.stats.powerScore', fn ($p) => $p > $before);

        $this->assertDatabaseHas('fighter_equipment', ['fighter_id' => $fighter->id, 'slot' => 'weapon']);
    }

    public function test_equipping_a_second_weapon_replaces_the_first(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $first = $this->give($user, 'tin-sword');
        $second = $this->give($user, 'stefan-fork');

        $this->actingAs($user)->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $first->id])->assertOk();
        $this->actingAs($user)->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $second->id])->assertOk();

        $this->assertDatabaseCount('fighter_equipment', 1);
        $this->assertDatabaseHas('fighter_equipment', ['player_equipment_id' => $second->id]);
    }

    public function test_equipping_a_piece_worn_elsewhere_moves_it(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);
        $b = $this->makeFighter($user);
        $armor = $this->give($user, 'can-armor');

        $this->actingAs($user)->postJson("/api/fighters/{$a->id}/equip", ['playerEquipmentId' => $armor->id])->assertOk();
        $this->actingAs($user)->postJson("/api/fighters/{$b->id}/equip", ['playerEquipmentId' => $armor->id])->assertOk();

        $this->assertDatabaseCount('fighter_equipment', 1);
        $this->assertDatabaseHas('fighter_equipment', ['fighter_id' => $b->id, 'player_equipment_id' => $armor->id]);
    }

    public function test_unequipping_lowers_power_again(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $base = $fighter->stats->power_score;
        $weapon = $this->give($user, 'tin-sword');

        $this->actingAs($user)->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $weapon->id])->assertOk();
        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/unequip", ['slot' => 'weapon'])
            ->assertOk()
            ->assertJsonPath('data.stats.powerScore', $base)
            ->assertJsonPath('data.equipment', []);
    }

    public function test_cannot_equip_a_piece_you_do_not_own(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $foreign = $this->give(User::factory()->create(), 'tin-sword');

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nie masz tego przedmiotu.');
    }

    public function test_cannot_equip_onto_someone_elses_fighter(): void
    {
        $owner = User::factory()->create();
        $fighter = $this->makeFighter($owner);
        $other = User::factory()->create();
        $piece = $this->give($other, 'tin-sword');

        $this->actingAs($other)
            ->postJson("/api/fighters/{$fighter->id}/equip", ['playerEquipmentId' => $piece->id])
            ->assertNotFound();
    }
}
