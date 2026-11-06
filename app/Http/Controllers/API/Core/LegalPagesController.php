<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\DynamicPages;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LegalPagesController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $pages = DynamicPages::where('status', 'active')->get(['id', 'title', 'slug']);
            return $this->success($pages, 'Legal pages retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching legal pages: ' . $e->getMessage(), 500);
        }
    }

    public function show($slug)
    {
        try {
            $page = DynamicPages::where('status', 'active')->where('slug', $slug)->firstOrFail();
            return $this->success([
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'page_content' => $page->page_content,
            ], 'Legal page retrieved successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Legal page not found.', 404);
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching the legal page: ' . $e->getMessage(), 500);
        }
    }
}
