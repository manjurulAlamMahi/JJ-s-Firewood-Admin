<?php

namespace Tests\Feature;

use App\Models\FAQ;
use App\Models\FAQCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_faqs_endpoint_returns_only_active_faqs_ordered_by_sort_order_with_category(): void
    {
        $category = FAQCategory::create(['name' => 'Orders & Delivery', 'sort_order' => 0, 'status' => 1]);

        FAQ::create(['question' => 'Second', 'answer' => 'A2', 'status' => 1, 'category_id' => $category->id, 'sort_order' => 2]);
        FAQ::create(['question' => 'First', 'answer' => 'A1', 'status' => 1, 'category_id' => $category->id, 'sort_order' => 1]);
        FAQ::create(['question' => 'Hidden', 'answer' => 'A3', 'status' => 0, 'category_id' => $category->id, 'sort_order' => 0]);

        $response = $this->getJson('/api/v2/faqs');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('First', $data[0]['question']);
        $this->assertSame('Second', $data[1]['question']);
        $this->assertSame('Orders & Delivery', $data[0]['category']['name']);
    }

    public function test_faqs_endpoint_handles_faqs_with_no_category(): void
    {
        FAQ::create(['question' => 'Uncategorized', 'answer' => 'A', 'status' => 1]);

        $response = $this->getJson('/api/v2/faqs');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNull($data[0]['category']);
    }
}
