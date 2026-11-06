<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FAQ;
use App\Traits\ApiResponse;

class FAQController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $faqs = FAQ::where('status', 1)->orderBy('sort_order')->latest()->get();
            $data = $faqs->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                    'category' => $faq->category ? [
                        'id' => null,
                        'name' => $faq->category,
                    ] : null,
                ];
            });
            return $this->success($data, 'FAQs retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching FAQs: ' . $e->getMessage(), 500);
        }
    }
}
