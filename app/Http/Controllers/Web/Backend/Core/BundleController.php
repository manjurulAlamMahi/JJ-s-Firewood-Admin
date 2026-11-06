<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleExtra;
use App\Models\Product;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BundleController extends Controller
{
    public function index()
    {
        try {
            return view('backend.layouts.bundles.index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Bundles page: ' . $e->getMessage());
        }
    }

    public function getData()
    {
        try {
            $items = Bundle::with('baseProduct')->latest()->get();
            return DataTables::of($items)
                ->addIndexColumn()
                ->addColumn('base_product', function ($row) {
                    return $row->baseProduct->name ?? '-';
                })
                ->addColumn('price', function ($row) {
                    $price = '$' . number_format($row->effective_price, 2);
                    if ($row->discount_percentage > 0) {
                        $price .= " <span class='badge bg-success'>{$row->discount_percentage}% off</span>";
                    }
                    return $price;
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return '<img src="' . asset('uploads/bundles/' . $row->image) . '" alt="Bundle Image" width="50">';
                    }
                    return '-';
                })
                ->addColumn('status', function ($row) {
                    return (int) $row->status;
                })
                ->addColumn('action', function ($row) {
                    return "
                        <a href='" . route('bundles.edit', $row->id) . "' class='btn btn-sm btn-icon btn-warning me-1' title='Edit'>
                            <i class='fa fa-edit'></i>
                        </a>
                        <button class='btn btn-sm btn-icon btn-danger delete-bundle' data-id='{$row->id}' title='Delete'>
                            <i class='fa fa-trash'></i>
                        </button>
                    ";
                })
                ->rawColumns(['image', 'price', 'status', 'action'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching Bundles data: ' . $e->getMessage()], 500);
        }
    }

    public function create()
    {
        try {
            $products = Product::where('status', 1)->orderBy('name')->get();
            return view('backend.layouts.bundles.create', compact('products'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Bundle creation page: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'base_product_id' => 'required|exists:products,id',
                'base_price' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'extras' => 'nullable|array',
                'extras.*' => 'exists:products,id',
                'required_extras' => 'nullable|array',
                'required_extras.*' => 'exists:products,id',
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/bundles'), $filename);
            } else {
                $filename = null;
            }

            $bundle = Bundle::create([
                'name' => $request->name,
                'description' => $request->description,
                'image' => $filename,
                'base_product_id' => $request->base_product_id,
                'base_price' => $request->base_price ?: null,
                'discount_percentage' => $request->discount_percentage ?: 0,
                'status' => 1,
            ]);

            foreach ((array) $request->input('extras', []) as $index => $productId) {
                BundleExtra::create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $productId,
                    'is_required' => false,
                    'sort_order' => $index,
                    'status' => 1,
                ]);
            }
            foreach ((array) $request->input('required_extras', []) as $index => $productId) {
                BundleExtra::create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $productId,
                    'is_required' => true,
                    'sort_order' => $index,
                    'status' => 1,
                ]);
            }

            return redirect()->route('bundles.index')->with('success', 'Bundle created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the Bundle: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $bundle = Bundle::with('extras')->findOrFail($id);
            $products = Product::where('status', 1)->orderBy('name')->get();
            $extraOptionProducts = $products->where('id', '!=', $bundle->base_product_id)->values();
            $selectedExtraIds = $bundle->extras->where('is_required', false)->pluck('product_id')->toArray();
            $selectedRequiredExtraIds = $bundle->extras->where('is_required', true)->pluck('product_id')->toArray();
            return view('backend.layouts.bundles.edit', compact('bundle', 'products', 'extraOptionProducts', 'selectedExtraIds', 'selectedRequiredExtraIds'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while loading the Bundle edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'base_product_id' => 'required|exists:products,id',
                'base_price' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'extras' => 'nullable|array',
                'extras.*' => 'exists:products,id',
                'required_extras' => 'nullable|array',
                'required_extras.*' => 'exists:products,id',
            ]);

            $bundle = Bundle::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($bundle->image && file_exists(public_path('uploads/bundles/' . $bundle->image))) {
                    unlink(public_path('uploads/bundles/' . $bundle->image));
                }
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/bundles'), $filename);
                $bundle->image = $filename;
            }

            $bundle->name = $request->name;
            $bundle->description = $request->description;
            $bundle->base_product_id = $request->base_product_id;
            $bundle->base_price = $request->base_price ?: null;
            $bundle->discount_percentage = $request->discount_percentage ?: 0;
            $bundle->save();

            // Replace the extras set wholesale — simplest correct sync, no diffing needed.
            BundleExtra::where('bundle_id', $bundle->id)->delete();
            foreach ((array) $request->input('extras', []) as $index => $productId) {
                BundleExtra::create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $productId,
                    'is_required' => false,
                    'sort_order' => $index,
                    'status' => 1,
                ]);
            }
            foreach ((array) $request->input('required_extras', []) as $index => $productId) {
                BundleExtra::create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $productId,
                    'is_required' => true,
                    'sort_order' => $index,
                    'status' => 1,
                ]);
            }

            return redirect()->route('bundles.index')->with('success', 'Bundle updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the Bundle: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $bundle = Bundle::findOrFail($id);
            $bundle->status = $request->status;
            $bundle->save();
            return response()->json(['success' => 'Bundle status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while updating the Bundle status: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $bundle = Bundle::findOrFail($id);
            if ($bundle->image && file_exists(public_path('uploads/bundles/' . $bundle->image))) {
                unlink(public_path('uploads/bundles/' . $bundle->image));
            }
            $bundle->delete(); // bundle_extras cascade via FK
            return response()->json(['success' => 'Bundle deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting the Bundle: ' . $e->getMessage()], 500);
        }
    }
}
