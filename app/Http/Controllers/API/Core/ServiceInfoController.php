<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceInfoController extends Controller
{
    public function index()
    {
        $serviceInfo = [
            'email' => 'service@example.com',
            'phone' => '+0123456789',
        ];
        return response()->json(['service_info' => $serviceInfo]);
    }


}