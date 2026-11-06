<?php

namespace App\Http\Controllers\Web\Backend\Core;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }
}
