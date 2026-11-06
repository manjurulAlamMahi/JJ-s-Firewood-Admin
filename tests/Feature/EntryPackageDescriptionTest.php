<?php

namespace Tests\Feature;

use App\Models\EntryPackage;
use App\Models\Giveaway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryPackageDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_a_description_on_create(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('entry-package.store'), [
            'name' => '10 Entries',
            'price' => 15.00,
            'entry_quantity' => 10,
            'description' => 'Great value bundle for regular entrants.',
        ]);

        $response->assertRedirect(route('entry-package.index'));
        $this->assertDatabaseHas('entry_packages', [
            'name' => '10 Entries',
            'description' => 'Great value bundle for regular entrants.',
        ]);
    }

    public function test_admin_can_update_a_description(): void
    {
        $admin = User::factory()->create();
        $package = EntryPackage::create([
            'name' => '5 Entries', 'price' => 8.00, 'entry_quantity' => 5, 'status' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('entry-package.update', $package->id), [
            'name' => '5 Entries',
            'price' => 8.00,
            'entry_quantity' => 5,
            'description' => 'Updated description.',
        ]);

        $response->assertRedirect(route('entry-package.index'));
        $this->assertDatabaseHas('entry_packages', [
            'id' => $package->id,
            'description' => 'Updated description.',
        ]);
    }

    public function test_description_appears_in_the_customer_giveaway_api_response(): void
    {
        $user = User::factory()->create();
        $package = EntryPackage::create([
            'name' => '10 Entries', 'price' => 15.00, 'entry_quantity' => 10, 'status' => 1,
            'description' => 'Great value bundle.',
        ]);
        $giveaway = Giveaway::create([
            'prize_name' => 'Test Prize',
            'prize_value' => 100,
            'prize_type' => 'bundle',
            'start_date' => now()->subDay(),
            'draw_date' => now()->addDays(6),
            'status' => Giveaway::STATUS_ACTIVE,
            'required_entries' => 100,
            'position' => 1,
            'winner_id' => null,
        ]);
        $giveaway->entryPackages()->attach($package->id, ['is_active' => 1, 'position' => 1]);

        $response = $this->actingAs($user, 'api')->getJson('/api/giveaway_centre');

        $response->assertStatus(200);
        $packages = $response->json('data.entry_packages') ?? [];
        $this->assertNotEmpty($packages);
        $descriptions = array_column($packages, 'description');
        $this->assertContains('Great value bundle.', $descriptions);
    }
}
