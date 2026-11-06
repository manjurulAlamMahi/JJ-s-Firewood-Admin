<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\WoodEducationContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WoodEducationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_active_entry_for_a_category(): void
    {
        $category = Category::create(['name' => 'Firewood', 'status' => 1]);
        WoodEducationContent::create([
            'title' => 'How firewood is seasoned',
            'short_description' => 'Learn the basics',
            'long_description' => '<h1>Seasoning</h1><p>Details here.</p>',
            'status' => 1,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v2/wood-education?category_id={$category->id}");

        $response->assertStatus(200);
        $this->assertSame('How firewood is seasoned', $response->json('data.title'));
        $this->assertSame('<h1>Seasoning</h1><p>Details here.</p>', $response->json('data.long_description'));
    }

    public function test_returns_null_data_when_category_has_no_entry(): void
    {
        $category = Category::create(['name' => 'Kindling', 'status' => 1]);

        $response = $this->getJson("/api/v2/wood-education?category_id={$category->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data'));
    }

    public function test_ignores_inactive_entries(): void
    {
        $category = Category::create(['name' => 'Marri', 'status' => 1]);
        WoodEducationContent::create([
            'title' => 'Draft content',
            'short_description' => 'Not ready',
            'status' => 0,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v2/wood-education?category_id={$category->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data'));
    }

    public function test_requires_a_valid_category_id(): void
    {
        $response = $this->getJson('/api/v2/wood-education?category_id=999999');

        $response->assertStatus(422);
    }

    public function test_category_id_is_required(): void
    {
        $response = $this->getJson('/api/v2/wood-education');

        $response->assertStatus(422);
    }
}
