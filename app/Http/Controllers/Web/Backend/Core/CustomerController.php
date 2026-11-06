<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomersExport;

class CustomerController extends Controller
{


    public function index(Request $request)
    {
        $search = $request->get('search');

        $customers = User::with(['orders'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $customers->getCollection()->transform(function ($customer) {
            $customer->total_spent = $customer->orders_sum_total ?? 0;
            $customer->total_orders = $customer->orders_count ?? 0;
            $customer->avg_order_value = $customer->total_orders > 0
                ? $customer->total_spent / $customer->total_orders
                : 0;

            $lastOrder = $customer->orders()->latest()->first();
            $customer->last_order_date = $lastOrder
                ? $lastOrder->created_at->format('d M Y')
                : null;

            return $customer;
        });

        $totalCustomers = User::where('is_admin', false)->count();
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
        $totalOrders = Order::count();
        $avgRevenuePerCustomer = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

        $stats = [
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_revenue_per_customer' => $avgRevenuePerCustomer
        ];

        return view('customers.index', compact('customers', 'search', 'stats'));
    }


    public function export()
    {
        $fileName = 'customers_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new CustomersExport, $fileName);
    }

    public function show($id)
    {
        $customer = User::with(['orders' => function ($query) {
            $query->latest();
        }])->findOrFail($id);

        $stats = [
            'total_orders' => $customer->orders()->count(),
            'total_spent' => $customer->orders()->sum('total'),
            'avg_order_value' => $customer->orders()->avg('total') ?? 0,
            'first_order' => $customer->orders()->oldest()->first()?->created_at,
            'last_order' => $customer->orders()->latest()->first()?->created_at,
            'pending_orders' => $customer->orders()->where('order_status', 'pending')->count(),
            'completed_orders' => $customer->orders()->where('order_status', 'delivered')->count(),
        ];

        $orders = $customer->orders()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('customers.show', compact('customer', 'stats', 'orders'));
    }
}
