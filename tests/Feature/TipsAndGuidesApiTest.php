<?php

namespace Tests\Feature;

use App\Models\TipsAndGuides;
use App\Models\TipCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipsAndGuidesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_category_on_each_item(): void
    {
        $category = TipCategory::create(['name' => 'Burning Tips', 'sort_order' => 0, 'status' => 1]);
        TipsAndGuides::create([
            'title' => 'How to season firewood',
            'short_description' => 'Short',
            'long_description' => 'Long',
            'status' => 1,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson('/api/v2/tips-and-guides');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame('Burning Tips', $data[0]['category']['name']);
    }

    public function test_show_includes_category(): void
    {
        $category = TipCategory::create(['name' => 'Storage & Stacking', 'sort_order' => 0, 'status' => 1]);
        $tip = TipsAndGuides::create([
            'title' => 'Stacking guide',
            'short_description' => 'Short',
            'long_description' => 'Long',
            'status' => 1,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v2/tips-and-guides/{$tip->id}");

        $response->assertStatus(200);
        $this->assertSame('Storage & Stacking', $response->json('data.category.name'));
    }

    public function test_index_handles_items_with_no_category(): void
    {
        TipsAndGuides::create([
            'title' => 'Uncategorized tip',
            'short_description' => 'Short',
            'long_description' => 'Long',
            'status' => 1,
        ]);

        $response = $this->getJson('/api/v2/tips-and-guides');

        $response->assertStatus(200);
        $this->assertNull($response->json('data.0.category'));
    }
}
