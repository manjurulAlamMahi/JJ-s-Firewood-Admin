<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TipsAndGuides;
use App\Traits\ApiResponse;

class TipsAndGuidesController extends Controller
{
    use ApiResponse;

    private function toArray(TipsAndGuides $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'short_description' => $item->short_description,
            'long_description' => $item->long_description,
            'image_url' => image_asset($item->image ? 'uploads/tips_and_guides/' . $item->image : null),
            'is_featured' => (bool)$item->is_featured,
            'category' => $item->category ? [
                'id' => null, // Provide null or mock id to avoid breaking old clients
                'name' => $item->category,
            ] : null,
        ];
    }

    public function index()
    {
        try {
            $tipsAndGuides = TipsAndGuides::where('status', 1)->latest()->get();
            $data = $tipsAndGuides->map(fn ($item) => $this->toArray($item));
            return $this->success($data, 'Tips and Guides retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching Tips and Guides: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $tipAndGuide = TipsAndGuides::where('status', 1)->findOrFail($id);
            return $this->success($this->toArray($tipAndGuide), 'Tip and Guide retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Tip and Guide not found.', 404);
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching the Tip and Guide: ' . $e->getMessage(), 500);
        }
    }
}
