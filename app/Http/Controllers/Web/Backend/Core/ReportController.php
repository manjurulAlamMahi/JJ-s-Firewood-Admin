<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    public function index()
    {
        return view('backend.layouts.report.index');
    }

    public function getData()
    {
        $data = Report::with('user:id,name,email,phone', 'images')->latest();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('user_name', function ($row) {
                return $row->user ? $row->user->name : 'N/A';
            })
            ->editColumn('message', function ($row) {
                return strlen($row->message) > 50 ? substr($row->message, 0, 50) . '...' : $row->message;
            })
            ->addColumn('images_count', function ($row) {
                return $row->images->count();
            })
            ->addColumn('action', function ($row) {
                return "
                    <button class='btn btn-sm btn-danger delete-report' data-id='{$row->id}'>
                        <i class='fa fa-trash'></i> 
                    </button>
                ";
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function show($id)
    {
        $report = Report::with('user', 'images')->findOrFail($id);
        return view('backend.layouts.report.show', compact('report'));
    }

    public function destroy($id)
    {
        $report = Report::with('images')->findOrFail($id);
        foreach ($report->images as $image) {
            if (file_exists(public_path($image->image))) {
                @unlink(public_path($image->image));
            }
        }
        $report->delete(); // Cascades in DB or we manually delete if needed, but we have cascade on migration

        return response()->json(['status' => true, 'message' => 'Report deleted successfully']);
    }
}
