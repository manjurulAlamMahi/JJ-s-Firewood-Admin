<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Report;
use App\Models\ReportImage;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Validation failed', 422);
        }

        $user = auth()->user();

        DB::beginTransaction();
        try {
            $report = Report::create([
                'user_id' => $user->id,
                'message' => $request->message,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = public_path('uploads/reports');

                    if (!file_exists($path)) {
                        mkdir($path, 0755, true);
                    }
                    $file->move($path, $filename);
                    $imagePath = 'uploads/reports/' . $filename;

                    ReportImage::create([
                        'report_id' => $report->id,
                        'image' => $imagePath,
                    ]);
                }
            }

            DB::commit();
            return $this->success($report->load('images'), 'Report submitted successfully.', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to submit report: ' . $e->getMessage(), 500);
        }
    }
}
