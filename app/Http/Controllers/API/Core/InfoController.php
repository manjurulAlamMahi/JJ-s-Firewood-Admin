<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Info;

class InfoController extends Controller
{
    public function index()
    {
        try {
            $infos = Info::all();
            $settings = \App\Models\SystemSetting::getCached();
            
            return response()->json([
                'success' => true,
                'data' => $infos,
                'config' => [
                    'is_bundles_enabled' => (bool) $settings->is_bundles_enabled
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve info: ' . $e->getMessage()
            ], 500);
        }
    }
}
