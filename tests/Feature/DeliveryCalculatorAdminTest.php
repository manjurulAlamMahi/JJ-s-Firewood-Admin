<?php

namespace Tests\Feature;

use App\Models\DeliveryType;
use App\Models\User;
use App\Models\ZipCode;
use App\Models\ZipCodeGroup;
use App\Models\ZoneWiseDeliveryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryCalculatorAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_zone_fee_and_deliverable_true_for_a_matched_zone_postcode(): void
    {
        $admin = User::factory()->create();
        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);
        $group = ZipCodeGroup::create(['name' => 'Zone A', 'status' => 1]);
        ZipCode::create(['zip_code' => '6000', 'status' => 1, 'group_id' => $group->id]);
        ZoneWiseDeliveryType::create([
            'zip_code_group_id' => $group->id, 'delivery_type_id' => $deliveryType->id, 'fee' => 5,
        ]);

        $response = $this->actingAs($admin)->postJson(route('delivery-calculator.calculate'), [
            'postcode' => '6000',
            'delivery_type_id' => $deliveryType->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'deliverable' => true,
            'fee' => 5.0,
            'zone_matched' => true,
        ]);
    }

    public function test_returns_base_fee_for_a_deliverable_postcode_with_no_zone_override(): void
    {
        $admin = User::factory()->create();
        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);
        ZipCode::create(['zip_code' => '6001', 'status' => 1, 'group_id' => null]);

        $response = $this->actingAs($admin)->postJson(route('delivery-calculator.calculate'), [
            'postcode' => '6001',
            'delivery_type_id' => $deliveryType->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'deliverable' => true,
            'fee' => 15.0,
            'zone_matched' => false,
        ]);
    }

    public function test_returns_deliverable_false_for_an_unknown_postcode(): void
    {
        $admin = User::factory()->create();
        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);

        $response = $this->actingAs($admin)->postJson(route('delivery-calculator.calculate'), [
            'postcode' => '0000',
            'delivery_type_id' => $deliveryType->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliverable' => false]);
    }
}
