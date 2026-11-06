<?php

namespace Tests\Feature;

use App\Models\DynamicPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_index_returns_only_active_pages(): void
    {
        DynamicPages::create(['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'page_content' => '<p>Policy</p>', 'status' => 'active']);
        DynamicPages::create(['title' => 'Draft Page', 'slug' => 'draft-page', 'page_content' => '<p>Draft</p>', 'status' => 'inactive']);

        $response = $this->getJson('/api/v2/legal-pages');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Privacy Policy', $data[0]['title']);
    }

    public function test_legal_page_show_returns_full_content_by_slug(): void
    {
        DynamicPages::create(['title' => 'Terms of Service', 'slug' => 'terms-of-service', 'page_content' => '<p>Terms body</p>', 'status' => 'active']);

        $response = $this->getJson('/api/v2/legal-pages/terms-of-service');

        $response->assertStatus(200);
        $this->assertSame('<p>Terms body</p>', $response->json('data.page_content'));
    }

    public function test_legal_page_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v2/legal-pages/does-not-exist');

        $response->assertStatus(404);
    }

    public function test_legal_page_show_returns_404_for_inactive_page(): void
    {
        DynamicPages::create(['title' => 'Draft', 'slug' => 'draft', 'page_content' => '<p>x</p>', 'status' => 'inactive']);

        $response = $this->getJson('/api/v2/legal-pages/draft');

        $response->assertStatus(404);
    }
}
